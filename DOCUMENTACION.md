# CertiCheck — Documentación Completa del Proyecto y Guía de Despliegue

> Documento de referencia integral de la plataforma **CertiCheck** (código fuente en este repositorio).
> Cubre: qué es, qué hace, arquitectura, tecnologías, estructura, base de datos, comunicaciones
> Laravel↔Python, almacenamiento de PDFs, seguridad, pagos, y una guía paso a paso para desplegar
> la aplicación en producción (nube).

---

## Tabla de Contenidos

1. [Visión General](#1-visión-general)
2. [Qué Hace el Proyecto](#2-qué-hace-el-proyecto)
3. [Funcionalidades (Qué se Puede Hacer)](#3-funcionalidades-qué-se-puede-hacer)
4. [Arquitectura del Sistema](#4-arquitectura-del-sistema)
5. [Stack Tecnológico y Cómo se Aplica](#5-stack-tecnológico-y-cómo-se-aplica)
6. [Estructura de Carpetas](#6-estructura-de-carpetas)
7. [Base de Datos](#7-base-de-datos)
8. [Flujo Principal de Trabajo](#8-flujo-principal-de-trabajo)
9. [Comunicación Laravel ↔ Python](#9-comunicación-laravel--python)
10. [Sistema de Cola Redis](#10-sistema-de-cola-redis)
11. [Almacenamiento de PDFs](#11-almacenamiento-de-pdfs)
12. [Autenticación, Roles y Seguridad](#12-autenticación-roles-y-seguridad)
13. [Sesión Única (Single Session)](#13-sesión-única-single-session)
14. [Sistema de Suscripciones y Pagos](#14-sistema-de-suscripciones-y-pagos)
15. [Scrapers de Sitios Gubernamentales](#15-scrapers-de-sitios-gubernamentales)
16. [Resolución de CAPTCHA y Anti-Detección](#16-resolución-de-captcha-y-anti-detección)
17. [Semáforo Distribuido y Concurrencia](#17-semáforo-distribuido-y-concurrencia)
18. [Autoescalado (Docker Swarm)](#18-autoescalado-docker-swarm)
19. [Comandos Artisan, Schedule y Monitoreo](#19-comandos-artisan-schedule-y-monitoreo)
20. [Frontend y UI](#20-frontend-y-ui)
21. [Testing](#21-testing)
22. [Guía de Despliegue en la Nube](#22-guía-de-despliegue-en-la-nube)
23. [Comandos de Desarrollo (Referencia Rápida)](#23-comandos-de-desarrollo-referencia-rápida)
24. [Checklist de Producción](#24-checklist-de-producción)

---

## 1. Visión General

**CertiCheck** es una plataforma web para **abogados colombianos** que automatiza por completo la
obtención de **certificados de antecedentes judiciales y disciplinarios** desde los portales
gubernamentales oficiales.

El abogado registra a su cliente (datos personales), selecciona qué certificados necesita y el
sistema se encarga de todo lo demás: ir a cada portal oficial, llenar los formularios, resolver
los CAPTCHA, descargar el PDF original y entregárselo listo para descargar / comprimir en ZIP.

La plataforma es un sistema **híbrido en dos partes**:

1. **Backend Laravel (PHP 8.5 / Laravel 13)** — aplicación web completa: autenticación, roles,
   suscripciones, pagos (ePayco), gestión de consultas, almacenamiento de PDFs y panel de administración.
2. **Worker Python (Playwright/Chromium)** — motor de automatización que consume una cola Redis y
   navega los portales del gobierno con un navegador real, resolviendo CAPTCHAs y descargando los
   certificados PDF originales.

La comunicación entre ambos lados no usa `shell_exec` ni procesos del sistema: se hace mediante
**Redis** (cola de trabajos) y una **API HTTP interna** con clave secreta.

---

## 2. Qué Hace el Proyecto

- Acepta datos de una persona (cédula CC, extranjería CE, pasaporte PA o NIT).
- Crea una "consulta" con los 4 certificados de antecedentes solicitables:
  | Sitio (`site`) | Entidad | Certificado | Método de obtención |
  |---|---|---|---|
  | `rnmc` | RNMC / Policía Nacional | Antecedentes judiciales | `page.pdf()` (captura del navegador) |
  | `judicial_police` | Policía Judicial (DIJIN) | Antecedentes judiciales | `page.pdf()` + reCAPTCHA v2 |
  | `comptroller` | Contraloría General | Antecedentes disciplinarios | Descarga nativa del PDF + reCAPTCHA v2 |
  | `attorney_general` | Procuraduría General | Antecedentes disciplinarios | Descarga nativa + preguntas de verificación |
- Encola automáticamente un trabajo por certificado en Redis.
- Los workers Python los procesan en paralelo (con límite de concurrencia global por portal).
- Suben el PDF resultante de vuelta a Laravel, donde queda almacenado.
- El abogado descarga cada PDF o todos en un ZIP.
- Gestiona el ciclo de vida completo: crear, cancelar, regenerar, eliminar consultas y certificados.
- Controla un límite de almacenamiento por abogado con liberación automática del más antiguo.
- Cobra mediante **suscripciones** con pago por **ePayco** (PSE).
- Provee un **panel de administración**: abogados, pagos, reportes de errores, planes de suscripción.

---

## 3. Funcionalidades (Qué se Puede Hacer)

### Como abogado (`role: abogado`)
- Registro con verificación de email, aceptación de términos y condiciones.
- Crear consultas de certificados para una persona (1 persona → hasta 4 certificados por consulta).
- Ver el progreso en tiempo real (polling cada 3 s con estados `pending → processing → success/failed`).
- Cancelar consultas en curso.
- Regenerar una consulta completa o un certificado individual fallido.
- Descargar cada certificado PDF (`Storage::download`) o todos en un ZIP.
- Administrar el almacenamiento propio: liberar certificados o consultas, borrado masivo.
- Gestionar su suscripción (ePayco), cancelarla, revisar estados.
- Reportar problemas (categorías `pago`, `certificado`, `otro`) y recibir notificación al resolverse.

### Como administrador (`role: admin`)
- Dashboard con KPIs: abogados, suscripciones activas, consultas, certificados exitosos,
  ingresos (total/mensual), desglose por sitio y ranking de abogados.
- CRUD de abogados: crear (con clave temporal y obligación de cambio), suspender, reactivar,
  cancelar suscripción, ver pagos.
- CRUD de planes de suscripción (precio, duración, estado).
- Gestión de reportes de errores: marcar resueltos, comentar, reenviar notificación.
- Monitoreo de la cola y estadísticas de duración por portal (comandos artisan).

### Automatización (parte Python)
- Consumir la cola `certificate_jobs`.
- Resolver cada portal, incluyendo CAPTCHA (CapSolver → 2Captcha de respaldo) y preguntas de
  verificación de la Procuraduría.
- Reportar resultados (PDF o error con mensaje) de vuelta a Laravel.
- Autoescalar el número de workers según la profundidad de la cola (Docker Swarm).

---

## 4. Arquitectura del Sistema

```
                 ┌─────────────────────────────────────────────────────┐
                 │                   USUARIO (Browser)                 │
                 │        Blade + Alpine.js + Tailwind CSS             │
                 └──────┬───────────────────────────────┬──────────────┘
                        │  HTTP / AJAX (fetch)          │  ePayco (PSE)
                        ▼                               ▼
      ┌──────────────────────────────┐     ┌───────────────────────────┐
      │          LARAVEL APP (PHP)   │     │      ePayco / Wompi       │
      │  Auth, roles, suscripciones  │     │   (checkout / webhook)    │
      │  Consultas y certificados    │     └─────────────┬─────────────┘
      │  API interna (reportes)      │                   │ webhook HTTP
      │  Panel admin                 │◄──────────────────┘
      └──────────┬────────┬──────────┘
                 │        │
     ‑- - - - - -│────────│- - - - - - - - - - - - - - - - - - - - - -
                rpush     │ HTTP POST (X-Internal-Api-Key)
                 │        ▼
      ┌──────────┴───────────────┐        ┌───────────────────────────┐
      │        REDIS             │        │   Python Worker(s)        │
      │  list "certificate_jobs" │ BLPOP  │  Playwright + Chromium    │
      │  (fuente de verdad)      │═══════▶│  capas por portal         │
      └──────────────────────────┘        │  semáforo distribuido     │
                                          └──────────┬────────────────┘
                                                     │ navega portales del gobierno
                                                     ▼
                                   ┌─────────────────────────────────┐
                                   │  RNMC / DIJIN / Contraloría /   │
                                   │  Procuraduría (portales reales) │
                                   └─────────────────────────────────┘
```

**Resumen del contrato entre Laravel y Python:**

- **Envío de trabajo**: `Redis::rpush('certificate_jobs', JSON)` desde PHP.
- **Consumo**: `BLPOP` desde Python (Loop con pool de hilos).
- **Resultado**: `POST /api/internal/certificate-requests/{id}/complete` con cabecera
  `X-Internal-Api-Key`. En éxito envía el PDF como multipart; en fallo envía `error_message`.
- **Concurrencia**: semáforo distribuido en Redis por sitio (límites globales entre réplicas).

---

## 5. Stack Tecnológico y Cómo se Aplica

### Backend (Laravel/PHP)
| Tecnología | Versión | Uso |
|---|---|---|
| PHP | 8.5 | Runtime del backend |
| laravel/framework | 13.8 (Laravel 13) | Framework completo MVC |
| laravel/sanctum | 4.0 | Autenticación API (rutas `api/*`) |
| spatie/laravel-permission | 8.1 | Roles (`admin`, `abogado`) y middleware `role:` |
| predis/predis | 3.5 | Cliente Redis (phpredis en producción, Redis 7) |
| laravel/breeze | 2.4 | Scaffolding de autenticación (login, registro, reset) |
| pestphp/pest | 4.7 | Framework de tests |

### Autenticación
- Guard **web** con sesión en base de datos (`sessions`), verificación de email (`MustVerifyEmail`),
  Breeze para vistas/controladores.
- **Sesión única** para abogados con `current_session_id` + token `force_token` cifrado (TTL 3 min).

### Frontend
| Tecnología | Uso |
|---|---|
| Blade | Plantillas server-side (65+ vistas) |
| Alpine.js 3 | Interactividad por "islas" (componentes inline en Blade) |
| Tailwind CSS 3 | Utilidades y paleta personalizada |
| Vite 8 | Bundling de JS/CSS (`resources/js/app.js`, `resources/css/app.css`) |
| SweetAlert2 (CDN) | Modales, confirmaciones y avisos |
| ePayco checkout.js (CDN) | Checkout de pagos |

No hay SPA, Livewire, Vue ni React. La comunicación con el servidor es **polling** AJAX
(`fetch`) sobre rutas web que responden JSON.

### Automatización (Python)
| Tecnología | Versión | Uso |
|---|---|---|
| Python | 3.12+ (imagen noble) | Runtime del worker |
| playwright | 1.53.0 | Navegación headless (Chromium) para scraping |
| playwright-stealth | 2.0.3 | Evadir detección de automatización |
| redis-py | 8.0.1 | Consumo de cola y semáforo distribuido |
| requests | — | Reporte HTTP a la API interna de Laravel |
| capsolver / 2captcha-python | 1.0.7 / 2.1.0 | Resolución de reCAPTCHA v2 |
| docker SDK + redis | — | Autoescalador (imagen liviana python:3.13-alpine) |
| pytest | 9.1.1 | Tests del worker (solo dev) |

### Infraestructura
- **Redis 7** como cola y fuente de verdad de trabajos (imagen `redis:7-alpine`, contenedor `certicheck-redis`).
- **MySQL** como base de datos principal de Laravel.
- **Docker Compose** para desarrollo/orquestación local.
- **Docker Swarm** (`docker-stack.yml`) para producción con autoescalado.
- **Almacenamiento local** de PDFs en `storage/app/private/certificates/`.

---

## 6. Estructura de Carpetas

```
certificados/
├── app/
│   ├── Console/Commands/              # Comandos artisan personalizados
│   │   ├── AdminCreateCommand.php         → admin:create
│   │   ├── CertificatesQueueStatus.php    → certificates:queue-status
│   │   ├── CertificatesStats.php          → certificates:stats
│   │   └── NotifyExpiringSubscriptions.php→ subscriptions:notify-expiring
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                  # Dashboard, Lawyers, Plans, ErrorReports (admin)
│   │   │   ├── Auth/                   # Breeze + registro + force-password + login/force
│   │   │   ├── Internal/               # CertificateRequestController (API del worker)
│   │   │   └── ...                     # Consultas, Suscripción, Storage, ePayco, Legal, Errores
│   │   ├── Middleware/                 # internal.api, single.session, subscription.active,
│   │   │                               # terms.accepted, force.password.change
│   │   └── Requests/                   # Form Requests (Login, Profile, StoreConsultationRequest)
│   ├── Models/                         # User, Subject, ConsultationRequest, CertificateRequest,
│   │   │                               # Subscription, SubscriptionPlan, Payment, ErrorReport
│   │   ├── Concerns/BelongsToLawyer.php    # trait: scoping por abogado
│   │   └── Scopes/LawyerScope.php          # scope global abogado
│   ├── Notifications/                  # ResetPassword, VerifyEmail,
│   │                                   # SubscriptionExpiringSoon, ErrorReportResolved
│   ├── Providers/AppServiceProvider.php
│   ├── Services/                       # Lógica de negocio
│   │   ├── CertificateJobDispatcher.php    # encola a Redis
│   │   ├── CertificateSitePriorityService.php  # orden de despacho por duración
│   │   ├── EpaycoSignatureService.php      # verifica firma webhook
│   │   └── LawyerStorageService.php        # cuota de almacenamiento por abogado
│   └── View/
│       ├── Components/                 # layout components (AppLayout, GuestLayout, LegalLayout)
│       └── Composers/SidebarStorageComposer.php  # barra de progreso de almacenamiento
├── automation/                         # ★ WORKER PYTHON
│   ├── worker.py                       # consumidor de la cola (BLPOP + pool de hilos)
│   ├── config.py                       # configuración por variables de entorno
│   ├── redis_semaphore.py              # semáforo distribuido (set + leases + Lua)
│   ├── autoscaler.py                   # autoescalador Docker Swarm por longitud de cola
│   ├── captcha_solver.py               # CapSolver → 2Captcha
│   ├── sites/
│   │   ├── __init__.py                 # contexto stealth + fix de Sec-CH-UA
│   │   ├── rnmc.py                     # Policía Nacional (page.pdf)
│   │   ├── contraloria.py              # Contraloría (download nativo + captcha)
│   │   ├── policia_judicial.py         # DIJIN (page.pdf + captcha + términos)
│   │   ├── procuraduria.py             # Procuraduría (iframes + preguntas verificación)
│   │   └── pgn_resolver.py             # resuelve preguntas de verificación
│   ├── Dockerfile / Dockerfile.autoscaler
│   ├── requirements*.txt
│   └── test_*.py, *_prototype.py, smoke_test.py
├── config/                             # certificados.php, legal.php, services.php, etc.
├── database/
│   ├── migrations/                     # 21 migraciones
│   ├── factories/                      # 5 factories
│   └── seeders/                        # DatabaseSeeder → RoleSeeder (admin, abogado)
├── lang/                               # es/en
├── public/                             # index.php, build/, favicon, etc.
├── resources/
│   ├── css/app.css                     # solo @tailwind
│   ├── js/app.js                       # Alpine + helpers globales
│   └── views/                          # 65+ plantillas Blade (ver §20)
├── routes/
│   ├── web.php                         # rutas web + grupos por middleware
│   ├── api.php                         # /api/user, webhook epayco, API interna
│   ├── auth.php                        # Breeze
│   └── console.php                     # schedule: subscriptions:notify-expiring 08:00
├── storage/app/private/certificates/   # ★ PDFs: {consultation_id}/{archivo}.pdf
├── tests/                              # Pest 4 (Feature + Unit + Concerns)
├── docker-compose.yml                  # redis + worker (local)
├── docker-compose.override.yml         # DRY_RUN para pruebas (nunca producción)
├── docker-stack.yml                    # Swarm: redis + worker + autoscaler
├── .env.example / .env                 # configuración
├── DOCS.md                             # documentación técnica previa
├── DOCUMENTACION.md                    # este documento
└── COMANDOS.txt                        # cheat-sheet de desarrollo
```

---

## 7. Base de Datos

Motor: **MySQL** (default `certificados`). 21 migraciones crean las tablas principales:

| Tabla | Propósito y columnas clave |
|---|---|
| `users` | `name, email, password, must_change_password, current_session_id, terms_accepted_at, terms_version_accepted` |
| `password_reset_tokens`, `sessions` | framework |
| `cache`, `cache_locks` | driver de caché (database) |
| `jobs`, `job_batches`, `failed_jobs` | cola de Laravel (presente pero **no usada** para certificados) |
| `roles`, `permissions`, `model_has_roles`, etc. | Spatie Permission |
| `subscriptions` | `user_id, subscription_plan_id, plan, status (active/suspended/cancelled/expired), starts_at, ends_at, expiry_notified_at` |
| `subjects` | `lawyer_id, document_type (CC/CE/PA/NIT), document_number, full_name, company_name, issuance_date` — unique `[lawyer_id, document_type, document_number]` |
| `consultation_requests` | `lawyer_id, subject_id, status (pending/success/failed/partial)` |
| `certificate_requests` | `consultation_request_id, site, status (pending/processing/success/failed), error_message, pdf_path, pdf_generated_at, duration_seconds` — unique `[consultation_request_id, site]` |
| `personal_access_tokens` | Sanctum |
| `payments` | `user_id, subscription_plan_id, reference (unique), amount_in_cents, payment_provider, gateway_transaction_id, wompi_transaction_id, status (pending/approved/declined/error/voided), raw_payload` |
| `error_reports` | `lawyer_id, subject, description, category (pago/certificado/otro), status (pending/resolved), admin_comment, resolved_by, resolved_at` |
| `subscription_plans` | `name, price_in_cents, duration_months, description, is_active` |

### Estados de una consulta agregada
`ConsultationRequest::refreshStatus()` agrega el estado de sus certificados hijos:
- cualquier hijo `pending/processing` → `pending`
- todos `success` → `success`
- todos `failed` → `failed`
- cualquier combinación → `partial`

### Scoping por abogado
El trait `BelongsToLawyer` + scope global `LawyerScope` filtran automáticamente las consultas,
sujetos y pagos por `lawyer_id = Auth::id()` salvo para el rol `admin`. Los controladores usan
`withoutGlobalScopes()` cuando necesitan validar propiedad o ver todo como admin.

---

## 8. Flujo Principal de Trabajo

1. **Autenticación** — El abogado inicia sesión (sesión única), acepta términos, verifica email,
   tiene suscripción activa (middleware `subscription.active`).
2. **Crear consulta** — `POST /consultation-requests` (FormRequest validado):
   - Verifica cuota de almacenamiento (`LawyerStorageService`). Si excede el límite responde
     **409** `{needs_confirmation, to_delete:[...]}` y el front muestra un modal de confirmación
     para liberar los certificados más antiguos.
   - Transacción: `firstOrCreate` de `Subject` → crea `ConsultationRequest` → crea un
     `CertificateRequest` por cada sitio seleccionado → despacha.
3. **Despacho** — `CertificateJobDispatcher::dispatchMultiple()` ordena los trabajos del más
   lento al más rápido (`CertificateSitePriorityService`, promedios reales cacheados 1 h) y hace
   `Redis::rpush('certificate_jobs', payload)` por cada uno, marcándolos `processing`.
   Payload: `{certificate_request_id, site, document_type, document_number, full_name, issuance_date}`.
4. **Scraping** — Uno de los workers Python hace `BLPOP`, toma el slot del semáforo distribuido
   de su sitio, navega el portal (con CAPTCHA si aplica), obtiene el PDF y reporta.
5. **Reporte** — `POST /api/internal/certificate-requests/{id}/complete`:
   - éxito → guarda el PDF en `storage/app/private/certificates/{consultation_id}/`, fija
     `status=success, pdf_path, pdf_generated_at, error_message=null`.
   - fallo → `status=failed, error_message, pdf_path=null`.
   - Registra `duration_seconds` y llama `refreshStatus()` de la consulta.
6. **Progreso en vivo** — El front hace polling cada 3 s a `GET /consultation-requests/{id}/status`.
7. **Descarga** — `GET /certificate-requests/{cert}/download` (PDF individual con nombre
   `"{doc} - {site}.pdf"`) o `GET /consultation-requests/{cr}/download-zip` (todos en un ZIP).
8. **Almacenamiento** — El abogado puede liberar certificados/consultas o eliminarlos en bloque.
9. **Regeneración** — Reintenta la consulta completa o un certificado individual fallido (con el
   mismo flujo de confirmación de cuota si corresponde).

---

## 9. Comunicación Laravel ↔ Python

El sistema **no usa procesos de shell ni integra Python en PHP**. La integración es desacoplada en
dos canales que hacen al sistema escalable e independiente:

### Canal 1 — Cola (Laravel → Python)
```
PHP:  Redis::rpush('certificate_jobs', json_encode($payload))
Python: value = redis.blpop('certificate_jobs', timeout=...)
```
- El `payload` contiene `certificate_request_id`, `site`, datos del sujeto y fecha de expedición.
- El worker marca el hijo como `processing` en Laravel a la hora de despacharlo (antes del rpush).
- `priority`: el despacho ordena por duración promedio histórica (fila primero, para que los
  certificados lentos empiecen antes).

### Canal 2 — API interna (Python → Laravel)
```
POST {LARAVEL_BASE_URL}/api/internal/certificate-requests/{id}/complete
Headers: X-Internal-Api-Key: <INTERNAL_API_KEY>
Content-Type: multipart/form-data  (campo "pdf")   # solo en éxito
```
- El middleware `internal.api` compara la cabecera con `config('services.internal_api.key')` usando
  `hash_equals`.
- Respuesta `{ok: true}`.
- Como Laravel corre en el host durante desarrollo, en `docker-compose.yml` el worker usa
  `LARAVEL_BASE_URL=http://host.docker.internal:8000` y `extra_hosts: host.docker.internal:host-gateway`.

> En producción `LARAVEL_BASE_URL` debe apuntar a la URL pública de la app con HTTPS.

### Seguridad de la API interna
- Clave compartida entre `.env` (Laravel) y el entorno del worker.
- No autentica sesión: solo la clave interna. **No exponerla**: la ruta `/api/internal/*` está detrás
  de autenticación por cabecera, pero la URL conviene no publicarla para el público general
  (puede quedar detrás de un reverse proxy con lista de IPs o un túnel privado si se prefiere).

---

## 10. Sistema de Cola Redis

- **Redis 7** (contenedor `certicheck-redis`, puerto 6379, volumen `redis-data`, healthcheck).
- **Lista `certificate_jobs`** = fuente de verdad del trabajo pendiente.
  - Comandos útiles: `php artisan certificates:queue-status` (muestra `LLEN`), o
    `redis-cli llen certificate_jobs`.
- **Driver de caché** por defecto `database`; hay una conexión Redis "cache" en `config/database.php`
  (db 1) disponible si se cambia `CACHE_STORE=redis`.
- El **queue nativo de Laravel** (tablas `jobs/failed_jobs`) NO se usa para certificados; se queda
  únicamente como scaffolding.

### Comportamiento del worker
- `worker.py` (450 líneas): un proceso con `ThreadPoolExecutor(WORKER_POOL_SIZE)` de hilos;
  `BLPOP` con timeout; `BoundedSemaphore` local de slots en vuelo por réplica.
- Cada hilo procesa un job; la concurrencia **por sitio** se limita con el semáforo distribuido.
- **Reintentos**: si el scraping falla, el mensaje se **reencaola** hasta `MAX_REQUEUE_ATTEMPTS=3`
  (contador en el payload). Al agotarse, se reporta `failed` a Laravel.
- **Apagado ordenado**: al recibir `SIGTERM`, deja de tomar jobs, espera a los en curso hasta
  `SHUTDOWN_WAIT_SECONDS=60` y reencola los que quedaban para no perderlos; si se pasa el límite,
  `os._exit(1)`.

---

## 11. Almacenamiento de PDFs

- Los PDFs se guardan en el **disco `local`** (`storage/app/private/certificates/`), por defecto
  en `storage/app/private/certificates/{consultation_request_id}/{archivo}.pdf`.
- Los guarda el controlador `Internal\CertificateRequestController@complete` cuando el worker reporta
  éxito (`Storage::disk('local')->putFile`), y registra `pdf_path` y `pdf_generated_at`.
- La descarga se hace con `Storage::download(...)` (no son públicos en la web; solo autenticados y
  autorizados).
- **Cuota por abogado** (`CERTIFICATE_STORAGE_LIMIT`, por defecto 500; en dev 23): `LawyerStorageService`
  cuenta certificados `success` con `pdf_path`, impide superar el límite y ofrece liberar los más
  antiguos (ordenados por `pdf_generated_at`) con confirmación previa en la UI.
  - `freeCertificates()` borra el archivo físico y nulifica `pdf_path/pdf_generated_at`.
  - `freeConsultation()` borra el directorio de la consulta.
- Los PDFs NO se generan en PHP: el worker genera/temporalmente los guarda en `automation/temp_certs/`
  y los **borra localmente tras subirlos** a Laravel.

> **Importante para la nube**: los PDFs viven en el volumen/a EFS del disco local. Hay que montar
> `storage/app/private` como volumen persistente (o migrar a un bucket S3 cambiando el driver del
> disco — el código usa `Storage::disk('local')` explícito en almacenar/liberar).

---

## 12. Autenticación, Roles y Seguridad

### Autenticación
- Breeze sobre guard `web`, sesión en DB (`sessions`), verificación de email obligatoria.
- Middlewares (alias en `bootstrap/app.php`):
  | Alias | Función |
  |---|---|
  | `role` | Spatie `RoleMiddleware` (`admin`, `abogado`) |
  | `single.session` | sesión única para abogados |
  | `terms.accepted` | obliga aceptar la versión vigente de términos |
  | `subscription.active` | obliga suscripción activa |
  | `force.password.change` | obliga cambio de clave inicial |
  | `internal.api` | valida `X-Internal-Api-Key` para el worker |

### Roles y permisos
- Spatie `roles`: `admin` y `abogado`. Seed en `RoleSeeder`.
- No hay "permissions" individuales ni Policies: la autorización es por rol + `abort_unless` inline +
  scope global `LawyerScope`.

### Otras medidas de seguridad
- Contraseñas hasheadas (bycrpt rounds 12), `hash_equals` para firmas/claves.
- Firma de webhook ePayco verificada con SHA-256.
- `Forbidden` para rutas no autorizadas; vista 419/403/500 personalizadas.
- `trustProxies(at: '*')` — requiere reverse proxy que entregue cabeceras `X-Forwarded-*`.
- `APP_DEBUG=false` en producción.

---

## 13. Sesión Única (Single Session)

Los abogados solo pueden tener **una sesión activa a la vez**:
- `users.current_session_id` se regenerea al iniciar sesión (`session()->getId()`).
- Si un segundo inició de sesión detecta una sesión existente (`current_session_id` + tabla `sessions`
  + `SESSION_LIFETIME`), el login normal devuelve un **`force_token` cifrado (TTL 3 min)** y la UI
  muestra un SweetAlert "Ya tienes una sesión activa" para aceptar el cambio de dispositivo.
- `AuthenticatedSessionController@forceLogin` valida el token, inicia sesión y actualiza
  `current_session_id`.
- `EnsureSingleSession` valida en cada request; si el id de sesión cambió, cierra la sesión y devuelve
  **401** (JSON) o aviso `session_closed_elsewhere`.
- El front hace **heartbeat** cada 5 s a `GET /session/heartbeat` y redirige a login si recibe 401.

---

## 14. Sistema de Suscripciones y Pagos

### Suscripciones
- Modelos `SubscriptionPlan` (período en meses, precio en **centavos de COP**), `Subscription`
  (vinculada al usuario y al plan).
- `User::hasActiveSubscription()`: `status=active` y `ends_at >= now`.
- Comandos: `subscriptions:notify-expiring` (diario 08:00) avisa a quien termina en 3 días.
- Admin puede suspender, reactivar o cancelar suscripciones de abogados.

### Pagos (ePayco)
- `checkout()`: crea `Payment` `pending` con `reference = CERTICHECK-{id}-{random}`
  y muestra el checkout de ePayco (`x_amount`, `x_currency_code=COP`).
- **Webhook** `POST /api/webhooks/epayco`: verifica firma con `EpaycoSignatureService`
  (`sha256(cust_id_cliente^p_key^x_ref_payco^x_transaction_id^x_amount^x_currency_code)`,
  comparada con `hash_equals`), mapea `x_transaction_state`
  (`Aceptada→approved`, rechazada→declined, etc.), actualiza el `Payment` y, si fue aprobado,
  **activa/extiende** la suscripción (`ends_at += duration_months` o crea una nueva).
- `return()` registra los query-params del retorno; `status()` expone `{active: bool}` que el front
  sondea cada 3 s hasta 20 intentos.
- Nota: existe la columna `wompi_transaction_id` y documentación de Wompi, pero el código activo de
  pago es **ePayco**. `payment_provider` queda en `epayco`.

---

## 15. Scrapers de Sitios Gubernamentales

Cada portal tiene su propio módulo en `automation/sites/` con estrategia específica:

| Archivo | Portal | Estrategia |
|---|---|---|
| `rnmc.py` | RNMC / Policía Nacional | Sin CAPTCHA; formulario → `page.pdf(path, format="A4", print_background=True)` |
| `policia_judicial.py` | Policía Judicial (DIJIN) | Acepta términos y condiciones + reCAPTCHA v2 → `page.pdf()` |
| `contraloria.py` | Contraloría General | reCAPTCHA v2 → `page.expect_download()` + `download.save_as(pdf)` |
| `procuraduria.py` | Procuraduría | Formulario en iframes + **preguntas de verificación** (hasta 8) + descarga multi-estrategia (evento de descarga, o respuesta con `content-type: application/pdf` escrita a disco) |

El dato `issuance_date` (fecha de expedición) solo se solicita para cédula de ciudadanía **CC** y es
necesario en RNMC.

### `pgn_resolver.py` (Procuraduría)
Resuelve las preguntas de verificación de forma programática:
- operaciones aritméticas;
- capital de departamento (mapa propio);
- primera letra del nombre;
- N-dígitos del documento (regex + mapa conocido).

---

## 16. Resolución de CAPTCHA y Anti-Detección

- **`captcha_solver.py`**: reCAPTCHA **v2** con **CapSolver** como proveedor primario y
  **2Captcha** como respaldo. Claves en `automation/.env`.
- **`sites/__init__.py` (`crear_context_stealthed`)**: contexto Playwright con `playwright-stealth`,
  cabeceras realistas y **fix del leak `Sec-CH-UA: "HeadlessChrome"`** mediante intercepción de
  rutas (el portal detectaría el headless por esa cabecera).
- Estas medidas hacen que el scraping funcione contra portales con protección anti-bot.

---

## 17. Semáforo Distribuido y Concurrencia

- **Problema**: N réplicas de workers compiten por scrapear el mismo portal; hay que limitar la
  concurrencia **global** para no saturar/que te bloqueen.
- **Solución**: `automation/redis_semaphore.py` implementa un semáforo distribuido con Redis:
  - `SET` de claves de permiso por sitio + **lease** (caducidad 180 s) para tolerar caídas;
  - scripts **Lua atómicos** para adquirir/liberar sin carreras;
  - hilo de **heartbeat** que renueva leases (crash-safe: si un worker muere, el permiso expira).
- **Límites por sitio** (env):
  | Sitio | Default |
  |---|---|
  | `rnmc` | 4 |
  | `contraloria` | 2 |
  | `judicial_police` | 2 |
  | `attorney_general` | 1 |
- **`WORKER_POOL_SIZE`** limita jobs simultáneos por réplica (10). Los semáforos `MAX_CONCURRENCY_*`
  son GLOBALES entre réplicas (a diferencia de `docker-compose.yml` local que usa `--scale` con
  semáforos locales). Adquirir permiso expira a los 600 s.
- En Swarm, con semáforo distribuido, se puede escalar réplicas con seguridad (el límite agregado
  por sitio se mantiene).
- Tests: `test_redis_semaphore.py`, `test_worker.py` (pytest).

---

## 18. Autoescalado (Docker Swarm)

`docker-stack.yml` define el stack de producción (entorno de prueba con `DRY_RUN=true`).

- **`redis`**: `redis:7-alpine`, límites 1 CPU/256 MB.
- **`worker`**: imagen `certificados-worker:latest`; `replicas: 2`, `stop_grace_period: 120s`
  (debe ser ≥ `SHUTDOWN_WAIT_SECONDS` para el drenaje ordenado), recursos por réplica
  límite 2 CPU/4 GB, reserva 0.25 CPU/1 GB (baseline ~9 MB + ~672 MB por Chromium headless).
- **`autoscaler`**: imagen `certificados-autoscaler:latest`, liviana (solo redis-py + SDK Docker),
  mide `LLEN certificate_jobs` cada `AUTOSCALE_INTERVAL_SECONDS` (15 s) y escala
  `certicheck_worker` entre 1 y 8 réplicas.

Política de escalado:
| Umbral | Acción |
|---|---|
| cola > `SCALE_UP_THRESHOLD` (10) por `SCALE_UP_STABLE_CYCLES` (2) ciclos | subir `REPLICAS_STEP_UP` (1) |
| cola < `SCALE_DOWN_THRESHOLD` (1) por `SCALE_DOWN_STABLE_CYCLES` (4) ciclos | bajar `REPLICAS_STEP_DOWN` (1), min 1 |
| tras una acción | cooldown `SCALE_COOLDOWN_SECONDS` (60 s) |

> **Seguridad**: el autoescalador monta `/var/run/docker.sock:ro` → acceso equivalente a root sobre
> el engine. Tratar como credencial privilegiada. Solo desplegarlo en el Swarm que se quiere escalar.

---

## 19. Comandos Artisan, Schedule y Monitoreo

| Comando | Descripción |
|---|---|
| `admin:create` | Crea admin con clave temporal (14 chars, `must_change_password=true`) |
| `certificates:queue-status` | Muestra `LLEN certificate_jobs` en Redis |
| `certificates:stats` | Tabla CLI con count/avg/min/max de `duration_seconds` por sitio |
| `subscriptions:notify-expiring` | Notifica suscripciones que expiran en 3 días (programado 08:00) |

Schedule (`routes/console.php`):
```php
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:00');
```

> En producción debe correr el scheduler: cron `* * * * * php artisan schedule:run` (ver §22).

Rutas internas útiles: `GET /up` (healthcheck Laravel), `GET /api/user` (sanctum).

---

## 20. Frontend y UI

### Vistas (resumen)
| Grupo | Vistas |
|---|---|
| Login/registro | Breeze + SweetAlert de sesión activa, fuerza de cambio de clave, reset, verificación |
| Dashboard abogado | `dashboard` → en realidad la ruta `/dashboard` apunta a `consultation-requests.create` |
| Consultas | `create` (form + conflictos de almacenamiento 409), `show` (progreso 3 s), `index` (historial + polling 5 s) |
| Almacenamiento | `index` (vista agrupada/individual, borrado masivo) + modal `manage-modal` reutilizable |
| Suscripción | `show` (planes), `checkout` (ePayco), `return` (polling de estado), `manage` |
| Reportes | `create` (abogado), `index/show` (admin con resolución) |
| Admin | dashboard KPIs + gráfico CSS por sitio, abogados, planes, pagos |
| Legal/landing | `landing`, términos, política de datos |
| Errores | 403/404/419/500 |

### JS y componentes Alpine
- `resources/js/app.js`: arranca Alpine y define `Alpine.data('manageStorageModal')` + helpers globales
  `swalConfirm`, `swalSuccess`, `certicheckSiteLabel`, `certicheckStorageConflict`, `certicheckConfirmFree`.
- Componentes inline en Blade: `consultationForm`, `consultationProgress`, `historyPoller`,
  `storageBulk`, `paymentReturn`, `sessionWatcher`, `Alpine.store('confirm')` (confirm-modal).
- **Patrón clave**: conflictos de cuota de almacenamiento se devuelven como **409 JSON**
  `{needs_confirmation, to_delete:[...]}` y se resuelven con el modal de gestión de almacenamiento.

### Identidad visual
- Paleta Tailwind personalizada: `ink #16324F`, `brass #B08D57`, `surface #F7F8FA`,
  `carbon #1F2429`, `rust #B54B3F`.
- Tipografías: Inter (sans), Source Serif 4 (serif), IBM Plex Mono (mono), vía fonts.bunny.net.
- SweetAlert2 y ePayco checkout vía CDN (el bundle de Vite solo incluye Alpine).

---

## 21. Testing

### Laravel (Pest 4)
```
php artisan test --compact
php artisan test --compact --filter=NombredelTest
```
- `tests/Feature/`: Admin (3), Auth (7), Console (2), ConsultationRequests (1) + 13 tests sueltos.
- `tests/Unit/`: Example, LawyerStorageService.
- `tests/Concerns/RefreshDatabaseWithRoles.php`: helper que refresca DB y siembra roles.
- Base de datos: MySQL local o `phpunit.xml` (revisar su configuración de BD de test).

### Python (pytest, requiere `requirements-dev.txt`)
```
python -m pytest test_worker.py -v
python -m pytest test_redis_semaphore.py -v
python -m pytest test_autoscaler.py -v
python -m pytest test_pgn_resolver.py -v
```

---

## 22. Guía de Despliegue en la Nube

El sistema tiene **tres piezas desplegables** + dependencias externas. Antes de empezar, lee el
flujo completo y el checklist de producción (§24).

### 22.1 Arquitectura objetivo recomendada

```
                       Internet
                          │ 443/80
                  ┌───────▼────────┐
                  │ Reverse Proxy   │  (Traefik / Caddy / Nginx + TLS/SSL)
                  └───┬───────┬─────┘
                      │       │
          ┌───────────▼──┐  ┌─▼──────────────┐
          │  Laravel App │  │ (API interna,  │
          │ PHP-FPM+nginx│  │  worker→Laravel)│
          │ + MySQL      │  └────────────────┘
          └───────┬──────┘
                  │
   ┌──────────────▼──────────────────┐
   │        Docker (la instancia)    │
   │  redis (certicheck-redis)       │
   │  worker (certificados-worker)   │
   │  autoscaler (certificados-…)    │
   └─────────────────────────────────┘
```

Recomendación mínima por rol:
- **Instancia de cómputo** (VM) con Docker Engine, mínimo **4 CPU / 8 GB RAM** (cada Chromium
  headless consume ~672 MB). Para producción inicial con pocos abogados puede bastar 2 CPU/4 GB
  ajustando `MAX_CONCURRENCY_*`.
- Los **workers/redis/autoscaler corren en contenedores** en esa misma instancia.
- Laravel puede correr **nativo (PHP-FPM + nginx)** en la misma VM o en su propio contenedor
  (este repo NO incluye Dockerfile para Laravel; si quieres contenedores para todo, crea uno).
- **MySQL** administrado (RDS/Cloud SQL) o en la VM.
- **Redis** en contenedor (este repo lo trae) o administrado (ElastiCache/Upstash).
- **Volumen persistente** para `storage/app/private` (disco de la VM, EBS, NFS, o EFS).

### 22.2 Paso 1 — Preparar la instancia

```bash
# Ubuntu/Debian
apt update && apt upgrade -y
apt install -y curl git unzip nginx php8.3-fpm php8.3-mysql \
  php8.3-curl php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-intl php8.3-zip
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Docker Engine (o usa el script oficial)
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
```

> El worker usa la imagen oficial de Playwright (`mcr.microsoft.com/playwright/python:v1.53.0-noble`),
> que ya trae Chromium y sus dependencias; **no instales Chromium en la VM**.

### 22.3 Paso 2 — Código y variables de entorno

```bash
cd /var/www
git clone https://github.com/EduardoGutierrezDizeo/certificados.git app
cd app
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los valores de producción (§22.7). Luego:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
# roles y admin
php artisan db:seed --force          # (DatabaseSeeder → roles)
php artisan admin:create             # crea tu primer admin
# enlace de almacenamiento público (no necesario para PDFs privados, pero estándar)
php artisan storage:link
```

> En 2026 la imagen `laravel/framework:13` requiere `php ^8.3`; ajusta las versiones del paquete
> `php8.x-fpm` a las disponibles en el repo de tu distro.

### 22.4 Paso 3 — Activar Redis y construir imágenes del worker

```bash
# Correr Redis (la fuente de verdad de la cola)
docker compose up -d redis

# Construir imágenes
docker build -t certificados-worker:latest automation/
docker build -t certificados-autoscaler:latest -f automation/Dockerfile.autoscaler automation/
```

### 22.5 Paso 4 — Desplegar worker (producción, sin DRY_RUN)

Opción A — **modo compose** (simple, 1 instancia):
```bash
docker compose up -d --no-deps --scale worker=2 --remove-orphans worker
```
> El `docker-compose.override.yml` activa `DRY_RUN=true`; **NO usar en producción**.
> Crea tu propio override de producción, por ejemplo `docker-compose.prod.yml`:
> - `LARAVEL_BASE_URL=https://tu-dominio.com`
> - `INTERNAL_API_KEY=<la misma del .env>`
> - `DRY_RUN=false` (o elimínala)
> - `TEMP_CERTS_DIR=/app/automation/temp_certs`
> - consumir `INTERNAL_API_KEY` desde el entorno del actual `.env`.

Opción B — **Docker Swarm** (con autoescalado):
```bash
docker swarm init
# Edita docker-stack.yml: DRY_RUN=false, LARAVEL_BASE_URL=https://tu-dominio.com,
# MAX_CONCURRENCY_* y los límites de tu instancia.
docker stack deploy -c docker-stack.yml certicheck
docker service scale certicheck_worker=2
docker service logs -f certicheck_worker
```

### 22.6 Paso 5 — Servir Laravel

**Nginx** (ejemplo):
```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/app/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$uri;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
    client_max_body_size 20M;   # subida multipart del PDF desde el worker
}
```
- Termina TLS con **certbot** (Let's Encrypt) o con **Caddy** (auto-TLS).
- configures `trustProxies` — ya está en `'*'`, pero entrega `X-Forwarded-Proto/For` desde el proxy.

> **Rutas a proteger**: `/api/internal/*` solo debe ser llamada por el worker (con la cabecera).
> Puedes restringirla por IP del worker o mantenerla tras el dominio con la clave — la clave es la
> barrera real; nunca coloques la ruta en un frontend público.

### 22.7 Paso 6 — Variables de entorno de producción (`.env`)

```ini
APP_NAME="CertiCheck"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1            # o el host de tu MySQL administrado
DB_PORT=3306
DB_DATABASE=certificados
DB_USERNAME=certificados
DB_PASSWORD=<secreto>

SESSION_DRIVER=database     # o redis
SESSION_LIFETIME=120

FILESYSTEM_DISK=local
CERTIFICATE_STORAGE_LIMIT=500   # cuota de PDFs por abogado

QUEUE_CONNECTION=database   # (no se usa para certificados; se respeta)

CACHE_STORE=database        # o redis si prefieres

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp            # o ses/resend/postmark
MAIL_HOST=smtp.tu-proveedor.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=certicheck@tu-dominio.com
MAIL_FROM_NAME="${APP_NAME}"

# --- Pagos ePayco ---
EPAYCO_PUBLIC_KEY=...
EPAYCO_PRIVATE_KEY=...
EPAYCO_CUST_ID_CLIENTE=...
EPAYCO_P_KEY=...
EPAYCO_TEST_MODE=false      # ← ¡imprescindible apagar el modo test!

# --- Comunicación con el worker ---
INTERNAL_API_KEY=<generar: openssl rand -hex 32>
```

### 22.8 Paso 7 — Scheduler, permisos y arranque

```bash
# Permisos
chown -R www-data:www-data storage bootstrap/cache

# Scheduler (cron de todos los minutos)
crontab -e
# * * * * * cd /var/www/app && php artisan schedule:run >> /dev/null 2>&1

# Supervisar el worker (si no usas Swarm, usa systemd o supervisor):
# systemd unit que ejecuta `docker compose up -d worker` en el path del proyecto.
```

### 22.9 Paso 8 — Persistencia y copias de seguridad

- **PDFs**: montar `storage/app/private` en disco persistente (EBS con snapshot, o NFS/EFS si hay
  varias réplicas de Laravel). Los PDFs NO están en la BD; sin este volumen se pierden.
- **Redis**: los jobs pendientes viven en memoria; en Swarm el volumen `redis-data` persiste en la
  instancia. Considera AOF si quieres tolerar reinicios sin pérdida.
- **MySQL**: snapshots/backups periódicos (`mysqldump`).
- Recuerda que un PDF "pagado" es un certificado oficial: respaldalos junto con la BD.

### 22.10 Paso 9 — Seguridad adicional en producción

- `APP_DEBUG=false`, `APP_KEY` única.
- Nunca comprometer `INTERNAL_API_KEY`, `EPAYCO_P_*`, claves de CAPTCHA.
- No montar `docker.sock` salvo en el Swarm objetivo (autoescalador).
- Restringir el tráfico de `/api/internal/*` (acl de nginx por IP del contenedor worker si se desea).
- Mantener `MAX_CONCURRENCY_*` conservadores para no saturar portales gubernamentales;
  empieza con (rnmc 2, contraloria 1, judicial_police 1, procuraduria 1) si hay duda.
- Monitorear: `php artisan certificates:queue-status`, `docker service ls`,
  `docker service logs -f certicheck_worker`, logs de Laravel (`storage/logs/laravel.log`).
- Configurar solución de email real (SMTP/SES/Resend) para verificación, reset y notificaciones.
- Preferir **MySQL administrado** y **Redis persistente** si hay presupuesto.

---

## 23. Comandos de Desarrollo (Referencia Rápida)

```bash
# Icon porta en el README de automation:
composer run dev            # artisan serve + queue:listen + vite (concurrently)

# Solo piezas:
php artisan serve
php artisan queue:listen    # no hace falta para certificados (los procesa el worker)
npm run dev                 # Vite (o npm run build para producción)

# Redis
docker compose up -d redis            # contenedor certicheck-redis

# Worker local
pip install -r requirements-dev.txt
.\start_worker_safe.ps1               # Windows: verifica duplicados
python worker.py                       # alternativo

# Docker worker
docker compose up -d --scale worker=2 # usa override de pruebas (DRY_RUN)

# Artisan útiles
php artisan admin:create
php artisan certificates:queue-status
php artisan certificates:stats
php artisan test --compact
```

---

## 24. Checklist de Producción

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generada.
- [ ] `APP_URL` = dominio público con HTTPS.
- [ ] MySQL y Redis accesibles y asegurados (`REDIS_PASSWORD` si es expuesto).
- [ ] `INTERNAL_API_KEY` generada y **idéntica** en `.env` de Laravel y en el stack worker.
- [ ] Worker con `DRY_RUN=false` y `LARAVEL_BASE_URL=https://tu-dominio.com` (NO `host.docker.internal`).
- [ ] `EPAYCO_TEST_MODE=false` y claves de producción de ePayco.
- [ ] Claves de **CapSolver/2Captcha** configuradas en el worker.
- [ ] `MAX_CONCURRENCY_*` y `WORKER_POOL_SIZE` ajustados a la instancia.
- [ ] Scheduler corriendo (cron `schedule:run`).
- [ ] Migraciones y seeders ejecutados; existe al menos un admin.
- [ ] Volume persistente para `storage/app/private` (PDFs).
- [ ] Backups programados (MySQL + carpeta de certificados).
- [ ] TLS/SSL activo y `X-Forwarded-Proto` entregado por el proxy.
- [ ] `npm run build` ejecutado (el bundle de Vite debe existir en `public/build`).
- [ ] Test: elegir plan, pagar (ePayco test→producción), crear una consulta y verificar que el
      worker descarga el PDF y lo reporta.
- [ ] Rutas `/api/internal/*` no expuestas públicamente sin la clave.
- [ ] Autoescalador montando `docker.sock` solo en el Swarm correcto.

---

*Documento generado a partir del código fuente del repositorio. Si algo cambia en el código,
actualiza este documento.*