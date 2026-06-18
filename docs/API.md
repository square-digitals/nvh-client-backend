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
      "admin_service_id": "admin-svc-uuid",
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

## Internal (Admin Backend → Client Portal)

All `/api/internal/*` routes are protected by the `X-Internal-Secret` header. They are server-to-server only and must never be called from a browser.

**Required header:** `X-Internal-Secret: <INTERNAL_SECRET>`

---

### POST /api/internal/service-status
Receive a service status update pushed from the admin backend. Updates the local service record and triggers client notifications when status changes.

**Auth required:** Internal secret header

**Request body:**
```json
{
  "external_id": "019edc00-...",
  "status": "active",
  "url": "https://myblog.com",
  "failed_reason": null,
  "provisioned_at": "2026-06-18T12:00:00Z"
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `external_id` | string | Yes | The client portal `service.id` |
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
