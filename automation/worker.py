import json
import os
import threading
from concurrent.futures import ThreadPoolExecutor

import redis
import requests
from dotenv import load_dotenv
from playwright.sync_api import sync_playwright

from sites import rnmc, policia_judicial, contraloria, procuraduria

load_dotenv()

REDIS_HOST = os.getenv("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
QUEUE_NAME = "certificate_jobs"
MAX_WORKERS = int(os.getenv("WORKER_CONCURRENCY", 3))

LARAVEL_BASE_URL = os.getenv("LARAVEL_BASE_URL", "http://127.0.0.1:8000")
INTERNAL_API_KEY = os.getenv("INTERNAL_API_KEY")

SITE_HANDLERS = {
    "rnmc": rnmc.consultar,
    "judicial_police": policia_judicial.consultar,
    "comptroller": contraloria.consultar,
    "attorney_general": procuraduria.consultar,
}

r = redis.Redis(
    host=REDIS_HOST,
    port=REDIS_PORT,
    decode_responses=True,
    socket_timeout=10,
    socket_connect_timeout=10,
)

_thread_local = threading.local()


def get_browser():
    if not hasattr(_thread_local, "playwright"):
        _thread_local.playwright = sync_playwright().start()
        _thread_local.browser = _thread_local.playwright.chromium.launch(headless=True)
    return _thread_local.browser


def reportar_resultado(certificate_request_id: int, resultado: dict):
    url = f"{LARAVEL_BASE_URL}/api/internal/certificate-requests/{certificate_request_id}/complete"
    headers = {"X-Internal-Api-Key": INTERNAL_API_KEY}

    try:
        if resultado["status"] == "success":
            pdf_path = resultado["pdf_path"]
            with open(pdf_path, "rb") as f:
                files = {"pdf": (os.path.basename(pdf_path), f, "application/pdf")}
                data = {"status": "success"}
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
            resp = requests.post(url, headers=headers, data=data, timeout=30)

        if resp.status_code != 200:
            print(f"[ERROR] Laravel respondió {resp.status_code} para id={certificate_request_id}: {resp.text}")
        else:
            print(f"[OK] Reportado id={certificate_request_id}: {resultado['status']}")

    except Exception as e:
        print(f"[ERROR] No se pudo reportar el resultado a Laravel para id={certificate_request_id}: {e}")


def procesar_job(payload: dict):
    certificate_request_id = payload["certificate_request_id"]
    site = payload["site"]

    handler = SITE_HANDLERS.get(site)
    if handler is None:
        reportar_resultado(certificate_request_id, {"status": "failed", "error_message": f"Sitio desconocido: {site}"})
        return

    print(f"[DEBUG] Procesando id={certificate_request_id} site={site}")

    try:
        browser = get_browser()
        resultado = handler(
            payload["document_type"],
            payload["document_number"],
            payload.get("full_name"),
            payload.get("issuance_date"),
            browser=browser,
        )
    except Exception as e:
        resultado = {"status": "failed", "error_message": f"Error inesperado en el worker: {e}"}

    reportar_resultado(certificate_request_id, resultado)


def main():
    print(f"[WORKER] Escuchando '{QUEUE_NAME}' en Redis {REDIS_HOST}:{REDIS_PORT} (máx {MAX_WORKERS} en paralelo)")
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        while True:
            item = r.blpop(QUEUE_NAME, timeout=5)
            if item is None:
                continue
            _, raw_payload = item
            try:
                payload = json.loads(raw_payload)
            except json.JSONDecodeError:
                print(f"[ERROR] Mensaje inválido en la cola: {raw_payload}")
                continue
            executor.submit(procesar_job, payload)


if __name__ == "__main__":
    main()