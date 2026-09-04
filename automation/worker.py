import json
import logging
import os
import signal
import threading
import time
from concurrent.futures import ThreadPoolExecutor, wait as futures_wait

import redis
import requests
from playwright.sync_api import sync_playwright

import config
from redis_semaphore import DistributedSemaphore
from sites import rnmc, policia_judicial, contraloria, procuraduria

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("certifcheck-worker")

REDIS_HOST = config.REDIS_HOST
REDIS_PORT = config.REDIS_PORT
QUEUE_NAME = config.QUEUE_NAME
POOL_SIZE = config.WORKER_POOL_SIZE

LARAVEL_BASE_URL = config.LARAVEL_BASE_URL
INTERNAL_API_KEY = config.INTERNAL_API_KEY

SITE_HANDLERS = {
    "rnmc": rnmc.consultar,
    "judicial_police": policia_judicial.consultar,
    "comptroller": contraloria.consultar,
    "attorney_general": procuraduria.consultar,
}

SITE_WAITING_COUNTERS = {site: {"waiting": 0} for site in config.SITE_CONCURRENCY}
_counter_lock = threading.Lock()

r = redis.Redis(
    host=REDIS_HOST,
    port=REDIS_PORT,
    decode_responses=True,
    socket_timeout=10,
    socket_connect_timeout=10,
)

# Semáforo distribuido respaldado en Redis. Garantiza que el límite por sitio se
# cumpla GLOBALMENTE entre todas las réplicas del worker que comparten el mismo
# Redis (no por contenedor). Cada permiso tiene un lease que lo libera
# automáticamente si el proceso que lo tomó crashea.
SEMAPHORE = DistributedSemaphore(
    r,
    config.SITE_CONCURRENCY,
    lease_seconds=config.SEMAPHORE_LEASE_SECONDS,
)

_thread_local = threading.local()

# Capacidad de "jobs en vuelo" por réplica (encolados en el pool + corriendo).
# El bucle principal SOLO reclama jobs de Redis cuando hay un slot libre en este
# semáforo: sin esto, executor.submit() (cola de tareas ilimitada del
# ThreadPoolExecutor) vaciaría TODA la cola de Redis en memoria al instante y
# `LLEN certificate_jobs` dejaría de reflejar la demanda no atendida (rompiendo
# el autoescalado basado en profundidad de cola, además de un riesgo de memoria
# por réplica). Con el límite, la longitud de la cola en Redis mide el backlog
# real: jobs que aún no encontraron worker.
IN_FLIGHT_SLOT = threading.BoundedSemaphore(POOL_SIZE)


def _pop_payload(in_flight_slots, redis_client, queue_name) -> str | None:
    """Equivale a un BLPOP CON capacidad acotada.

    Adquiere un slot de capacidad; si no hay slot libre en 1s devuelve None SIN
    tocar Redis (el llamador vuelve a chequear el flag de apagado en el while).
    Si hay slot, hace BLPOP y: devuelve el payload crudo (el slot queda OCUPADO,
    se libera cuando el future termina), o None (timeout/error/cola vacía, el
    slot se libera de inmediato porque no se tomó ningún job).
    """
    if not in_flight_slots.acquire(timeout=1.0):
        return None
    try:
        item = redis_client.blpop(queue_name, timeout=config.BLPOP_TIMEOUT)
    except Exception as e:
        in_flight_slots.release()
        logger.warning("[REDIS] Error en BLPOP (no crítico, se reintenta): %s", e)
        return None
    if item is None:
        in_flight_slots.release()
        return None
    return item[1]


def get_browser():
    if not hasattr(_thread_local, "playwright"):
        _thread_local.playwright = sync_playwright().start()
        _thread_local.browser = _thread_local.playwright.chromium.launch(headless=True)
    return _thread_local.browser


def reportar_resultado(certificate_request_id: int, resultado: dict, duration_seconds: int | None = None):
    url = f"{LARAVEL_BASE_URL}/api/internal/certificate-requests/{certificate_request_id}/complete"
    headers = {"X-Internal-Api-Key": INTERNAL_API_KEY}

    try:
        if resultado["status"] == "success":
            pdf_path = resultado["pdf_path"]
            with open(pdf_path, "rb") as f:
                files = {"pdf": (os.path.basename(pdf_path), f, "application/pdf")}
                data = {"status": "success"}
                if duration_seconds is not None:
                    data["duration_seconds"] = duration_seconds
                resp = requests.post(url, headers=headers, data=data, files=files, timeout=30)
            try:
                os.remove(pdf_path)
            except OSError:
                pass
        else:
            data = {
                "status": "failed",
                "error_message": resultado.get("error_message", "Error desconocido"),
            }
            if duration_seconds is not None:
                data["duration_seconds"] = duration_seconds
            resp = requests.post(url, headers=headers, data=data, timeout=30)

        if resp.status_code != 200:
            logger.error("Laravel respondió %s para id=%s: %s", resp.status_code, certificate_request_id, resp.text)
        else:
            logger.info("[OK] Reportado id=%s: %s", certificate_request_id, resultado["status"])

    except Exception as e:
        logger.error("No se pudo reportar el resultado a Laravel para id=%s: %s", certificate_request_id, e)


def _increment_waiting(site: str):
    with _counter_lock:
        SITE_WAITING_COUNTERS[site]["waiting"] += 1
        return SITE_WAITING_COUNTERS[site]["waiting"]


def _decrement_waiting(site: str):
    with _counter_lock:
        if SITE_WAITING_COUNTERS[site]["waiting"] > 0:
            SITE_WAITING_COUNTERS[site]["waiting"] -= 1


def _ejecutar_site(payload: dict, site: str, certificate_request_id: int):
    """Ejecuta el trabajo del sitio.

    En modo DRY_RUN NO lanza Playwright ni toca portales: simula el trabajo con
    un sleep configurable y devuelve un resultado simulado. En modo normal
    ejecuta el handler real del scraping.
    """
    if config.DRY_RUN:
        logger.info(
            "[DRY_RUN] Simulando trabajo para site=%s id=%s (sleep %.1fs) - sin Playwright ni red a portales",
            site,
            certificate_request_id,
            config.DRY_RUN_DURATION_SECONDS,
        )
        time.sleep(config.DRY_RUN_DURATION_SECONDS)
        return {"status": "success", "dry_run": True}

    handler = SITE_HANDLERS[site]
    browser = get_browser()
    return handler(
        payload["document_type"],
        payload["document_number"],
        payload.get("full_name"),
        payload.get("issuance_date"),
        browser=browser,
    )


def procesar_job(payload: dict):
    certificate_request_id = payload["certificate_request_id"]
    site = payload["site"]

    handler = SITE_HANDLERS.get(site)

    if handler is None:
        reportar_resultado(certificate_request_id, {"status": "failed", "error_message": f"Sitio desconocido: {site}"})
        return

    token = SEMAPHORE.try_acquire(site)
    if token is None:
        waiting = _increment_waiting(site)
        logger.warning(
            "[ESPERA] Límite global de concurrencia lleno para sitio=%s, id=%s esperando. Jobs esperando de %s: %d",
            site,
            certificate_request_id,
            site,
            waiting,
        )
        token = SEMAPHORE.acquire(site, timeout=config.SEMAPHORE_ACQUIRE_TIMEOUT)
        _decrement_waiting(site)
        if token is None:
            reportar_resultado(
                certificate_request_id,
                {"status": "failed", "error_message": f"Timeout esperando permiso de concurrencia para {site}"},
            )
            return
        logger.info(
            "[ESPERA] Job id=%s de sitio=%s comenzó tras liberarse slot. Jobs esperando de %s: %d",
            certificate_request_id,
            site,
            site,
            SITE_WAITING_COUNTERS[site]["waiting"],
        )
    else:
        logger.info("[OK] Slot adquirido para sitio=%s id=%s (límite %s)", site, certificate_request_id, site)

    try:
        logger.info("[PROCESANDO] JOB id=%s site=%s INICIO", certificate_request_id, site)
        logger.debug("Procesando id=%s site=%s", certificate_request_id, site)
        start_time = time.monotonic()
        resultado = _ejecutar_site(payload, site, certificate_request_id)
        duration_seconds = int(round(time.monotonic() - start_time))
    except Exception as e:
        resultado = {"status": "failed", "error_message": f"Error inesperado en el worker: {e}"}
        duration_seconds = None
    finally:
        logger.info("[PROCESANDO] JOB id=%s site=%s FIN", certificate_request_id, site)
        SEMAPHORE.release(site, token)

    # Log del resultado final de ESTE job, inmediatamente tras terminar el
    # scraping real (o la simulacion) y ANTES de reportar a Laravel. Permite
    # ver el status/exito/error local incluso si Laravel o MySQL estan caidos.
    resultado_status = resultado.get("status", "unknown")
    resultado_duration = f"{duration_seconds}s" if duration_seconds is not None else "n/a"
    resultado_error = resultado.get("error_message")
    if resultado_error:
        logger.info(
            "[RESULTADO] id=%s site=%s status=%s duracion=%s error=%s",
            certificate_request_id,
            site,
            resultado_status,
            resultado_duration,
            resultado_error,
        )
    else:
        logger.info(
            "[RESULTADO] id=%s site=%s status=%s duracion=%s",
            certificate_request_id,
            site,
            resultado_status,
            resultado_duration,
        )

    if config.DRY_RUN:
        # En DRY_RUN NO se llama a reportar_resultado real (no pega a la API
        # interna de Laravel). El resultado ya quedo logueado arriba.
        return

    reportar_resultado(certificate_request_id, resultado, duration_seconds=duration_seconds)


def reencolar_job(payload: dict) -> bool:
    """Reencola en Redis un job que salió de la cola (BLPOP) pero aún NO ejecutó
    trabajo real (su future se canceló antes de arrancar, o se llegó al timeout
    de shutdown con jobs bloqueados esperando el semáforo).

    Incrementa su contador `retry_count` (default 0) y lo deja al FRENTE de la
    cola para que otra réplica activa lo tome cuanto antes. Si el job ya alcanzó
    `MAX_REQUEUE_ATTEMPTS` NO se reencola: queda definitivamente sin procesar y
    se registra un CRITICAL inequívoco para marcarlo para atención manual.
    Devuelve True solo si efectivamente se reencoló.
    """
    certificate_request_id = payload.get("certificate_request_id")
    site = payload.get("site")
    attempt = int(payload.get("retry_count", 0))

    if attempt >= config.MAX_REQUEUE_ATTEMPTS:
        logger.critical(
            "[REQUEUE] certificate_request_id=%s site=%s alcanzó el límite de %d "
            "reintentos de reencolado. QUEDA DEFINITIVAMENTE SIN PROCESAR: "
            "requiere atención manual.",
            certificate_request_id,
            site,
            config.MAX_REQUEUE_ATTEMPTS,
        )
        return False

    retried_payload = dict(payload)
    retried_payload["retry_count"] = attempt + 1
    r.lpush(QUEUE_NAME, json.dumps(retried_payload, ensure_ascii=False))
    logger.warning(
        "[REQUEUE] certificate_request_id=%s site=%s reencolado al frente de la "
        "cola (intento %d/%d).",
        certificate_request_id,
        site,
        attempt + 1,
        config.MAX_REQUEUE_ATTEMPTS,
    )
    return True


def _requeue_futures_not_started(future_to_payload: dict) -> int:
    """Cancela los futures TODAVÍA NO iniciados (siguen en la cola interna del
    ThreadPoolExecutor) y reencola su payload original en Redis. Los futures que
    YA están corriendo no se tocan (el shutdown los espera). Si `future.cancel()`
    termina devolviendo False (un hilo acabó de tomarlo), se deja correr y el
    shutdown lo esperará. Devuelve cuántos jobs se reencolaron.
    """
    requeued = 0
    for future in list(future_to_payload):
        if future.done() or future.running():
            continue
        payload = future_to_payload.get(future)
        if future.cancel():
            if payload is not None:
                reencolar_job(payload)
                requeued += 1
    return requeued


def _requeue_all_pending(future_to_payload: dict) -> int:
    """Reencola TODOS los payloads de futures no terminados. Es el camino del
    timeout de shutdown: un job 'pendiente' a esa altura suele estar bloqueado
    esperando el semáforo distribuido (no hace trabajo real), así que reencolarlo
    no duplica procesamiento. Se intenta cancelar cada future como best-effort y
    el `os._exit(1)` inmediato del llamador mata el proceso antes de que un hilo
    pueda completar el work (evitando duplicados). Devuelve cuántos se reencoló..
    """
    requeued = 0
    for future in list(future_to_payload):
        if future.done():
            continue
        payload = future_to_payload.pop(future, None)
        if payload is None:
            continue
        future.cancel()  # best-effort: pending -> CANCELLED; running no-op
        reencolar_job(payload)
        requeued += 1
    return requeued


def main():
    limits_summary = ", ".join(f"{site}={limit}" for site, limit in config.SITE_CONCURRENCY.items())
    if config.DRY_RUN:
        logger.warning(
            "[DRY_RUN] MODO DE PRUEBA ACTIVO - NO se lanza Playwright ni se tocan portales reales. "
            "Los jobs se simulan con sleep de %.1fs y NO se reporta a Laravel.",
            config.DRY_RUN_DURATION_SECONDS,
        )
    logger.info(
        "[WORKER] Escuchando '%s' en Redis %s:%s (pool=%d, límites por sitio: %s%s)",
        QUEUE_NAME,
        REDIS_HOST,
        REDIS_PORT,
        POOL_SIZE,
        limits_summary,
        " | DRY_RUN=ON" if config.DRY_RUN else "",
    )

    shutdown_event = threading.Event()

    def _handle_shutdown(signum, frame):
        logger.info("[SHUTDOWN] Recibida señal %s. Dejando de tomar jobs nuevos...", signum)
        shutdown_event.set()

    signal.signal(signal.SIGTERM, _handle_shutdown)
    # SIGINT también se captura para permitir Ctrl+C en desarrollo local.
    signal.signal(signal.SIGINT, _handle_shutdown)

    # Mapa future -> payload original. Permite recuperar el payload EXACTO de
    # cualquier future (para reencolarlo) sin reconstruirlo. El done-callback
    # purga la entrada cuando el future termina (normal o cancelado).
    future_to_payload: dict = {}
    futures_lock = threading.Lock()

    def _forget(future):
        with futures_lock:
            future_to_payload.pop(future, None)
        # El job asociado a este future dejó de ocupar capacidad (terminó o fue
        # reencolado): liberar su slot de in-flight.
        IN_FLIGHT_SLOT.release()

    def _track(future, payload):
        with futures_lock:
            future_to_payload[future] = payload
        future.add_done_callback(_forget)

    with ThreadPoolExecutor(max_workers=POOL_SIZE) as executor:
        while not shutdown_event.is_set():
            raw_payload = _pop_payload(IN_FLIGHT_SLOT, r, QUEUE_NAME)
            if raw_payload is None:
                # Sin slot libre (cap. llena) o BLPOP sin datos: no se tomó job.
                continue
            try:
                payload = json.loads(raw_payload)
            except json.JSONDecodeError:
                IN_FLIGHT_SLOT.release()
                logger.error("Mensaje inválido en la cola: %s", raw_payload)
                continue
            future = executor.submit(procesar_job, payload)
            _track(future, payload)

        # 1) Jobs que salieron de la cola (BLPOP) pero cuyo future aún NO empezó:
        #    se cancelan y su payload vuelve a Redis (retry_count+1, límite
        #    MAX_REQUEUE_ATTEMPTS). Ningún certificado se pierde al escalar hacia
        #    abajo: las réplicas que quedan activas lo toman de nuevo.
        n_requeued = _requeue_futures_not_started(future_to_payload)
        logger.info(
            "[SHUTDOWN] %d job(s) que no habían empezado fueron reencolados a la cola.",
            n_requeued,
        )
        logger.info(
            "[SHUTDOWN] Esperando a que terminen los jobs en curso (máx %ss)...",
            config.SHUTDOWN_WAIT_SECONDS,
        )

        # No aceptar jobs nuevos en el pool. NO usamos cancel_futures=True: los
        # no iniciados ya se cancelaron y reencolaron explícitamente arriba.
        executor.shutdown(wait=False, cancel_futures=False)

        deadline = time.monotonic() + config.SHUTDOWN_WAIT_SECONDS
        while True:
            with futures_lock:
                pending = [f for f in future_to_payload if not f.done()]
            if not pending:
                break
            remaining = deadline - time.monotonic()
            if remaining <= 0:
                # Los pendientes a esta altura suelen estar bloqueados esperando
                # el semáforo distribuido (sin trabajo real). Se reencolan para
                # no perderlos ni siquiera en el camino forzado de salida.
                n_timeout = _requeue_all_pending(future_to_payload)
                logger.warning(
                    "[SHUTDOWN] Timeout de %ss alcanzado con %d job(s) aún "
                    "pendientes. Reencolados %d; forzando salida.",
                    config.SHUTDOWN_WAIT_SECONDS,
                    len(pending),
                    n_timeout,
                )
                os._exit(1)
            done, _ = futures_wait(pending, timeout=min(1.0, remaining))
            time.sleep(0.05)

        logger.info("[SHUTDOWN] Apagado ordenado completado.")


if __name__ == "__main__":
    main()
