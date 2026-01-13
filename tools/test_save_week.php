<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Employee;
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

$addons = $item->addons ?? [];
$addonDate = null;
if (is_array($addons) && count($addons) > 0) {
    $addonDate = $addons[0]['date'] ?? null;
}

$saveData = [
    'year' => $year,
    'week' => $week,
    'items' => [
        [
            'employee_id' => $emp->id,
            'type' => $item->type,
            'total_days' => $item->total_days,
            'present_days' => $item->present_days,
            'total_hours' => $item->total_hours,
            'overtime' => $item->overtime_amount ?? $item->overtime_hours,
            'gross' => $item->gross_amount,
            'cash' => 5000,
            'bank' => 0,
            'weekly_amount' => $item->weekly_amount,
            'addons' => $addons,
            'addons_selected_dates' => json_encode($addonDate ? [$addonDate] : []),
        ]
    ],
];

$controller = $app->make(App\Http\Controllers\PayrollController::class);
$controller->saveWeek(new Request($saveData));
echo "saveWeek called\n";

$item = PayrollItem::where('payroll_run_id', $run->id)->where('employee_id', $emp->id)->first();
print_r($item->toArray());
