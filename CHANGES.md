# Payroll System — Changes & Roadmap

> Maintained by the development team. Update this file with every significant change.  
> Format: `[YYYY-MM-DD]` for completed items.

---

## ✅ Completed Changes

### Payroll Calculation Fixes `[2026-05-14]`
- **Bug fix** — `weekly_amount` was saved without OT for both daily and hourly employees (`AttendanceService.php`). Fixed to use full gross (base + OT).
- **Bug fix** — Trusted branch (locked attendance) used `overtime_hours` for daily employees instead of `overtime_amount` (`PayrollService.php`).
- **Bug fix** — `bank_amount` defaulted to full gross instead of `bank_transfer_fix_amount` when cash was zero, in both the no-run and trusted merge paths.
- **Bug fix** — Untrusted merge branch overwrote `bank_amount` with `gross - cash` even when cash was zero, discarding the bank fix amount.

### Recalculate from Attendance `[2026-05-14]`
- Added **"Recalculate from Attendance"** button in the draft status banner.
- Warning modal with two clear bullets before confirming. Amber/neutral design.
- New `POST /payroll/refresh-week` route and `PayrollController::refreshWeek` method.
- After recalculate: weekly totals reset from attendance, cash/bank reset to employee defaults.
- Finalized weeks are blocked from recalculation.

### Activity Log (History Panel) `[2026-05-14]`
- New history panel below the payroll table on weekly payroll page.
- Shows per-operation entries: **Recalculated**, **Payroll saved**, **Finalized**, **Created**.
- Each save/recalculate entry is **expandable** — shows per-employee before → after diff for Weekly Total, Cash, Bank.
- Junk auto-timestamp audit entries filtered out; only meaningful operations shown.
- Falls back to PayrollRun metadata when `audit_logs` has no entries yet (old runs).

### Audit Log Table `[2026-05-14]`
- Created `audit_logs` table migration (`2026_04_15_075823_create_audit_logs_table.php`).
- `Auditable` trait wrapped in try/catch — audit failure can never crash a business operation.

### Test Coverage `[2026-05-14]`
- Added 22 new unit tests (72 total, all passing).
- Tests cover: OT in weekly_amount for both employee types, auto-OT from excess hours, bank_fix as default, trusted branch overtime field, trusted branch bank default, clampCash edge cases, full merge scenarios.

### Phase 1A — Brand Color `[2026-05-14]`
- Added `--color-brand-*` palette (`#9e2a2b` deep red) to `resources/css/app.css` under Tailwind v4 `@theme`.
- Replaced all 39 `blue-*` Tailwind classes with `brand-*` across 11 blade files: layouts, auth, dashboard, employees, payroll, attendance, users, history.
- Frontend assets rebuilt (`npm run build`).

### Phase 1B — Security Config `[2026-05-14]`
- `AppServiceProvider`: force HTTPS scheme in production environment.
- `.env.example` updated with secure defaults: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=strict`, `SENTRY_SEND_DEFAULT_PII=false`.
- **Action required on production server** — update `.env` manually with these values.

### Currency Symbol `[2026-05-14]`
- Changed all `₹` (Rupee) → `€` (Euro) across attendance, dashboard, and employee modals.

### Audit Fix: `composer install` Reminder `[2026-05-14]`
- `barryvdh/laravel-dompdf` was declared in `composer.json` but not installed on production. Solution: run `composer install --no-dev --optimize-autoloader` on server after each deploy.

---

### Phase 2 — Advance / Running Balance System `[2026-05-14]`
- **Migration** `2026_05_14_171704` — added `advance_given`, `advance_recovered`, `advance_balance` to `payroll_items`.
- **Formula** — `advance_balance = max(0, prev_balance + bank_transferred - weekly_earned)`. Covers all scenarios: advance given, partial recovery, full settlement, normal week.
- **Bank > weekly allowed** — `saveWeek` now permits bank to exceed earnings (advance scenario); cash is forced to 0 when this happens.
- **Prev balance loaded** — `PayrollService::buildRowsFromAttendance` batch-queries each employee's most recent `advance_balance` from prior weeks and passes it as `prev_advance_balance`.
- **Reactive Balance column** — new "Balance" column after Weekly Bank in the payroll table. Red badge "Advance €X" when outstanding; `—` when zero. Updates live as admin adjusts the bank input.
- **`payroll.js`** — `advanceBalance(index)` reactive method. `updateFromBank` no longer caps bank at weekly (allows advance input).
- **5 new tests** — advance given, accumulation across weeks, full settlement, no advance in normal week, formula equivalence with JS.
- **77 tests total, all passing.**

## 🔄 In Progress

> Nothing in progress — Phase 2 complete, Phase 3 next.

---

## 📋 Upcoming Phases

---

### ~~Phase 2 — Advance / Running Balance System~~ ✅ Done

---

### Phase 2 — Advance / Running Balance System (archived)
> Completed 2026-05-14

Track when bank transfer exceeds weekly earnings (advance given) and allow recovery in future weeks.

**New DB columns on `payroll_items`:**
- `advance_given` — extra money transferred beyond earned this week
- `advance_recovered` — prior advance clawed back this week
- `advance_balance` — cumulative running balance snapshot

**UI:**
- New "Balance" column after Weekly Bank in payroll table
- Red badge when advance is outstanding, green when settled
- Settlement suggestion auto-populated based on next week's earnings
- Admin can do full / partial / zero recovery per employee

**Additional possibilities to consider:**
- Advance cap per employee (block transfer if balance > 2× weekly rate)
- Auto-recovery toggle per employee
- Balance line on PDF payslip
- Dashboard alert when balance exceeds threshold
- Monthly balance statement export

---

### Phase 3 — Authentication & Password Security
> Estimated: 3–4 days

- [ ] Strong password policy: `Password::min(8)->mixedCase()->numbers()->symbols()` in `StoreUserRequest`
- [ ] Password reset flow (forgot password email → reset link)
- [ ] **IDOR fix** on `PayslipController` — currently only checks "can view any run", not "can view this specific employee"
- [ ] Account lockout after 5 failed login attempts (per account, not just per IP)
- [ ] Session invalidation when user's role changes

---

### Phase 4 — Audit & Security Headers
> Estimated: 2–3 days

- [ ] Security headers middleware: `X-Frame-Options`, `Content-Security-Policy`, `X-Content-Type-Options`, `Referrer-Policy`
- [ ] Log payslip views: who viewed which employee's payslip, when, from what IP
- [ ] Log CSV/XLSX exports in audit trail
- [ ] Log password changes in audit trail
- [ ] Log failed login attempts in audit trail

---

### Phase 5 — Data Protection
> Estimated: 2–3 days

- [ ] Encrypt employee bank details (`bank_account`, `bank_ifsc`, `bank_name`) using Laravel encrypted cast
- [ ] Redact sensitive fields from audit log `old_values`/`new_values`
- [ ] Use `Str::slug()` for employee names in payslip filenames (prevent path traversal)
- [ ] Rate limiting on password change route and user management routes

---

### Phase 6 — Medium-Term Hardening
> Target: Month 2

- [ ] Two-Factor Authentication (TOTP) — high priority for a payroll system
- [ ] Pagination on employee rate history endpoint (currently returns full history)
- [ ] `SESSION_SAME_SITE=strict`
- [ ] `composer audit` added to deploy script (catches known CVEs)
- [ ] Remove unused `remember_token` column or implement properly
- [ ] GDPR: define data retention policy for `audit_logs` table

---

## 🚀 Deploy Checklist

Run these after every `git pull` on production:

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan view:clear
npm run build   # if frontend assets changed
```

---

## 🔐 Production `.env` Security Checklist

```
APP_DEBUG=false
APP_ENV=production
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SENTRY_SEND_DEFAULT_PII=false
BCRYPT_ROUNDS=12
```
