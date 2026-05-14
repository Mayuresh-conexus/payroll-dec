<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;

// Login (only for guests) — SEC-04: throttled to 10 attempts/minute
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.attempt');
});

// Logout (only for logged in users)
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');


// Protected area Admin and Manager Only
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    // Attendance Management
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/daily-rate', [AttendanceController::class, 'storeDailyRate'])->name('attendance.daily_rate.store');
    Route::post('/attendance/hourly', [AttendanceController::class, 'storeHourly'])->name('attendance.hourly.store');
    // Combined save route for merged attendance table
    Route::post('/attendance/save', [AttendanceController::class, 'storeCombined'])->name('attendance.save');
});


// Protected area Admin, Manager and Staff Only
Route::middleware(['auth', 'role:admin,manager,staff'])->group(function () {
    // Dashboard
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // MISSING-09: Password change (any logged-in user can change their own password)
    Route::get('/profile/password', [\App\Http\Controllers\ProfileController::class, 'showPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');
});


// Protected area ADMIN Only
Route::middleware(['auth', 'role:admin'])->group(function () {

    // Employees CRUD
    Route::resource('employees', EmployeeController::class)->except(['show']);
    // Employee rate history (AJAX)
    Route::get('employees/{employee}/rates', [EmployeeController::class, 'rates'])->name('employees.rates');

    // MISSING-01: User Management
    Route::resource('users', UsersController::class)->except(['show']);

    // Payroll Management
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/save-week', [PayrollController::class, 'saveWeek'])->name('payroll.saveWeek');
    Route::post('/payroll/refresh-week', [PayrollController::class, 'refreshWeek'])->name('payroll.refreshWeek');
    Route::post('/payroll/finalize-week', [PayrollController::class, 'finalizeWeek'])->name('payroll.finalizeWeek'); // MISSING-02
    Route::get('/payroll/export-week', [PayrollController::class, 'exportWeekCsv'])->name('payroll.exportWeekCsv')->middleware('throttle:10,1');

    // Monthly payroll
    Route::get('/payroll/monthly', [\App\Http\Controllers\MonthlyPayrollController::class, 'index'])->name('payroll.monthly.index');
    Route::post('/payroll/save-month', [\App\Http\Controllers\MonthlyPayrollController::class, 'saveMonth'])->name('payroll.saveMonth');
    Route::post('/payroll/finalize-month', [\App\Http\Controllers\MonthlyPayrollController::class, 'finalizeMonth'])->name('payroll.finalizeMonth'); // MISSING-02
    Route::get('/payroll/export-month', [\App\Http\Controllers\MonthlyPayrollController::class, 'exportMonthXlsx'])->name('payroll.exportMonthXlsx')->middleware('throttle:10,1');

    // Attendance-style report under payroll
    Route::get('/payroll/weekly-report', [PayrollController::class, 'weeklyReport'])->name('payroll.weekly.report');
    Route::get('/payroll/weekly-report/csv', [PayrollController::class, 'weeklyReportCsv'])->name('payroll.weekly.report.csv')->middleware('throttle:10,1');

    // E1: PDF Payslips (per employee per week)
    Route::get('/payroll/{year}/{week}/payslip/{employee}', [\App\Http\Controllers\PayslipController::class, 'show'])
        ->name('payroll.payslip')
        ->middleware('throttle:20,1');
});
