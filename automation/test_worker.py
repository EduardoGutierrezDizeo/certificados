import json
import logging
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from unittest.mock import MagicMock

import pytest
import redis

import config
import redis_semaphore
import worker

# Los tests usan el mismo Redis real (certicheck-redis) que el worker en
# producción, pero siempre limpian sus claves del semáforo antes y después.
TEST_REDIS = redis.Redis(host="127.0.0.1", port=6379, decode_responses=True)
SITES = ["rnmc", "comptroller", "judicial_police", "attorney_general"]


@pytest.fixture(autouse=True)
def _reset_state(monkeypatch):
    """Rebuild the global Redis-backed semaphore with known limits and clear
    its keys (and the job queue), so each test starts from a predictable state."""
    redis_semaphore.flush_semaphore_keys(TEST_REDIS, SITES)
    TEST_REDIS.delete(worker.QUEUE_NAME)
    monkeypatch.setattr(worker, "SEMAPHORE", redis_semaphore.DistributedSemaphore(
        TEST_REDIS,
        config.SITE_CONCURRENCY,
        lease_seconds=30,
    ))
    worker.SITE_WAITING_COUNTERS = {site: {"waiting": 0} for site in config.SITE_CONCURRENCY}
    worker.reportar_resultado = lambda *a, **k: None
    monkeypatch.setattr(worker.config, "DRY_RUN", False)
    monkeypatch.setattr(worker.config, "DRY_RUN_DURATION_SECONDS", 0.01)
    yield
    redis_semaphore.flush_semaphore_keys(TEST_REDIS, SITES)
    TEST_REDIS.delete(worker.QUEUE_NAME)


def _submit_jobs(executor, payloads):
    futures = []
    for payload in payloads:
        futures.append(executor.submit(worker.procesar_job, payload))
    return futures


def _track_concurrency(handler_impl):
    """Wrap a handler so it asserts it never exceeds the global semaphore limit
    and reports the peak concurrency observed for each site."""
    lock = threading.Lock()
    active = {site: 0 for site in config.SITE_CONCURRENCY}
    peak = {site: 0 for site in config.SITE_CONCURRENCY}
    limit = dict(config.SITE_CONCURRENCY)

    def make_site_handler(site):
        def handler(*args, **kwargs):
            with lock:
                active[site] += 1
                peak[site] = max(peak[site], active[site])
            try:
                time.sleep(0.05)
                return {"status": "success", "pdf_path": "dummy.pdf"}
            finally:
                with lock:
                    active[site] -= 1
        return handler

    for site in worker.SITE_HANDLERS:
        worker.SITE_HANDLERS[site] = make_site_handler(site)

    return peak, limit


def _make_payload(site, i):
    return {
        "certificate_request_id": i,
        "site": site,
        "document_type": "CC",
        "document_number": "1000000000",
        "full_name": "Juan Perez",
        "issuance_date": "26/08/2021",
    }


def test_procuraduria_concurrency_never_exceeds_limit():
    site = "attorney_general"
    peak, limit = _track_concurrency(worker.SITE_HANDLERS)
    payloads = [_make_payload(site, i) for i in range(20)]

    with ThreadPoolExecutor() as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert peak[site] <= limit[site]
    assert peak[site] == 1  # limit is 1


def test_contraloria_concurrency_never_exceeds_limit():
    site = "comptroller"
    peak, limit = _track_concurrency(worker.SITE_HANDLERS)
    payloads = [_make_payload(site, i) for i in range(20)]

    with ThreadPoolExecutor() as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert peak[site] <= limit[site]
    assert peak[site] == 2  # limit is 2


def test_rnmc_concurrency_never_exceeds_limit():
    site = "rnmc"
    peak, limit = _track_concurrency(worker.SITE_HANDLERS)
    payloads = [_make_payload(site, i) for i in range(40)]

    with ThreadPoolExecutor() as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert peak[site] <= limit[site]
    assert peak[site] == 4  # limit is 4


def test_policia_judicial_concurrency_never_exceeds_limit():
    site = "judicial_police"
    peak, limit = _track_concurrency(worker.SITE_HANDLERS)
    payloads = [_make_payload(site, i) for i in range(20)]

    with ThreadPoolExecutor() as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert peak[site] <= limit[site]
    assert peak[site] == 2  # limit is 2


def test_mixed_sites_run_under_independent_limits():
    peak, limit = _track_concurrency(worker.SITE_HANDLERS)

    payloads = [_make_payload("attorney_general", i) for i in range(10)]
    payloads += [_make_payload("rnmc", i) for i in range(20)]

    with ThreadPoolExecutor() as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert peak["attorney_general"] == 1 == limit["attorney_general"]
    assert peak["rnmc"] == 4 == limit["rnmc"]


def test_dry_run_never_invokes_handler_or_browser(monkeypatch):
    """Con DRY_RUN=true, procesar_job debe respetar el semáforo pero NO lanzar
    el navegador ni llamar al handler real, y NO reportar a Laravel."""
    monkeypatch.setattr(worker.config, "DRY_RUN", True)
    monkeypatch.setattr(worker.config, "DRY_RUN_DURATION_SECONDS", 0.01)

    handler_calls = {"count": 0}
    original_get_browser = worker.get_browser

    def fake_handler(*args, **kwargs):
        handler_calls["count"] += 1
        return {"status": "success"}

    def fake_get_browser(*args, **kwargs):
        raise AssertionError("get_browser() no debe llamarse en DRY_RUN")

    monkeypatch.setitem(worker.SITE_HANDLERS, "rnmc", fake_handler)
    monkeypatch.setattr(worker, "get_browser", fake_get_browser)

    report_calls = {"count": 0}
    original_report = worker.reportar_resultado

    def fake_report(*args, **kwargs):
        report_calls["count"] += 1

    monkeypatch.setattr(worker, "reportar_resultado", fake_report)

    payloads = [_make_payload("rnmc", i) for i in range(12)]

    with ThreadPoolExecutor(max_workers=8) as executor:
        futures = _submit_jobs(executor, payloads)
        for f in futures:
            f.result(timeout=30)

    assert handler_calls["count"] == 0  # nunca se ejecutó el handler real
    assert report_calls["count"] == 0  # nunca se reportó a Laravel
    monkeypatch.setattr(worker, "get_browser", original_get_browser)
    monkeypatch.setattr(worker, "reportar_resultado", original_report)


# --- Reencolado de jobs ante SIGTERM / shutdown ---------------------------------


def test_cancelled_before_start_future_is_requeued_with_increment(monkeypatch):
    """(a) Un future que sigue PENDIENTE (cancelado antes de arrancar) debe
    reencolar su payload original en Redis con retry_count incrementado."""
    payload = _make_payload("rnmc", 70001)
    monkeypatch.setattr(worker.config, "MAX_REQUEUE_ATTEMPTS", 3)

    blocker_started = threading.Event()
    release_blocker = threading.Event()

    def _blocker():
        blocker_started.set()
        release_blocker.wait(10)

    with ThreadPoolExecutor(max_workers=1) as executor:
        # Ocupamos el único hilo del pool para que el future del job quede pendiente.
        running_task = executor.submit(_blocker)
        assert blocker_started.wait(5)

        future = executor.submit(worker.procesar_job, payload)
        assert not future.done() and not future.running()

        future_to_payload = {future: payload}
        requeued = worker._requeue_futures_not_started(future_to_payload)
        assert requeued == 1
        assert future.cancelled()

        release_blocker.set()
        running_task.result(timeout=5)

    raw = TEST_REDIS.lrange(worker.QUEUE_NAME, 0, -1)
    jobs = [json.loads(x) for x in raw]
    requeued_jobs = [j for j in jobs if j.get("certificate_request_id") == 70001]
    assert len(requeued_jobs) == 1
    assert requeued_jobs[0]["retry_count"] == 1
    assert requeued_jobs[0]["site"] == "rnmc"
    # El payload se conserva completo (document_number, full_name, etc.).
    assert requeued_jobs[0]["document_number"] == payload["document_number"]


def test_requeue_limit_reached_is_not_requeued_and_logged_critical(caplog):
    """(b) Un job que ya agotó MAX_REQUEUE_ATTEMPTS NO se reencola y queda
    registrado con un CRITICAL inequívoco para atención manual."""
    payload = _make_payload("rnmc", 70002)
    payload["retry_count"] = worker.config.MAX_REQUEUE_ATTEMPTS  # ya en el límite

    queue_len_before = TEST_REDIS.llen(worker.QUEUE_NAME)
    with caplog.at_level(logging.CRITICAL, logger="certifcheck-worker"):
        ok = worker.reencolar_job(payload)

    assert ok is False
    assert TEST_REDIS.llen(worker.QUEUE_NAME) == queue_len_before

    critical = [r for r in caplog.records if r.levelno == logging.CRITICAL]
    assert len(critical) == 1
    assert "70002" in critical[0].getMessage()
    assert "SIN PROCESAR" in critical[0].getMessage()


def test_pop_payload_releases_slot_when_blpop_returns_none():
    """Si BLPOP hace timeout (sin datos), el slot no debe quedar tomado."""
    slots = threading.BoundedSemaphore(1)
    fake_redis = MagicMock()
    fake_redis.blpop.return_value = None

    result = worker._pop_payload(slots, fake_redis, worker.QUEUE_NAME)

    assert result is None
    assert slots._value == 1  # slot liberado
    fake_redis.blpop.assert_called_once()


def test_pop_payload_releases_slot_when_blpop_raises():
    """Si BLPOP falla (p.ej. Redis caído un instante), el slot se libera."""
    slots = threading.BoundedSemaphore(1)
    fake_redis = MagicMock()
    fake_redis.blpop.side_effect = Exception("redis caído")

    result = worker._pop_payload(slots, fake_redis, worker.QUEUE_NAME)

    assert result is None
    assert slots._value == 1


def test_pop_payload_returns_payload_and_keeps_slot_taken():
    """Cuando sí hay job, se devuelve el payload y el slot queda OCUPADO (la
    responsabilidad de liberarlo pasa al done-callback del future)."""
    slots = threading.BoundedSemaphore(1)
    fake_redis = MagicMock()
    fake_redis.blpop.return_value = (worker.QUEUE_NAME, '{"certificate_request_id": 1, "site": "rnmc"}')

    result = worker._pop_payload(slots, fake_redis, worker.QUEUE_NAME)

    assert result == '{"certificate_request_id": 1, "site": "rnmc"}'
    assert slots._value == 0  # slot ocupado hasta que el future termine


def test_pop_payload_never_blpops_when_capacity_full():
    """PROPIEDAD CLAVE para el autoescalado: con la capacidad de la réplica
    llena (pool=1 ocupado), el worker NO debe seguir reclamando jobs de Redis.
    Así `LLEN certificate_jobs` refleja el backlog real (demanda no atendida)
    en vez de vaciarse al instante en la memoria del worker."""
    slots = threading.BoundedSemaphore(1)
    slots.acquire()  # capacidad llena
    fake_redis = MagicMock()

    result = worker._pop_payload(slots, fake_redis, worker.QUEUE_NAME)

    assert result is None
    fake_redis.blpop.assert_not_called()  # no tocó Redis
    assert slots._value == 0  # y no "liberó" un slot que no tenía


def test_running_future_is_never_requeued(monkeypatch):
    """(c) Un future que YA está corriendo (hace trabajo real) nunca se reencola:
    evitaría procesar dos veces el mismo certificado. El shutdown lo espera."""
    site = "rnmc"
    handler_started = threading.Event()
    release_handler = threading.Event()

    def slow_handler(*args, **kwargs):
        handler_started.set()
        release_handler.wait(10)
        return {"status": "success", "pdf_path": "dummy.pdf"}

    monkeypatch.setitem(worker.SITE_HANDLERS, site, slow_handler)
    payload = _make_payload(site, 70003)

    with ThreadPoolExecutor(max_workers=2) as executor:
        future = executor.submit(worker.procesar_job, payload)
        assert handler_started.wait(5)  # el job arrancó de verdad
        assert future.running()

        future_to_payload = {future: payload}
        requeued = worker._requeue_futures_not_started(future_to_payload)
        assert requeued == 0
        assert not future.cancelled()

        release_handler.set()
        future.result(timeout=10)

    assert TEST_REDIS.llen(worker.QUEUE_NAME) == 0  # nunca se reencoló
