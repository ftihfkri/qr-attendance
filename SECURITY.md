# Security review & PDPA notes

This app stores personal data (name, Koperasi ID, phone, email), so it falls
under PDPA. Below is the review and the hardening applied.

## Hardening applied (in code)
- **Brute-force / spam throttling**: `POST /login` (10/min), `/register` (5/min),
  `/checkin` (30/min) via Laravel `throttle` middleware. Verified: 11th rapid
  login attempt returns HTTP 429.
- **Security headers** on every response (`SecurityHeaders` middleware):
  `X-Frame-Options: DENY` (clickjacking), `X-Content-Type-Options: nosniff`,
  `Referrer-Policy`, `X-XSS-Protection`, `Permissions-Policy`, and `HSTS` over HTTPS.
- **XSS**: all user data is escaped — Blade `{{ }}` server-side, and an `esc()`
  helper for values rendered into JS templates (attendee list, verify, users).
- **CSRF**: enabled on all state-changing routes (Laravel web middleware); AJAX
  sends the `X-CSRF-TOKEN` header. No routes are CSRF-exempt.
- **SQL injection**: all DB access uses Eloquent (parameterized). No raw queries
  with user input.
- **Auth & roles**: all `/admin/*` routes require login; user management
  (`/admin/users*`) requires the `admin` role (`role:admin` middleware) and is
  enforced server-side, not just hidden in the UI.
- **Passwords**: stored as bcrypt hashes (`Hash::make`), never returned by any API.
- **Sessions**: regenerated on login; "session expired" handled gracefully
  (popup for AJAX, friendly 419 page for forms).
- **Proxy/HTTPS**: trusts Railway's proxy so HTTPS is detected; forces HTTPS URLs
  in production.

## You MUST set these in production (Railway variables)
```
APP_ENV=production
APP_DEBUG=false            # never true in prod — leaks stack traces + data
APP_KEY=base64:...         # set a fixed key
SESSION_SECURE_COOKIE=true # cookies only over HTTPS
```
(DB_* point at the MySQL service.)

## Residual items / accepted risks
- **Duplicate-ID message**: submitting an existing Koperasi ID reveals it was
  already used (minor enumeration). Acceptable for an attendance use case.
- **Edit access**: any logged-in staff can edit any submission (by design — they
  verify/correct entries). Restrict to `admin` if you want tighter control.
- **At-rest encryption**: personal data is stored in MySQL in plaintext columns
  (standard). Rely on DB access controls + backups encryption from the host.
- **Data retention (PDPA)**: add a policy to purge old attendance after the event
  / retention period. The "Clear list" button removes a session's records.
- **Audit**: consider logging who edits/deletes records if you need accountability.

## Quick checks you can run
- `curl -I https://<app>/login` → confirm the security headers above.
- Confirm `APP_DEBUG=false` (a forced error should show a generic page, not a stack trace).
