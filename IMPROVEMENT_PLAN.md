# Payroll System — Improvement Plan & MVP Audit

> **Synced with:** `PROJECT_DOCS.md` (audited April 2026)  
> **Scope:** Full audit of all controllers, models, migrations, blade views, routes, middleware, and frontend code.

---

## Quick Summary

| Area | Status |
|---|---|
| Core payroll calculation | ✅ MVP Ready |
| Weekly attendance (daily rate) | ✅ MVP Ready |
| Weekly attendance (hourly) | ✅ MVP Ready |
| Employee CRUD + rate history | ✅ MVP Ready |
| Weekly payroll sheet + XLSX export | ✅ MVP Ready |
| Monthly payroll aggregation | ✅ MVP Ready |
| Monthly XLSX export | ✅ MVP Ready |
| Role-based access | ✅ MVP Ready |
| Dashboard stats | ✅ MVP Ready |
| Auth (login/logout) | ✅ MVP Ready |
| User management (admin) | ❌ Missing |
| Payroll finalization / locking | ⚠️ Partial |
| Currency symbol (hardcoded €) | ❌ Bug |
| Search / filter employees | ❌ Missing |
| Validation feedback in modals | ⚠️ Partial |
| Mobile responsiveness | ⚠️ Partial |
| Production environment config | ❌ Not done |

---

## Section 1 — Bugs Found

### 🐛 BUG-01: Currency Symbol Hardcoded as `€` (Euro)
**Files:** `resources/views/employees/index.blade.php` — lines 279, 297, 487, 501

The employee create/edit forms show `€` as the currency prefix in rate fields. Almost certainly wrong.

**Fix:** Replace with `₹` or a config:
```php
// config/payroll.php (new file)
return ['currency_symbol' => env('PAYROLL_CURRENCY', '₹')];
```
Then in blade: `{{ config('payroll.currency_symbol') }}`

---

### 🐛 BUG-02: `saveWeek` Validates `week` With `max:52` But Some Years Have 53 Weeks
**File:** `app/Http/Controllers/PayrollController.php` — line 336
```php
'week' => 'required|integer|min:1|max:52', // ← wrong
```
The `index()` correctly uses `Carbon::create($year, 12, 28)->isoWeek()` to detect 53-week years, but `saveWeek()` hardcodes `max:52`. Week 53 will fail validation on long years.

**Fix:** Change to `max:53`

---

### 🐛 BUG-03: Route Name `exportWeekCsv` Actually Generates XLSX
**File:** `routes/web.php` — line 55, `PayrollController.php` — line 425

The route is named `payroll.exportWeekCsv` and the method is `exportWeekCsv` but both generate an **XLSX** file via PhpSpreadsheet. This naming mismatch is a developer trap.

**Fix:** Rename to `exportWeekXlsx` / `payroll.exportWeekXlsx` and update all Blade `route()` calls.

---

### 🐛 BUG-04: `bank_transfer_fix_amount` Is Required in UI But Nullable in DB
**File:** `resources/views/employees/index.blade.php` — lines 324, 536

Both modals mark it as required (`*`) but the migration and validator use `nullable`. Inconsistent.

**Fix:** Either add `'bank_transfer_fix_amount' => 'required|numeric'` to controller validation, or remove the `*` from the UI.

---

### 🐛 BUG-05: Dead Script in `employees/index.blade.php`
**File:** Lines 668–687

`updateRateFields(e)` is a legacy vanilla JS function that manipulates DOM elements no longer in the current Alpine.js-driven form. Already commented as "no longer required."

**Fix:** Delete lines 668–687 entirely.

---

### 🐛 BUG-06: `$att` Variable Could Be Undefined in `exportWeekCsv` Daily Branch
**File:** `app/Http/Controllers/PayrollController.php` — line 677

`$att` is set inside `if ($item->type === 'daily_rate')` block but referenced further down via `$att->overtime_map`. If no `DailyRateAttendance` record exists for that employee, `$att` is null and `$att->overtime_map` throws a PHP fatal error.

**Fix:** Add null guard:
```php
if ($isIn && $att && is_array($att->overtime_map) && isset($att->overtime_map[$key]) ...)
```
This check exists in code but verify explicitly that `$att` is initialized as `null` before the if block.

---

## Section 2 — Missing Features

### 🔴 MISSING-01: No User Management UI
**Priority:** HIGH — MVP Blocker for multi-user teams

Currently there's no way to create/edit/delete users from the browser. Must use `php artisan tinker`.

**What's needed:**
- `UsersController` with index, store, update, destroy
- Admin-only routes: `GET/POST /users`, `PUT /users/{id}`, `DELETE /users/{id}`
- UI page similar to employees (modal-based CRUD)
- Role selector: `admin` / `manager` / `staff`
- Password set/reset by admin

---

### 🔴 MISSING-02: Payroll Run Finalization is Wired in DB But Never Used
**Priority:** HIGH — Data integrity risk

`payroll_runs.status` is `enum('draft','final')` in the migration but **always saved as `'draft'`**. The `'final'` state is never set. No route, no UI, no guard prevents editing a finalized run.

**What's needed:**
- `POST /payroll/finalize-week?year=&week=` → set `status = 'final'`
- Blade: show "Final" badge and disable all inputs when `$run->status === 'final'`
- Same for monthly: `POST /payroll/finalize-month?month=`

---

### 🔴 MISSING-03: No Search / Filter on Employee List
**Priority:** HIGH for usability

10 employees per page with no search. Finding an employee requires paging through the list.

**Fix options:**
- Server-side: `?search=John` query param in `EmployeeController@index`
- Client-side: Alpine.js filter on the table rows
- Filter by: name, code, department, type, active/inactive

---

### 🟡 MISSING-04: Validation Errors Not Shown Inside Modals
**Priority:** MEDIUM

When a modal form fails server-side validation, the page reloads with `$errors` but the modal is closed. User sees a blank page — they don't know which field failed.

**Fix:**
```blade
{{-- Re-open modal on error --}}
<div x-data="employeesData()"
     x-init="openCreate = {{ $errors->any() && !old('_method') ? 'true' : 'false' }}">
```
Add inline error messages per field inside the modal forms.

---

### 🟡 MISSING-05: No Attendance Warning When Payroll Already Exists for the Week
**Priority:** MEDIUM

When a manager edits attendance for a past week where payroll has already been saved, there's no visual warning. They may change attendance thinking it's safe, not realizing payroll was already generated.

**Fix:** In `AttendanceController::index()`, check if a `PayrollRun` exists for the selected week and pass `$runExists` to blade. Show a yellow alert banner.

---

### 🟡 MISSING-06: No Payroll History / Run List Page
**Priority:** MEDIUM

No way to browse all past payroll runs. The only way to see past payroll is to navigate week-by-week.

**What's needed:**
- `GET /payroll/history` → paginated list of all `PayrollRun` records
- Columns: Period (Week / Month), Year, Status (Draft/Final), Gross Total, Quick Export link

---

### 🟡 MISSING-07: Bank Account Details Not Exposed in UI
**Priority:** MEDIUM

The `employees` table was created with `bank_name`, `bank_account`, `bank_ifsc` columns in mind but:
- These are **not in `Employee::$fillable`**
- They are **not shown** in any form or view
- They cannot be set from the UI at all

**Fix:** Add to `$fillable` and add to the edit employee modal (useful for bank transfer records on monthly payroll).

---

### 🟡 MISSING-08: `is_paid` on PayrollItem Is Never Set
**Priority:** MEDIUM

`payroll_items.is_paid` exists and defaults to `false` but no route or action ever sets it to `true`. There is no "mark as paid" workflow.

**What's needed:** A button on the payroll sheet to mark a row (or all rows) as paid, set `is_paid = true`, optionally populate `transfer_date`.

---

### 🟢 MISSING-09: No Password Change for Users
**Priority:** LOW

No self-service password change. Admin must use Tinker.

**Fix:** Simple `GET/POST /profile/password` form guarded by `auth`.

---

### 🟢 MISSING-10: Weekly CSV Report Excludes Hourly Employees
**File:** `PayrollController::weeklyReportCsv()` — line 807

```php
$employees = Employee::where('type', 'daily_rate')->orderBy('name')->get();
```
Hourly employees are completely excluded from the weekly attendance CSV report.

**Fix:** Include both types or add separate export options.

---

### 🟢 MISSING-11: No Forgot Password Flow
**Priority:** LOW

Standard Laravel password broker is not set up. Low priority for internal tool with admin-managed accounts.

---

## Section 3 — Code Quality Issues

### ⚠️ CODE-01: Massive Logic Duplication Across Attendance Controller
**File:** `AttendanceController.php` (651 lines)

`storeDailyRate()`, `storeHourly()`, and `storeCombined()` share ~300 lines of near-identical logic. `storeCombined` is the main path, basically copy-pastes the other two.

**Fix:** Extract private helpers:
- `normalizeDailyRow(array $row, Employee $emp, Carbon $weekStart): array`
- `normalizeHourlyRow(array $row, Employee $emp, Carbon $weekStart): array`  
- `upsertPayrollSnapshot(PayrollRun $run, Employee $emp, array $payload): void`

---

### ⚠️ CODE-02: Debug Log Left in Production `saveMonth()`
**File:** `MonthlyPayrollController.php` — line 415
```php
\Log::debug('Request Data:', $data); // ← remove this
```
Writes every payroll save (including financial data) to the log file. Will bloat logs in production.

**Fix:** Delete line 415.

---

### ⚠️ CODE-03: `weekly_active_days` Is Required in UI But Never Used in Calculations
**Files:** All controllers, `Employee.php`

The field is validated, stored in DB, and displayed — but **no calculation reads it**. All attendance logic hardcodes `total_working_days = 6`.

**Fix:** Either use it (`$employee->weekly_active_days ?? 6`) in `storeDailyRate` / `storeCombined`, or remove it from the UI and mark it as deprecated in the DB.

---

### ⚠️ CODE-04: No DB Transactions Wrapping Attendance Saves
**File:** `AttendanceController.php` — all three store methods

If a DB error occurs mid-loop (e.g., 20 employees, fails on employee 10), the first 10 are saved, the last 10 are not. Partial attendance records are worse than no records.

**Fix:**
```php
DB::transaction(function () use ($data, $year, $week, $lockWeek) {
    foreach ($data['attendance'] as $employeeId => $row) { ... }
});
```

---

### ⚠️ CODE-05: `PayrollRun::status` = `'final'` Is Dead Schema
All `updateOrCreate` calls hardcode `'status' => 'draft'`. The `'final'` enum value exists in the DB but is never used anywhere in the application. Resolve by implementing MISSING-02.

---

### ⚠️ CODE-06: Misleading "Hard Delete" Comment
**File:** `EmployeeController.php` — line 137
```php
$employee->delete(); // hard delete  ← wrong comment
```
The `Employee` model uses `SoftDeletes`, so this is a soft delete. The comment is wrong.

**Fix:** Update to `// Soft delete (deleted_at is set; record retained in DB)`

---

### ⚠️ CODE-07: N+1 Query Risk in `buildEmployeeWeekMap` (Monthly Controller)
**File:** `MonthlyPayrollController.php` — lines 87–252

For each PayrollItem in the month, a separate query fetches attendance. With 20 employees × 4 weeks = **80+ extra queries on every monthly page load**.

**Fix:** Eager-load attendance before the loop:
```php
$allDailyAtt = DailyRateAttendance::where('year', $year)
    ->whereIn('week_number', $weeks)->get()
    ->groupBy(fn($a) => $a->employee_id . '|' . $a->week_number);
```

---

### ⚠️ CODE-08: `EmployeeController@rates` Uses `optional()` Helper Unnecessarily
**File:** `EmployeeController.php` — line 164
```php
'created_by_name' => $r->created_by ? optional(User::find($r->created_by))->name : null,
```
This runs a separate `User::find()` query per rate record (another N+1). For employees with 50+ rate changes, this is 50 extra queries.

**Fix:** Pre-load users or use a join:
```php
$userNames = User::whereIn('id', $rates->pluck('created_by')->filter()->unique())->pluck('name', 'id');
// then: $userNames[$r->created_by] ?? null
```

---

## Section 4 — UX / Frontend Issues

### 🎨 UX-01: No Loading State on Form Submit Buttons
Double-clicking Save on attendance or payroll submits the form twice. No spinner or disabled state.

**Fix:** `@click="$el.disabled = true; $el.form.submit()"` or simple Alpine loading state.

---

### 🎨 UX-02: Delete Employee Has No Data Loss Warning
If an employee has payroll history and is deleted, cascade delete removes all their `payroll_items`. The `confirm()` dialog says "Delete this employee?" with no mention of data loss.

**Fix:** Stronger warning: *"Deleting will permanently remove all attendance and payroll history for this employee."*

---

### 🎨 UX-03: No Grand Total Row Visible in Payroll Sheet UI
`$totals` (gross / cash / bank) is calculated and passed to the payroll blade, but there is no visible total footer row in the HTML table — only in the exported XLSX.

**Fix:** Add a sticky `<tfoot>` row showing totals.

---

### 🎨 UX-04: Week Navigation Has No Minimum Boundary
Users can navigate backward indefinitely to years with no data. Consider disabling the "prev" button when at the first payroll run's week.

---

### 🎨 UX-05: `manager` Role Cannot View Payroll
All payroll routes require `role:admin`. Managers can enter attendance but cannot view the resulting payroll. Likely unintentional — review access requirements.

---

### 🎨 UX-06: No `404` / `403` / `500` Custom Error Pages
**File:** `resources/views/errors/` directory exists but contents unknown.

Laravel's default error pages are plain and expose framework info. Custom branded error pages improve professionalism and hide sensitive information.

---

## Section 5 — Security & Production Readiness

### 🔒 SEC-01: `APP_DEBUG=true` in `.env`
**Severity: CRITICAL for production**

Exposes stack traces, DB credentials, and env variables on any error. Must be `false` in production.

---

### 🔒 SEC-02: No CSRF Token Expiry Handling
Session expires after 120 minutes (`SESSION_LIFETIME=120`). Next form submit silently returns `419 Page Expired`. No user-friendly error is shown.

**Fix:** Add a custom `TokenMismatchException` handler in `bootstrap/app.php`:
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (TokenMismatchException $e, Request $request) {
        return back()->withErrors(['csrf' => 'Your session expired. Please try again.']);
    });
})
```

---

### 🔒 SEC-03: No Login Rate Limiting
The login POST route has no rate limiting. An attacker could brute-force credentials.

**Fix:** Add `->middleware('throttle:10,1')` to the login route (10 attempts per minute per IP).

---

### 🔒 SEC-04: Root DB User with Empty Password
`.env` uses `DB_USERNAME=root` with `DB_PASSWORD=` for production use. Should be a dedicated limited-privilege user.

---

### 🔒 SEC-05: `created_by` in EmployeeRate Could Be Spoofed
The `created_by` value comes from form data in rare cases. Always force `'created_by' => auth()->id()` in the controller — currently this is done correctly but worth confirming for any future endpoints.

---

## MVP Verdict

### ✅ Ready for Internal MVP (small, trusted team)
The **core workflow is solid and complete**:
- Full employee lifecycle (create → attendance → weekly payroll → monthly summary → export)
- Historical rate tracking via `rateAt()`
- Attendance locking with payroll trust logic
- Role-based access (admin / manager / staff)
- Polished Tailwind UI with Alpine.js modals

### ❌ Not Ready for Production
| Blocker | Fix |
|---|------|
| `APP_DEBUG=true` | Set to `false` |
| No user management UI | Build `UsersController` |
| Currency shows `€` | Replace with `₹` |
| `max:52` week validation bug | Change to `max:53` |
| No payroll finalization | Implement lock/final status |
| Root DB, no password | Create proper DB user |

---

## Prioritized Fix Queue

### 🔴 Do Now (Before Any Real Users — < 1 hour total)
| # | Task | Effort |
|---|------|--------|
| 1 | Fix `€` → `₹` currency symbol | 5 min |
| 2 | Fix `max:52` → `max:53` in `saveWeek` | 2 min |
| 3 | Remove `\Log::debug` from `saveMonth` | 1 min |
| 4 | Fix "hard delete" comment | 1 min |
| 5 | Remove dead `updateRateFields()` script | 1 min |
| 6 | Align `bank_transfer_fix_amount` required/nullable mismatch | 10 min |

### 🟡 Do This Week (Core Stability — ~8 hours)
| # | Task | Effort |
|---|------|--------|
| 7 | Build User Management UI | 2–3 hrs |
| 8 | Implement payroll finalization (lock run) | 1 hr |
| 9 | Add employee search/filter | 1 hr |
| 10 | Wrap attendance saves in `DB::transaction()` | 30 min |
| 11 | Show "payroll already exists" warning on attendance page | 30 min |
| 12 | Show validation errors inside modals | 1–2 hrs |

### 🟢 Do Later (Polish & Performance — ~8 hours)
| # | Task | Effort |
|---|------|--------|
| 13 | Add bank account fields to employee modal | 1 hr |
| 14 | Add "Mark as paid" on weekly payroll | 2 hrs |
| 15 | Refactor duplicate attendance controller logic | 2–3 hrs |
| 16 | Fix N+1 queries in monthly payroll build | 1 hr |
| 17 | Add Grand Total footer row to payroll table UI | 30 min |
| 18 | Login rate limiting + CSRF expiry handler | 30 min |
| 19 | Include hourly employees in weekly CSV report | 30 min |
| 20 | Password change UI for users | 1 hr |
