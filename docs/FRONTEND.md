# NVH Client Portal — Frontend Integration Guide

This document is written for the frontend team building `npanel.newventureshosting.com`. It explains how every API endpoint works, why it works that way, and exactly how to implement each flow in the browser.

---

## Environments

| Environment | Frontend URL | Backend API URL |
|---|---|---|
| Local | `http://localhost:3000` | `http://127.0.0.1:8001` |
| Production | `https://npanel.newventureshosting.com` | `https://npanelapi.newventureshosting.com` |
| Staging | `https://npanel-staging.newventureshosting.com` | `https://npanelapi-staging.newventureshosting.com` |

All API responses are `application/json`. There are no HTML responses.

---

## Authentication Model — Read This First

The API uses **cookie-based authentication**, not token headers. This is deliberate — it prevents tokens from being accessible to JavaScript and protects against XSS.

### How it works

After a successful login or register, the server sets two cookies:

| Cookie | Readable by JS? | Purpose |
|---|---|---|
| `nvh_client_token` | **No** (HttpOnly) | The auth token — sent automatically on every request |
| `XSRF-TOKEN` | **Yes** | Must be read and echoed back as a header on every mutating request |

You never store or manage `nvh_client_token` yourself. The browser handles it automatically. But you **must** handle `XSRF-TOKEN` manually.

### Cookie lifetime

Both cookies expire after **60 minutes** of inactivity. If the user's session expires, any authenticated request will return `401`. Handle this by redirecting to `/login`.

### CSRF Protection

Every `POST`, `PUT`, `PATCH`, and `DELETE` request (except login, register, forgot-password, and reset-password) must include:

```
X-XSRF-TOKEN: <value of the XSRF-TOKEN cookie>
```

The value in the cookie is URL-encoded. You must decode it before sending it in the header. Axios does this automatically if configured correctly (see setup below). If using `fetch`, you must do it manually.

### Cross-origin credentials

Because the frontend and backend are on different subdomains, every request must include credentials. Without this, cookies are never sent and nothing works.

---

## Axios Setup (Recommended)

```js
import axios from 'axios'

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL, // https://npanelapi.newventureshosting.com
  withCredentials: true,                    // send cookies on every request
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Axios reads the XSRF-TOKEN cookie and sets X-XSRF-TOKEN automatically
// on all non-GET requests — no extra code needed.

export default api
```

Set `NEXT_PUBLIC_API_URL` in your env file per environment:

```bash
# .env.local (development)
NEXT_PUBLIC_API_URL=http://127.0.0.1:8001

# .env.staging
NEXT_PUBLIC_API_URL=https://npanelapi-staging.newventureshosting.com

# .env.production
NEXT_PUBLIC_API_URL=https://npanelapi.newventureshosting.com
```

---

## Fetch Setup (Alternative)

If you use `fetch`, you must decode and attach the CSRF token yourself:

```js
function getCookie(name) {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'))
  return match ? decodeURIComponent(match[2]) : null
}

async function apiFetch(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase()
  const mutating = !['GET', 'HEAD', 'OPTIONS'].includes(method)

  return fetch(`${process.env.NEXT_PUBLIC_API_URL}${path}`, {
    ...options,
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...(mutating ? { 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') } : {}),
      ...options.headers,
    },
  })
}
```

---

## Error Handling — Standard Response Shapes

Every error response follows one of these shapes:

**Validation error (422):**
```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field must be at least 8 characters."]
  }
}
```

**Auth error (401):** Session expired or no cookie.
```json
{ "message": "Unauthenticated." }
```

**Forbidden (403):** Authenticated but not allowed (suspended account, unverified email).
```json
{ "message": "Your email address is not verified." }
```

**Not found (404):**
```json
{ "message": "Not found." }
```

**Server error (500):** In production/staging, no stack trace is exposed.
```json
{ "message": "Server Error" }
```

**Recommended global interceptor:**
```js
api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      // Session expired — redirect to login
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)
```

---

## Authentication Flows

### 1. Register

**POST** `/api/auth/register`

Creates a new client account and logs them in immediately. A verification email is sent automatically after registration.

**Request:**
```json
{
  "name": "Alice Johnson",
  "email": "alice@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Success — 201:**
```json
{
  "client": {
    "id": "019edbda-1234-7abc-8def-000000000001",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "status": "active",
    "plan": null,
    "email_verified_at": null
  }
}
```

Sets `nvh_client_token` and `XSRF-TOKEN` cookies.

**What to do after:**
- The client is logged in but `email_verified_at` is `null`.
- Redirect to a "Check your email" page.
- Do **not** send them directly to the dashboard — protected routes require verification and will return 403.

**Errors:**
- `422` — validation failed (e.g. email already taken, passwords don't match)

---

### 2. Login

**POST** `/api/auth/login`

Rate limited to 5 attempts per minute per IP.

**Request:**
```json
{
  "email": "alice@example.com",
  "password": "password123"
}
```

**Success — 200:**
```json
{
  "client": {
    "id": "019edbda-...",
    "name": "Alice Johnson",
    "email": "alice@example.com",
    "status": "active",
    "plan": "starter",
    "email_verified_at": "2026-06-18T10:00:00Z"
  }
}
```

Sets `nvh_client_token` and `XSRF-TOKEN` cookies (60-minute lifetime).

**What to do after:**
- Check `email_verified_at`. If `null`, redirect to "Check your email" page.
- If verified, redirect to the dashboard.

**Errors:**
- `422` — wrong credentials. The error message says "These credentials do not match our records." Display it to the user.
- `403` — account suspended. Display a message: "Your account has been suspended. Contact support."
- `429` — rate limit hit. Display: "Too many attempts. Please try again later."

---

### 3. Logout

**POST** `/api/auth/logout`

Revokes the current token and clears both cookies. No request body needed.

**Headers required:** `X-XSRF-TOKEN`

**Success — 200:**
```json
{ "message": "Logged out." }
```

**What to do after:** Redirect to `/login`. Clear any client-side state (zustand store, React context, etc.).

---

### 4. Get Current User (me)

**GET** `/api/auth/me`

Use this on app load to check if the user is still logged in and hydrate your auth state.

**Success — 200:**
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

**Errors:**
- `401` — no session (not logged in). Redirect to `/login`.

**Recommended pattern (Next.js):**

```js
// In your root layout or auth provider
useEffect(() => {
  api.get('/api/auth/me')
    .then(res => setClient(res.data.client))
    .catch(() => router.push('/login'))
}, [])
```

---

### 5. Forgot Password

**POST** `/api/auth/forgot-password`

Sends a password reset email. **Always returns 200 regardless of whether the email is registered** — this is intentional to prevent email enumeration attacks. Never tell the user "that email isn't registered."

**Request:**
```json
{ "email": "alice@example.com" }
```

**Success — 200:**
```json
{ "message": "If that email is registered, a reset link has been sent." }
```

**What to show:** "If that email is registered, you'll receive a reset link shortly."

---

### 6. Reset Password

**POST** `/api/auth/reset-password`

The reset link in the email includes a `token` and `email` in the URL. Parse them from the query string and include them in this request.

**Example reset link format:**
```
https://npanel.newventureshosting.com/reset-password?token=abc123&email=alice%40example.com
```

**Request:**
```json
{
  "token": "abc123",
  "email": "alice@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Success — 200:**
```json
{ "message": "Password has been reset." }
```

**What to do after:** Redirect to `/login` with a success message.

**Errors:**
- `422` — token is invalid or expired, passwords don't match, or email not found.
  Check `errors.token` — if it contains "invalid", show "This reset link has expired. Please request a new one."

---

## Email Verification

The client must verify their email before they can access any service, invoice, or ticket endpoints. Unverified clients get **403** with `"message": "Your email address is not verified."` on those routes.

**Implementation checklist:**
- On register: redirect to `/verify-email` page telling the client to check their inbox.
- On login: if `email_verified_at` is `null`, redirect to `/verify-email`.
- On any 403 response from a protected route: check if it's a verification error and redirect accordingly.
- The verification link is sent automatically on register. You do not need a "resend" endpoint at this stage.

---

## Services

All service endpoints require:
1. The client is logged in (`nvh_client_token` cookie present)
2. Email is verified (`email_verified_at` is not null)
3. `X-XSRF-TOKEN` header on mutating requests (POST, DELETE)

### Service Status Lifecycle

A service moves through these statuses in order:

```
pending_approval → provisioning → active
                              ↓
                           failed
pending_approval → rejected
active / provisioning → terminated (by client)
active → suspended (by admin)
```

| Status | Meaning | What to show |
|---|---|---|
| `pending_approval` | Submitted, waiting for admin review | "Under review" badge |
| `provisioning` | Admin approved, being set up | "Setting up…" with spinner |
| `active` | Live and running | Green "Active" badge + show URL |
| `failed` | Provisioning failed | Red "Failed" + show `failed_reason` |
| `rejected` | Rejected by admin | Red "Rejected" + show `failed_reason` |
| `suspended` | Temporarily suspended by admin | Yellow "Suspended" |
| `terminated` | Permanently closed | Grey "Terminated" |

Terminal statuses (no further changes expected): `active`, `failed`, `rejected`, `terminated`.

---

### List Services

**GET** `/api/services`

**Success — 200:**
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
      "admin_service_id": null,
      "provisioned_at": "2026-06-18T12:00:00Z",
      "synced_at": "2026-06-18T12:01:00Z",
      "created_at": "2026-06-18T10:00:00Z",
      "updated_at": "2026-06-18T12:01:00Z"
    }
  ]
}
```

Returns an empty array `[]` if the client has no services.

---

### Get Single Service

**GET** `/api/services/{id}`

Returns 404 if the service doesn't exist **or** belongs to another client — you will never get a 403 that leaks the existence of another client's service.

**Success — 200:**
```json
{
  "service": { ...same shape as above... }
}
```

---

### Request a New Service

**POST** `/api/services`

**Headers:** `X-XSRF-TOKEN`

**Request:**
```json
{
  "name": "My Blog",
  "domain": "myblog.com",
  "type": "wordpress"
}
```

| Field | Required | Notes |
|---|---|---|
| `name` | Yes | Display name for the service |
| `domain` | Yes | Must be a real public domain. Rejects localhost, private IPs, `.local`, `.internal`, `.test`, `.example`, `.invalid` TLDs |
| `type` | No | Defaults to `wordpress` |

**Success — 201:**
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

**What happens after:** The service is submitted to the admin team for review. Status will update from `pending_approval` → `provisioning` → `active` (or `rejected`/`failed`). The client portal polls the admin backend in the background — the frontend should poll `GET /api/services/{id}` every 30–60 seconds while status is `pending_approval` or `provisioning`.

**Errors:**
- `422` — validation failed. Common: `domain` is not a valid public domain.

---

### Terminate a Service

**DELETE** `/api/services/{id}`

**Headers:** `X-XSRF-TOKEN`

Returns 404 if the service is already `terminated` or `rejected`, or belongs to another client.

**Success — 200:**
```json
{ "message": "Service terminated." }
```

**What to show:** Confirm with the user before calling this — termination is permanent. After success, update the service status in your local state to `terminated` (or re-fetch the list).

---

## Invoices

All invoice endpoints require login + email verification.

### Invoice Statuses

| Status | Meaning |
|---|---|
| `unpaid` | Due and not yet paid |
| `paid` | Payment received |
| `overdue` | Past due date, not paid |
| `void` | Cancelled — no payment needed |

### List Invoices

**GET** `/api/invoices`

Returns all invoices newest first. Empty array if none.

**Success — 200:**
```json
{
  "invoices": [
    {
      "id": "019edc01-...",
      "client_id": "019edbda-...",
      "external_id": "admin-inv-001",
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

**Display notes:**
- `amount` is a string decimal — use a currency formatter: `NGN 15,000.00`
- `due_date`, `period_start`, `period_end` are date strings (`YYYY-MM-DD`)
- `paid_at` and `synced_at` are full ISO 8601 timestamps

---

### Get Single Invoice

**GET** `/api/invoices/{id}`

Returns 404 if not found or belongs to another client.

**Success — 200:**
```json
{
  "invoice": { ...same shape as above... }
}
```

---

## Polling Pattern (Service Status)

After a client creates a service, the status changes asynchronously. The frontend should poll until a terminal state is reached.

```js
const TERMINAL_STATUSES = ['active', 'failed', 'rejected', 'terminated']
const POLL_INTERVAL_MS  = 30_000 // 30 seconds

async function pollServiceStatus(serviceId, onUpdate) {
  const res = await api.get(`/api/services/${serviceId}`)
  const service = res.data.service

  onUpdate(service)

  if (!TERMINAL_STATUSES.includes(service.status)) {
    setTimeout(() => pollServiceStatus(serviceId, onUpdate), POLL_INTERVAL_MS)
  }
}
```

Stop polling when status reaches a terminal state. Show appropriate UI:
- `active` → green badge, show `service.url`
- `failed` / `rejected` → red badge, show `service.failed_reason`
- `terminated` → grey badge, no actions

---

## Session Management

### On App Load

Always call `GET /api/auth/me` first:

```js
try {
  const { data } = await api.get('/api/auth/me')
  // Logged in — hydrate auth state
  setClient(data.client)
} catch (err) {
  if (err.response?.status === 401) {
    // Not logged in
    router.push('/login')
  }
}
```

### On 401 from Any Request

The session expired. Redirect to login immediately:

```js
api.interceptors.response.use(null, err => {
  if (err.response?.status === 401) {
    router.push('/login')
  }
  return Promise.reject(err)
})
```

### On 403 from a Protected Route

Two possible reasons:
1. `"Your email address is not verified."` — redirect to `/verify-email`
2. `"Your account has been suspended."` — show suspension message

```js
if (err.response?.status === 403) {
  const msg = err.response.data?.message ?? ''
  if (msg.includes('verified')) {
    router.push('/verify-email')
  } else {
    showSuspensionBanner()
  }
}
```

---

## Suggested Page → Endpoint Mapping

| Page | Endpoints used |
|---|---|
| `/register` | `POST /api/auth/register` |
| `/login` | `POST /api/auth/login` |
| `/verify-email` | (no API call — just UI telling user to check inbox) |
| `/forgot-password` | `POST /api/auth/forgot-password` |
| `/reset-password` | `POST /api/auth/reset-password` |
| `/dashboard` | `GET /api/auth/me`, `GET /api/services`, `GET /api/invoices` |
| `/services` | `GET /api/services` |
| `/services/new` | `POST /api/services` |
| `/services/[id]` | `GET /api/services/{id}` (+ poll while not terminal) |
| `/services/[id]/terminate` | `DELETE /api/services/{id}` |
| `/invoices` | `GET /api/invoices` |
| `/invoices/[id]` | `GET /api/invoices/{id}` |

---

## Common Mistakes to Avoid

**1. Not sending `withCredentials: true`**
Cookies are never sent to a different origin without this. Every request will get 401.

**2. Not sending `X-XSRF-TOKEN` on mutations**
POST/DELETE requests without this header get 419. Axios handles this automatically if `withCredentials` is set. Fetch does not.

**3. Sending the raw URL-encoded cookie value**
The `XSRF-TOKEN` cookie value is URL-encoded (`%2F` etc.). Decode it before putting it in the header. `decodeURIComponent(cookieValue)`. Axios does this for you.

**4. Redirecting to the dashboard before email verification**
The API will return 403 on every service/invoice/ticket route. Check `email_verified_at` after login and register.

**5. Constructing service/invoice URLs from user input**
Always use the `id` returned by the API. Never build paths like `/services/${domain}` — IDs are UUIDs and are the only safe lookup key.

**6. Not handling 429 (rate limit)**
Login and register are rate-limited to 5/min. Show a friendly message instead of a generic error.

**7. Assuming cookies persist across browser restarts**
Session cookies expire after 60 minutes. Always validate the session with `GET /api/auth/me` on app load.
