"""Tests de la lógica de decisión del autoescalador.

La política se prueba SIN un Swarm real: el cliente de Docker y el de Redis se
simulan (MagicMock), y `evaluate()` es lógica pura sin I/O. Además hay un test
end-to-end de la lógica con un docker_client fake cuyo spec refleja los cambios
de réplicas, probando el ciclo completo arriba -> abajo hasta el mínimo.
"""

from unittest.mock import MagicMock

import pytest

import autoscaler


def make_settings(**overrides) -> autoscaler.AutoscalerSettings:
    defaults = dict(
        interval_seconds=5,
        redis_host="127.0.0.1",
        redis_port=6379,
        queue_name="certificate_jobs",
        worker_service_name="certicheck_worker",
        scale_up_threshold=10,
        scale_up_stable_cycles=2,
        scale_down_threshold=1,
        scale_down_stable_cycles=4,
        replicas_step_up=1,
        replicas_step_down=1,
        min_replicas=1,
        max_replicas=8,
        scale_cooldown_seconds=60,
    )
    defaults.update(overrides)
    return autoscaler.AutoscalerSettings(**defaults)


def make_autoscaler(settings=None, *, replicas=2):
    docker_client = MagicMock()
    service = MagicMock()
    service.attrs = {"Spec": {"Mode": {"Replicated": {"Replicas": replicas}}}}
    docker_client.services.get.return_value = service
    return autoscaler.Autoscaler(
        MagicMock(),
        docker_client,
        settings or make_settings(),
    ), docker_client, service


# --- Escalar hacia arriba ---------------------------------------------------


def test_scale_up_requires_two_consecutive_high_cycles():
    """Con SCALE_UP_STABLE_CYCLES=2, el primer ciclo alto NO dispara; el
    segundo consecutivo sí."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=2))

    first = a.evaluate(queue_len=25, current_replicas=2, now=0)
    second = a.evaluate(queue_len=25, current_replicas=2, now=0)

    assert first.action == "noop"
    assert "1/2" in first.reason
    assert second.action == "up"
    assert second.target_replicas == 3


def test_scale_up_stable_cycles_breaks_when_queue_recovers():
    """Un ciclo alto aislado (luego la cola vuelve a la zona estable) NO debe
    dejar un streak que dispare una subida más tarde."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=2))

    a.evaluate(queue_len=25, current_replicas=2, now=0)  # 1er ciclo alto
    a.evaluate(queue_len=5, current_replicas=2, now=0)   # vuelve a la normalidad
    decision = a.evaluate(queue_len=25, current_replicas=2, now=0)

    assert a.up_streak == 1  # el streak se reinició al pasar por la zona estable
    assert decision.action == "noop"


def test_scale_up_respects_max_replicas():
    """En el techo, una cola desbordada no puede escalar: noop con motivo MAX."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=1, max_replicas=8))

    decision = a.evaluate(queue_len=25, current_replicas=8, now=0)

    assert decision.action == "noop"
    assert "MAX_REPLICAS" in decision.reason
    assert decision.target_replicas is None


def test_scale_up_caps_target_at_max_when_step_would_overflow():
    """Si current + paso superaría el techo, el target se satura en MAX."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=1, max_replicas=8))

    decision = a.evaluate(queue_len=25, current_replicas=7, now=0)

    assert decision.action == "up"
    assert decision.target_replicas == 8


# --- Escalar hacia abajo ----------------------------------------------------


def test_scale_down_requires_stable_cycles():
    """Con SCALE_DOWN_STABLE_CYCLES=4, hace falta cola baja 4 ciclos seguidos
    para bajar (para no hacer caso a respiros momentáneos)."""
    a, _, _ = make_autoscaler(make_settings(scale_down_stable_cycles=4))

    decisions = [a.evaluate(queue_len=0, current_replicas=4, now=0) for _ in range(4)]

    assert [d.action for d in decisions[:3]] == ["noop"] * 3
    assert decisions[3].action == "down"
    assert decisions[3].target_replicas == 3


def test_scale_down_respects_min_replicas():
    """En el piso no se puede bajar más: noop con motivo MIN."""
    a, _, _ = make_autoscaler(make_settings(scale_down_stable_cycles=1, min_replicas=1))

    decision = a.evaluate(queue_len=0, current_replicas=1, now=0)

    assert decision.action == "noop"
    assert "MIN_REPLICAS" in decision.reason
    assert decision.target_replicas is None


def test_scale_down_floors_target_at_min_when_step_would_underflow():
    """current - paso nunca puede quedar por debajo de MIN."""
    a, _, _ = make_autoscaler(make_settings(scale_down_stable_cycles=1, min_replicas=1))

    decision = a.evaluate(queue_len=0, current_replicas=2, now=0)

    assert decision.action == "down"
    assert decision.target_replicas == 1


# --- Zona estable -----------------------------------------------------------


def test_comfort_zone_between_thresholds_is_noop_and_resets_streaks():
    """Cola entre SCALE_DOWN_THRESHOLD y SCALE_UP_THRESHOLD: no op y resetea
    los streaks acumulados."""
    a, _, _ = make_autoscaler()
    a.up_streak = 3
    a.down_streak = 2

    decision = a.evaluate(queue_len=5, current_replicas=4, now=0)

    assert decision.action == "noop"
    assert "zona estable" in decision.reason
    assert a.up_streak == 0
    assert a.down_streak == 0


def test_boundary_exact_threshold_is_comfort_zone():
    """cola == SCALE_UP_THRESHOLD no dispara subida (es ''más de''); cola ==
    SCALE_DOWN_THRESHOLD no dispara bajada (es ''menos de'')."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=1, scale_down_stable_cycles=1))

    assert a.evaluate(queue_len=10, current_replicas=2, now=0).action == "noop"
    assert a.evaluate(queue_len=1, current_replicas=2, now=0).action == "noop"


# --- Cooldown ---------------------------------------------------------------


def test_cooldown_blocks_scale_up_until_expired():
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=1, scale_cooldown_seconds=60))
    a.cooldown_until = 100  # cooldown activo hasta t=100

    blocked = a.evaluate(queue_len=25, current_replicas=2, now=50)
    allowed = a.evaluate(queue_len=25, current_replicas=2, now=100)

    assert blocked.action == "noop"
    assert "cooldown" in blocked.reason
    assert allowed.action == "up"
    assert allowed.target_replicas == 3


def test_cooldown_blocks_scale_down_until_expired():
    a, _, _ = make_autoscaler(make_settings(scale_down_stable_cycles=1, scale_cooldown_seconds=60))
    a.cooldown_until = 100

    blocked = a.evaluate(queue_len=0, current_replicas=4, now=50)

    assert blocked.action == "noop"
    assert "cooldown" in blocked.reason


def test_streaks_keep_accumulating_during_cooldown():
    """Durante el cooldown el streak se sigue acumulando: apenas expire, la
    siguiente evaluación escala sin esperar otros ciclos de confirmación."""
    a, _, _ = make_autoscaler(make_settings(scale_up_stable_cycles=2, scale_cooldown_seconds=60))
    a.cooldown_until = 100

    a.evaluate(queue_len=25, current_replicas=2, now=50)  # 1/2, cooldown
    a.evaluate(queue_len=25, current_replicas=2, now=50)  # 2/2, cooldown
    decision = a.evaluate(queue_len=25, current_replicas=2, now=100)  # cooldown expirado

    assert decision.action == "up"
    assert decision.target_replicas == 3


# --- run_cycle: capa de I/O con docker/redis mockeados ----------------------


def test_run_cycle_scales_up_via_docker_client():
    """run_cycle lee la cola, decide subir y llama service.scale() con el target."""
    settings = make_settings(scale_up_stable_cycles=1, scale_cooldown_seconds=60)
    a, docker_client, service = make_autoscaler(settings, replicas=2)
    a.r.llen.return_value = 25

    decision = a.run_cycle(now=0)

    assert decision.action == "up"
    assert decision.target_replicas == 3
    service.scale.assert_called_once_with(3)
    assert a.cooldown_until == 60


def test_run_cycle_does_not_scale_on_comfort_zone():
    settings = make_settings(scale_up_stable_cycles=1, scale_down_stable_cycles=1)
    a, docker_client, service = make_autoscaler(settings, replicas=2)
    a.r.llen.return_value = 5

    decision = a.run_cycle(now=0)

    assert decision.action == "noop"
    service.scale.assert_not_called()


class FakeService:
    """Spec Swarm mutable: attrs reflejan el `scale()` para simular que el
    engine actualizó el desired replicas."""

    def __init__(self, replicas: int):
        self.attrs = {"Spec": {"Mode": {"Replicated": {"Replicas": replicas}}}}

    def scale(self, count: int):
        self.attrs["Spec"]["Mode"]["Replicated"]["Replicas"] = count


class FakeDockerClient:
    def __init__(self, replicas: int):
        self.service = FakeService(replicas)
        self.services = MagicMock()
        self.services.get.return_value = self.service


def test_full_cycle_up_then_down_with_fake_docker_spec():
    """Ciclo completo con cliente Docker fake: sube hasta el máximo y, al vaciar
    la cola, baja hasta el mínimo, respetando MIN/MAX. w/ cooldown=0."""
    settings = make_settings(
        scale_up_stable_cycles=1,
        scale_down_stable_cycles=1,
        scale_cooldown_seconds=0,
        min_replicas=1,
        max_replicas=4,
    )
    fake_docker = FakeDockerClient(replicas=2)
    a = autoscaler.Autoscaler(MagicMock(), fake_docker, settings)

    # Ráfaga sostenida (2 ciclos altos) y luego cola vacía (5 ciclos): el
    # side_effect alimenta en orden las 6 llamadas a run_cycle().
    a.r.llen.side_effect = [25, 30, 0, 0, 0, 0]

    decisions = [a.run_cycle(now=0) for _ in range(6)]

    assert [d.action for d in decisions] == ["up", "up", "down", "down", "down", "noop"]
    assert fake_docker.service.attrs["Spec"]["Mode"]["Replicated"]["Replicas"] == 1
    assert "MIN_REPLICAS" in decisions[-1].reason


def test_from_env_applies_overrides(monkeypatch):
    """Los defaults se reemplazan por variables de entorno."""
    monkeypatch.setenv("SCALE_UP_THRESHOLD", "30")
    monkeypatch.setenv("MAX_REPLICAS", "6")
    monkeypatch.setenv("SCALE_COOLDOWN_SECONDS", "120")
    monkeypatch.setenv("WORKER_SERVICE_NAME", "certicheck_worker")

    settings = autoscaler.AutoscalerSettings.from_env()

    assert settings.scale_up_threshold == 30
    assert settings.max_replicas == 6
    assert settings.scale_cooldown_seconds == 120
    assert settings.interval_seconds == 15  # default intacto


def test_from_env_defaults():
    settings = autoscaler.AutoscalerSettings.from_env()

    assert settings.interval_seconds == 15
    assert settings.scale_up_threshold == 10
    assert settings.scale_up_stable_cycles == 2
    assert settings.scale_down_threshold == 1
    assert settings.scale_down_stable_cycles == 4
    assert settings.replicas_step_up == 1
    assert settings.replicas_step_down == 1
    assert settings.min_replicas == 1
    assert settings.max_replicas == 8
    assert settings.scale_cooldown_seconds == 60