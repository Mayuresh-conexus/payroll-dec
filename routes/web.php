<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ManagerAssignmentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

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
    // Employee profile (manager-scoped via EmployeePolicy@view)
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

    // Attendance Management
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/daily-rate', [AttendanceController::class, 'storeDailyRate'])->name('attendance.daily_rate.store');
    Route::post('/attendance/hourly', [AttendanceController::class, 'storeHourly'])->name('attendance.hourly.store');
    // Combined save route for merged attendance table
    Route::post('/attendance/save', [AttendanceController::class, 'storeCombined'])->name('attendance.save');
});

// Protected area Admin and Manager Only
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    // Dashboard
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Password change (any logged-in user can change their own password)
    Route::get('/profile/password', [\App\Http\Controllers\ProfileController::class, 'showPasswordForm'])->name('profile.password');
    Route::post('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');
});

// Protected area ADMIN Only
Route::middleware(['auth', 'role:admin'])->group(function () {

    // Employees CRUD
    Route::resource('employees', EmployeeController::class)->except(['show']);
    // Delete a single rate history entry (soft delete)
    Route::delete('employees/{employee}/rates/{rate}', [EmployeeController::class, 'destroyRate'])->name('employees.rates.destroy');

    // MISSING-01: User Management
    Route::resource('users', UsersController::class)->except(['show']);

    // Manager Assignment
    Route::get('/managers', [ManagerAssignmentController::class, 'index'])->name('managers.index');
    Route::post('/managers/{manager}/assign', [ManagerAssignmentController::class, 'assign'])->name('managers.assign');
    Route::delete('/managers/{manager}/employees/{employee}', [ManagerAssignmentController::class, 'unassign'])->name('managers.unassign');

    // Payroll Management
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/save-week', [PayrollController::class, 'saveWeek'])->name('payroll.saveWeek');
    Route::post('/payroll/refresh-week', [PayrollController::class, 'refreshWeek'])->name('payroll.refreshWeek');
    Route::post('/payroll/finalize-week', [PayrollController::class, 'finalizeWeek'])->name('payroll.finalizeWeek'); // MISSING-02
    Route::post('/payroll/revert-week', [PayrollController::class, 'revertWeek'])->name('payroll.revertWeek');
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

    // Database Backups
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups/run', [BackupController::class, 'run'])
        ->name('backups.run')->middleware('throttle:5,1');
    Route::get('/backups/{filename}/download', [BackupController::class, 'download'])
        ->where('filename', '[A-Za-z0-9_\-\.]+\.sql\.gz')
        ->name('backups.download')->middleware('throttle:10,1');
    Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])
        ->where('filename', '[A-Za-z0-9_\-\.]+\.sql\.gz')
        ->name('backups.restore')->middleware('throttle:3,1');
    Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])
        ->where('filename', '[A-Za-z0-9_\-\.]+\.sql\.gz')
        ->name('backups.destroy');
    Route::put('/backups/schedule', [BackupController::class, 'updateSchedule'])->name('backups.schedule.update');
});
