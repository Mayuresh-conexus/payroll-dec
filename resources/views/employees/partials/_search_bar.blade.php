{{-- _search_bar.blade.php — Filter/search form for the employees list --}}
<form method="GET" action="{{ route('employees.index') }}"
    class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}"
        placeholder="Search by name, code or department…"
        class="flex-1 min-w-[200px] rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">

    <select name="type"
        class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-slate-500 focus:border-slate-500 bg-white">
        <option value="">All types</option>
        <option value="daily_rate" @selected(request('type') === 'daily_rate')>Daily rate</option>
        <option value="hourly"     @selected(request('type') === 'hourly')>Hourly</option>
    </select>

    <select name="status"
        class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-slate-500 focus:border-slate-500 bg-white">
        <option value="">All status</option>
        <option value="active"   @selected(request('status') === 'active')>Active</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
    </select>

    <button type="submit"
        class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-800">
        Search
    </button>

    @if (request()->hasAny(['search', 'type', 'status']))
        <a href="{{ route('employees.index') }}"
            class="px-3 py-2 rounded-lg border border-slate-200 text-sm text-slate-600 hover:bg-slate-50">
            Clear
        </a>
    @endif
</form>
