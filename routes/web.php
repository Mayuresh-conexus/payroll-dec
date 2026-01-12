<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\AuthController;

// Login (only for guests)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

// Logout (only for logged in users)
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Protected area
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Employees CRUD
    Route::resource('employees', EmployeeController::class)->except(['show']);
    // Employee rate history (AJAX)
    Route::get('employees/{employee}/rates', [EmployeeController::class, 'rates'])->name('employees.rates');

    // Attendance Management
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/daily-rate', [AttendanceController::class, 'storeDailyRate'])->name('attendance.daily_rate.store');
    Route::post('/attendance/hourly', [AttendanceController::class, 'storeHourly'])->name('attendance.hourly.store');
    // Combined save route for merged attendance table
    Route::post('/attendance/save', [AttendanceController::class, 'storeCombined'])->name('attendance.save');

    // Payroll Management
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/save-week', [PayrollController::class, 'saveWeek'])->name('payroll.saveWeek');
    Route::get('/payroll/export-week', [PayrollController::class, 'exportWeekCsv'])->name('payroll.exportWeekCsv');

    // Monthly payroll
    Route::get('/payroll/monthly', [\App\Http\Controllers\MonthlyPayrollController::class, 'index'])->name('payroll.monthly.index');
    Route::post('/payroll/save-month', [\App\Http\Controllers\MonthlyPayrollController::class, 'saveMonth'])->name('payroll.saveMonth');
    Route::get('/payroll/export-month', [\App\Http\Controllers\MonthlyPayrollController::class, 'exportMonthXlsx'])->name('payroll.exportMonthXlsx');

    // attendance style report under payroll
    Route::get('/payroll/weekly-report', [PayrollController::class, 'weeklyReport'])->name('payroll.weekly.report');
    Route::get('/payroll/weekly-report/csv', [PayrollController::class, 'weeklyReportCsv'])->name('payroll.weekly.report.csv');
});
