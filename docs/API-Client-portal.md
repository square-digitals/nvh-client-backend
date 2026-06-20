# NVH Client Portal — Comprehensive Backend Plan

**Stack:** Laravel 11 · PostgreSQL · Sanctum · Spatie Activity Log · Redis queues · Docker (Alpine)
**Frontend:** Next.js 15 (App Router) — same stack as admin portal
**Sync target:** NVH Admin Backend (`square-digitals/nvh-admin-backend`) — via `POST /api/internal/*` endpoints

---

## Codebase

The client portal is a **separate repository** — do not build it inside this repo.

| Repo | Purpose |
|------|---------|
| `square-digitals/nvh-admin-backend` | Admin API — this repo. Source of truth for provisioning, billing, and client records. |
| `square-digitals/nvh-client-portal-backend` | Client portal API — new repo to create. Self-service layer for end clients. |
| `square-digitals/nvh-admin-frontend` | Admin Next.js dashboard |
| `square-digitals/nvh-client-portal-frontend` | Client portal Next.js frontend |

**Use this repo (`nvh-admin-backend`) as the reference implementation** for:
- Docker setup (`Dockerfile`, `docker/nginx.conf`, `docker/supervisord.conf`, `docker/wait-for-db.sh`)
- Cookie-based auth pattern (`AuthController`, `AppServiceProvider` Sanctum hook)
- CSRF middleware (`VerifyCsrfCookie`)
- Internal secret middleware (`ValidateInternalSecret`)
- Error sanitization in `bootstrap/app.php`
- `PublicDomain` validation rule for domain fields

Copy these files directly into the client portal repo and adapt as needed.

---

## Overview

The client portal is a self-service interface where NVH hosting clients can:
- Register, log in, and manage their own account
- Order new hosting services (WordPress)
- View their active services and live status
- Download and pay invoices
- Open and track support tickets

The admin backend is the **source of truth for provisioning**. The client portal does not touch Coolify or servers directly — it syncs orders and state through the internal API.

---

## Architecture

```
┌──────────────────────────────┐
│  Client Browser              │
│  (Next.js — nvh-client-      │
│   portal-frontend)           │
└──────────────┬───────────────┘
               │ HTTPS + cookie auth (nvh_client_token)
               ▼
┌──────────────────────────────┐     X-Internal-Secret     ┌──────────────────────────┐
│  Client Portal API           │ ──────────────────────▶   │  NVH Admin Backend       │
│  (nvh-client-portal-backend) │   POST /internal/clients  │  (this repo)             │
│                              │   POST /internal/services │                          │
│                              │   POST /internal/invoices │  Coolify API ───▶ VPS    │
│                              │                           │                          │
│                              │ ◀──────────────────────── │  POST /internal/         │
│                              │   service-status webhook  │  service-status (push)   │
└──────────────────────────────┘                           └──────────────────────────┘
```

**Data flow rules:**
- Client portal pushes client/service/invoice state **to** admin backend (sync)
- Admin backend pushes service status **to** client portal (webhook)
- Client portal **never** calls Coolify directly
- Admin backend **never** calls client portal except via the internal webhook

---

## How Client Portal Communicates With Admin Backend

### Direction 1 — Client Portal → Admin Backend (outbound sync)

The client portal makes HTTP calls to the admin backend's internal API using a shared secret. These are server-to-server calls made from Laravel jobs, never from the browser.

**Authentication:** `X-Internal-Secret: <shared secret>` header

**Base URL:** `config('services.nvh_admin.base_url')` — set via `NVH_ADMIN_BASE_URL` env var

**Shared secret:** `NVH_INTERNAL_SECRET` — must match on both the admin backend and client portal. Generate with `openssl rand -hex 32`. Store in Coolify env vars for both apps.

**Endpoints called:**

| Event | Admin endpoint | When |
|-------|---------------|------|
| Client registers | `POST /api/internal/clients/sync` | After registration |
| Client updates profile | `POST /api/internal/clients/sync` | After email/name change |
| Client orders service | `POST /api/internal/services/sync` | After service created |
| Invoice paid by client | `POST /api/internal/invoices/sync` | After payment confirmed |

**`AdminApiService` class** (create at `app/Services/AdminApiService.php`):

```php
class AdminApiService
{
    public function __construct(private \GuzzleHttp\Client $http)
    {
        $this->http = new \GuzzleHttp\Client([
            'base_uri' => config('services.nvh_admin.base_url'),
            'headers'  => [
                'X-Internal-Secret' => config('services.nvh_admin.secret'),
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    public function syncClient(Client $client): void
    {
        $this->http->post('/api/internal/clients/sync', [
            'json' => ['clients' => [[
                'external_id' => $client->id,
                'name'        => $client->name,
                'email'       => $client->email,
                'plan'        => $client->plan,
                'status'      => $client->status,
            ]]],
        ]);
    }

    public function syncService(Service $service): void
    {
        $this->http->post('/api/internal/services/sync', [
            'json' => ['services' => [[
                'external_id'        => $service->id,
                'client_external_id' => $service->client_id,
                'type'               => $service->type,
                'status'             => $service->status,
                'name'               => $service->name,
                'domain'             => $service->domain,
            ]]],
        ]);
    }

    public function syncInvoicePaid(Invoice $invoice): void
    {
        $this->http->post('/api/internal/invoices/sync', [
            'json' => [[
                'external_id' => $invoice->external_id,
                'status'      => 'paid',
                'paid_at'     => now()->toIso8601String(),
            ]],
        ]);
    }
}
```

Wrap all calls in try/catch — a failure in sync should NOT fail the client request. Dispatch sync via queued jobs so the client gets a fast response regardless:

```php
SyncClientToAdminJob::dispatch($client)->onQueue('sync');
```

### Direction 2 — Admin Backend → Client Portal (inbound webhook)

When a service is provisioned, approved, rejected, or its status changes, the admin backend pushes the update to the client portal via a webhook.

**Add to admin backend** (`POST /api/internal/service-status` — build when client portal is ready):

```json
POST https://client-portal.newventureshosting.com/api/internal/service-status
X-Internal-Secret: <shared secret>

{
  "external_id": "<client portal service.id>",
  "status": "active",
  "url": "https://myblog.com",
  "failed_reason": null,
  "provisioned_at": "2026-06-17T10:00:00Z"
}
```

**Client portal handles this at** `POST /internal/service-status` protected by `internal.secret` middleware — updates the local `services` record and triggers email notification to the client.

**Until the webhook is built:** use `PollServiceStatusJob` — the admin backend already has `GET /api/internal/services/{external_id}` planned (Step 9 below). Poll every 2 minutes for services in `pending_approval` or `provisioning` status.

### Shared Secret Setup

Both apps must have the same secret. In Coolify:

Both apps in Coolify: `NVH_INTERNAL_SECRET=<shared secret>`

The client portal's `ValidateInternalSecret` middleware validates inbound calls. The `AdminApiService` attaches the secret to all outbound calls.

---

## Security Architecture

### Authentication — Cookie-Based (mirror admin backend exactly)

**Do NOT use localStorage or Bearer tokens in response bodies.** Use the identical cookie auth pattern as the admin backend:

1. Copy `app/Http/Middleware/EncryptCookies.php` — exclude `nvh_client_token` and `XSRF-TOKEN` from encryption
2. Copy `app/Http/Middleware/VerifyCsrfCookie.php` — CSRF validation on POST/PATCH/DELETE
3. In `AppServiceProvider::boot()`:

```php
Sanctum::getAccessTokenFromRequestUsing(function ($request) {
    return $request->cookie('nvh_client_token') ?: $request->bearerToken();
});
```

4. In login, set cookies identically to the admin backend:

```php
$secure   = ! app()->environment('local');
$sameSite = app()->environment('staging') ? 'None' : 'Lax';

$authCookie = cookie(
    name: 'nvh_client_token', value: $token, minutes: 60,
    path: '/', domain: config('session.domain'),
    secure: $secure, httpOnly: true, sameSite: $sameSite,
);

$csrfCookie = cookie(
    name: 'XSRF-TOKEN', value: Str::random(40), minutes: 60,
    path: '/', domain: config('session.domain'),
    secure: $secure, httpOnly: false, sameSite: $sameSite,
);
```

**Token lifetime:** 60 minutes for clients (longer than the 10-minute admin lifetime — clients are not admins, and frequent re-login is bad UX for a self-service portal).

**Cookie name:** `nvh_client_token` — not `nvh_token` — so it doesn't conflict if both portals are on subdomains of `newventureshosting.com`.

### CSRF Protection

Copy `VerifyCsrfCookie` from the admin backend exactly. All POST/PATCH/DELETE routes require:
- `Cookie: XSRF-TOKEN=<value>` (set by login, readable by JS)
- `X-XSRF-TOKEN: <same value>` header (frontend attaches on every mutating request)

GET requests are exempt. Webhook endpoints are exempt (they use `X-Internal-Secret` instead).

### No 2FA Required for Clients

TOTP 2FA is admin-only. Do not implement forced 2FA enrollment for client portal users — it would hurt conversion and is not necessary for the threat model.

### Rate Limiting

Apply `throttle:5,1` (5 per minute per IP) to:
- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`

### Authorization — Always Scope to Authenticated Client

Every model binding must scope to the authenticated client. Never trust URL IDs alone:

```php
// In ServiceController:
public function show(Request $request, string $id): JsonResponse
{
    $service = $request->user()->services()->findOrFail($id);
    // ...
}
```

Never do `Service::findOrFail($id)` without scoping — that allows any authenticated client to see another client's data (IDOR).

### Domain Validation

Copy `app/Rules/PublicDomain.php` from the admin backend. Apply it to any `domain` field submitted by clients:

```php
'domain' => ['required', 'string', new PublicDomain()],
```

This blocks SSRF attempts (localhost, private IPs, internal TLDs).

### Error Sanitization

Copy the exception handler from `bootstrap/app.php` in the admin backend:

```php
$exceptions->render(function (\Throwable $e, $request) {
    if (app()->isProduction() && $request->is('api/*')) {
        $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        if ($status >= 500) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
});
```

Never expose stack traces, internal URLs, or DB error messages to clients.

### Internal Webhook Security

Copy `ValidateInternalSecret` middleware from admin backend. Protect all `/internal/*` routes with it. Block secret passed as query param (already in the middleware).

### Payment Webhook Security

Stripe and Paystack webhooks must verify signatures — do not process any webhook without signature validation:

```php
// Stripe
$event = \Stripe\Webhook::constructEvent(
    $payload, $request->header('Stripe-Signature'), config('services.stripe.webhook_secret')
);

// Paystack
$hash = hash_hmac('sha512', $payload, config('services.paystack.secret_key'));
if ($hash !== $request->header('x-paystack-signature')) {
    return response()->json(['message' => 'Invalid signature.'], 401);
}
```

### CORS

Configure `config/cors.php` identically to the admin backend. Set `CORS_ALLOWED_ORIGINS` in Coolify to the exact client portal frontend URL. Never use `'*'` — the `supports_credentials: true` requirement makes wildcard origins invalid anyway.

---

## Packages to Install

```bash
composer require laravel/sanctum
composer require spatie/laravel-activitylog
composer require guzzlehttp/guzzle
composer require spatie/laravel-query-builder
composer require stripe/stripe-php        # or yabacon/paystack-php for NGN
```

No `spatie/laravel-permission` needed — clients do not have roles. All authorization is ownership-scoped.

---

## Environment Variables

```
APP_NAME=nvh-client-portal
APP_ENV=production
APP_KEY=<generate>
APP_DEBUG=false
APP_URL=https://client.newventureshosting.com

DB_CONNECTION=pgsql
DB_HOST=<postgres host>
DB_PORT=5432
DB_DATABASE=nvh_client_portal
DB_USERNAME=<user>
DB_PASSWORD=<password>

QUEUE_CONNECTION=redis
REDIS_HOST=redis
CACHE_STORE=redis

SESSION_DOMAIN=.newventureshosting.com
CORS_ALLOWED_ORIGINS=https://client.newventureshosting.com

# Admin backend communication
NVH_ADMIN_BASE_URL=https://admin.newventureshosting.com
NVH_INTERNAL_SECRET=<shared secret — must match admin backend>

# Payments
STRIPE_KEY=<pk_live_...>
STRIPE_SECRET=<sk_live_...>
STRIPE_WEBHOOK_SECRET=<whsec_...>
```

---

## Database Schema

### `clients` table
```
id                  uuid, PK
name                string
email               string, unique
password            string, hashed
email_verified_at   timestamp, nullable
phone               string, nullable
company             string, nullable
status              enum: active, suspended  default: active
plan                string, nullable
external_admin_id   string, nullable         ← admin backend client.id
remember_token
timestamps
```

### `services` table
```
id                  uuid, PK
client_id           uuid, FK → clients
type                enum: wordpress
name                string
domain              string, nullable
status              enum: pending_approval, provisioning, active, suspended, failed, rejected, terminated
url                 string, nullable
failed_reason       string, nullable
admin_service_id    string, nullable         ← admin backend service.id
provisioned_at      timestamp, nullable
synced_at           timestamp, nullable
timestamps
```

### `invoices` table
```
id                  uuid, PK
client_id           uuid, FK → clients
external_id         string, unique           ← used for admin sync
amount              decimal 10,2
currency            string, 3
status              enum: unpaid, paid, overdue, void
due_date            date
paid_at             timestamp, nullable
period_start        date, nullable
period_end          date, nullable
synced_at           timestamp, nullable
timestamps
```

### `support_tickets` table
```
id                  uuid, PK
client_id           uuid, FK → clients
service_id          uuid, FK → services, nullable
subject             string
body                text
status              enum: open, in_progress, resolved, closed
priority            enum: low, normal, high, urgent  default: normal
resolved_at         timestamp, nullable
timestamps
```

### `ticket_replies` table
```
id                  uuid, PK
ticket_id           uuid, FK → support_tickets
author_type         enum: client, admin
author_id           string
body                text
timestamps
```

---

## Authentication Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| `POST` | `/auth/register` | No | Register new client, sync to admin backend |
| `POST` | `/auth/login` | No | Authenticate, set `nvh_client_token` cookie |
| `POST` | `/auth/logout` | Yes | Revoke token, clear cookies |
| `GET`  | `/auth/me` | Yes | Return authenticated client |
| `POST` | `/auth/forgot-password` | No | Send reset link |
| `POST` | `/auth/reset-password` | No | Reset password with token |

**Registration flow:**
1. Client submits name, email, password
2. Create `clients` row, dispatch `SyncClientToAdminJob`
3. Admin backend creates the client record
4. Send welcome + email verification email

**Login response** (same structure as admin backend):
```json
HTTP 200
Set-Cookie: nvh_client_token=...; HttpOnly; Secure; SameSite=Lax
Set-Cookie: XSRF-TOKEN=...; Secure; SameSite=Lax

{
  "client": {
    "id": "...",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "status": "active",
    "plan": "starter",
    "email_verified_at": "2026-06-01T10:00:00Z"
  }
}
```

---

## All Endpoints

```php
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1');
    Route::post('reset-password',  [ResetPasswordController::class, 'reset'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'csrf'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'csrf', 'verified'])->group(function () {

    // Account
    Route::get('account',                  [AccountController::class, 'show']);
    Route::patch('account',                [AccountController::class, 'update']);
    Route::post('account/change-password', [AccountController::class, 'changePassword']);
    Route::delete('account',               [AccountController::class, 'destroy']);

    // Services
    Route::get('services',              [ServiceController::class, 'index']);
    Route::post('services',             [ServiceController::class, 'store']);
    Route::get('services/{service}',    [ServiceController::class, 'show']);
    Route::delete('services/{service}', [ServiceController::class, 'terminate']);

    // Invoices
    Route::get('invoices',                [InvoiceController::class, 'index']);
    Route::get('invoices/{invoice}',      [InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);

    // Tickets
    Route::get('tickets',                   [TicketController::class, 'index']);
    Route::post('tickets',                  [TicketController::class, 'store']);
    Route::get('tickets/{ticket}',          [TicketController::class, 'show']);
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply']);
    Route::post('tickets/{ticket}/close',   [TicketController::class, 'close']);
});

// Inbound from admin backend — service status updates
Route::prefix('internal')->middleware('internal.secret')->group(function () {
    Route::post('service-status',  [ServiceStatusController::class, 'update']);
    Route::post('invoices/sync',   [InvoiceSyncController::class, 'sync']);
});

// Payment provider webhooks — signature validated inside controller
Route::post('webhooks/stripe',   [StripeWebhookController::class, 'handle']);
Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);
```

---

## File Structure

```
app/
  Http/
    Controllers/
      Auth/
        AuthController.php
        ForgotPasswordController.php
        ResetPasswordController.php
      AccountController.php
      ServiceController.php
      InvoiceController.php
      TicketController.php
      Internal/
        ServiceStatusController.php   ← receives admin webhook
        InvoiceSyncController.php
      Webhooks/
        StripeWebhookController.php
        PaystackWebhookController.php
    Middleware/
      EncryptCookies.php              ← copy from admin backend, rename cookie
      VerifyCsrfCookie.php            ← copy from admin backend exactly
      ValidateInternalSecret.php      ← copy from admin backend exactly
  Jobs/
    SyncClientToAdminJob.php
    SyncServiceToAdminJob.php
    PollServiceStatusJob.php          ← fallback until webhook is live
    SendWelcomeEmailJob.php
    NotifyServiceLiveJob.php
    NotifyServiceRejectedJob.php
    NotifyInvoiceGeneratedJob.php
  Mail/
    WelcomeEmail.php
    ServiceApprovedEmail.php
    ServiceLiveEmail.php
    ServiceRejectedEmail.php
    InvoiceEmail.php
    TicketReplyEmail.php
  Models/
    Client.php
    Service.php
    Invoice.php
    SupportTicket.php
    TicketReply.php
  Rules/
    PublicDomain.php                  ← copy from admin backend exactly
  Services/
    AdminApiService.php               ← all HTTP calls to admin backend
    StripeService.php
  Providers/
    AppServiceProvider.php            ← Sanctum cookie hook
```

---

## Step 9 — Admin Backend Read Endpoint (build on admin backend)

To allow the client portal `PollServiceStatusJob` to check service status before the webhook push is implemented, add to the admin backend:

```
GET /api/internal/services/{external_id}
X-Internal-Secret: <secret>
```

Returns:
```json
{
  "external_id": "019e3abc-...",
  "status": "active",
  "url": "https://myblog.com",
  "failed_reason": null,
  "provisioned_at": "2026-06-17T10:00:00Z"
}
```

---

## Build Order

| Step | What | Depends on |
|------|------|------------|
| 1 | Docker + PostgreSQL + env (copy from admin backend) | — |
| 2 | Migrations (all tables) | 1 |
| 3 | Auth (register, login, logout, me) — cookie auth | 2 |
| 4 | CSRF + internal secret middleware | 3 |
| 5 | `AdminApiService` + client sync to admin backend | 3 |
| 6 | Services (request + list + show) | 4, 5 |
| 7 | Service status polling job | 6 |
| 8 | Inbound service-status webhook from admin | 6 |
| 9 | Invoices (list + detail) | 6 |
| 10 | Invoice sync from admin backend | 9 |
| 11 | Payment (Stripe or Paystack) | 9 |
| 12 | Support tickets | 6 |
| 13 | Email notifications | 6–12 |
| 14 | Next.js frontend | 3–13 |

---

## Next.js Frontend Notes

The client portal frontend follows the same pattern as the admin portal frontend:

- Cookie auth — `withCredentials: true` on all Axios requests (same as admin portal)
- `XSRF-TOKEN` cookie read on mount, attached as `X-XSRF-TOKEN` header on all mutating requests
- Protected routes via middleware checking for `nvh_client_token` cookie presence
- Handle `401` globally → redirect to `/login`
- Handle `403 { requires_2fa_setup: true }` is NOT needed here (no 2FA for clients)
- Status polling on service detail page every 10 seconds for `pending_approval` / `provisioning` status
- Stripe Elements or Paystack Inline on invoice pay page

**Pages:** Login · Register · Forgot Password · Dashboard · Services · Service Detail · New Service · Invoices · Invoice Detail · Tickets · Ticket Detail · Account Settings

**Key difference from admin portal:** No role/permission system — all clients have the same access, scoped to their own data only.

---

## Notifications

| Event | Recipient | Channel |
|-------|-----------|---------|
| Registration | Client | Email — welcome + verify |
| Service request received | Client | Email |
| Service approved | Client | Email — "being deployed" |
| Service live | Client | Email — "your site is live at {url}" |
| Service rejected | Client | Email — reason included |
| Invoice generated | Client | Email — due date + amount |
| Invoice overdue | Client | Email |
| Ticket reply from admin | Client | Email |
| Ticket reply from client | Admin | Email (to support inbox) |

All mail dispatched via queued `Mailable` jobs (`ShouldQueue`). Configure via `MAIL_*` env vars (Mailgun or SMTP).
