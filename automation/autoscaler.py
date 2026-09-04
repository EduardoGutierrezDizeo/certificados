"""Autoescalado del worker de CertiCheck.

Servicio liviano (solo redis-py + SDK de Docker; NO necesita Playwright) que corre
dentro del stack de Swarm y ajusta automáticamente el número de réplicas del
servicio ``certicheck_worker`` según la carga real: la longitud de la cola
``certificate_jobs`` en Redis.

Lógica (ver defaults en ``AutoscalerSettings``):

- Escala HACIA ARRIBA si la cola tiene más de ``SCALE_UP_THRESHOLD`` jobs
  pendientes durante al menos ``SCALE_UP_STABLE_CYCLES`` ciclos consecutivos,
  sumando ``REPLICAS_STEP_UP`` réplicas en cada acción hasta un máximo
  ``MAX_REPLICAS`` (innegociable, nunca se escala sin límite).
- Escala HACIA ABAJO si la cola lleva menos de ``SCALE_DOWN_THRESHOLD`` jobs
  durante ``SCALE_DOWN_STABLE_CYCLES`` ciclos consecutivos, restando
  ``REPLICAS_STEP_DOWN`` réplicas hasta un mínimo ``MIN_REPLICAS`` (nunca 0:
  siempre debe quedar al menos un worker escuchando).
- Respeta un cooldown ``SCALE_COOLDOWN_SECONDS`` entre DOS CUALQUIER acciones de
  escalado consecutivas (arriba o abajo) para evitar oscilaciones.
- Loguea CADA decisión (subir / bajar / sin cambios) con su motivo: cola
  observada, réplicas actuales, réplicas nuevas y qué umbral la disparó.

ACCESO AL SOCKET DE DOCKER: este proceso monta ``/var/run/docker.sock`` (read-only)
para ejecutar el equivalente a ``docker service scale``. Ese acceso es equivalente
a permisos root sobre el host Docker: es una credencial privilegiada y debe
tratarse como tal (ver README).
"""

import logging
import os
import time
from dataclasses import dataclass

import redis

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("certicheck-autoscaler")


@dataclass(frozen=True)
class AutoscalerSettings:
    """Parámetros de la política de autoescalado.

    Todos son configurables por variable de entorno (defaults conservadores,
    NO medidos: se ajustarán en la Fase 4 con datos reales contra los portales).
    """

    # Intervalo entre ciclos de medición.
    interval_seconds: int = 15

    # Conexión a Redis (la cola certicheck_worker comparte el mismo Redis).
    redis_host: str = "127.0.0.1"
    redis_port: int = 6379
    queue_name: str = "certificate_jobs"

    # Servicio Swarm cuyo número de réplicas se ajusta.
    worker_service_name: str = "certicheck_worker"

    # Escalar arriba: cola > umbral durante N ciclos consecutivos.
    scale_up_threshold: int = 10
    scale_up_stable_cycles: int = 2

    # Escalar abajo: cola < umbral durante N ciclos consecutivos.
    scale_down_threshold: int = 1
    scale_down_stable_cycles: int = 4

    # Tamaño del paso en cada acción de escalado.
    replicas_step_up: int = 1
    replicas_step_down: int = 1

    # Piso y techo de réplicas. SIEMPRE acotado: min>=1 (al menos un worker
    # escuchando), max<=techo del cluster.
    min_replicas: int = 1
    max_replicas: int = 8

    # Cooldown (segundos) entre DOS CUALQUIER acciones consecutivas de escalado.
    scale_cooldown_seconds: float = 60

    @classmethod
    def from_env(cls) -> "AutoscalerSettings":
        def _int(name: str, default: int) -> int:
            return int(os.getenv(name, default))

        return cls(
            interval_seconds=_int("AUTOSCALE_INTERVAL_SECONDS", 15),
            redis_host=os.getenv("REDIS_HOST", "127.0.0.1"),
            redis_port=_int("REDIS_PORT", 6379),
            queue_name=os.getenv("QUEUE_NAME", "certificate_jobs"),
            worker_service_name=os.getenv("WORKER_SERVICE_NAME", "certicheck_worker"),
            scale_up_threshold=_int("SCALE_UP_THRESHOLD", 10),
            scale_up_stable_cycles=_int("SCALE_UP_STABLE_CYCLES", 2),
            scale_down_threshold=_int("SCALE_DOWN_THRESHOLD", 1),
            scale_down_stable_cycles=_int("SCALE_DOWN_STABLE_CYCLES", 4),
            replicas_step_up=_int("REPLICAS_STEP_UP", 1),
            replicas_step_down=_int("REPLICAS_STEP_DOWN", 1),
            min_replicas=_int("MIN_REPLICAS", 1),
            max_replicas=_int("MAX_REPLICAS", 8),
            scale_cooldown_seconds=float(os.getenv("SCALE_COOLDOWN_SECONDS", 60)),
        )


@dataclass(frozen=True)
class ScalingDecision:
    """Resultado de evaluar UN ciclo de medición. `action` es "up", "down" o
    "noop"; `target_replicas` solo tiene sentido cuando se escala."""

    action: str
    reason: str
    queue_len: int
    current_replicas: int
    target_replicas: int | None = None


class Autoscaler:
    """Toma la decisión de escalado a partir de la cola y la aplica vía Docker.

    `evaluate()` es lógica pura (sin I/O): recibe la cola, las réplicas actuales
    y el reloj, y devuelve una decisión. `run_cycle()` es la capa de I/O que
    lee Redis, lee/escribe Docker y registra cada decisión. Los streaks de ciclos
    consecutivos se mantienen en la instancia. docker_client y redis_client son
    intercambiables por mocks en los tests (no hace falta un Swarm real).
    """

    def __init__(self, redis_client, docker_client, settings: AutoscalerSettings):
        self.r = redis_client
        self.docker = docker_client
        self.settings = settings
        # Conteo de ciclos consecutivos por encima del umbral alto / por debajo
        # del umbral bajo. Fuera de las bandas activas se resetean a 0.
        self.up_streak = 0
        self.down_streak = 0
        # Momento (reloj de monotonic/per-instance) hasta el que está prohibido
        # volver a escalar. Se fija tras CADA acción (up o down).
        self.cooldown_until = 0.0

    def queue_len(self) -> int:
        return self.r.llen(self.settings.queue_name)

    def get_current_replicas(self) -> int:
        service = self.docker.services.get(self.settings.worker_service_name)
        return int(service.attrs["Spec"]["Mode"]["Replicated"]["Replicas"])

    def set_replicas(self, count: int) -> None:
        self.docker.services.get(self.settings.worker_service_name).scale(count)

    def evaluate(self, queue_len: int, current_replicas: int, now: float) -> ScalingDecision:
        """Lógica pura de decisión para un ciclo. Muta los streaks en la
        instancia para recordar las observaciones consecutivas."""
        s = self.settings

        if queue_len > s.scale_up_threshold:
            self.up_streak += 1
            self.down_streak = 0
            if self.up_streak < s.scale_up_stable_cycles:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} >umbral {s.scale_up_threshold} pero solo "
                    f"{self.up_streak}/{s.scale_up_stable_cycles} ciclos altos",
                    queue_len,
                    current_replicas,
                )
            if current_replicas >= s.max_replicas:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} >umbral {s.scale_up_threshold} pero ya en "
                    f"MAX_REPLICAS={s.max_replicas}",
                    queue_len,
                    current_replicas,
                )
            if now < self.cooldown_until:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} >umbral {s.scale_up_threshold}, subiría a "
                    f"{min(current_replicas + s.replicas_step_up, s.max_replicas)} "
                    f"pero en cooldown (faltan {int(self.cooldown_until - now)}s)",
                    queue_len,
                    current_replicas,
                )
            target = min(current_replicas + s.replicas_step_up, s.max_replicas)
            return ScalingDecision(
                "up",
                f"cola={queue_len} >umbral {s.scale_up_threshold} sostenida {self.up_streak} ciclos",
                queue_len,
                current_replicas,
                target,
            )

        if queue_len < s.scale_down_threshold:
            self.up_streak = 0
            self.down_streak += 1
            if self.down_streak < s.scale_down_stable_cycles:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} <umbral {s.scale_down_threshold} pero solo "
                    f"{self.down_streak}/{s.scale_down_stable_cycles} ciclos estables",
                    queue_len,
                    current_replicas,
                )
            if current_replicas <= s.min_replicas:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} <umbral {s.scale_down_threshold} pero ya en "
                    f"MIN_REPLICAS={s.min_replicas}",
                    queue_len,
                    current_replicas,
                )
            if now < self.cooldown_until:
                return ScalingDecision(
                    "noop",
                    f"cola={queue_len} <umbral {s.scale_down_threshold}, bajaría a "
                    f"{max(current_replicas - s.replicas_step_down, s.min_replicas)} "
                    f"pero en cooldown (faltan {int(self.cooldown_until - now)}s)",
                    queue_len,
                    current_replicas,
                )
            target = max(current_replicas - s.replicas_step_down, s.min_replicas)
            return ScalingDecision(
                "down",
                f"cola={queue_len} <umbral {s.scale_down_threshold} durante "
                f"{self.down_streak} ciclos estables",
                queue_len,
                current_replicas,
                target,
            )

        # Zona entre umbrales (cola ni vacía ni desbordada): estable.
        self.up_streak = 0
        self.down_streak = 0
        return ScalingDecision(
            "noop",
            f"cola={queue_len} en zona estable "
            f"({s.scale_down_threshold}..{s.scale_up_threshold})",
            queue_len,
            current_replicas,
        )

    def _log(self, decision: ScalingDecision) -> None:
        if decision.action == "up":
            logger.info(
                "[SCALE_UP] motivo: %s | cola=%d | réplicas %d -> %d (paso %d, max %d)",
                decision.reason,
                decision.queue_len,
                decision.current_replicas,
                decision.target_replicas,
                self.settings.replicas_step_up,
                self.settings.max_replicas,
            )
        elif decision.action == "down":
            logger.info(
                "[SCALE_DOWN] motivo: %s | cola=%d | réplicas %d -> %d (paso %d, min %d)",
                decision.reason,
                decision.queue_len,
                decision.current_replicas,
                decision.target_replicas,
                self.settings.replicas_step_down,
                self.settings.min_replicas,
            )
        else:
            logger.info(
                "[STABLE] sin cambios | motivo: %s | cola=%d | réplicas actuales %d",
                decision.reason,
                decision.queue_len,
                decision.current_replicas,
            )

    def run_cycle(self, now: float | None = None) -> ScalingDecision:
        """Un ciclo completo: medir cola + réplicas, decidir, aplicar (si aplica)
        y loguear la decisión. Devuelve la decisión (útil en tests)."""
        if now is None:
            now = time.monotonic()
        queue_len = self.queue_len()
        current = self.get_current_replicas()
        decision = self.evaluate(queue_len, current, now)
        if decision.action in ("up", "down"):
            self.set_replicas(decision.target_replicas)
            self.cooldown_until = now + self.settings.scale_cooldown_seconds
            self.up_streak = 0
            self.down_streak = 0
        self._log(decision)
        return decision


def main() -> None:
    settings = AutoscalerSettings.from_env()

    r = redis.Redis(
        host=settings.redis_host,
        port=settings.redis_port,
        decode_responses=True,
        socket_timeout=10,
        socket_connect_timeout=10,
    )

    # Importación diferida: el SDK de Docker solo se necesita en runtime, no para
    # ejecutar la lógica de decisión (los tests la prueban con mocks, sin Swarm).
    import docker  # noqa: PLC0415

    docker_client = docker.DockerClient.from_env()

    autoscaler = Autoscaler(r, docker_client, settings)

    logger.info(
        "[AUTOSCALER] Arrancando. cola=%s servicio=%s | "
        "subir si cola>%d por %d ciclos (paso +%d) | "
        "bajar si cola<%d por %d ciclos (paso -%d) | "
        "min=%d max=%d | cooldown=%ss | intervalo=%ss",
        settings.queue_name,
        settings.worker_service_name,
        settings.scale_up_threshold,
        settings.scale_up_stable_cycles,
        settings.replicas_step_up,
        settings.scale_down_threshold,
        settings.scale_down_stable_cycles,
        settings.replicas_step_down,
        settings.min_replicas,
        settings.max_replicas,
        settings.scale_cooldown_seconds,
        settings.interval_seconds,
    )

    while True:
        try:
            autoscaler.run_cycle()
        except Exception as e:  # noqa: BLE001
            # Un fallo puntual (p.ej. Redis caído un instante) no debe matar al
            # autoescalador: se registra, se espera el intervalo y se reintenta.
            logger.error("[AUTOSCALER] Error en el ciclo (no crítico, se reintenta): %s", e)
        time.sleep(settings.interval_seconds)


if __name__ == "__main__":
    main()