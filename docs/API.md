# NVH Client Portal — API Reference

**Base URL:** `https://npanelapi.newventureshosting.com`
**All responses:** `application/json`
**Auth:** Cookie-based Sanctum (`nvh_client_token` HttpOnly cookie)
**CSRF:** All mutating client routes require `X-XSRF-TOKEN` header matching the `XSRF-TOKEN` cookie

---

## Authentication

### POST /api/auth/register
Register a new client account.

**Auth required:** No
**Rate limit:** 5/min per IP

**Request body:**
```json
{
  "name": "Alice Johnson",
  "email": "alice@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response `201`:**
```json
{
  "client": {
    "id": "019edbda-...",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "status": "active",
    "plan": null,
    "email_verified_at": null
  }
}
```
Sets cookies: `nvh_client_token` (HttpOnly), `XSRF-TOKEN`

---

### POST /api/auth/login
Authenticate a client.

**Auth required:** No
**Rate limit:** 5/min per IP

**Request body:**
```json
{
  "email": "alice@example.com",
  "password": "password123"
}
```

**Response `200`:**
```json
{
  "client": {
    "id": "019edbda-...",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "status": "active",
    "plan": null,
    "email_verified_at": "2026-06-18T10:00:00Z"
  }
}
```
Sets cookies: `nvh_client_token` (HttpOnly, 60min), `XSRF-TOKEN` (60min)

**Error `403`:** Account suspended
**Error `422`:** Invalid credentials

---

### POST /api/auth/logout
Revoke the current session token and clear cookies.

**Auth required:** Yes
**Headers:** `X-XSRF-TOKEN: <value>`

**Response `200`:**
```json
{ "message": "Logged out." }
```

---

### GET /api/auth/me
Return the authenticated client's profile.

**Auth required:** Yes

**Response `200`:**
```json
{
  "client": {
    "id": "019edbda-...",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "phone": null,
    "company": null,
    "status": "active",
    "plan": "starter",
    "email_verified_at": "2026-06-18T10:00:00Z"
  }
}
```

---

### POST /api/auth/forgot-password
Send a password reset link.

**Auth required:** No
**Rate limit:** 5/min per IP

**Request body:**
```json
{ "email": "alice@example.com" }
```

**Response `200`:** Always returns success to prevent email enumeration
```json
{ "message": "If that email is registered, a reset link has been sent." }
```

---

### POST /api/auth/reset-password
Reset password using a token from the reset email.

**Auth required:** No
**Rate limit:** 5/min per IP

**Request body:**
```json
{
  "token": "<reset-token>",
  "email": "alice@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response `200`:**
```json
{ "message": "Password has been reset." }
```

---

## Services

All service routes require email verification (`verified` middleware).

### GET /api/services
List all services belonging to the authenticated client.

**Auth required:** Yes

**Response `200`:**
```json
{
  "services": [
    {
      "id": "019edc00-...",
      "client_id": "019edbda-...",
      "type": "wordpress",
      "name": "My Blog",
      "domain": "myblog.com",
      "status": "active",
      "url": "https://myblog.com",
      "failed_reason": null,
      "provisioned_at": "2026-06-18T12:00:00Z",
      "synced_at": "2026-06-18T12:01:00Z",
      "created_at": "2026-06-18T10:00:00Z",
      "updated_at": "2026-06-18T12:01:00Z"
    }
  ]
}
```

---

### POST /api/services
Request a new hosting service.

**Auth required:** Yes
**Headers:** `X-XSRF-TOKEN: <value>`

**Request body:**
```json
{
  "name": "My Blog",
  "domain": "myblog.com",
  "type": "wordpress"
}
```
`type` is optional and defaults to `wordpress`. `domain` must be a public FQDN (no private IPs, localhost, or internal TLDs).

**Response `201`:**
```json
{
  "service": {
    "id": "019edc00-...",
    "type": "wordpress",
    "name": "My Blog",
    "domain": "myblog.com",
    "status": "pending_approval",
    "created_at": "2026-06-18T10:00:00Z"
  }
}
```
Dispatches `SyncServiceToAdminJob` and `PollServiceStatusJob` (2-min delay) on the `sync` queue.

---

### GET /api/services/{service}
Get a single service. Scoped to the authenticated client — returns 404 for other clients' services.

**Auth required:** Yes

**Response `200`:**
```json
{
  "service": {
    "id": "019edc00-...",
    "type": "wordpress",
    "name": "My Blog",
    "domain": "myblog.com",
    "status": "active",
    "url": "https://myblog.com",
    "failed_reason": null,
    "provisioned_at": "2026-06-18T12:00:00Z"
  }
}
```

---

### DELETE /api/services/{service}
Terminate a service. Scoped to the authenticated client.

**Auth required:** Yes
**Headers:** `X-XSRF-TOKEN: <value>`

Returns 404 if the service is already `terminated` or `rejected`.

**Response `200`:**
```json
{ "message": "Service terminated." }
```
Dispatches `SyncServiceToAdminJob` on the `sync` queue.

---

## Invoices

All invoice routes require email verification (`verified` middleware).

### GET /api/invoices
List all invoices belonging to the authenticated client, newest first.

**Auth required:** Yes

**Response `200`:**
```json
{
  "invoices": [
    {
      "id": "019edc01-...",
      "client_id": "019edbda-...",
      "amount": "15000.00",
      "currency": "NGN",
      "status": "unpaid",
      "due_date": "2026-07-18",
      "paid_at": null,
      "period_start": "2026-06-18",
      "period_end": "2026-07-18",
      "synced_at": "2026-06-18T10:00:00Z",
      "created_at": "2026-06-18T10:00:00Z",
      "updated_at": "2026-06-18T10:00:00Z"
    }
  ]
}
```

---

### GET /api/invoices/{invoice}
Get a single invoice. Scoped to the authenticated client — returns 404 for other clients' invoices.

**Auth required:** Yes

**Response `200`:**
```json
{
  "invoice": {
    "id": "019edc01-...",
    "client_id": "019edbda-...",
    "amount": "15000.00",
    "currency": "NGN",
    "status": "paid",
    "due_date": "2026-07-18",
    "paid_at": "2026-06-20T09:00:00Z",
    "period_start": "2026-06-18",
    "period_end": "2026-07-18",
    "synced_at": "2026-06-20T09:01:00Z",
    "created_at": "2026-06-18T10:00:00Z",
    "updated_at": "2026-06-20T09:01:00Z"
  }
}
```

**Response `404`:** Invoice not found or belongs to another client

---

## Internal (Admin Backend → Client Portal)

All `/api/internal/*` routes are protected by the `X-Internal-Secret` header. They are server-to-server only and must never be called from a browser.

**Required header:** `X-Internal-Secret: <NVH_INTERNAL_SECRET>`

---

### POST /api/internal/invoices/sync
Create or update an invoice record synced from the admin backend. Keyed on `id` (the admin's invoice UUID) — safe to call multiple times with the same payload (idempotent).

**Auth required:** Internal secret header

**Request body:**
```json
{
  "client_id":    "019edbda-...",
  "id":           "admin-inv-001",
  "amount":       15000.00,
  "currency":     "NGN",
  "status":       "unpaid",
  "due_date":     "2026-07-18",
  "paid_at":      null,
  "period_start": "2026-06-18",
  "period_end":   "2026-07-18"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `client_id` | string | Yes | Client portal `client.id` (shared with admin backend) |
| `id` | string | Yes | Admin backend invoice UUID (upsert key — stored as invoice `id` here) |
| `amount` | numeric | Yes | Invoice amount (must be ≥ 0) |
| `currency` | string | Yes | 3-letter ISO code (e.g. `NGN`) — uppercased automatically |
| `status` | string | Yes | One of: `unpaid`, `paid`, `overdue`, `void` |
| `due_date` | date | Yes | Payment due date |
| `paid_at` | ISO8601\|null | No | Timestamp when payment was received |
| `period_start` | date\|null | No | Billing period start |
| `period_end` | date\|null | No | Billing period end |

**Response `200`:**
```json
{
  "invoice": {
    "id": "admin-inv-001",
    "client_id": "019edbda-...",
    "amount": "15000.00",
    "currency": "NGN",
    "status": "unpaid",
    "due_date": "2026-07-18",
    "paid_at": null,
    "period_start": "2026-06-18",
    "period_end": "2026-07-18",
    "synced_at": "2026-06-18T10:00:00Z",
    "created_at": "2026-06-18T10:00:00Z",
    "updated_at": "2026-06-18T10:00:00Z"
  }
}
```

**Response `404`:** Client not found
**Response `401`:** Missing or invalid `X-Internal-Secret`
**Response `422`:** Validation error

---

### POST /api/internal/invoice-issued
Receives a newly created invoice pushed from the admin backend when `POST /api/invoices` is called there. Creates the invoice in the client portal (or updates it if it already exists by `id`). The client is resolved via `client_id` (shared UUID).

**Auth required:** `X-Internal-Secret` header

**Request body:**
```json
{
  "id":         "admin-inv-uuid",
  "client_id":  "client-portal-uuid",
  "amount":     "29.99",
  "currency":   "NGN",
  "status":     "unpaid",
  "due_date":   "2026-07-01",
  "period_start": "2026-07-01",
  "period_end":   "2026-07-31"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | string | Yes | Admin backend invoice UUID — stored as the invoice `id` in the portal |
| `client_id` | string | Yes | Shared client UUID (same on both systems) |
| `amount` | numeric | Yes | Invoice amount (must be ≥ 0) |
| `currency` | string | Yes | 3-letter ISO code (e.g. `NGN`) — uppercased automatically |
| `status` | string | Yes | One of: `unpaid`, `paid`, `overdue`, `void` |
| `due_date` | date | Yes | Payment due date |
| `period_start` | date\|null | No | Billing period start |
| `period_end` | date\|null | No | Billing period end |

**Response `200`:**
```json
{
  "invoice": {
    "id": "admin-inv-uuid",
    "client_id": "client-portal-uuid",
    "amount": "29.99",
    "currency": "NGN",
    "status": "unpaid",
    "due_date": "2026-07-01",
    "paid_at": null,
    "period_start": "2026-07-01",
    "period_end": "2026-07-31",
    "synced_at": "2026-06-22T10:00:00Z",
    "created_at": "2026-06-22T10:00:00Z",
    "updated_at": "2026-06-22T10:00:00Z"
  }
}
```

**Response `404`:** Client not found (unknown `client_id`)
**Response `401`:** Missing or invalid `X-Internal-Secret`
**Response `422`:** Validation error

---

### POST /api/internal/invoice-paid
Fired by the admin backend when an invoice is marked as paid. Looks up the invoice by `id` and updates its status and `paid_at` timestamp.

**Auth required:** `X-Internal-Secret` header

**Request body:**
```json
{
  "id":     "019eef8d-b6c3-7357-bf63-f3f770f00b36",
  "status": "paid",
  "paid_at": "2026-06-22T13:45:00.000000Z"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | string | Yes | Admin backend invoice UUID — match key |
| `status` | string | Yes | One of: `unpaid`, `paid`, `overdue`, `void` |
| `paid_at` | ISO8601 | Yes | Timestamp when payment was received |

**Response `200`:**
```json
{
  "invoice": {
    "id": "019eef8d-...",
    "status": "paid",
    "paid_at": "2026-06-22T13:45:00.000000Z",
    "synced_at": "2026-06-22T13:45:01.000000Z"
  }
}
```

**Response `404`:** Invoice not found (unknown `id`)
**Response `401`:** Missing or invalid `X-Internal-Secret`
**Response `422`:** Validation error

---

### POST /api/internal/trigger-sync
Pushes all clients and services to the admin backend in bulk. Called by the admin's "Sync" button to pull everything into the admin dashboard instantly.

**Auth required:** Internal secret header

**No request body.**

**Response `200`:**
```json
{ "message": "ok" }
```

Sends two requests to the admin backend:
- `POST /api/internal/clients/sync` — all clients with `id`, `name`, `email`, `status`, `plan_slug`
- `POST /api/internal/services/sync` — all services with `id`, `client_id`, `type`, `name`, `domain`, `status`

Failures on either call are logged silently and do not affect the `200` response.

---

### POST /api/internal/client-status
Receive a client status update pushed directly from the admin backend. Updates the local client record, invalidates active sessions on suspension, and sends email notifications when status changes.

**Required headers:**
- `X-Internal-Secret: <shared secret>`
- `X-Webhook-Signature: <hmac-sha256 of raw request body using NVH_INTERNAL_SECRET>`

**Request body (suspended):**
```json
{
  "id": "uuid-of-the-client",
  "status": "suspended",
  "suspended_reason": "Non-payment of invoice #INV-0042.",
  "suspended_at": "2026-06-20T10:00:00+00:00"
}
```

**Request body (unsuspended):**
```json
{
  "id": "uuid-of-the-client",
  "status": "active",
  "suspended_reason": null,
  "suspended_at": null
}
```

**Response `200`:**
```json
{ "message": "Client status updated." }
```

**Response `401`:** Invalid or missing `X-Internal-Secret` or `X-Webhook-Signature`
**Response `404`:** Client not found
**Response `422`:** Validation error

---

### POST /api/internal/webhooks/admin *(deprecated — no longer called by admin backend)*
Previously received generic `client.suspended` / `client.unsuspended` events. Replaced by `POST /api/internal/client-status`. Kept in place but will never fire.

---

### POST /api/internal/service-status
Receive a service status update pushed from the admin backend. Updates the local service record and triggers client notifications when status changes.

**Auth required:** Internal secret header

**Request body:**
```json
{
  "id": "019edc00-...",
  "status": "active",
  "url": "https://myblog.com",
  "failed_reason": null,
  "provisioned_at": "2026-06-18T12:00:00Z"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `id` | string | Yes | The service UUID (shared between both backends) |
| `status` | string | Yes | One of: `pending_approval`, `provisioning`, `active`, `suspended`, `failed`, `rejected`, `terminated` |
| `url` | string\|null | No | Live URL once provisioned |
| `failed_reason` | string\|null | No | Human-readable failure reason |
| `provisioned_at` | ISO8601\|null | No | Timestamp when provisioning completed |

**Response `200`:**
```json
{ "message": "Service status updated." }
```

**Response `404`:** Service not found
**Response `401`:** Missing or invalid `X-Internal-Secret`
**Response `422`:** Validation error
