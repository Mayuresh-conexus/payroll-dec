{{-- backups/partials/_schedule.blade.php --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 sm:p-6"
    x-data="{ frequency: '{{ $schedule->frequency }}' }">
    <div class="flex items-center gap-2 mb-4">
        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
        </svg>
        <h3 class="text-sm font-semibold text-slate-800">Backup Schedule</h3>
    </div>

    <form action="{{ route('backups.schedule.update') }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <input type="hidden" name="enabled" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="enabled" value="1" @checked($schedule->enabled)
                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Enable automatic backups
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Frequency</label>
                <select name="frequency" x-model="frequency"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none bg-white">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Run at <span class="text-slate-400 font-normal">({{ config('app.timezone') }})</span></label>
                <input type="time" name="run_at" value="{{ \Carbon\Carbon::parse($schedule->run_at)->format('H:i') }}"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none">
            </div>
            <div x-show="frequency === 'weekly'">
                <label class="block text-xs font-medium text-slate-600 mb-1">Day of week</label>
                <select name="day_of_week"
                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none bg-white">
                    @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $i => $day)
                        <option value="{{ $i }}" @selected($schedule->day_of_week === $i)>{{ $day }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Keep backups for (days)</label>
            <input type="number" name="retention_days" min="1" max="365" value="{{ $schedule->retention_days }}"
                class="w-full sm:w-48 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-2 focus:ring-slate-500/60 outline-none"
                placeholder="Leave blank to keep forever">
            <p class="mt-1 text-[11px] text-slate-400">Scheduled runs automatically delete backups older than this. Manual backups are never auto-deleted. Leave blank to keep forever.</p>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-100 gap-4">
            <p class="text-xs text-slate-400">
                Last ran: {{ $schedule->last_run_at?->diffForHumans() ?? 'Never' }}
                — requires a server cron entry (<code class="font-mono">* * * * * php artisan schedule:run</code>) to fire automatically.
            </p>
            <button type="submit"
                class="px-4 py-2 rounded-lg bg-slate-900 text-sm font-semibold text-white hover:bg-slate-800 transition shrink-0">
                Save Schedule
            </button>
        </div>
    </form>
</div>
