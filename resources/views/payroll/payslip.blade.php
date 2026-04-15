<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip — {{ $employee->name }} — W{{ $week }}/{{ $year }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5px;
            color: #334155;
            background: #fff;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div style="padding: 38px 48px;">
{{-- ══════════════════════════════════════════════════════════ --}}
{{-- HEADER --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 20px;">
    <tr>
        <td width="60%" style="vertical-align: bottom;">
            <div style="font-size: 20px; font-weight: bold; color: #0f172a; letter-spacing: -0.5px;">Payroll System</div>
            <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                Week {{ $week }} / {{ $year }} &nbsp;|&nbsp; {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
            </div>
        </td>
        <td width="40%" style="vertical-align: top; text-align: right;">
            <span style="background: #0f172a; color: #fff; font-size: 9px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; padding: 4px 10px; border-radius: 3px;">Payslip</span>
            <div style="margin-top: 6px;">
                @if ($run->status === 'final')
                    <span style="color: #059669; font-size: 9px; font-weight: bold;">&#10003; FINALIZED</span>
                @else
                    <span style="color: #d97706; font-size: 9px; font-weight: bold;">&#9679; DRAFT</span>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- EMPLOYEE + PAY PERIOD DETAILS --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 22px;">
    <tr>
        {{-- Employee Details --}}
        <td width="48%" style="vertical-align: top; padding-right: 16px;">
            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px;">Employee Details</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="38%" style="padding: 3px 0; color: #64748b; font-size: 10px;">Name</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Code</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->employee_code }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Department</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->department ?? '—' }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Type</td>
                    <td style="padding: 3px 0;">
                        @if ($employee->type === 'daily_rate')
                            <span style="background: #ecfdf5; color: #059669; font-size: 8.5px; font-weight: bold; text-transform: uppercase; padding: 2px 7px; border-radius: 3px;">Daily Rate</span>
                        @else
                            <span style="background: #eff6ff; color: #2563eb; font-size: 8.5px; font-weight: bold; text-transform: uppercase; padding: 2px 7px; border-radius: 3px;">Hourly</span>
                        @endif
                    </td>
                </tr>
                @if ($employee->joining_date)
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Joined</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->joining_date->format('d M Y') }}</td>
                </tr>
                @endif
            </table>
        </td>

        {{-- Spacer --}}
        <td width="4%">&nbsp;</td>

        {{-- Pay Period Details --}}
        <td width="48%" style="vertical-align: top; padding-left: 16px; border-left: 1px solid #e2e8f0;">
            <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px;">Pay Period</div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="40%" style="padding: 3px 0; color: #64748b; font-size: 10px;">Year / Week</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $year }} / Week {{ $week }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Period</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Payroll Run</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">#{{ $run->id }}</td>
                </tr>
                <tr>
                    <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Status</td>
                    <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ ucfirst($run->status) }}</td>
                </tr>
            </table>

            @if ($employee->bank_name || $employee->bank_account)
                <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px; margin-top: 14px;">Bank Details</div>
                <table width="100%" cellpadding="0" cellspacing="0">
                    @if ($employee->bank_name)
                    <tr>
                        <td width="40%" style="padding: 3px 0; color: #64748b; font-size: 10px;">Bank</td>
                        <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->bank_name }}</td>
                    </tr>
                    @endif
                    @if ($employee->bank_account)
                    <tr>
                        <td style="padding: 3px 0; color: #64748b; font-size: 10px;">Account</td>
                        <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->bank_account }}</td>
                    </tr>
                    @endif
                    @if ($employee->bank_ifsc)
                    <tr>
                        <td style="padding: 3px 0; color: #64748b; font-size: 10px;">IFSC</td>
                        <td style="padding: 3px 0; font-weight: bold; color: #0f172a; font-size: 10px;">{{ $employee->bank_ifsc }}</td>
                    </tr>
                    @endif
                </table>
            @endif
        </td>
    </tr>
</table>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- ATTENDANCE SUMMARY --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px;">Attendance — Week {{ $week }}</div>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 22px; table-layout: fixed;">
    <thead>
        <tr style="background: #f8fafc;">
            @if ($item->type === 'daily_rate')
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Days Present</th>
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Working Days</th>
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Daily Rate</th>
                <th width="25%" style="padding: 8px 10px; text-align: right; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">OT Amount</th>
            @else
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Hours Worked</th>
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">OT Hours</th>
                <th width="25%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Hourly Rate</th>
                <th width="25%" style="padding: 8px 10px; text-align: right; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Hrs/Day</th>
            @endif
        </tr>
    </thead>
    <tbody>
        <tr>
            @if ($item->type === 'daily_rate')
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">{{ $item->present_days ?? '—' }}</td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">{{ $item->total_days ?? '—' }}</td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">&#8377;{{ number_format($item->applied_daily_rate ?? $employee->daily_rate ?? 0, 2) }}</td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; text-align: right; color: #334155;">
                    @if ($item->overtime_amount > 0) &#8377;{{ number_format($item->overtime_amount, 2) }} @else — @endif
                </td>
            @else
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">{{ number_format($item->total_hours ?? 0, 2) }} h</td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">
                    @if (($item->overtime_hours ?? 0) > 0) {{ number_format($item->overtime_hours, 2) }} h @else — @endif
                </td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">&#8377;{{ number_format($item->applied_hourly_rate ?? $employee->hourly_rate ?? 0, 2) }}/hr</td>
                <td style="padding: 9px 10px; border: 1px solid #e2e8f0; text-align: right; color: #334155;">{{ $item->applied_hours_per_day ?? $employee->hours_per_day ?? '—' }} h</td>
            @endif
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- PAY BREAKDOWN --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px;">Pay Breakdown</div>

<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 30px; table-layout: fixed;">
    <thead>
        <tr style="background: #f8fafc;">
            <th width="70%" style="padding: 8px 10px; text-align: left; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Description</th>
            <th width="30%" style="padding: 8px 10px; text-align: right; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border: 1px solid #e2e8f0;">Amount (&#8377;)</th>
        </tr>
    </thead>
    <tbody>
        {{-- Weekly Earnings --}}
        <tr>
            <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">
                @if ($item->type === 'daily_rate')
                    Weekly earnings ({{ $item->present_days ?? 0 }} days &times; &#8377;{{ number_format($item->applied_daily_rate ?? $employee->daily_rate ?? 0, 2) }})
                @else
                    Weekly earnings ({{ number_format($item->total_hours ?? 0, 2) }} hrs &times; &#8377;{{ number_format($item->applied_hourly_rate ?? $employee->hourly_rate ?? 0, 2) }})
                @endif
            </td>
            <td style="padding: 9px 10px; border: 1px solid #e2e8f0; text-align: right; color: #334155;">{{ number_format($item->weekly_amount ?? 0, 2) }}</td>
        </tr>

        {{-- Overtime --}}
        @if (($item->overtime_amount ?? 0) > 0)
        <tr>
            <td style="padding: 9px 10px; border: 1px solid #e2e8f0; color: #334155;">Overtime allowance</td>
            <td style="padding: 9px 10px; border: 1px solid #e2e8f0; text-align: right; color: #334155;">{{ number_format($item->overtime_amount, 2) }}</td>
        </tr>
        @endif

        {{-- Gross Total --}}
        <tr style="background: #f1f5f9;">
            <td style="padding: 11px 10px; border: 1px solid #cbd5e1; font-weight: bold; color: #0f172a; font-size: 11px;">Total Gross Pay</td>
            <td style="padding: 11px 10px; border: 1px solid #cbd5e1; text-align: right; font-weight: bold; color: #0f172a; font-size: 11px;">{{ number_format($item->gross_amount ?? 0, 2) }}</td>
        </tr>

        {{-- Cash --}}
        <tr style="background: #f0fdf4;">
            <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #059669; font-size: 10px;">&#8627; Paid by Cash</td>
            <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; color: #059669; font-weight: bold; font-size: 10px;">{{ number_format($item->cash_amount ?? 0, 2) }}</td>
        </tr>

        {{-- Bank --}}
        <tr style="background: #eff6ff;">
            <td style="padding: 8px 10px; border: 1px solid #e2e8f0; color: #2563eb; font-size: 10px;">&#8627; Paid by Bank Transfer</td>
            <td style="padding: 8px 10px; border: 1px solid #e2e8f0; text-align: right; color: #2563eb; font-weight: bold; font-size: 10px;">{{ number_format($item->bank_amount ?? 0, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ══════════════════════════════════════════════════════════ --}}
{{-- SIGNATURES --}}
{{-- ══════════════════════════════════════════════════════════ --}}
<table width="100%" cellpadding="0" cellspacing="0" style="border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 10px; table-layout: fixed;">
    <tr>
        <td width="33%" style="text-align: center; padding: 0 10px;">
            <div style="border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 6px; font-size: 9px; color: #64748b;">Prepared By</div>
        </td>
        <td width="33%" style="text-align: center; padding: 0 10px;">
            <div style="border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 6px; font-size: 9px; color: #64748b;">Authorized By</div>
        </td>
        <td width="34%" style="text-align: center; padding: 0 10px;">
            <div style="border-top: 1px solid #94a3b8; margin-top: 40px; padding-top: 6px; font-size: 9px; color: #64748b;">Employee Acknowledgment</div>
        </td>
    </tr>
</table>

<div style="text-align: center; margin-top: 18px; font-size: 8.5px; color: #94a3b8;">
    Generated on {{ $generatedAt->format('d M Y, H:i') }} &nbsp;|&nbsp; This is a system-generated document.
</div>
</div>{{-- /wrapper --}}

</body>
</html>
