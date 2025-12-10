<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;

// Dashboard
Route::view('/', 'dashboard')->name('dashboard');

// Employees CRUD
Route::resource('employees', EmployeeController::class)->except(['show']);

// Attendance Management
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
Route::post('/attendance/daily-rate', [AttendanceController::class, 'storeDailyRate'])->name('attendance.daily_rate.store');
Route::post('/attendance/hourly', [AttendanceController::class, 'storeHourly'])->name('attendance.hourly.store');


// Payroll Management
Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
Route::post('/payroll/save-week', [PayrollController::class, 'saveWeek'])->name('payroll.saveWeek');
Route::get('/payroll/export-week', [PayrollController::class, 'exportWeekCsv'])->name('payroll.exportWeekCsv');

// attendance style report under payroll
Route::get('/payroll/weekly-report', [PayrollController::class, 'weeklyReport'])->name('payroll.weekly.report');
Route::get('/payroll/weekly-report/csv', [PayrollController::class, 'weeklyReportCsv'])->name('payroll.weekly.report.csv');
