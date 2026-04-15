{{-- payroll/partials/_cash_summary.blade.php — Cash and Bank totals summary panel --}}
<div class="p-4 border-t border-slate-100 bg-white">
    <div class="text-sm font-semibold text-slate-800 mb-2">Cash and Bank Summary</div>
    <table class="text-sm w-full">
        <tbody class="divide-y">
            @php
                $summaryRows = [
                    ['label' => 'Total Gross',  'key' => 'totals.gross',       'tip' => 'The total gross amount before any deductions or additions.'],
                    ['label' => 'Weekly Cash',  'key' => 'totals.weeklyCash',  'tip' => 'Total of cash given to the employee in the current week.'],
                    ['label' => 'Addon Cash',   'key' => 'totals.addonCash',   'tip' => 'Cash given to the employee for overtime or additional work.'],
                    ['label' => 'Total Cash',   'key' => 'totals.cash',        'tip' => 'The total amount of cash provided, including weekly and addon cash.'],
                    ['label' => 'Total Bank',   'key' => 'totals.bank',        'tip' => 'The amount remaining after deducting total cash from gross total.'],
                ];
            @endphp
            @foreach ($summaryRows as $row)
                <tr>
                    <td class="py-2 text-slate-600 flex items-center relative group">
                        <span>{{ $row['label'] }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-2 text-slate-400 cursor-pointer" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12 22C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10-4.48 10-10 10zm0-18C7.03 4 4 7.03 4 12s3.03 8 8 8 8-3.03 8-8-3.03-8-8-8zm-1 13h2v-2h-2v2zm0-4h2V7h-2v6z" />
                        </svg>
                        <div class="absolute left-0 mt-2 bg-white text-xs text-slate-500 p-2 rounded shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                            {{ $row['tip'] }}
                        </div>
                    </td>
                    <td class="py-2 text-right font-semibold" x-text="formatMoney({{ $row['key'] }})"></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
