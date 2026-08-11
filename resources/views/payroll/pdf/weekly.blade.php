<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Weekly Payroll — Week {{ $week }}/{{ $year }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #334155;
            background: #fff;
            line-height: 1.4;
        }
    </style>
</head>
<body>
<div style="padding: 28px 32px;">

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- HEADER --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 16px;">
        <tr>
            <td width="60%" style="vertical-align: bottom;">
                <div style="font-size: 18px; font-weight: bold; color: #0f172a; letter-spacing: -0.5px;">Payroll System</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 4px;">Weekly Payroll Register — Week {{ $week }} / {{ $year }}</div>
            </td>
            <td width="40%" style="vertical-align: top; text-align: right;">
                @if ($run->status === 'final')
                    <span style="background: #ecfdf5; color: #059669; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 4px 9px; border-radius: 3px;">&#10003; Finalized</span>
                @else
                    <span style="background: #fffbeb; color: #d97706; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; padding: 4px 9px; border-radius: 3px;">&#9679; Draft</span>
                @endif
                <div style="margin-top: 6px; font-size: 8px; color: #94a3b8;">Generated {{ $generatedAt->format('d M Y, H:i') }}</div>
            </td>
        </tr>
    </table>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- PAYROLL TABLE --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; table-layout: fixed;">
        <thead>
            <tr style="background: #0f172a;">
                <th style="width: 9%; padding: 7px 6px; text-align: left; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Code</th>
                <th style="width: 18%; padding: 7px 6px; text-align: left; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Name</th>
                <th style="width: 9%; padding: 7px 6px; text-align: center; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Type</th>
                <th style="width: 18%; padding: 7px 6px; text-align: center; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Attendance</th>
                <th style="width: 12%; padding: 7px 6px; text-align: right; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Weekly Total</th>
                <th style="width: 11%; padding: 7px 6px; text-align: right; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Cash</th>
                <th style="width: 11%; padding: 7px 6px; text-align: right; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Bank</th>
                <th style="width: 12%; padding: 7px 6px; text-align: right; font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #fff; border: 1px solid #0f172a;">Arrears</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                @php $emp = $row['employee']; @endphp
                <tr style="background: {{ $i % 2 === 0 ? '#ffffff' : '#f8fafc' }};">
                    <td style="padding: 6px; border: 1px solid #e2e8f0; font-family: 'DejaVu Sans Mono', monospace;">{{ $emp->employee_code }}</td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">{{ $emp->name }}</td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: center;">
                        {{ $row['type'] === 'daily_rate' ? 'Daily' : 'Hourly' }}
                    </td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: center;">
                        @if ($row['type'] === 'daily_rate')
                            {{ $row['present_days'] }}/{{ $row['total_days'] }} days
                            @if (! empty($row['overtime_amount']))
                                <br><span style="color: #7c3aed;">+ {{ number_format($row['overtime_amount'], 2) }} OT</span>
                            @endif
                        @else
                            {{ number_format($row['total_hours'] ?? 0, 2) }} hrs
                            @if (! empty($row['overtime_hours']))
                                <br><span style="color: #7c3aed;">+ {{ number_format($row['overtime_hours'], 2) }} OT</span>
                            @endif
                        @endif
                    </td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: right;">{{ number_format($row['weekly_amount'] ?? 0, 2) }}</td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: right;">{{ number_format($row['cash_amount'] ?? 0, 2) }}</td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: right;">{{ number_format($row['bank_amount'] ?? 0, 2) }}</td>
                    <td style="padding: 6px; border: 1px solid #e2e8f0; text-align: right; color: {{ ($row['arrears'] ?? 0) > 0 ? '#dc2626' : '#94a3b8' }};">
                        {{ number_format($row['arrears'] ?? 0, 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="padding: 14px; border: 1px solid #e2e8f0; text-align: center; color: #94a3b8;">
                        No attendance found for this week.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr style="background: #f1f5f9;">
                    <td colspan="4" style="padding: 8px 6px; border: 1px solid #cbd5e1; font-weight: bold; text-align: right; color: #0f172a; font-size: 9.5px;">Totals</td>
                    <td style="padding: 8px 6px; border: 1px solid #cbd5e1; font-weight: bold; text-align: right; color: #0f172a; font-size: 9.5px;">{{ number_format($totals['weekly'], 2) }}</td>
                    <td style="padding: 8px 6px; border: 1px solid #cbd5e1; font-weight: bold; text-align: right; color: #0f172a; font-size: 9.5px;">{{ number_format($totals['cash'], 2) }}</td>
                    <td style="padding: 8px 6px; border: 1px solid #cbd5e1; font-weight: bold; text-align: right; color: #0f172a; font-size: 9.5px;">{{ number_format($totals['bank'], 2) }}</td>
                    <td style="padding: 8px 6px; border: 1px solid #cbd5e1; font-weight: bold; text-align: right; color: #0f172a; font-size: 9.5px;">{{ number_format($totals['arrears'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div style="text-align: center; margin-top: 18px; font-size: 8px; color: #94a3b8;">
        This is a system-generated payroll register.
    </div>
</div>
</body>
</html>
