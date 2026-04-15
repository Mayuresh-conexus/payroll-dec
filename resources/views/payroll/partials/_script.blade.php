{{--
    payroll/partials/_script.blade.php
    PHP → JS data bridge only. The payrollPage() component function lives in
    resources/js/components/payroll.js (bundled via Vite).
--}}
<script>
    window.payrollServerRows = @json($rows);
    window.payrollConfig     = { year: {{ $year }}, month: {{ $month }} };
</script>
