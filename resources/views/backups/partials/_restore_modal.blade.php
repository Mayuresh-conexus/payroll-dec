{{-- backups/partials/_restore_modal.blade.php --}}
<div x-show="restoreModalOpen" x-cloak
    x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 backdrop-blur-sm">
    <div @click.outside="if (! restoring) restoreModalOpen = false"
        class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md mx-4 overflow-hidden">

        {{-- Header --}}
        <div class="flex items-start gap-3 p-5 border-b border-slate-100">
            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Restore Database?</h3>
                <p class="mt-0.5 text-xs text-slate-500 font-mono" x-text="restoreTarget?.filename"></p>
            </div>
        </div>

        <form :action="restoreTarget ? '{{ url('backups') }}/' + restoreTarget.filename + '/restore' : '#'" method="POST"
            @submit="beginRestore()">
            @csrf
            <input type="hidden" name="_modal" value="restore">
            <input type="hidden" name="_target_filename" :value="restoreTarget ? restoreTarget.filename : ''">

            {{-- Body --}}
            <div class="p-5 space-y-4">
                <div class="rounded-lg bg-amber-50 border border-amber-100 divide-y divide-amber-100">
                    <div class="flex items-center gap-3 px-4 py-3 text-xs text-amber-800">
                        <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        This will overwrite ALL current data in the live database.
                    </div>
                    <div class="flex items-center gap-3 px-4 py-3 text-xs text-amber-800">
                        <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        A safety backup is taken automatically before restoring.
                    </div>
                    <div class="flex items-center gap-3 px-4 py-3 text-xs text-amber-800">
                        <svg class="w-4 h-4 flex-shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/>
                        </svg>
                        This action cannot be undone once complete.
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Type the filename to confirm: <span class="font-mono text-slate-800" x-text="restoreTarget?.filename"></span>
                    </label>
                    <input type="text" x-model="restoreConfirmText" name="confirmation" autocomplete="off"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-rose-500/60 focus:border-rose-500 outline-none"
                        placeholder="Type the exact filename">
                </div>
            </div>

            {{-- In-progress notice, shown once the request is on its way --}}
            <div x-show="restoring" x-cloak class="px-5 pb-5">
                <div class="rounded-lg bg-slate-900 text-white px-4 py-3 flex items-center gap-3">
                    <svg class="w-5 h-5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-medium">Restoring database…</p>
                        <p class="text-xs text-slate-300 mt-0.5">Taking a safety backup first. Do not close this tab.</p>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-4 bg-slate-50 border-t border-slate-100">
                <button type="button" @click="restoreModalOpen = false" x-show="! restoring"
                    class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit" :disabled="restoring || restoreConfirmText !== restoreTarget?.filename"
                    class="px-4 py-2 rounded-lg text-white text-sm font-medium transition inline-flex items-center gap-2"
                    :class="restoring
                        ? 'bg-slate-400 cursor-wait'
                        : (restoreConfirmText === restoreTarget?.filename ? 'bg-rose-600 hover:bg-rose-700' : 'bg-rose-300 cursor-not-allowed')">
                    <svg x-show="restoring" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                    </svg>
                    <span x-text="restoring ? 'Restoring…' : 'Yes, Restore Database'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
