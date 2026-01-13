<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
use App\Http\Controllers\AttendanceController;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use Illuminate\Http\Request;

$emp = Employee::where('is_active', true)->first();
if (! $emp) {
    echo "no employee found\n";
    exit;
}

$year = (int) 2026;
$week = (int) 2;

$data = [
    'year' => $year,
    'week' => $week,
    'attendance' => [
        $emp->id => [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 10, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ],
    ],
];

$controller = $app->make(AttendanceController::class);
$controller->storeCombined(new Request($data));
echo "attendance saved\n";

$run = PayrollRun::where('year', $year)->where('week_number', $week)->first();
if (! $run) {
    echo "no payroll run\n";
    exit;
}

$item = PayrollItem::where('payroll_run_id', $run->id)->where('employee_id', $emp->id)->first();
if (! $item) {
    echo "no payroll item\n";
    exit;
}

print_r($item->toArray());