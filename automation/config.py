import os
from pathlib import Path

from dotenv import load_dotenv

load_dotenv()

# Directorio temporal donde los scrapers escriben los PDFs generados antes de
# subirlos a Laravel. Se monta como volumen si se quiere persistir entre runs.
TEMP_CERTS_DIR = Path(os.getenv("TEMP_CERTS_DIR", Path(__file__).parent / "temp_certs"))
TEMP_CERTS_DIR.mkdir(parents=True, exist_ok=True)

# --- Conexión a Redis ------------------------------------------------------
# Dentro de Docker apuntan al servicio Redis (ver docker-compose.yml). En
# desarrollo local usan 127.0.0.1 por defecto.
REDIS_HOST = os.getenv("REDIS_HOST", "127.0.0.1")
REDIS_PORT = int(os.getenv("REDIS_PORT", 6379))
QUEUE_NAME = os.getenv("QUEUE_NAME", "certificate_jobs")

# --- Conexión a la API interna de Laravel -----------------------------------
# Dentro de Docker apunta al host de Laravel (ver docker-compose.yml).
LARAVEL_BASE_URL = os.getenv("LARAVEL_BASE_URL", "http://127.0.0.1:8000")
INTERNAL_API_KEY = os.getenv("INTERNAL_API_KEY", "")

# --- Concurrencia -----------------------------------------------------------
# Pool general de hilos del worker.
WORKER_POOL_SIZE = int(os.getenv("WORKER_POOL_SIZE", 10))

# Límites de concurrencia independientes por sitio.
SITE_CONCURRENCY = {
    "rnmc": int(os.getenv("MAX_CONCURRENCY_RNMC", 4)),
    "comptroller": int(os.getenv("MAX_CONCURRENCY_CONTRALORIA", 2)),
    "judicial_police": int(os.getenv("MAX_CONCURRENCY_POLICIA_JUDICIAL", 2)),
    "attorney_general": int(os.getenv("MAX_CONCURRENCY_PROCURADURIA", 1)),
}

# Tiempo (segundos) a esperar por los jobs en curso al recibir SIGTERM antes
# de forzar la salida durante el apagado ordenado.
SHUTDOWN_WAIT_SECONDS = int(os.getenv("SHUTDOWN_WAIT_SECONDS", 60))

# Número máximo de veces que un job que salió de la cola (BLPOP) pero NO llegó a
# ejecutarse puede ser re-encolado en Redis cuando el worker recibe SIGTERM (o
# llega al timeout de shutdown). Si un job agota este límite queda definitivamente
# sin procesar y se registra en CRITICAL para atención manual.
MAX_REQUEUE_ATTEMPTS = int(os.getenv("MAX_REQUEUE_ATTEMPTS", 3))

# --- Semáforo distribuido ----------------------------------------------------
# Lease (segundos) de cada permiso del semáforo por sitio. Si un worker crashea
# a mitad de un job, su permiso expira solo al cabo de este tiempo y otro worker
# puede reclamarlo sin intervención manual.
SEMAPHORE_LEASE_SECONDS = int(os.getenv("SEMAPHORE_LEASE_SECONDS", 180))

# Tiempo máximo (segundos) que un job espera a que se libere un permiso para su
# sitio antes de declararse como fallido (timeout).
SEMAPHORE_ACQUIRE_TIMEOUT = int(os.getenv("SEMAPHORE_ACQUIRE_TIMEOUT", 600))

# --- Modo DRY_RUN -------------------------------------------------------------
# Cuando DRY_RUN=true, el worker ejecuta TODO el flujo real (BLPOP, parseo del
# payload, semáforo distribuido por sitio, pool de hilos) pero NO lanza
# Playwright ni toca ningún portal: simula el trabajo con un sleep de
# DRY_RUN_DURATION_SECONDS y loguea un resultado simulado. Se usa únicamente
# para pruebas (colas, semáforos, límites por sitio) sin afectar portales reales.
DRY_RUN = os.getenv("DRY_RUN", "false").strip().lower() in ("1", "true", "yes", "on")
DRY_RUN_DURATION_SECONDS = float(os.getenv("DRY_RUN_DURATION_SECONDS", 2))

# Timeout del BLPOP. Un valor bajo hace que el worker revise el flag de
# apagado con mayor frecuencia.
BLPOP_TIMEOUT = 5
