# Imagen oficial de Playwright para Python con Chromium y sus dependencias de
# sistema preinstaladas. La versión de imagen DEBE coincidir con la de
# playwright==1.53.0 fijada en requirements.txt.
FROM mcr.microsoft.com/playwright/python:v1.53.0-noble

# Evita que Python genere .pyc y buffering de stdout (necesario para ver los
# logs del worker en tiempo real dentro del contenedor).
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app/automation

# Primero copiamos solo los archivos de dependencias para aprovechar la caché
# de capas de Docker (instalar dependencias solo cambia si cambian los
# requirements).
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Después copiamos el resto del código.
COPY . .

# WORKER_POOL_SIZE se dimensiona dentro del contenedor: cada réplica tiene su
# propio pool de hilos; el límite agregado de concurrencia por sitio se controla
# con MAX_CONCURRENCY_* (cada réplica aplica su propio semáforo local).
ENV WORKER_POOL_SIZE=10

# El worker usa señales (SIGTERM) para apagado ordenado. Es el proceso principal.
STOPSIGNAL SIGTERM

CMD ["python", "worker.py"]
