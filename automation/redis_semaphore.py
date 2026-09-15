"""Semáforo distribuido por sitio, respaldado en Redis.

Sustituye al `threading.Semaphore` local (que solo es efectivo dentro de un
único proceso). Este semáforo mantiene, para cada sitio, un SET en Redis
(`cert_sem:<site>`) con un token por permiso en curso. El límite por sitio se
respeta a nivel GLOBAL: todos los workers que comparten el mismo Redis ven el
mismo SET, así que el número de tokens activos (permisos en curso) nunca supera
el límite configurado, sin importar cuántas réplicas haya.

Seguridad ante crashes: cada permiso tiene un *lease* (TTL). Si un worker se
cae o crashea sin liberar su permiso, su token deja de existir en Redis cuando
expira y cualquier otro worker puede volver a adquirirlo sin intervención
manual. Mientras el job corre normalmente, un *heartbeat* renueva el lease.
"""

import threading
import time
import uuid
from typing import Optional

import redis


class DistributedSemaphore:
    def __init__(
        self,
        client: redis.Redis,
        limits: dict,
        *,
        worker_id: Optional[str] = None,
        lease_seconds: int = 180,
        retry_seconds: float = 0.05,
    ):
        self._redis = client
        self._limits = dict(limits)
        self._worker_id = worker_id or uuid.uuid4().hex[:8]
        self._lease_ms = int(lease_seconds * 1000)
        self._retry = retry_seconds

        self._heartbeats: dict = {}
        self._lock = threading.Lock()

    # --- Keys ----------------------------------------------------------------
    def _set_key(self, site: str) -> str:
        return f"cert_sem:{site}"

    def _lease_key(self, site: str, token: str) -> str:
        return f"cert_sem:{site}:lease:{token}"

    # --- Scripts / acciones atómicas -----------------------------------------
    # Adquiere un permiso si el SET correspondiente tiene menos miembros que el
    # límite. Antes, "poda" los tokens cuyo lease ya expiró (crash-safe). Todo
    # el script corre de forma atómica en Redis, por lo que distintos procesos
    # no se pisan entre sí (sin condición de carrera).
    _ACQUIRE_LUA = """
    local lease_prefix = 'cert_sem:' .. ARGV[1] .. ':lease:'
    local members = redis.call('SMEMBERS', KEYS[1])
    for _, tok in ipairs(members) do
        if redis.call('EXISTS', lease_prefix .. tok) == 0 then
            redis.call('SREM', KEYS[1], tok)
        end
    end
    if redis.call('SCARD', KEYS[1]) < tonumber(ARGV[3]) then
        redis.call('SET', lease_prefix .. ARGV[2], '1', 'PX', tonumber(ARGV[4]))
        redis.call('SADD', KEYS[1], ARGV[2])
        return 1
    end
    return 0
    """

    _RELEASE_LUA = """
    redis.call('SREM', KEYS[1], ARGV[1])
    redis.call('DEL', KEYS[2])
    return 1
    """

    def _acquire_once(self, site: str) -> Optional[str]:
        token = f"{self._worker_id}:{uuid.uuid4().hex}"
        limit = self._limits[site]
        ok = self._redis.eval(
            self._ACQUIRE_LUA, 1, self._set_key(site),
            site, token, limit, self._lease_ms,
        )
        return token if ok else None

    # --- API pública ---------------------------------------------------------
    def try_acquire(self, site: str) -> Optional[str]:
        """Intenta adquirir sin esperar. Devuelve el token o None."""
        token = self._acquire_once(site)
        if token:
            self._start_heartbeat(site, token)
        return token

    def acquire(self, site: str, timeout: Optional[float] = 0.0) -> Optional[str]:
        """Bloquea hasta adquirir un permiso o hasta `timeout` segundos.

        Con `timeout=0` no espera (equivale a `try_acquire`). Con `None` espera
        indefinidamente. Devuelve el token si se obtuvo, o None si expiró el
        tiempo de espera.
        """
        deadline = None if timeout is None else time.monotonic() + timeout
        while True:
            token = self._acquire_once(site)
            if token:
                self._start_heartbeat(site, token)
                return token
            if deadline is not None and time.monotonic() >= deadline:
                return None
            time.sleep(self._retry)

    def release(self, site: str, token: str) -> None:
        """Libera un permiso adquirido y detiene su heartbeat."""
        with self._lock:
            hb = self._heartbeats.pop((site, token), None)
            if hb:
                hb[0].set()
        try:
            self._redis.eval(
                self._RELEASE_LUA, 2,
                self._set_key(site), self._lease_key(site, token), token,
            )
        except redis.RedisError:
            # Si falla la liberación, el lease expirará solo; best effort.
            pass

    # --- Heartbeat (renovación del lease) ------------------------------------
    def _start_heartbeat(self, site: str, token: str) -> None:
        stop = threading.Event()

        def beat():
            interval = max(0.1, self._lease_ms / 3000.0)
            while not stop.wait(interval):
                try:
                    self._redis.set(self._lease_key(site, token), "1", px=self._lease_ms)
                except redis.RedisError:
                    pass

        thread = threading.Thread(
            target=beat, daemon=True, name=f"sema-{site}-{token[:6]}"
        )
        thread.start()
        with self._lock:
            self._heartbeats[(site, token)] = (stop, thread)


def flush_semaphore_keys(client: redis.Redis, sites: list) -> None:
    """Borra todas las claves del semáforo (para tests o reinicio limpio)."""
    keys = []
    for site in sites:
        keys.append(f"cert_sem:{site}")
        for token in list(client.smembers(f"cert_sem:{site}")):
            keys.append(f"cert_sem:{site}:lease:{token}")
    if keys:
        client.delete(*keys)
