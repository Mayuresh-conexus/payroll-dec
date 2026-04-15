# Payroll Management System — Complete Project Documentation

> **Last audited:** April 2026  
> **Environment:** XAMPP / Windows, Laravel 12, PHP 8.2, MySQL  
> **App Name:** `Payroll` (`APP_NAME=Payroll`)  
> **DB:** `payroll_dec` (MySQL, root, no password — local only)

---

## Table of Contents

1. [Technology Stack](#1-technology-stack)
2. [Project Structure Overview](#2-project-structure-overview)
3. [Database Schema](#3-database-schema)
4. [Models & Relationships](#4-models--relationships)
5. [Routes](#5-routes)
6. [Controllers — Full Logic Breakdown](#6-controllers--full-logic-breakdown)
   - [AuthController](#61-authcontroller)
   - [DashboardController](#62-dashboardcontroller)
   - [EmployeeController](#63-employeecontroller)
   - [AttendanceController](#64-attendancecontroller)
   - [PayrollController](#65-payrollcontroller)
   - [MonthlyPayrollController](#66-monthlypayrollcontroller)
7. [Middleware](#7-middleware)
8. [Blade Views — Layout & Pages](#8-blade-views--layout--pages)
9. [Key Business Logic Explained](#9-key-business-logic-explained)
10. [Frontend Stack](#10-frontend-stack)
11. [Setup & Run Instructions](#11-setup--run-instructions)
12. [Gotchas & Important Notes for Future Edits](#12-gotchas--important-notes-for-future-edits)

---

## 1. Technology Stack

| Layer            | Technology                         | Version         |
|------------------|-------------------------------------|-----------------|
| **Backend**      | Laravel Framework                   | ^12.0           |
| **Language**     | PHP                                 | ^8.2            |
| **Database**     | MySQL (via XAMPP)                   | 5.7+ / 8.x      |
| **ORM**          | Eloquent (Laravel)                  | built-in        |
| **Auth**         | Laravel built-in Auth (sessions)    | built-in        |
| **Frontend CSS** | Tailwind CSS (loaded via CDN)       | latest CDN      |
| **Frontend JS**  | Alpine.js (loaded via CDN)          | 3.x CDN         |
| **Build Tool**   | Vite + laravel-vite-plugin          | ^7.x / ^2.x     |
| **Spreadsheet**  | PhpSpreadsheet (phpoffice)          | ^5.3            |
| **Testing**      | Pest PHP                            | ^4.1            |
| **Date/Time**    | Carbon                              | built-in        |
| **Linting**      | Laravel Pint                        | ^1.24           |
| **Dev Server**   | `php artisan serve` + Vite          | —               |

> **Note:** Tailwind and Alpine.js are loaded via CDN in the `app.blade.php` layout, **not** bundled through Vite. The Vite pipeline is available but not actively used for CSS/JS in the current views. The `package.json` lists Tailwind v4 as a dev dependency (for Vite), but actual CSS in blades uses the CDN tag.

---

## 2. Project Structure Overview

```
payroll-dec/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php              (base)
│   │   │   ├── AuthController.php          (login / logout)
│   │   │   ├── DashboardController.php     (home stats)
│   │   │   ├── EmployeeController.php      (CRUD + rate history)
│   │   │   ├── AttendanceController.php    (daily & hourly attendance)
│   │   │   ├── PayrollController.php       (weekly payroll + exports)
│   │   │   └── MonthlyPayrollController.php (monthly payroll + export)
│   │   └── Middleware/
│   │       └── RoleMiddleware.php          (role-based access control)
│   └── Models/
│       ├── User.php
│       ├── Employee.php                    (SoftDeletes, rateAt())
│       ├── EmployeeRate.php                (rate history per employee)
│       ├── DailyRateAttendance.php         (weekly daily-rate attendance)
│       ├── HourlyAttendance.php            (weekly hourly attendance)
│       ├── PayrollRun.php                  (header record per week/month)
│       └── PayrollItem.php                 (one row per employee per run)
├── database/
│   └── migrations/                        (14 migration files)
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── app.blade.php              (main sidebar+header layout)
│       │   └── auth.blade.php             (centered login layout)
│       ├── auth/
│       │   └── login.blade.php
│       ├── dashboard.blade.php            (stats cards)
│       ├── employees/
│       │   └── index.blade.php            (full CRUD SPA-like page)
│       ├── attendance/
│       │   └── index.blade.php            (daily + hourly tabs)
│       └── payroll/
│           ├── index.blade.php            (weekly payroll sheet)
│           ├── monthly_index.blade.php    (monthly payroll sheet)
│           └── weekly_report.blade.php    (attendance-style report)
├── routes/
│   └── web.php                            (all HTTP routes)
├── .env                                   (local config)
├── composer.json
├── package.json
└── vite.config.js
```

---

## 3. Database Schema

### `users`
| Column       | Type         | Notes                         |
|--------------|--------------|-------------------------------|
| id           | bigint PK    |                               |
| name         | string       |                               |
| email        | string unique|                               |
| password     | string       |                               |
| role         | string       | `admin`, `manager`, `staff`   |
| timestamps   |              |                               |

### `employees`
| Column                   | Type           | Notes                                  |
|--------------------------|----------------|----------------------------------------|
| id                       | bigint PK      |                                        |
| employee_code            | string unique  |                                        |
| name                     | string         |                                        |
| joining_date             | date nullable  |                                        |
| department               | string nullable|                                        |
| type                     | enum           | `daily_rate` or `hourly` — **immutable once set** |
| daily_rate               | decimal(10,2)  | For daily_rate employees               |
| hourly_rate              | decimal(10,2)  | For hourly employees                   |
| hours_per_day            | decimal(5,2)   | Default hours for hourly employees     |
| bank_transfer_fix_amount | decimal(5,2)   | Fixed bank transfer amount             |
| weekly_active_days       | decimal(5,0)   | How many days per week they work       |
| is_active                | boolean        | Default true                           |
| deleted_at               | timestamp      | Soft delete                            |
| timestamps               |                |                                        |

### `employee_rates` (rate history)
| Column        | Type           | Notes                                       |
|---------------|----------------|---------------------------------------------|
| id            | bigint PK      |                                             |
| employee_id   | FK → employees |                                             |
| rate_type     | enum           | `daily_rate`, `hourly_rate`, `hours_per_day`|
| amount        | decimal(12,4)  |                                             |
| effective_from| date nullable  |                                             |
| effective_to  | date nullable  |                                             |
| created_by    | bigint nullable| User ID who created this entry              |
| timestamps    |                |                                             |

### `daily_rate_attendances`
| Column              | Type          | Notes                                       |
|---------------------|---------------|---------------------------------------------|
| id                  | bigint PK     |                                             |
| employee_id         | FK            |                                             |
| year                | integer       |                                             |
| week_number         | integer       | ISO week number (1–53)                      |
| total_working_days  | integer       | Default 6                                   |
| present_days        | integer       | Sum of days marked present                  |
| days_map            | json          | `{"mon":1,"tue":1,...,"sun":0}`             |
| overtime_map        | json          | `{"mon":0.0,...}` amount per day            |
| overtime_amount     | float         | Total overtime amount for the week          |
| locked              | boolean       | Prevents re-edit after lock                 |
| timestamps          |               |                                             |

### `hourly_attendances`
| Column         | Type     | Notes                                           |
|----------------|----------|-------------------------------------------------|
| id             | bigint PK|                                                 |
| employee_id    | FK       |                                                 |
| year           | integer  |                                                 |
| week_number    | integer  |                                                 |
| hours_map      | json     | `{"mon":8.0,"tue":8.0,...,"sun":0}`             |
| ot_map         | json     | `{"mon":0.0,...}` OT hours per day              |
| total_hours    | float    | Regular hours (excluding overtime)              |
| overtime_hours | float    | Total OT hours for the week                     |
| locked         | boolean  |                                                 |
| timestamps     |          |                                                 |

### `payroll_runs`
| Column       | Type         | Notes                                             |
|--------------|--------------|---------------------------------------------------|
| id           | bigint PK    |                                                   |
| year         | integer      |                                                   |
| week_number  | integer      | ISO week # for weekly; `0YYYYMM` for monthly      |
| period_type  | string       | `weekly` or `monthly`                             |
| month        | string       | `YYYY-MM` format (for monthly runs)               |
| status       | string       | `draft`, etc.                                     |
| created_by   | bigint       | User ID                                           |
| generated_at | timestamp    |                                                   |
| timestamps   |              |                                                   |

### `payroll_items`
| Column               | Type           | Notes                                          |
|----------------------|----------------|------------------------------------------------|
| id                   | bigint PK      |                                                |
| payroll_run_id       | FK → payroll_runs| Cascade delete                               |
| employee_id          | FK → employees | Cascade delete                                 |
| type                 | enum           | `daily_rate` or `hourly`                       |
| present_days         | integer nullable|                                               |
| total_days           | integer nullable|                                               |
| total_hours          | decimal(8,2)   | Regular hours (hourly only)                    |
| overtime_hours       | decimal(8,2)   | OT hours (hourly) / used for daily too         |
| overtime_amount      | decimal        | OT as currency amount (daily_rate employees)   |
| weekly_amount        | decimal        | Base pay for the week (excluding OT)           |
| gross_amount         | decimal        | weekly_amount + overtime/addons                |
| cash_amount          | decimal        | Cash portion of payment                        |
| bank_amount          | decimal        | Bank transfer portion                          |
| addons               | json           | Array of `{date, amount, cash}` — OT addons    |
| applied_daily_rate   | decimal        | Snapshot of rate used at the time              |
| applied_hourly_rate  | decimal        | Snapshot of rate used at the time              |
| applied_hours_per_day| decimal        | Snapshot of hours_per_day at the time          |
| transfer_id          | string nullable|                                                |
| transfer_date        | date nullable  |                                                |
| transfer_status      | string         | `pending`, `completed`, `failed`               |
| note                 | text nullable  |                                                |
| is_paid              | boolean        |                                                |
| timestamps           |                |                                                |
| **unique**           |                | `(payroll_run_id, employee_id)`                |

---

## 4. Models & Relationships

### `Employee`
- Uses `SoftDeletes` — hard deletes in controller currently call `$employee->delete()` (this triggers soft delete due to trait)
- Uses `HasFactory`
- **Key method:** `rateAt(DateTimeInterface $date, string $type): ?float`
  - Looks up `employee_rates` for the most recent rate of a given type effective on or before `$date`
  - Falls back to the raw column (`daily_rate` / `hourly_rate`) on the `employees` table if no rate record found
- **Booted hook:** Prevents changing the `type` field (daily ↔ hourly) via update — throws `ValidationException`
- Relationship: `hasMany(EmployeeRate::class)`

### `EmployeeRate`
- Relationship: `belongsTo(Employee::class)`
- `rate_type` enum: `daily_rate`, `hourly_rate`, `hours_per_day`
- Supports date ranges via `effective_from` / `effective_to` (nullable = open-ended)

### `DailyRateAttendance`
- Casts `days_map` and `overtime_map` as `array` (JSON stored in DB)
- `locked = true` prevents the attendance manager from re-saving the week
- Relationship: `belongsTo(Employee::class)`

### `HourlyAttendance`
- Casts `hours_map` and `ot_map` as `array`
- `total_hours` = regular hours only (OT subtracted out)
- `overtime_hours` = total OT for the week
- Relationship: `belongsTo(Employee::class)`

### `PayrollRun`
- Represents a payroll batch (weekly or monthly)
- Relationship: `hasMany(PayrollItem::class)`
- For **monthly** runs: `period_type = 'monthly'`, `month = 'YYYY-MM'`
- For **weekly** runs: `period_type = 'weekly'`, `week_number = ISO week`

### `PayrollItem`
- One record per employee per payroll run
- Casts `addons` as `array` — each addon is `['date' => 'YYYY-MM-DD', 'amount' => float, 'cash' => bool]`
- Relationships:
  - `belongsTo(Employee::class)`
  - `belongsTo(PayrollRun::class, 'payroll_run_id')` — exposed as both `run()` and `payrollRun()` alias

### `User`
- Standard Laravel user model
- Extra column: `role` (added via migration `2026_01_20_082745_add_role_to_users_table.php`)
- Roles: `admin`, `manager`, `staff`

---

## 5. Routes

All routes defined in `routes/web.php`. No API routes.

```
GET  /login                         → AuthController@showLogin          (guest only)
POST /login                         → AuthController@login              (guest only)
POST /logout                        → AuthController@logout             (auth required)

GET  /                              → DashboardController@index         (auth + any role)

── auth + role:admin,manager ────────────────────────────────────────────────────────
GET  /attendance                    → AttendanceController@index
POST /attendance/daily-rate         → AttendanceController@storeDailyRate
POST /attendance/hourly             → AttendanceController@storeHourly
POST /attendance/save               → AttendanceController@storeCombined  ← main save handler

── auth + role:admin ────────────────────────────────────────────────────────────────
GET    /employees                   → EmployeeController@index          (paginated list)
POST   /employees                   → EmployeeController@store
GET    /employees/{id}/edit         → EmployeeController@edit
PUT    /employees/{id}              → EmployeeController@update
DELETE /employees/{id}              → EmployeeController@destroy
GET    /employees/{id}/rates        → EmployeeController@rates          (AJAX JSON)

GET  /payroll                       → PayrollController@index           (weekly view)
POST /payroll/save-week             → PayrollController@saveWeek
GET  /payroll/export-week           → PayrollController@exportWeekCsv   (XLSX download)
GET  /payroll/weekly-report         → PayrollController@weeklyReport
GET  /payroll/weekly-report/csv     → PayrollController@weeklyReportCsv (CSV download)

GET  /payroll/monthly               → MonthlyPayrollController@index
POST /payroll/save-month            → MonthlyPayrollController@saveMonth
GET  /payroll/export-month          → MonthlyPayrollController@exportMonthXlsx (XLSX)
```

---

## 6. Controllers — Full Logic Breakdown

---

### 6.1 AuthController

**File:** `app/Http/Controllers/AuthController.php`

| Method       | Route             | Logic                                                                 |
|--------------|-------------------|-----------------------------------------------------------------------|
| `showLogin`  | GET /login        | If already logged in → redirect to dashboard. Else → `auth.login` view |
| `login`      | POST /login       | Validate email+password. `Auth::attempt()` with remember-me. Session regenerate. Redirect to intended URL (default: dashboard). On fail → back with error. |
| `logout`     | POST /logout      | `Auth::logout()`, invalidate session, regenerate CSRF token, redirect to login |

---

### 6.2 DashboardController

**File:** `app/Http/Controllers/DashboardController.php`

`index()` collects and passes:
- **`employeeStats`**: total, active, count by type (daily/hourly)
- **`attendanceStats`**: current week lock status for both daily and hourly; sum of present days, total working days, total hours, total OT hours
- **`payrollStats`**: latest payroll run (by year+week desc), its gross/cash/bank sum across all items

View: `dashboard`

---

### 6.3 EmployeeController

**File:** `app/Http/Controllers/EmployeeController.php`

| Method    | Logic                                                                                                      |
|-----------|------------------------------------------------------------------------------------------------------------|
| `index`   | Paginate employees (10 per page, desc by ID). Returns `employees.index` view.                             |
| `store`   | Validate new employee. Create `Employee`. Also create initial `EmployeeRate` records for daily_rate, hourly_rate, hours_per_day when provided. Effective date defaults to today unless `rate_effective_from` is posted. |
| `update`  | Validate. If any rate field has *changed* from current, write a new `EmployeeRate` row (preserving history). Still updates the `employees` table columns for UI convenience. Forces `is_active` from checkbox. The `type` field validation is locked to the existing employee type (prevents enum change). |
| `destroy` | Hard delete (soft delete triggered via `SoftDeletes` trait on model). |
| `rates`   | JSON endpoint: returns all `EmployeeRate` records for an employee, ordered newest first, with creator name. Used by AJAX in the employee list to show rate history modal. |

---

### 6.4 AttendanceController

**File:** `app/Http/Controllers/AttendanceController.php`

#### `index(Request $request)`
Query params: `year`, `week`, `tab` (daily|hourly)

- Loads active `daily_rate` and `hourly` employees separately
- Loads existing `DailyRateAttendance` and `HourlyAttendance` for the selected week, keyed by `employee_id`
- Computes ISO week dates (Mon→Sun) for column headers
- Determines `todayKey` (mon..sun) to highlight today's column when viewing current week
- Checks lock status for both daily and hourly tables
- Loads previous week attendance for "copy from previous" feature
- View: `attendance.index`

#### `storeDailyRate(Request $request)` — POST /attendance/daily-rate
- Validates: year, week, lock_week, attendance array with days (0/1) and overtime_map (per-day amounts)
- For each employee:
  - Builds normalized `days_map` (mon..sun: 0 or 1)
  - Sums `present_days`
  - Builds `overtime_map` per day and `overtime_amount` total
  - `DailyRateAttendance::updateOrCreate()` — upserts by employee+year+week
  - Also creates/updates a `PayrollItem` snapshot: `weekly_amount = present_days × daily_rate`, `addons` = per-day OT entries, `gross = weekly_amount + addons_total`
  - Ensures a `PayrollRun` exists for the week (creates draft if not)
- After loop: locks/unlocks all daily attendance for the week if `lock_week=1`
- Redirect back to attendance.index with `tab=daily`

#### `storeHourly(Request $request)` — POST /attendance/hourly
- Validates: year, week, lock_week, attendance array with hours_map and ot_map per employee
- For each employee:
  - Normalizes hours: if present flag not given, derives from hours > 0
  - Uses employee's `hours_per_day` as default when present but no hours entered
  - Computes `extraOt` = hours beyond default hours_per_day
  - `regularHours = total - otTotal`
  - `HourlyAttendance::updateOrCreate()` — upserts by employee+year+week
  - Creates `PayrollItem` snapshot: `weekly_amount = regularHours × hourly_rate`, `addons` = OT hours × rate per day
- After loop: locks/unlocks all hourly attendance for the week
- Redirect back to attendance.index with `tab=hourly`

#### `storeCombined(Request $request)` — POST /attendance/save *(primary save handler)*
- Combines both daily and hourly logic in a single request
- Iterates over all submitted employee rows, determines employee type, and routes to the correct calculation logic (same as above for each type)
- Locks/unlocks both daily and hourly tables at the end
- **This is the main form submission route** — the attendance blade sends all employees in one POST

---

### 6.5 PayrollController

**File:** `app/Http/Controllers/PayrollController.php`

#### `index(Request $request)` — GET /payroll

Core payroll display logic:

1. **Check lock status** for both daily and hourly attendance for the selected week
2. **Load PayrollRun** (if exists) for the week with all items + employees
3. **`buildRowsFromAttendance(year, week)`** — always build fresh rows from attendance:
   - For each daily attendance: calls `employee->rateAt($weekStart, 'daily_rate')` for historical rate, computes `gross = present_days × daily_rate + overtime_amount`
   - For each hourly attendance: gross = `(hours × rate) + (ot × rate)`
   - Returns keyed collection by `employee_id|type`
4. **Merge payroll run items** over attendance rows:
   - If attendance is **locked** for that type → trust payroll run values entirely (locked attendance ensures payroll finality)
   - If attendance is **not locked** → use fresh gross from attendance, but carry over `cash_amount`, `weekly_amount`, `addons` from any existing payroll item
   - If no payroll run at all → set bank = gross − cash (cash defaults to 0)
5. **Totals**: sum of gross, cash, bank across all rows
6. View: `payroll.index` with year, week, month, run, rows, totals, weeksInYear

#### `buildRowsFromAttendance(int $year, int $week)` — protected helper

Builds the raw row array from raw attendance data without layering payroll overrides. Called internally by `index()`.

#### `saveWeek(Request $request)` — POST /payroll/save-week

Saves the payroll sheet edited in the browser:
1. Maps `employee` object to `employee_id` if needed (handles both object and ID)
2. Validates all item fields
3. `PayrollRun::updateOrCreate()` for the week
4. For each item: clamps bank so `cash + bank ≤ weekly_amount`, stores `PayrollItem`
5. Snapshots applied rates using `rateAt()` at week start
6. Redirects back to weekly payroll view

#### `exportWeekCsv(Request $request)` — GET /payroll/export-week

Generates and streams an **XLSX** (Excel) file (named `payroll_week_{week}_{year}.xlsx`):

**Sheet structure:**
- Row 1: `WEEK {n} - {year}` merged header (dark navy background, gold text)
- Row 2: Day dates (Mon date → Sun date) above columns D–J
- Row 3–4: Column headers merged (Employee Code, Name, Type, Mon–Sun, Present Days, Total Hours, Overtime, Weekly Total, Cash, Bank)
  - Black background for employee columns, gray for day columns, dark green for financial columns
- Row 5+: One row per payroll item
  - Daily employees: Mon–Sat show IN/OFF (red for OFF), Sun shows CLOSED (dark blue)
  - Hourly employees: Mon–Sun show hours worked (e.g., `7.5 + 1 OT`)
  - Overtime column: currency amount for daily, hours × rate for hourly
  - All columns auto-sized, thin borders applied to entire range

#### `weeklyReport(Request $request)` — GET /payroll/weekly-report

Attendance-style view showing employee attendance for a week with payroll data overlay. View: `payroll.weekly_report`.

#### `weeklyReportCsv(Request $request)` — GET /payroll/weekly-report/csv

Streams a **CSV** for daily-rate employees only:
- Columns: Employee Code, Name, Department, Year, Week, Mon–Sun (IN/OFF), Present Days, Absent Days

---

### 6.6 MonthlyPayrollController

**File:** `app/Http/Controllers/MonthlyPayrollController.php`

#### `index(Request $request)` — GET /payroll/monthly

1. Calls `buildMonthRows($month)` to aggregate weekly payroll data into a monthly summary
2. Calculates totals
3. View: `payroll.monthly_index`

#### `getWeeksForMonth(string $month): array` — protected helper

Finds all weekly PayrollRuns whose ISO week overlaps with the given month. Returns sorted array of week numbers.

#### `buildEmployeeWeekMap(string $month): array` — protected helper

Core aggregation logic:
- For each PayrollItem in the relevant weeks, fetches attendance data (daily or hourly) to determine per-day presence
- Computes `presentInMonth` (days that actually fall in the requested month − handles weeks that span month boundaries)
- **Prorates** weekly amounts by `factor = presentInMonth / totalPresentInWeek`
- Allocates addons that fall within the month
- Returns a nested map: `[employee_id|type][week_number] => {gross, cash, bank, weekly, addon_total, addon_cash_total, addons}`

#### `buildMonthRows(string $month)` — protected helper

Converts the week map into a flat row per employee:
- Prorates `cash_amount` by the proportion of days in the current month
- Merges saved monthly run values (transfer_id, transfer_date, transfer_status, note) if a monthly run already exists
- Returns a `Collection` of row arrays

#### `saveMonth(Request $request)` — POST /payroll/save-month

- `PayrollRun::updateOrCreate()` with `period_type = 'monthly'` and `month = 'YYYY-MM'`
- Saves one `PayrollItem` per submitted employee (gross, cash, bank, overtime, transfer metadata)
- Redirects to monthly index

#### `exportMonthXlsx(Request $request)` — GET /payroll/export-month

Streams an XLSX file (`payroll_month_{YYYY-MM}.xlsx`):
- Dark navy title row, slate header row with auto-filter and freeze pane at row 4
- Columns: Employee Code, Name, Type, Weeks, Gross, Cash, Bank, Note, Transfer ID, Transfer Date, Transfer Status
- Zebra striping (alternating row fill)
- Currency format on Gross/Cash/Bank columns
- Conditional formatting on Transfer Status column:
  - `pending` → amber background
  - `completed` → green background
  - `failed` → red background
- Transfer date stored as Excel date serial

---

## 7. Middleware

### `RoleMiddleware` — `App\Http\Middleware\RoleMiddleware`

**File:** `app/Http/Middleware/RoleMiddleware.php`

Usage in routes: `role:admin,manager` (accepts variadic roles)

Logic:
1. If user is not authenticated → `abort(403)`
2. If `Auth::user()->role` is not in the allowed roles list → `abort(403)`
3. Otherwise → pass request through

Registered in `bootstrap/app.php` as `role` alias.

---

## 8. Blade Views — Layout & Pages

### Layout: `layouts/app.blade.php`

A full-page sidebar + header shell. Dependencies loaded via CDN:
- **Tailwind CSS** (`cdn.tailwindcss.com`)
- **Alpine.js** (`unpkg.com/alpinejs@3.x.x`)

Structure:
- `x-data="{ sidebarCollapsed: false }"` — Alpine state for sidebar toggle
- **Sidebar** (dark `slate-900`): collapsible from `w-64` to `w-20`
  - Dashboard link (all roles)
  - Employees link (admin only — checked with `auth()->user()->role`)
  - Attendance link (always visible for logged in)
  - Payroll submenu with Alpine `open` state (admin only):
    - Weekly Payroll
    - Monthly Payroll
- **Header**: hamburger toggle, page title (`@yield('page_title')`), user avatar with initial, logout form
- **Main**: `@yield('content')`, with `session('success')` flash message banner
- Custom tooltip CSS via `[data-tooltip]` attribute
- `[x-cloak]` hidden until Alpine initializes

### Layout: `layouts/auth.blade.php`

Centered full-screen login layout with no sidebar.

### `auth/login.blade.php`

Simple email/password login form with remember-me checkbox.

### `dashboard.blade.php`

Displays stat cards using data from `DashboardController`:
- Employee stats (total, active, daily count, hourly count)
- Current week attendance lock status, present days, hours
- Latest payroll run totals (gross, cash, bank)

### `employees/index.blade.php`

Large single-page blade (~43KB) containing:
- Employee list in paginated table
- Add employee modal
- Edit employee modal (inline form)
- Rate history modal (populated via AJAX fetch to `/employees/{id}/rates`)
- All modals managed with Alpine.js `x-show` / `x-data`

### `attendance/index.blade.php`

Large single-page blade (~28KB) containing:
- Week navigator (prev/next week, year input)
- **Tab switcher** (Daily / Hourly) using Alpine.js
- **Daily Rate tab**: Grid with employees × days (Mon–Sun). Each cell is a checkbox (IN/OFF). Overtime amount field per day. Lock week checkbox. Copy from previous week button.
- **Hourly tab**: Grid with employees × days. Each cell has hours input. Overtime hours per day. Lock week checkbox. Copy from previous week button.
- Form submits to `/attendance/save` (storeCombined)
- `todayKey` highlights today's column

### `payroll/index.blade.php`

Large single-page blade (~34KB):
- Week navigator
- Payroll table: one row per employee (code, name, type, present days, total hours, OT, weekly amount, cash, bank, addons)
- Cash and bank amounts are editable inline
- Weekly amount is editable
- Addons section (collapsible per row)
- Form submits to `/payroll/save-week`
- Export button → `/payroll/export-week?year=&week=`
- Lock indicators per row if attendance is locked

### `payroll/monthly_index.blade.php`

Monthly payroll summary view:
- Month picker
- Aggregated employee rows (prorated from weekly data)
- Transfer metadata fields (transfer ID, date, status)
- Save and Export buttons

### `payroll/weekly_report.blade.php`

Read-only attendance-style report with payroll overlay. Shows IN/OFF per day for all active employees with their weekly/cash/bank totals.

---

## 9. Key Business Logic Explained

### Employee Type Lock
Once created, an employee's `type` (daily_rate or hourly) **cannot be changed**. The `Employee` model's `booted()` hook throws a `ValidationException` if `type` is dirty on update.

### Historical Rate Lookup (`rateAt`)
Every payroll and attendance calculation uses `$employee->rateAt($weekStart, 'daily_rate')` instead of reading the current column value. This ensures historical payroll data is not affected by future rate changes. The `employee_rates` table acts as a rate changelog.

### Payroll Row Priority (Weekly)
The `PayrollController::index()` layering logic has three cases:

| Condition | Behaviour |
|---|---|
| Attendance **locked** for type | Payroll run values fully trusted (attendance frozen) |
| Attendance **not locked**, payroll run exists | Fresh gross from attendance; carry over saved cash, weekly_amount, addons |
| **No** payroll run at all | Fresh from attendance; bank = gross − cash (cash = 0) |

### Prorated Monthly Payroll
Because an ISO week can span two calendar months, the monthly controller calculates `presentInMonth / totalPresent` as a proration factor and applies it to weekly amounts. Days that fall outside the month are excluded from the month's payroll.

### Addon System
Overtime for daily-rate employees is tracked as **cash amounts per day** stored in `addons` JSON. For hourly employees addons store OT hours × hourly_rate. The addon's `cash: true` flag indicates it was paid in cash (affects the cash_amount split).

### Lock System
Setting `locked = true` on attendance records signals that attendance is finalized. The payroll controller trusts the stored payroll item values completely for locked weeks, preventing drift when attendance is no longer editable.

---

## 10. Frontend Stack

| Tool       | Version   | How loaded          | Purpose                          |
|------------|-----------|---------------------|----------------------------------|
| Tailwind   | CDN 3/4   | `<script>` CDN tag  | All utility CSS in blade files   |
| Alpine.js  | 3.x CDN   | `<script defer>` CDN| Reactivity (modals, tabs, collapse)|
| Vite       | ^7.0.7    | `npm run dev/build` | Asset pipeline (not actively used for main CSS/JS) |
| Axios      | ^1.11     | npm dev dependency  | Available but CDN approach used  |

> The `vite.config.js` sets up `laravel-vite-plugin` + `@tailwindcss/vite` but **the actual app.blade.php uses CDN links**, not `@vite()` directives. This is intentional for rapid development but should be unified if moving to production.

---

## 11. Setup & Run Instructions

### First Time Setup
```bash
cd C:\xampp\htdocs\payroll-dec

# Install PHP dependencies
composer install

# Copy env (already done — .env exists)
# Generate app key if fresh clone
php artisan key:generate

# Run all migrations
php artisan migrate

# Install Node deps (optional, for asset building)
npm install
npm run build

# Create first admin user via tinker
php artisan tinker
>>> App\Models\User::create(['name'=>'Admin','email'=>'admin@payroll.com','password'=>bcrypt('password'),'role'=>'admin'])
```

### Running Locally (XAMPP)
Since this runs on XAMPP, the project is served directly from `http://localhost/payroll-dec/public/` OR via `php artisan serve`:

```bash
php artisan serve
# Visit: http://localhost:8000
```

Or use the composer `dev` script:
```bash
composer run dev
# Starts: php artisan serve + queue listener + vite dev server concurrently
```

### Database
- Connection: MySQL on `127.0.0.1:3306`
- Database name: `payroll_dec`
- Username: `root`, Password: *(empty)*
- Session & cache stored in database tables

---

## 12. Gotchas & Important Notes for Future Edits

### ⚠️ Employee Type is Immutable
Never try to update `type` on an existing employee — the model will reject it with a validation exception. If you need to add a "change type" feature, create a new flow that migrates all attendance/payroll data.

### ⚠️ `weekly_amount` vs `gross_amount`
These two are distinct:
- `weekly_amount` = base pay only (present_days × rate)
- `gross_amount` = weekly_amount + overtime/addons
- On `saveWeek()`, the submitted `weekly_amount` is used as `gross_amount` (they're equated there)

### ⚠️ Week Spanning Month Boundary
The monthly controller handles weeks that cross month boundaries by prorating. If you add new financial fields, make sure they are also prorated using the `factor = presentInMonth / totalPresent` pattern.

### ⚠️ Addons JSON Structure
`addons` must always be an array of `['date' => 'YYYY-MM-DD', 'amount' => float, 'cash' => bool]`. The `PayrollItem` model casts this automatically, but when reading from a raw array (not model), always `json_decode($itemAddons, true) ?: []`.

### ⚠️ Lock Logic
If you add new attendance types or fields, update the lock check in `PayrollController::index()` (`$dailyLockedWeek` / `$hourlyLockedWeek`).

### ⚠️ Tailwind CDN vs Vite
CSS is loaded via CDN in `app.blade.php`. If you ever switch to Vite + `@vite('resources/css/app.css')`, remove the CDN `<script>` tag and configure `tailwind.config.js`. Don't run both simultaneously.

### ⚠️ `storeCombined` is the Primary Save
The attendance page always POSTs to `/attendance/save` (storeCombined). The older `/attendance/daily-rate` and `/attendance/hourly` routes still exist but are not the primary paths. Prefer `storeCombined` for any new attendance logic.

### ⚠️ Soft Deletes on Employee
`Employee` uses `SoftDeletes`. Queries like `Employee::all()` will automatically exclude soft-deleted records. Use `Employee::withTrashed()` to include them. The controller calls `$employee->delete()` which is a soft delete.

### ℹ️ Rate Snapshot in PayrollItem
When saving payroll items, `applied_daily_rate`, `applied_hourly_rate`, and `applied_hours_per_day` are snapshot columns that capture the rate in effect at that week's start date. This ensures historical payroll reports remain accurate even after rate changes.

### ℹ️ ISO Week vs Calendar Week
The system exclusively uses ISO week numbering (Carbon `isoWeek()`/`setISODate()`). This means:
- Week 1 starts on the Monday of the week containing January 4th
- Some years have 53 weeks (checked via `Carbon::create($year, 12, 28)->isoWeek()`)
- Week navigation in the UI accounts for 52 or 53 weeks

### ℹ️ Export Format
- Weekly export → `.xlsx` via PhpSpreadsheet (despite being named `exportWeekCsv` in the route name)
- Monthly export → `.xlsx` via PhpSpreadsheet
- Weekly attendance report → `.csv` (pure CSV, text/csv)

---

*This document was auto-generated by auditing all controllers, models, migrations, blade views, routes, and configuration files in the project.*
