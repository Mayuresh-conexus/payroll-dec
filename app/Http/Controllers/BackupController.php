<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBackupScheduleRequest;
use App\Models\AuditLog;
use App\Models\BackupSchedule;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backupService) {}

    public function index(): View
    {
        $backups = $this->backupService->list();
        $schedule = BackupSchedule::current();
        $history = AuditLog::where('model_type', 'Backup')
            ->with('user')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('backups.index', compact('backups', 'schedule', 'history'));
    }

    public function run(): RedirectResponse
    {
        try {
            $exitCode = Artisan::call('backup:run', ['--reason' => 'manual']);
        } catch (\Throwable $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        if ($exitCode !== 0) {
            return back()->withErrors(['backup' => trim(Artisan::output())]);
        }

        $backup = $this->backupService->list()->first();

        $this->writeBackupAudit('created', [
            'filename' => $backup['filename'] ?? null,
            'size_bytes' => $backup['size_bytes'] ?? null,
            'reason' => 'manual',
        ]);

        return back()->with('success', 'Backup created: '.($backup['filename'] ?? 'unknown'));
    }

    public function download(string $filename): StreamedResponse
    {
        $directory = config('backup.directory');

        if (! $this->backupService->isValidFilename($filename) || ! Storage::disk('local')->exists("{$directory}/{$filename}")) {
            abort(404);
        }

        $this->writeBackupAudit('downloaded', ['filename' => $filename]);

        return Storage::disk('local')->download("{$directory}/{$filename}");
    }

    public function restore(Request $request, string $filename): RedirectResponse
    {
        $directory = config('backup.directory');

        if (! $this->backupService->isValidFilename($filename) || ! Storage::disk('local')->exists("{$directory}/{$filename}")) {
            abort(404);
        }

        $request->validate(['confirmation' => 'required|string']);

        if ($request->input('confirmation') !== $filename) {
            return back()->withErrors(['confirmation' => 'Confirmation text does not match the backup filename.'])->withInput();
        }

        try {
            $exitCode = Artisan::call('backup:restore', ['filename' => $filename, '--force' => true]);
        } catch (\Throwable $e) {
            return back()->withErrors(['restore' => $e->getMessage()])->withInput();
        }

        if ($exitCode !== 0) {
            return back()->withErrors(['restore' => trim(Artisan::output())])->withInput();
        }

        $preRestore = $this->backupService->list()->firstWhere('type', 'prerestore');

        $this->writeBackupAudit('restored', [
            'restored_from' => $filename,
            'pre_restore_filename' => $preRestore['filename'] ?? null,
        ]);

        return back()->with(
            'restore_completed',
            "Restored from {$filename}. A safety backup of the previous state was saved as ".($preRestore['filename'] ?? 'unknown').'.'
        );
    }

    public function destroy(string $filename): RedirectResponse
    {
        try {
            $this->backupService->delete($filename);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        $this->writeBackupAudit('deleted', ['filename' => $filename]);

        return back()->with('success', "Backup {$filename} deleted.");
    }

    public function updateSchedule(UpdateBackupScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $schedule = BackupSchedule::current();
        $old = $schedule->only(['enabled', 'frequency', 'run_at', 'day_of_week', 'retention_days']);

        $schedule->fill($data);
        $schedule->updated_by = auth()->id();
        $schedule->save();

        $this->writeBackupAudit('updated', ['old' => $old, 'new' => $data]);

        return back()->with('success', 'Backup schedule updated.');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function writeBackupAudit(string $action, array $details): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action, // created | restored | deleted | downloaded | updated
                'model_type' => 'Backup',
                'model_id' => null,
                'old_values' => null,
                'new_values' => $details,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Backup audit write failed: '.$e->getMessage());
        }
    }
}
