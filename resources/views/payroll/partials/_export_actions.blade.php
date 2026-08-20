{{-- payroll/partials/_export_actions.blade.php — download this week's figures

     Lives beside the week's other actions rather than up in the page toolbar:
     exporting is something you do once the numbers are settled, so it belongs
     next to recalculating and finalising them. Shared by both banner states —
     a finalised week is the one most likely to be exported. --}}

<a href="{{ route('payroll.exportWeekCsv', ['year' => $year, 'week' => $week]) }}"
   data-tooltip="Export report in Excel" aria-label="Export report in Excel"
   class="inline-flex items-center justify-center p-1.5 rounded-lg hover:bg-white transition">
    <img src="{{ asset('images/xls.svg') }}" alt="" class="w-6 h-6">
</a>

<a href="{{ route('payroll.exportWeekPdf', ['year' => $year, 'week' => $week]) }}"
   data-tooltip="Export report in PDF" aria-label="Export report in PDF"
   class="inline-flex items-center justify-center p-1.5 rounded-lg hover:bg-white transition">
    <img src="{{ asset('images/pdf.svg') }}" alt="" class="w-6 h-6">
</a>
