<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A public (bank) holiday, stored as an inclusive date range.
 *
 * Holidays that fall on a weekend are entered by hand on the date they are
 * actually observed — nothing here shifts a date automatically.
 */
class Holiday extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'holidays';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Day keys in the same Monday-first order the rest of the app uses.
     */
    public const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Holidays touching any part of the given inclusive date range.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from);
    }

    /**
     * The bank holidays falling inside one payroll week.
     *
     * Keyed by day so callers can ask "is Tuesday a holiday?" without repeating
     * date arithmetic. A date covered by two overlapping holidays yields a single
     * entry, which is what stops a premium being paid twice for one day.
     *
     * @return array<string, string> day key (mon..sun) => holiday name
     */
    public static function mapForWeek(\DateTimeInterface $weekStart): array
    {
        $monday = Carbon::instance($weekStart)->startOfDay();
        $sunday = $monday->copy()->addDays(6);

        $holidays = static::query()->overlapping($monday, $sunday)->orderBy('start_date')->get();

        if ($holidays->isEmpty()) {
            return [];
        }

        $map = [];

        foreach (self::DAY_KEYS as $offset => $dayKey) {
            $date = $monday->copy()->addDays($offset);

            foreach ($holidays as $holiday) {
                if ($date->betweenIncluded($holiday->start_date, $holiday->end_date)) {
                    $map[$dayKey] = $holiday->name;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * The extra pay earned for working bank holidays, and how it splits.
     *
     * Working a bank holiday pays double, so the premium is a second helping of
     * what the day already earned — one day's rate for daily staff, every hour
     * worked (overtime included) for hourly staff. Callers pass the *payable*
     * map, which is what makes an unworked holiday, or one falling after someone's
     * leaving date, worth nothing.
     *
     * Lives here rather than in either service because both AttendanceService and
     * PayrollService need the identical answer, and this codebase has been bitten
     * before by the same sum drifting apart in two places.
     *
     * @param  array<string, mixed>  $payableMap  days (0|1) or hours, keyed mon..sun
     * @param  array<string, string>  $holidayMap  from mapForWeek()
     * @return array{0: float, 1: float, 2: float, 3: float} amount, cash, bank, percent
     */
    public static function premium(array $payableMap, array $holidayMap, float $rate, float $bankPercent): array
    {
        $units = 0.0;

        foreach (array_keys($holidayMap) as $dayKey) {
            $units += (float) ($payableMap[$dayKey] ?? 0);
        }

        if ($units <= 0 || $rate <= 0) {
            return [0.0, 0.0, 0.0, $bankPercent];
        }

        $amount = round($units * $rate, 2);
        $bank = round($amount * $bankPercent / 100, 2);

        // Subtract rather than round a second time, so cash + bank always equals
        // the premium exactly and no cent is created or lost.
        $cash = round($amount - $bank, 2);

        return [$amount, $cash, $bank, $bankPercent];
    }
}
