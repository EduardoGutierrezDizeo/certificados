# Worker de certificados (CertiCheck)

Worker en Python que consume los certificados encolados en Redis
(`certificate_jobs`) y los resuelve contra los portales externos (RNMC,
Contraloría, Policía Judicial y Procuraduría) usando Playwright/Chromium.
Luego reporta el resultado (éxito con PDF, o error) de vuelta a la API interna
de Laravel.

## Variables de entorno

El worker se configura mediante variables de entorno. Los valores por defecto
(desarrollo local) están definidos en `config.py`. Dentro de Docker se fijan
en `docker-compose.yml`.

| Variable                       | Descripción                                             | Default                |
|--------------------------------|---------------------------------------------------------|------------------------|
| `REDIS_HOST`                   | Host de Redis                                           | `127.0.0.1`            |
| `REDIS_PORT`                   | Puerto de Redis                                         | `6379`                 |
| `QUEUE_NAME`                   | Lista Redis de la cola de jobs                          | `certificate_jobs`     |
| `LARAVEL_BASE_URL`             | Base URL de la API interna de Laravel                   | `http://127.0.0.1:8000`|
| `INTERNAL_API_KEY`             | Clave interna para autenticarse ante Laravel            | *(vacía)*              |
| `WORKER_POOL_SIZE`             | Tamaño del pool de hilos del worker                     | `10`                   |
| `MAX_CONCURRENCY_RNMC`         | Máx. trabajos concurrentes para el sitio RNMC           | `4`                    |
| `MAX_CONCURRENCY_CONTRALORIA`  | Máx. trabajos concurrentes para Contraloría             | `2`                    |
| `MAX_CONCURRENCY_POLICIA_JUDICIAL` | Máx. trabajos concurrentes para Policía Judicial    | `2`                    |
| `MAX_CONCURRENCY_PROCURADURIA` | Máx. trabajos concurrentes para Procuraduría            | `1`                    |
| `SHUTDOWN_WAIT_SECONDS`        | Segundos a esperar por jobs en curso al recibir SIGTERM | `60`                   |
| `TEMP_CERTS_DIR`               | Directorio temporal para los PDFs generados             | `./temp_certs`         |

> Nota: los límites `MAX_CONCURRENCY_*` son **locales por réplica**. Cada
> worker (contenedor) aplica su propio semáforo por sitio. Si se escala con
> `--scale worker=N`, el límite agregado efectivo por sitio es el valor del
> `MAX_CONCURRENCY_*` multiplicado por `N`. El pool general
> (`WORKER_POOL_SIZE`) limita cuántos jobs procesa una réplica a la vez.

## Ejecución local

### Worker seguro (pruebas manuales)

**Siempre** usá `start_worker_safe.ps1` para arrancar el worker en desarrollo
local. Este script verifica que no haya otro `worker.py` corriendo antes de
arrancar uno nuevo, previniendo instancias duplicadas que contaminan pruebas y
desperdician intentos de CAPTCHA.

```powershell
# Arrancar worker (verifica duplicados automáticamente)
.\start_worker_safe.ps1

# Detener el worker
.\start_worker_safe.ps1 -Stop
```

El script usa `Get-CimInstance Win32_Process` para detectar cualquier proceso
`worker.py` por su línea de comandos, no solo por nombre de ejecutable. Si ya
hay uno corriendo, se niega a arrancar otro y muestra los PIDs existentes.

### Ejecución directa (alternativa)

```bash
pip install -r requirements-dev.txt
python worker.py
```

Requiere un Redis local y el servidor de Laravel corriendo en `127.0.0.1:8000`.

### Redis: fuente de verdad única (docker-compose)

Redis se gestiona **solo** a través de `docker-compose.yml` (servicio `redis`, el
contenedor se llama `certicheck-redis` y expone el puerto 6379). Es la fuente de
verdad hacia adelante: tanto los workers como cualquier otra cosa apuntan a ese
mismo Redis.

> No levantes un contenedor Redis standalone con `docker run --name
> certicheck-redis ...`: compite por el mismo nombre y puerto 6379 con el
> servicio de compose y no pueden correr a la vez. En la Fase 2 (autoescalado)
> es esencial que todos los workers compartan este mismo Redis.

## Docker

Construye la imagen del worker (basada en la imagen oficial de Playwright para
Python) y levanta la pila:

```bash
# Stack completo (1 worker + Redis del proyecto)
docker compose up -d

# Escalar a N réplicas independientes del worker
docker compose up -d --scale worker=2
```

Los workers consumen de la misma cola Redis con `BLPOP`, por lo que reparten
jobs entre réplicas sin colisión.

### Apagado ordenado

Al recibir `SIGTERM` (p. ej. `docker compose stop`), el worker deja de tomar
jobs nuevos, termina los que están en curso (hasta `SHUTDOWN_WAIT_SECONDS`) y
sale con código 0.

### Prueba de Chromium dentro del contenedor

```bash
docker run --rm certificados-worker python smoke_test.py
```

Debe imprimir que Chromium arrancó correctamente.

## Autoescalado del worker (Docker Swarm)

En el stack (`docker-stack.yml`) corre el servicio **`autoscaler`**
(`automation/autoscaler.py`), un contenedor liviano (solo redis-py + SDK de
Docker, sin Playwright) que mide periódicamente la longitud de la cola
`certificate_jobs` en Redis y ajusta las réplicas del servicio
`certicheck_worker` entre un mínimo y un máximo configurables.

No hace falta adivinar un número fijo de réplicas: el sistema responde a la
demanda real (la carga son ~100+ abogados enviando consultas a su propio ritmo).
Los valores por defecto son conservadores y NO medidos; se ajustarán en la
Fase 4 con datos reales contra los portales.

### Política

- **Escalar arriba**: si `LLEN certificate_jobs` > `SCALE_UP_THRESHOLD`
  durante `SCALE_UP_STABLE_CYCLES` ciclos consecutivos, se suman
  `REPLICAS_STEP_UP` réplicas, hasta `MAX_REPLICAS`. El techo existe **siempre**:
  nunca se escala sin límite.
- **Escalar abajo**: si la cola lleva < `SCALE_DOWN_THRESHOLD` durante
  `SCALE_DOWN_STABLE_CYCLES` ciclos consecutivos, se restan
  `REPLICAS_STEP_DOWN` réplicas, hasta `MIN_REPLICAS` (nunca 0: siempre queda al
  menos un worker escuchando).
- **Cooldown**: `SCALE_COOLDOWN_SECONDS` entre dos acciones consecutivas
  cualesquiera (arriba o abajo) para evitar oscilar.
- **Logging**: cada decisión (subir / bajar / sin cambios) se registra con el
  motivo: cola observada, réplicas actuales, réplicas nuevas y qué umbral la
  disparó. Imprescindible para diagnosticar y ajustar umbrales en la Fase 4.

### Variables de entorno del autoescalador

| Variable                       | Descripción                                       | Default |
|--------------------------------|---------------------------------------------------|---------|
| `AUTOSCALE_INTERVAL_SECONDS`   | Intervalo entre ciclos de medición                | `15`    |
| `QUEUE_NAME`                   | Lista Redis a medir                               | `certificate_jobs` |
| `WORKER_SERVICE_NAME`          | Servicio Swarm a escalar                          | `certicheck_worker` |
| `SCALE_UP_THRESHOLD`           | Cola > este valor dispara subir                   | `10`    |
| `SCALE_UP_STABLE_CYCLES`       | Ciclos consecutivos altos antes de subir          | `2`     |
| `SCALE_DOWN_THRESHOLD`         | Cola < este valor dispara bajar                   | `1`     |
| `SCALE_DOWN_STABLE_CYCLES`     | Ciclos consecutivos estables antes de bajar       | `4`     |
| `REPLICAS_STEP_UP`             | Réplicas a sumar por acción de subida             | `1`     |
| `REPLICAS_STEP_DOWN`           | Réplicas a restar por acción de bajada            | `1`     |
| `MIN_REPLICAS`                 | Piso de réplicas (>= 1, nunca 0)                  | `1`     |
| `MAX_REPLICAS`                 | Techo de réplicas (siempre acotado)               | `8`     |
| `SCALE_COOLDOWN_SECONDS`       | Cooldown entre dos acciones de escalado           | `60`    |

> ADVERTENCIA DE SEGURIDAD: el autoescalador monta `/var/run/docker.sock`
> (read-only) para ejecutar el equivalente a `docker service scale`. Ese acceso
> **equivale a permisos root sobre el host Docker** y debe tratarse con el mismo
> cuidado que una credencial privilegiada (mismo riesgo que `watchtower` u otras
> herramientas de gestión). No es un detalle menor: solo desplegar en el stack
> con el socket del engine al que se quiere escalar. Montar el socket read-only
> reduce la superficie (el contenedor no puede crear archivos), pero el API de
> Docker que recibe sigue siendo la de un administrador del engine.

## Tests

Tests unitarios del worker (concurrencia por sitio) con pytest:

```bash
python -m pytest test_worker.py -v
```

Tests de la lógica de decisión del autoescalador (con el cliente Docker
simulado/mockeado; no requiere un Swarm real):

```bash
python -m pytest test_autoscaler.py -v
```

> `pytest` vive en `requirements-dev.txt`; no está incluido en la imagen de
> producción.
