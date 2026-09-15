# CertiCheck — Documentación Completa del Proyecto

---

## Tabla de Contenidos

1. [Visión General](#1-visión-general)
2. [Qué es CertiCheck](#2-qué-es-certicheck)
3. [Arquitectura del Sistema](#3-arquitectura-del-sistema)
4. [Stack Tecnológico](#4-stack-tecnológico)
5. [Estructura de Carpetas](#5-estructura-de-carpetas)
6. [Modelado de Base de Datos](#6-modelado-de-base-de-datos)
7. [Flujo Principal de Trabajo](#7-flujo-principal-de-trabajo)
8. [Comunicación Laravel ↔ Python](#8-comunicación-laravel--python)
9. [Sistema de Cola Redis](#9-sistema-de-cola-redis)
10. [Almacenamiento de PDFs](#10-almacenamiento-de-pdfs)
11. [Autenticación y Seguridad](#11-autenticación-y-seguridad)
12. [Sistema de Roles y Permisos](#12-sistema-de-roles-y-permisos)
13. [Sesión Única (Single Session)](#13-sesión-única-single-session)
14. [Sistema de Suscripciones](#14-sistema-de-suscripciones)
15. [Pasarelas de Pago](#15-pasarelas-de-pago)
16. [Scraper de Sitios Gubernamentales](#16-scraper-de-sitios-gubernamentales)
17. [Resolución de CAPTCHA](#17-resolución-de-captcha)
18. [Semáforo Distribuido y Concurrency](#18-semáforo-distribuido-y-concurrency)
19. [Autoscaling (Docker Swarm)](#19-autoscaling-docker-swarm)
20. [Comandos Artisan y Monitoreo](#20-comandos-artisan-y-monitoreo)
21. [Despliegue con Docker](#21-despliegue-con-docker)
22. [Frontend y UI](#22-frontend-y-ui)
23. [Testing](#23-testing)
24. [Comandos de Desarrollo](#24-comandos-de-desarrollo)

---

## 1. Visión General

**CertiCheck** es una plataforma web diseñada para abogados colombianos que automatiza la solicitud y descarga de certificados de antecedentes judiciales y disciplinarios de cuatro entidades gubernamentales. La aplicación combina un backend robusto en Laravel con un worker de automatización en Python que navega los portales del gobierno usando un navegador real (Playwright/Chromium) para descargar los certificados PDF originales.

**En resumen:** Un abogado ingresa los datos de una persona, selecciona qué certificados necesita, y el sistema se encarga automáticamente de ir a cada portal gubernamental, llenar los formularios, resolver CAPTCHAs, descargar los PDFs, y entregarlos al usuario.

---

## 2. Qué es CertiCheck

### Propósito
Los abogados en Colombia necesitan constantemente certificados de antecedentes para sus clientes (procesos judiciales, contratación, trámites legales). Actualmente deben ir portal por portal,手动mente llenar formularios, resolver CAPTCHAs, y descargar cada certificado. CertiCheck automatiza todo ese proceso.

### Entidades Gobernamentales Soportadas

| Sitio | Entidad | Tipo de Certificado | Método de Descarga |
|-------|---------|--------------------|--------------------|
| `rnmc` | RNMC (Red Nacional de Municipios y Contralorías) / Policía Nacional | Antecedentes judiciales | `page.pdf()` — captura renderizada del navegador |
| `judicial_police` | Policía Judicial (DIJIN) | Antecedentes judiciales | `page.pdf()` + reCAPTCHA v2 |
| `comptroller` | Contraloría General de la República | Antecedentes disciplinarios | Descarga de archivo nativo + reCAPTCHA v2 |
| `attorney_general` | Procuraduría General de la República | Antecedentes disciplinarios | Descarga de archivo nativo + preguntas de verificación |

### Tipos de Documento Soportados
- **CC** — Cédula de Ciudadanía (requiere `issuance_date`)
- **CE** — Cédula de Extranjería
- **PA** — Pasaporte
- **NIT** — Número de Identificación Tributaria

---

## 3. Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        USUARIO (Browser)                        │
│                   Alpine.js + Tailwind CSS                      │
└─────────────┬───────────────────────────────────┬───────────────┘
              │ HTTP / AJAX                       │ Webhook
              ▼                                   ▼
┌─────────────────────────────┐    ┌──────────────────────────────┐
│      LARAVEL APP (PHP)      │    │     ePayco Webhook           │
│  - Autenticación            │    │     (Confirmación de pago)   │
│  - Roles (Spatie)           │    └──────────────────────────────┘
│  - Suscripciones            │
│  - Consultas y certificados │
│  - API interna              │
│  - Panel admin              │
└──────────┬──────────────────┘
           │
           │ push JSON → Redis List
           ▼
┌─────────────────────────────┐
│      REDIS (Cola de jobs)   │
│   key: certificate_jobs     │
└──────────┬──────────────────┘
           │
           │ BLPOP (consumidor persistente)
           ▼
┌─────────────────────────────┐
│   PYTHON WORKER (Playwright) │
│  - Consume la cola Redis    │
│  - Navega portales gov.     │
│  - Resuelve CAPTCHAs        │
│  - Descarga PDFs            │
│  - Reporta resultado a      │
│    Laravel via API interna   │
└──────────┬──────────────────┘
           │
           │ POST con PDF adjunto
           ▼
┌─────────────────────────────┐
│  API INTERNA DE LARAVEL     │
│  /internal/certificate-     │
│  requests/{id}/complete     │
│  - Almacena PDF             │
│  - Actualiza estado         │
└─────────────────────────────┘
```

### Flujo de Datos Completo

1. **Abogado** crea una solicitud de consulta con datos del sujeto y sitios deseados
2. **Laravel** crea `ConsultationRequest` + `CertificateRequest` por cada sitio
3. **CertificateJobDispatcher** empuja payloads JSON a la lista Redis `certificate_jobs`
4. **Python Worker** consume con `BLPOP`, ejecuta el scraper correspondiente
5. **Scraper** navega el portal gubernamental con Playwright (Chromium headless)
6. **Worker** reporta resultado a Laravel vía `POST /internal/certificate-requests/{id}/complete`
7. **Laravel** almacena el PDF en `storage/app/private/certificates/{id}/` y actualiza estados
8. **Frontend** hace polling del endpoint `status` para mostrar progreso en tiempo real
9. **Abogado** descarga PDFs individuales o ZIP con todos los certificados

---

## 4. Stack Tecnológico

### Backend (Laravel)
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| PHP | 8.5 | Lenguaje del backend |
| Laravel | 13.x | Framework web (MVC, Eloquent, Auth, Queue, Storage) |
| Laravel Breeze | 2.x | Scaffold de autenticación (Blade stack) |
| Laravel Sanctum | 4.x | Token-based API authentication |
| Spatie Permission | 8.x | Roles y permitos (admin, abogado) |
| predis/predis | 3.x | Cliente Redis para PHP |
| Pest | 4.x | Testing framework |

### Frontend
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| Alpine.js | 3.x | Interactividad reactiva en el DOM |
| Tailwind CSS | 3.x | Framework de utilidades CSS |
| Vite | 8.x | Bundler de assets |
| SweetAlert2 | CDN | Modales de confirmación/notificación |

### Automatización (Python)
| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| Python | 3.13 | Lenguaje del worker |
| Playwright | 1.53.0 | Navegación de navegador headless |
| playwright-stealth | 2.0.3 | Anti-detección de bots |
| Redis (python) | 8.0.1 | Consumo de la cola de jobs |
| 2captcha-python | 2.1.0 | Resolución de reCAPTCHA (fallback) |
| capsolver | 1.0.7 | Resolución de reCAPTCHA (primario) |
| Docker | 7.1.0 | Orquestación del autoscaler |

### Infraestructura
| Tecnología | Propósito |
|------------|-----------|
| MySQL | Base de datos principal |
| Redis | Cola de jobs + caché + semáforos |
| Docker | Containerización del worker y Redis |
| Docker Swarm | Orquestación y autoscaling |
| ngrok | Túnel para desarrollo local (webhooks) |

### Pasarelas de Pago
| Pasarela | Estado | Propósito |
|----------|--------|-----------|
| ePayco | Activo | Pagos PSE/tarjeta de crédito |
| Wompi | Configurado (parcialmente) | Alternativa de pago |

---

## 5. Estructura de Carpetas

```
Certificados/
├── app/
│   ├── Console/Commands/           # Comandos Artisan personalizados
│   │   ├── CertificatesQueueStatus.php    # certificates:queue-status
│   │   ├── CertificatesStats.php          # certificates:stats
│   │   ├── CreateAdminCommand.php         # admin:create
│   │   └── NotifyExpiringSubscriptions.php # subscriptions:notify-expiring
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                     # Panel de administración
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── ErrorReportController.php
│   │   │   │   ├── LawyerController.php
│   │   │   │   └── SubscriptionPlanController.php
│   │   │   ├── Auth/                      # Controladores de autenticación (Breeze)
│   │   │   │   ├── AuthenticatedSessionController.php  # + forceLogin() para sesión única
│   │   │   │   ├── ForcePasswordController.php
│   │   │   │   ├── RegisteredUserController.php
│   │   │   │   └── ... (8 controladores auth)
│   │   │   ├── Internal/                  # API interna para el Python worker
│   │   │   │   └── CertificateRequestController.php
│   │   │   ├── ConsultationRequestController.php   # CRUD principal de consultas
│   │   │   ├── EpaycoWebhookController.php         # Webhook de confirmación de pago
│   │   │   ├── ErrorReportController.php           # Reportes de problemas
│   │   │   ├── LegalController.php                 # Términos y condiciones
│   │   │   ├── ProfileController.php
│   │   │   ├── SessionController.php               # Heartbeat de sesión
│   │   │   └── SubscriptionController.php          # Checkout y gestión de suscripciones
│   │   ├── Middleware/
│   │   │   ├── EnsureSingleSession.php     # Enforce una sesión por abogado
│   │   │   ├── EnsureSubscriptionActive.php
│   │   │   ├── EnsureTermsAccepted.php
│   │   │   ├── ForcePasswordChange.php
│   │   │   └── VerifyInternalApiKey.php    # Protege API interna
│   │   └── Requests/
│   │       ├── Auth/LoginRequest.php
│   │       ├── ProfileUpdateRequest.php
│   │       └── StoreConsultationRequestRequest.php
│   ├── Models/
│   │   ├── Concerns/BelongsToLawyer.php    # Trait con scope global
│   │   ├── Scopes/LawyerScope.php          # Filtra por lawyer_id automáticamente
│   │   ├── User.php
│   │   ├── Subject.php
│   │   ├── ConsultationRequest.php
│   │   ├── CertificateRequest.php
│   │   ├── Subscription.php
│   │   ├── SubscriptionPlan.php
│   │   ├── Payment.php
│   │   └── ErrorReport.php
│   ├── Notifications/
│   │   ├── ErrorReportResolved.php
│   │   ├── ResetPassword.php
│   │   ├── SubscriptionExpiringSoon.php
│   │   └── VerifyEmail.php
│   ├── Providers/AppServiceProvider.php
│   ├── Services/
│   │   ├── CertificateJobDispatcher.php        # Empuja jobs a Redis
│   │   ├── CertificateSitePriorityService.php  # Ordena sitios por duración
│   │   └── EpaycoSignatureService.php          # Verifica firmas ePayco
│   └── View/Components/
│       ├── AppLayout.php
│       ├── GuestLayout.php
│       └── LegalLayout.php
├── automation/                          # Worker Python de automatización
│   ├── worker.py                        # Consumidor principal de la cola
│   ├── config.py                        # Configuración del worker
│   ├── redis_semaphore.py               # Semáforo distribuido con Redis
│   ├── autoscaler.py                    # Autoscaler para Docker Swarm
│   ├── captcha_solver.py                # Resolución de reCAPTCHA
│   ├── pgn_resolver.py                  # Resolver de preguntas Procuraduría
│   ├── sites/
│   │   ├── __init__.py                  # Creación de contextos stealth
│   │   ├── rnmc.py                      # Scraper RNMC/Policía Nacional
│   │   ├── contraloria.py               # Scraper Contraloría
│   │   ├── policia_judicial.py           # Scraper Policía Judicial
│   │   ├── procuraduria.py              # Scraper Procuraduría
│   │   └── pgn_resolver.py              # Duplicado del resolver
│   ├── tests/
│   │   ├── test_worker.py
│   │   ├── test_autoscaler.py
│   │   └── test_redis_semaphore.py
│   ├── temp_certs/                      # Directorio temporal de PDFs
│   ├── logs/                            # Logs del worker
│   ├── requirements.txt
│   ├── requirements-dev.txt
│   ├── requirements-autoscaler.txt
│   ├── Dockerfile
│   ├── Dockerfile.autoscaler
│   ├── README.md
│   └── start_worker_safe.ps1            # Launcher PowerShell
├── config/
│   ├── app.php                          # Locale: es, faker: es_CO
│   ├── auth.php
│   ├── cache.php
│   ├── database.php                     # Default: MySQL
│   ├── filesystems.php                  # Default: local (storage/app/private)
│   ├── legal.php                        # Custom: terms_version, terms_updated_at
│   ├── permission.php                   # Spatie Permission config
│   ├── sanctum.php
│   └── services.php                     # internal_api key, epayco credentials
├── database/
│   ├── factories/                       # User, Subscription, SubscriptionPlan, Payment, ErrorReport
│   ├── migrations/                      # 19 migraciones
│   └── seeders/                         # RoleSeeder (admin, abogado), DatabaseSeeder
├── resources/
│   ├── css/app.css                      # Tailwind directives
│   ├── js/app.js                        # Alpine.js init + SweetAlert helpers
│   └── views/                           # 56 archivos Blade
│       ├── layouts/                     # app, guest, legal-layout, navigation
│       ├── admin/                       # Dashboard, lawyers, subscription-plans, error-reports
│       ├── auth/                        # login, register, forgot-password, etc.
│       ├── components/                  # 15 componentes Blade reutilizables
│       ├── consultation-requests/       # create, show, index
│       ├── subscription/                # show, checkout, return, manage
│       ├── error-reports/               # create
│       ├── legal/                       # accept, terms, privacy
│       └── profile/                     # edit, partials
├── routes/
│   ├── web.php                          # Rutas web (123 líneas)
│   ├── api.php                          # API + webhook ePayco + API interna
│   ├── auth.php                         # Rutas de autenticación Breeze
│   └── console.php                      # Scheduler (notificación de suscripciones)
├── storage/
│   └── app/private/certificates/        # PDFs organizados por consultation_request_id
│       ├── 3/                           # Cada número = un ConsultationRequest
│       │   ├── abc123.pdf
│       │   └── def456.pdf
│       ├── 4/
│       └── ...
├── tests/
│   ├── Feature/
│   │   ├── Admin/                       # Tests del panel admin
│   │   ├── Auth/                        # Tests de autenticación
│   │   └── Console/                     # Tests de comandos Artisan
│   ├── Unit/
│   └── Concerns/
├── docker-compose.yml                   # Redis + worker (desarrollo)
├── docker-compose.override.yml          # Override: DRY_RUN=true
├── docker-stack.yml                     # Docker Swarm (producción)
├── composer.json
├── package.json
├── tailwind.config.js                   # Paleta: ink, brass, surface, carbon, rust
├── vite.config.js
└── CLAUDE.md                            # Instrucciones para AI assistants
```

---

## 6. Modelado de Base de Datos

### Diagrama de Relaciones

```
┌──────────┐     ┌──────────────┐     ┌──────────────────────┐
│  users   │────<│ subscriptions│>────│ subscription_plans   │
│          │     └──────────────┘     └──────────────────────┘
│          │
│          │────<┌──────────────┐     ┌──────────────────────┐
│          │     │   payments   │>────│ subscription_plans   │
│          │     └──────────────┘     └──────────────────────┘
│          │
│          │────<┌──────────────┐
│          │     │   subjects   │     (abogado = lawyer_id)
│          │     └──────┬───────┘
│          │            │
│          │     ┌──────┴───────┐
│          │     │ consultation │
│          │     │   _requests  │
│          │     └──────┬───────┘
│          │            │
│          │     ┌──────┴────────┐
│          │     │  certificate  │
│          │     │   _requests   │ (1 por sitio)
│          │     └───────────────┘
│          │
│          │────<┌──────────────┐
│          │     │ error_reports│
│          │     └──────────────┘
└──────────┘
```

### Tablas Principales

#### `users`
Campos: `id`, `name`, `email`, `email_verified_at`, `password`, `must_change_password` (bool), `current_session_id` (string|null), `terms_accepted_at` (datetime), `terms_version_accepted` (string), `remember_token`, `timestamps`.

#### `subjects`
Campos: `id`, `lawyer_id` (FK→users), `document_type` (enum: CC/CE/PA/NIT), `document_number`, `full_name`, `company_name`, `issuance_date` (date, requerido para CC), `timestamps`.
**Unique:** (lawyer_id, document_type, document_number) — un abogado no registra el mismo documento dos veces.

#### `consultation_requests`
Campos: `id`, `lawyer_id` (FK→users), `subject_id` (FK→subjects), `status` (enum: pending/success/failed/partial), `timestamps`.

**Lógica de `status`:** Se calcula dinámicamente con `refreshStatus()` basándose en los estados de sus `certificateRequests` hijos:
- Todos success → `success`
- Todos failed → `failed`
- Todos pending → `pending`
- Mezcla → `partial`

#### `certificate_requests`
Campos: `id`, `consultation_request_id` (FK), `site` (enum: comptroller/judicial_police/rnmc/attorney_general), `status` (enum: pending/processing/success/failed), `error_message` (text|null), `pdf_path` (string|null), `duration_seconds` (int|null), `timestamps`.
**Unique:** (consultation_request_id, site) — una solicitud por sitio por consulta.

#### `subscription_plans`
Campos: `id`, `name`, `price_in_cents` (int), `duration_months` (int), `description`, `is_active` (bool), `timestamps`.

#### `subscriptions`
Campos: `id`, `user_id` (FK), `subscription_plan_id` (FK), `plan` (string legacy), `status` (enum: active/suspended/cancelled/expired), `starts_at` (date), `ends_at` (date), `expiry_notified_at` (datetime|null), `timestamps`.

#### `payments`
Campos: `id`, `user_id` (FK), `subscription_plan_id` (FK), `reference` (string, unique — formato `CERTICHECK-{id}-{random}`), `payment_provider` (string — 'epayco' o 'wompi'), `amount_in_cents` (int), `wompi_transaction_id` (string|null), `gateway_transaction_id` (string|null), `status` (enum: pending/approved/declined/error/voided), `raw_payload` (json), `timestamps`.

#### `error_reports`
Campos: `id`, `lawyer_id` (FK→users), `subject`, `description`, `category` (enum: pago/certificado/otro), `status` (enum: pending/resolved), `admin_comment` (text|null), `resolved_by` (FK→users|null), `resolved_at` (datetime|null), `timestamps`.

### Permisos (Spatie)
- Roles: `admin`, `abogado`
- Permisos: no definidos explícitamente (se usa middleware `role:admin` / `role:abogado`)

---

## 7. Flujo Principal de Trabajo

### Flujo Completo: Solicitud de Certificados

```
1. Abogado → POST /consultation-requests
   │
   ├─ Subject::firstOrCreate (datos de la persona)
   ├─ ConsultationRequest::create (status: pending)
   ├─ CertificateRequest::create × N (1 por sitio seleccionado)
   │   └─ Site: comptroller, judicial_police, rnmc, attorney_general
   │
   └─ CertificateJobDispatcher::dispatchMultiple()
       └─ Para cada CertificateRequest:
           ├─ Marcar status → "processing"
           └─ LPUSH JSON a Redis lista "certificate_jobs"

2. Python Worker (consumiendo BLPOP de "certificate_jobs")
   │
   ├─ Extrae payload de la cola
   ├─ Obtiene DistributedSemaphore para el sitio
   ├─ Ejecuta el scraper correspondiente (sites/{site}.py)
   │   ├─ Crea contexto Playwright stealthed
   │   ├─ Navega al portal gubernamental
   │   ├─ Llena formularios
   │   ├─ Resuelve CAPTCHA (si aplica)
   │   ├─ Descarga/captura el PDF
   │   └─ Retorna ruta del PDF temporal
   │
   └─ POST /internal/certificate-requests/{id}/complete
       ├─ status: "success" + pdf_file (el PDF)
       │   └─ Laravel almacena en storage/app/private/certificates/{consultation_id}/
       └─ status: "failed" + error_message
           └─ Laravel registra el error

3. Frontend (Alpine.js)
   │
   └─ Polling cada 2s → GET /consultation-requests/{id}/status
       └─ Muestra progreso: pending → processing → success/failed
```

### Flujo de Descarga

```
Abogado → GET /consultation-requests/{id}/download/{certId}
  │
  ├─ Verifica: certificado success + tiene pdf_path
  ├─ Verifica: es dueño O es admin
  └─ Storage::download(pdf_path, nombre_amigable.pdf)
      └─ Nombre: "{documento} - {etiqueta_sitio}.pdf"
```

---

## 8. Comunicación Laravel ↔ Python

### Dirección: Laravel → Python (dispatching)

El worker Python **NO** se comunica directamente con Laravel para recibir trabajos. En su lugar, usa **Redis como intermediario**:

**Laravel (emisor):**
```php
// CertificateJobDispatcher::dispatch()
Redis::lpush('certificate_jobs', json_encode([
    'certificate_request_id' => $certificateRequest->id,
    'site' => $certificateRequest->site,
    'document_type' => $subject->document_type,
    'document_number' => $subject->document_number,
    'full_name' => $subject->full_name,
    'issuance_date' => $subject->issuance_date->format('d/m/Y'),
]));
```

**Python (consumidor):**
```python
# worker.py
job_data = self.redis.blpop(self.queue_name, timeout=BLPOP_TIMEOUT)
payload = json.loads(job_data[1])
# Procesa el job...
```

### Dirección: Python → Laravel (reporte de resultados)

Después de procesar, el worker hace un **HTTP POST** a una API interna de Laravel:

```python
# worker.py → report_success()
requests.post(
    f"{self.base_url}/api/internal/certificate-requests/{cert_id}/complete",
    headers={"X-Internal-Api-Key": self.api_key},
    files={"pdf": open(pdf_path, "rb")},
    data={
        "status": "success",
        "duration_seconds": str(duration),
    }
)
```

**Laravel (receptor):**
```php
// Internal\CertificateRequestController::complete()
// 1. Valida la API key (VerifyInternalApiKey middleware)
// 2. Valida el payload
// 3. Almacena el PDF:
$pdfPath = $certificateRequest->consultation_request_id . '/' . $filename;
Storage::disk('local')->put($pdfPath, file_get_contents($pdfFile));
$certificateRequest->update(['pdf_path' => $pdfPath, 'status' => 'success']);
// 4. Refresca el estado de la consulta padre
$certificateRequest->consultationRequest->refreshStatus();
```

### Autenticación de la API Interna

La API interna usa un **API key estático** definido en `.env` como `INTERNAL_API_KEY`, transmitido en el header `X-Internal-Api-Key`. El middleware `VerifyInternalApiKey` compara usando `hash_equals()` para prevenir timing attacks.

---

## 9. Sistema de Cola Redis

### Por qué Redis y no Laravel Queue

Laravel Queue se configuró con `QUEUE_CONNECTION=sync` en `.env`. En su lugar, se usa Redis directamente por estas razones:

1. **Independencia tecnológica**: El worker es Python, no PHP. Redis es accesible desde ambos lenguajes.
2. **Control fino**: Permite BLPOP con timeout, semáforos distribuidos, y métricas en tiempo real.
3. **Escalabilidad**: El autoscaler de Docker Swarm escala workers basándose en la longitud de la cola Redis.
4. **Simplicidad**: Un solo list `certificate_jobs` como cola FIFO.

### Estructura del Payload

```json
{
    "certificate_request_id": 42,
    "site": "rnmc",
    "document_type": "CC",
    "document_number": "1234567890",
    "full_name": "Juan Pérez",
    "issuance_date": "15/03/2020"
}
```

### Monitoreo de la Cola

```bash
# Longitud de la cola
php artisan certificates:queue-status
# → Redis LLEN certificate_jobs

# Estadísticas de duración por sitio
php artisan certificates:stats
# → Promedio de duration_seconds por site
```

---

## 10. Almacenamiento de PDFs

### Ubicación

Los PDFs se almacenan en el disco `local` de Laravel, que apunta a `storage/app/private/`:

```
storage/app/private/certificates/
├── 3/                              # ConsultationRequest ID
│   ├── a1b2c3d4e5f6g7h8i9j0.pdf   # Nombre generado de 40 caracteres
│   └── k1l2m3n4o5p6q7r8s9t0.pdf
├── 4/
│   └── u1v2w3x4y5z6a7b8c9d0.pdf
└── ...
```

### Flujo de Almacenamiento

1. **Python Worker** descarga/captura el PDF a `automation/temp_certs/` (directorio temporal)
2. **Worker** hace POST a Laravel con el archivo PDF como multipart/form-data
3. **Laravel** recibe el archivo en `Internal\CertificateRequestController::complete()`
4. **Laravel** genera nombre: `{random_40_chars}.pdf`
5. **Laravel** almacena vía `Storage::disk('local')->put()` en la ruta `{consultation_request_id}/{filename}.pdf`
6. **Laravel** guarda la ruta relativa en `certificate_requests.pdf_path`

### Servicio de Descarga

```php
// ConsultationRequestController::download()
return Storage::download(
    $certificateRequest->pdf_path,
    $friendlyName  // "{documento} - {etiqueta_sitio}.pdf"
);
```

### Limpieza

Cuando se elimina una consulta (`destroy()`), se elimina también el directorio de PDFs:
```php
Storage::disk('private')->deleteDirectory(
    'certificates/' . $consultationRequest->id
);
```

---

## 11. Autenticación y Seguridad

### Stack de Autenticación

- **Laravel Breeze** (Blade stack) como scaffold base
- **Laravel Sanctum** para tokens API
- **Spatie Permission** para roles
- **Sesión basada en database** (`SESSION_DRIVER=database`)

### Flujo de Login

```
1. Email + Password → AuthenticatedSessionController::store()
2. Rate limiting: 5 intentos, lockout 60 segundos
3. Verificación de sesión única (para abogados):
   ├─ Si ya tiene sesión activa → generar force_token (3 min expira)
   │   └─ Redirect a login con modal "Sesión en otro dispositivo"
   └─ Si no → login normal, set current_session_id
4. Regeneración de sesión
5. Redirect por rol: admin → admin.lawyers.index, abogado → dashboard
```

### Cambio Forzoso de Contraseña

Los usuarios creados por el admin (`must_change_password = true`) son redirigidos al formulario de cambio de contraseña en cada carga de página hasta que lo completen.

### Verificación de Email

Middleware `verified` aplicado a rutas de abogado. Los emails de verificación están en español con branding de CertiCheck.

### Protección de la API Interna

El middleware `VerifyInternalApiKey` protege las rutas `/api/internal/*`:
```php
// Solo acepta requests con header válido
hash_equals(config('services.internal_api.key'), $request->header('X-Internal-Api-Key'));
```

---

## 12. Sistema de Roles y Permisos

### Roles

| Rol | Descripción | Acceso |
|-----|-------------|--------|
| `admin` | Administrador del sistema | Panel admin completo, gestión de abogados, planes, reportes |
| `abogado` | Abogado registrado | Consultas, certificados, perfil, suscripción, reportar problemas |

### Middleware de Autorización

```php
// routes/web.php
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Solo admin
});

Route::middleware(['auth', 'verified', 'role:abogado', 'single.session', 'terms.accepted', 'subscription.active'])->group(function () {
    // Abogado con todas las verificaciones
});
```

### Autorización Inline

No existen Policies. La autorización se maneja con `abort_unless` inline:
```php
abort_unless(
    $consultationRequest->lawyer_id === auth()->id() || auth()->hasRole('admin'),
    403
);
```

---

## 13. Sesión Única (Single Session)

### Problema
Un abogado podría iniciar sesión en múltiples dispositivos/browsers simultáneamente.

### Solución

1. **Al login:** Se verifica si el usuario ya tiene un `current_session_id` activo en la tabla `sessions`
2. **Conflicto detectado:** Se genera un `force_token` (cifrado, 3 min expira) y se redirige al login con un modal SweetAlert
3. **Forzar sesión:** `forceLogin()` valida el token, mata la sesión anterior, crea la nueva
4. **Heartbeat:** `GET /session/heartbeat` mantiene viva la sesión (responde `{"active": true}`)
5. **Session Checker:** Componente Blade `<x-session-checker />` hace polling periódico; si detecta que la sesión fue cerrada en otro dispositivo, fuerza logout

### Cierre de Sesión Remoto

El middleware `EnsureSingleSession` detecta si el `current_session_id` ya no coincide con la sesión actual:
```php
if ($user->current_session_id !== $request->session()->getId()) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    // JSON 401 para requests AJAX, redirect para requests normales
}
```

---

## 14. Sistema de Suscripciones

### Modelo de Negocio

Los abogados deben tener una suscripción activa para crear consultas de certificados.

### Estados de Suscripción

```
active → suspended → expired
  ↓         ↓          ↓
cancelled ←───────────┘
```

### Flujo de Suscripción

1. **Admin crea abogado** → Se crea suscripción `active` con el plan seleccionado + pago `ADMIN-GRANT` de $0
2. **Abogado compra** → Checkout ePayco → Webhook confirma → `activarSuscripcion()`
3. **Activación:**
   - Si tiene suscripción activa → extiende `ends_at` sumando `duration_months`
   - Si no tiene → crea nueva con `starts_at = now`, `ends_at = now + duration_months`
4. **Expiración:** La suscripción expira cuando `ends_at` pasa (verificada en `hasActiveSubscription()`)
5. **Notificación:** `subscriptions:notify-expiring` corre diario a las 08:00, notifica a quienes expiran en 3 días

### Middleware

```php
// EnsureSubscriptionActive
if ($user->isAbogado() && !$user->hasActiveSubscription()) {
    return redirect()->route('subscription.show');
}
```

### Planes de Suscripción

CRUD completo en el panel admin. Cada plan tiene:
- Nombre descriptivo
- Precio en centavos (multiplicado ×100 al guardar — entrada en unidades de moneda)
- Duración en meses
- Estado activo/inactivo

---

## 15. Pasarelas de Pago

### ePayco (Activa)

**Integración:**
- Checkout.js de ePayco se carga en la vista `subscription/checkout.blade.php`
- Configuración: public key, test mode, currency COP
- Precio: se envía en centavos (COP × 100)

**Flujo:**
```
1. Abogado selecciona plan → SubscriptionController::checkout()
   └─ Crea Payment con reference "CERTICHECK-{id}-{random}", status: pending
   └─ Retorna vista con checkout.js configurado

2. Abogado completa pago en widget ePayco
   └─ ePayco redirige a /subscription/return con query params
   └─ SubscriptionController::return() loggea la respuesta

3. ePayco envía webhook a POST /api/webhooks/epayco
   └─ EpaycoWebhookController::handle()
       ├─ Verifica firma SHA-256 (x_signature)
       ├─ Mapea estado: Aceptada→approved, Rechazada→declined, etc.
       ├─ Actualiza Payment con gateway_transaction_id + raw_payload
       └─ Si approved → activarSuscripcion()
```

**Verificación de Firma:**
```php
// EpaycoSignatureService
$expected = hash('sha256', "{$custIdCliente}^{$pKey}^{$xRefPayco}^{$xTransId}^{$xAmount}^{$xCurrency}");
hash_equals($expected, $xSignature);
```

### Wompi (Configurada parcialmente)

Credenciales presentes en `.env` pero la integración parece no estar completamente activa. Las migraciones originales referencian `wompi_transaction_id`.

---

## 16. Scraper de Sitios Gubernamentales

### Arquitectura de Scrapers

Cada sitio gubernamental tiene su propio módulo Python en `automation/sites/`:

```python
# Interfaz común (no formal, pero convencional)
async def run(certificate_request_id, document_type, document_number, full_name, issuance_date):
    # 1. Crear contexto browser stealthed
    # 2. Navegar al portal
    # 3. Llenar formularios
    # 4. Resolver CAPTCHA si aplica
    # 5. Descargar/capturar PDF
    # 6. Retornar ruta del PDF
```

### Contexto Stealthed

```python
# sites/__init__.py
async def crear_context_stealthed(playwright):
    browser = await playwright.chromium.launch(headless=True)
    context = await browser.new_context(
        user_agent="Mozilla/5.0 ...",
        viewport={"width": 1920, "height": 1080},
    )
    # Aplica playwright-stealth (anti-detección)
    # Corrige header Sec-CH-UA para ocultar "HeadlessChrome"
    return context
```

### Scraper: RNMC (`sites/rnmc.py`)

- **Portal:** RNMC/Policía Nacional
- **CAPTCHA:** No
- **Método PDF:** `page.pdf()` — renderiza la página y captura como PDF
- **Requisito especial:** Requiere `issuance_date` para cédulas (CC)
- **Complejidad:** Baja — formulario directo

### Scraper: Policía Judicial (`sites/policia_judicial.py`)

- **Portal:** DIJIN - Policía Judicial
- **CAPTCHA:** reCAPTCHA v2 (resuelto con captcha_solver)
- **Método PDF:** `page.pdf()`
- **Extras:** Acepta términos y condiciones antes de buscar
- **Complejidad:** Media

### Scraper: Contraloría (`sites/contraloria.py`)

- **Portal:** Contraloría General de la República
- **CAPTCHA:** reCAPTCHA v2 (resuelto con captcha_solver)
- **Método PDF:** `expect_download()` — intercepta la descarga nativa del sitio
- **Complejidad:** Media

### Scraper: Procuraduría (`sites/procuraduria.py`)

- **Portal:** Procuraduría General de la Nación
- **CAPTCHA:** Preguntas de verificación (no reCAPTCHA)
- **Método PDF:** Multi-estrategia: descarga nativa + `page.pdf()` como fallback
- **Especial:** Navega iframes, responde preguntas matemáticas/letras/capitales
- **Complejidad:** Alta — el más complejo de todos
- **Reintentos:** Hasta 8 intentos de preguntas

### PGN Resolver (`sites/pgn_resolver.py`)

Resuelve los tipos de pregunta de verificación de Procuraduría:

| Tipo de Pregunta | Estrategia |
|------------------|------------|
| Operación matemática | Evalúa la expresión (ej: "¿Cuánto es 7 + 3?") |
| Capital de departamento | Lookup de mapa Colombia |
| Primera letra del nombre | Toma el carácter en posición 0 |
| Dígito del documento | Extrae el carácter en la posición indicada |

---

## 17. Resolución de CAPTCHA

### Proveedor Primario: CapSolver

```python
# captcha_solver.py
async def solve_recaptcha_v2(site_key, page_url):
    # 1. Intentar con CapSolver primero
    result = await capsolver.solve_recaptcha_v2(site_key, page_url)
    if result:
        return result
    # 2. Fallback a 2Captcha
    return await twocaptcha.solve_recaptcha_v2(site_key, page_url)
```

### Configuración

Las API keys de CapSolver y 2Captcha se configuran en `automation/.env` y se pasan al worker via Docker environment o variables de entorno del sistema.

---

## 18. Semáforo Distribuido y Concurrency

### Problema

Los portales gubernamentales tienen rate limits y pueden bloquear IPs si reciben demasiadas solicitudes simultáneas.

### Solución: DistributedSemaphore

El `redis_semaphore.py` implementa un semáforo distribuido usando Redis:

**Capacidades máximas por sitio (configurables):**

| Sitio | Max Concurrency |
|-------|----------------|
| `rnmc` | 4 |
| `comptroller` | 2 |
| `judicial_police` | 2 |
| `attorney_general` | 1 |

**Mecanismo:**
- Usa `SET` de Redis como registro de leases activos
- Lua scripts atómicos para acquire/release
- Heartbeat cada N segundos para detectar crashes
- Lease timeout configurable (180 segundos)
- Acquire timeout (600 segundos) — si no puede obtener el semáforo, cancela el job

```python
# Uso en worker.py
async with self.semaphore.acquire(site):
    # Solo ejecuta si hay disponibilidad en el semáforo
    result = await scraper.run(...)
```

---

## 19. Autoscaling (Docker Swarm)

### `autoscaler.py`

Un daemon que corre como servicio Docker y escala automáticamente el número de workers basándose en la carga de la cola Redis.

**Configuración de Escalado:**

| Parámetro | Valor | Descripción |
|-----------|-------|-------------|
| `SCALE_UP_THRESHOLD` | 10 jobs | Si la cola tiene >10 jobs por 2 ciclos consecutivos → escalar arriba |
| `SCALE_DOWN_THRESHOLD` | 1 job | Si la cola tiene <1 job por 4 ciclos consecutivos → escalar abajo |
| `MIN_REPLICAS` | 1 | Mínimo de workers |
| `MAX_REPLICAS` | 8 | Máximo de workers |
| `COOLDOWN_SECONDS` | 60 | Espera entre escalados |
| `CHECK_INTERVAL` | 15s | Frecuencia de verificación |

**Flujo:**
```
Cada 15 segundos:
  1. Consultar LLEN certificate_jobs en Redis
  2. Comparar con thresholds
  3. Si necesita escalar:
       docker service scale certicheck_worker=N
  4. Respetar cooldown de 60s entre escalados
```

**Docker Swarm Stack (`docker-stack.yml`):**
- Redis: servicio replicado con volumen persistente
- Autoscaler: monta `/var/run/docker.sock:ro` para controlar Docker
- Worker: imagen `certificados-worker:latest`, replicas: 2, límites de recursos (4GB RAM, 2 CPU)

---

## 20. Comandos Artisan y Monitoreo

### Comandos Personalizados

| Comando | Propósito |
|---------|-----------|
| `php artisan certificates:queue-status` | Muestra la longitud actual de la cola Redis |
| `php artisan certificates:stats` | Estadísticas de duración promedio por sitio |
| `php artisan admin:create` | Crea un usuario administrador con contraseña temporal |
| `php artisan subscriptions:notify-expiring` | Notifica abogados cuya suscripción expira en 3 días |

### Tareas Programadas

```php
// routes/console.php
Schedule::command('subscriptions:notify-expiring')->dailyAt('08:00');
```

Ejecución: `php artisan schedule:work` (daemon) o `php artisan schedule:run` (cron).

### Comandos de Monitoreo del Worker

```bash
# Verificar cola
redis-cli LLEN certificate_jobs

# Verificar semáforos
redis-cli KEYS "cert_sem:*"

# Stats del worker
php artisan certificates:stats
```

---

## 21. Despliegue con Docker

### Desarrollo Local (`docker-compose.yml`)

```yaml
services:
  redis:
    image: redis:7-alpine
    ports: ["6379:6379"]
    volumes: [redis-data:/data]

  worker:
    build: ./automation
    environment:
      REDIS_HOST: certicheck-redis
      QUEUE_NAME: certificate_jobs
      LARAVEL_BASE_URL: http://host.docker.internal:8000
      WORKER_POOL_SIZE: 10
    deploy:
      replicas: 1
```

### Override para Testing (`docker-compose.override.yml`)

```yaml
services:
  worker:
    environment:
      DRY_RUN: "true"                    # Simula sin navegar portales reales
      DRY_RUN_DURATION_SECONDS: "2"      # Simula 2 segundos de trabajo
      MAX_CONCURRENCY_RNMC: "2"          # Reduce concurrency
```

### Producción (`docker-stack.yml`)

```yaml
services:
  redis:
    image: redis:7-alpine
    networks: [certicheck]
    deploy:
      replicas: 1
      restart_policy: {condition: on-failure}

  autoscaler:
    image: certificados-autoscaler:latest
    volumes: ["/var/run/docker.sock:/var/run/docker.sock:ro"]
    networks: [certicheck]
    environment:
      SCALE_UP_THRESHOLD: "10"
      SCALE_DOWN_THRESHOLD: "1"
      MIN_REPLICAS: "1"
      MAX_REPLICAS: "8"

  worker:
    image: certificados-worker:latest
    networks: [certicheck]
    stop_grace_period: 120s
    deploy:
      replicas: 2
      resources:
        limits:   {memory: 4G, cpus: "2"}
        reservations: {memory: 1G, cpus: "0.25"}
```

### Imágenes Docker

**Worker (`automation/Dockerfile`):**
```dockerfile
FROM mcr.microsoft.com/playwright/python:v1.53.0-noble
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
CMD ["python", "worker.py"]
```

**Autoscaler (`automation/Dockerfile.autoscaler`):**
```dockerfile
FROM python:3.13-alpine
COPY requirements-autoscaler.txt .
RUN pip install --no-cache-dir -r requirements-autoscaler.txt
COPY . .
CMD ["python", "autoscaler.py"]
```

---

## 22. Frontend y UI

### Paleta de Colores

| Color | Hex | Uso |
|-------|-----|-----|
| **ink** | #16324F | Color primario (navys) — headers, botones, sidebar |
| **brass** | #B08D57 | Color de acento (dorado) — highlights, bordes |
| **surface** | #F7F8FA | Fondo general |
| **carbon** | #1F2429 | Texto oscuro |
| **rust** | #B54B3F | Errores, alertas |

### Tipografía

| Fuente | Uso |
|--------|-----|
| **Inter** | Sans-serif general (texto, UI) |
| **Source Serif 4** | Serif para títulos de landing |
| **IBM Plex Mono** | Monospace para códigos |

### Componentes Blade Reutilizables

- `<x-app-layout>` / `<x-guest-layout>` / `<x-legal-layout>` — Layouts
- `<x-primary-button>` / `<x-danger-button>` / `<x-secondary-button>` — Botones
- `<x-modal>` / `<x-confirm-modal>` — Modales con Alpine.js
- `<x-text-input>` / `<x-input-label>` / `<x-input-error>` — Formularios
- `<x-dropdown>` / `<x-dropdown-link>` / `<x-nav-link>` — Navegación
- `<x-session-checker>` — Heartbeat de sesión única (solo para abogados)
- `<x-application-logo>` — Logo SVG (check-in-circle)

### Interactividad (Alpine.js)

No usa Livewire ni Vue. Toda la interactividad se maneja con Alpine.js:

- **Polling de estado:** `setInterval` + `fetch` para mostrar progreso de certificados en tiempo real
- **Session checker:** Componente que polla heartbeat y fuerza logout si la sesión fue cerrada remotamente
- **Modales de confirmación:** SweetAlert2 (`swalConfirm()`) para acciones destructivas
- **Sidebar responsive:** `x-data="sidebarOpen"` toggle

### Responsive Design

- Sidebar colapsable en móvil con hamburger menu
- Tablas con scroll horizontal en móvil
- Cards adaptativas con Tailwind breakpoints

---

## 23. Testing

### Framework: Pest 4

```bash
# Ejecutar todos los tests
php artisan test --compact

# Ejecutar tests específicos
php artisan test --compact --filter=SingleSessionTest
```

### Categorías de Tests

| Categoría | Archivos | Cobertura |
|-----------|----------|-----------|
| **Feature/Admin** | Dashboard, Lawyers, ErrorReports, SubscriptionPlans | CRUD admin, métricas |
| **Feature/Auth** | Login, Register, ForcePassword, SingleSession, Terms | Autenticación completa |
| **Feature/Console** | CertificateStats, QueueStatus, CreateAdmin | Comandos Artisan |
| **Unit** | CertificateSitePriority, SpanishLocale | Lógica de negocio |

### Tests Notables

- `CertificateSitePriorityTest` — Verifica el ordenamiento de sitios por duración
- `CertificatesStatsTest` — Verifica estadísticas de duración
- `InternalCertificateRequestTest` — Verifica el endpoint de callback del worker
- `SingleSessionTest` — Verifica el comportamiento de sesión única
- `EnsureTermsAcceptedTest` — Verifica aceptación de términos
- `SpanishEmailNotificationsTest` — Verifica que los emails están en español

### Tests del Worker Python

```bash
cd automation
python -m pytest tests/
```

- `test_worker.py` — Concurrencia, DRY_RUN, requeue en shutdown
- `test_redis_semaphore.py` — Semáforo distribuido con multiprocesamiento
- `test_autoscaler.py` — Lógica de escalado con mocks

---

## 24. Comandos de Desarrollo

### Inicio Completo del Entorno

```powershell
# 1. Redis
docker start certicheck-redis

# 2. Túnel ngrok (para webhooks de ePayco)
ngrok http 8000

# 3. Laravel
php artisan serve

# 4. Frontend (hot reload)
npm run dev

# 5. Worker Python
.\venv\Scripts\Activate.ps1
python worker.py

# 6. Scheduler
php artisan schedule:work
```

### Comandos Útiles

```bash
# Crear admin
php artisan admin:create

# Verificar cola
php artisan certificates:queue-status

# Stats de certificados
php artisan certificates:stats

# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Testing
php artisan test --compact

# Formatear código
vendor/bin/pint --dirty --format agent

# Compilar frontend
npm run build
npm run dev
```

### Variables de Entorno Importantes

| Variable | Propósito |
|----------|-----------|
| `APP_URL` | URL pública (debe ser la URL de ngrok para webhooks) |
| `INTERNAL_API_KEY` | API key para comunicación Laravel ↔ Python |
| `WOMPI_*` | Credenciales Wompi (parcialmente configuradas) |
| `EPAYCO_*` | Credenciales ePayco (activas) |
| `WOMPI_SANDBOX` / `EPAYCO_TEST_MODE` | Modo sandbox/test |

---

## Apéndice: Estrategias y Patrones

### Patrón: Job Dispatcher sin Laravel Queue

En lugar de usar el sistema de queues nativo de Laravel (que estaría en `app/Jobs/`), se usa un `CertificateJobDispatcher` que empuja directamente a Redis con `LPUSH`. Esto permite que un proceso Python (no PHP) consuma los jobs.

### Patrón: Scope Global con Trait

El trait `BelongsToLawyer` + el scope `LawyerScope` filtran automáticamente todas las consultas por `lawyer_id = Auth::id()` a menos que el usuario sea admin. Esto asegura que cada abogado solo vea sus propios datos.

```php
// Model/Concerns/BelongsToLawyer.php
public static function bootBelongsToLawyer(): void
{
    static::addGlobalScope(new LawyerScope);
    static::creating(function ($model) {
        $model->lawyer_id = auth()->id();
    });
}
```

### Patrón: Priorización de Sitios

El `CertificateSitePriorityService` ordena los sitios de más lento a más rápido antes de enviarlos a la cola. Esto permite que los sitios rápidos (RNMC: 7s) se completen mientras los lentos (Procuraduría: 30s) aún procesan, mejorando el tiempo total percibido.

### Patrón: Graceful Shutdown

El worker Python captura SIGTERM y:
1. Deja de consumir nuevos jobs
2. Espera a que los jobs en cours (in-flight) terminen (hasta `SHUTDOWN_WAIT`)
3. Re-encola jobs que no completaron (máximo `MAX_REQUEUE_ATTEMPTS = 3`)

### Patrón: Semáforo con Lease

El semáforo distribuido no solo limita concurrencia sino que tiene **lease timeout**: si un worker crashea sin liberar el semáforo, el lease expira después de `SEMAPHORE_LEASE_SECONDS (180s)` y otro worker puede tomar su lugar.

### Patrón: Status Polling sin WebSockets

En lugar de usar Laravel Echo/WebSockets/Livewire para actualizaciones en tiempo real, se usa **polling** con `setInterval` + `fetch` cada 2 segundos. Esto es más simple y no requiere infraestructura adicional.

### Patrón: Verificación de Firma de Webhook

ePayco usa firma SHA-256 con concatenación de campos secretos. Laravel verifica la firma exacta usando `hash_equals()` para prevenir timing attacks.
