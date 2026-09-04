"""Tests del semáforo distribuido.

Simulan MÚLTIPLES procesos/replicas que comparten el mismo Redis y verifican:
1. que el límite de concurrencia por sitio se respeta de forma GLOBAL (no por
   réplica), incluso con más intentos concurrentes que el límite permite;
2. la seguridad ante crashes: un worker que adquiere un permiso y "muere"
   (sin liberarlo) hace que el permiso se libere solo al expirar el lease, sin
   necesitar intervención manual.
"""

import multiprocessing as mp
import os
import threading
import time

import redis

import redis_semaphore

TEST_REDIS = redis.Redis(host="127.0.0.1", port=6379, decode_responses=True)
SITE = "e2e-sem"


def _flush():
    redis_semaphore.flush_semaphore_keys(TEST_REDIS, [SITE])


# --- Targets de multiprocessing (deben ser de nivel módulo para spawn) --------


def _fill_process(site, worker_id, limit, lease_seconds, threads, hold):
    """Una 'réplica': crea su propio cliente Redis y su propio semáforo, y lanza
    `threads` hilos que intentan adquirir un permiso y lo mantienen `hold` s."""
    r = redis.Redis(host="127.0.0.1", port=6379, decode_responses=True)
    sem = redis_semaphore.DistributedSemaphore(
        r, {site: limit}, worker_id=worker_id, lease_seconds=lease_seconds
    )

    def one():
        token = sem.try_acquire(site)
        if token is None:
            return
        try:
            time.sleep(hold)
        finally:
            sem.release(site, token)

    threads_list = [threading.Thread(target=one) for _ in range(threads)]
    for t in threads_list:
        t.start()
    for t in threads_list:
        t.join()
    r.close()


def _crash_process(site, worker_id, limit, lease_seconds):
    """Adquiere UN permiso y simula un crash duro: termina sin liberarlo. Como el
    heartbeat vive en este proceso (hilo daemon), muere con él y el lease NO se
    renueva, por lo que expirará solo."""
    r = redis.Redis(host="127.0.0.1", port=6379, decode_responses=True)
    sem = redis_semaphore.DistributedSemaphore(
        r, {site: limit}, worker_id=worker_id, lease_seconds=lease_seconds
    )
    token = sem.try_acquire(site)
    if token is None:
        print("CRASH-ACQUIRE-FAILED")
    os._exit(1)


# --- Tests --------------------------------------------------------------------


def test_global_limit_respected_across_multiple_processes():
    """El límite GLOBAL se cumple entre todas las réplicas combinadas: con 3
    procesos x 2 hilos (6 intentos) y límite 2, el número de permisos en curso
    en el Redis compartido nunca supera 2."""
    _flush()
    limit = 2
    lease = 30
    hold = 0.6

    procs = []
    for i in range(3):
        p = mp.Process(
            target=_fill_process, args=(SITE, f"p{i}", limit, lease, 2, hold)
        )
        p.start()
        procs.append(p)

    max_scard = 0
    start = time.monotonic()
    while time.monotonic() - start < hold + 1.0:
        scard = TEST_REDIS.scard(f"cert_sem:{SITE}")
        max_scard = max(max_scard, scard)
        time.sleep(0.02)

    for p in procs:
        p.join(timeout=10)

    # Si el semáforo fuera por-replica (bug), el SCARD llegaría a
    # limit * procesos = 6. Debe quedarse en `limit`.
    assert max_scard <= limit, f"el límite global se excedió: {max_scard} > {limit}"
    assert max_scard == limit, f"se esperaba pico de {limit}, se vio {max_scard}"

    _flush()


def test_lease_releases_permit_after_crash_without_manual_reset():
    """Seguridad ante crash: un worker adquiere un permiso y muere sin liberarlo
    (heartbeat muere con él). Tras expirar el lease, otro proceso puede volver a
    adquirirlo sin reiniciar nada manualmente."""
    _flush()
    limit = 1
    lease = 1.5  # lease corto para poder probar la expiración rápido

    # 1) El "worker" adquiere el único permiso y crashea (no lo libera).
    p = mp.Process(target=_crash_process, args=(SITE, "crash", limit, lease))
    p.start()
    p.join(timeout=5)
    assert p.exitcode == 1, "el proceso debería haber 'crasheado' (exit 1)"

    # 2) Inmediatamente después, el permiso sigue bloqueado por el token (aún no
    #    expiró), así que un segundo proceso NO puede adquirirlo.
    sem = redis_semaphore.DistributedSemaphore(
        TEST_REDIS, {SITE: limit}, worker_id="parent", lease_seconds=lease
    )
    token_before = sem.try_acquire(SITE)
    assert token_before is None, "el permiso debería seguir bloqueado (lease vigente)"

    # 3) Esperamos a que expire el lease del permiso del worker muerto.
    time.sleep(lease + 1.0)

    # 4) Ahora debe volver a poder adquirirse automáticamente.
    token_after = sem.acquire(SITE, timeout=2.0)
    assert token_after, "el permiso no se liberó automáticamente tras el crash+lease"
    sem.release(SITE, token_after)

    _flush()
