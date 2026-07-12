<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\DockerController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SSLController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\WordPressController;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard
Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Website Management (Phase 3)
    Route::get('/websites', [WebsiteController::class, 'index'])->name('websites.index');
    Route::post('/websites', [WebsiteController::class, 'store'])->name('websites.store');
    Route::get('/websites/{site}/config', [WebsiteController::class, 'config'])->name('websites.config');
    Route::post('/websites/{site}/toggle', [WebsiteController::class, 'toggle'])->name('websites.toggle');
    Route::delete('/websites/{site}', [WebsiteController::class, 'destroy'])->name('websites.destroy');

    // Database Management (Phase 3)
    Route::get('/databases', [DatabaseController::class, 'index'])->name('databases.index');
    Route::post('/databases', [DatabaseController::class, 'store'])->name('databases.store');
    Route::post('/databases/users/drop', [DatabaseController::class, 'dropUser'])->name('databases.users.drop');
    Route::post('/databases-root-password', [DatabaseController::class, 'rootPassword'])->name('databases.root_password');
    Route::post('/databases-sync', [DatabaseController::class, 'syncAll'])->name('databases.sync');
    Route::get('/databases-recycle', [DatabaseController::class, 'recycled'])->name('databases.recycle');
    Route::post('/databases-recycle/restore', [DatabaseController::class, 'restoreRecycled'])->name('databases.recycle.restore');
    Route::post('/databases-recycle/purge', [DatabaseController::class, 'purgeRecycled'])->name('databases.recycle.purge');
    Route::get('/databases-recycle/download', [DatabaseController::class, 'downloadRecycled'])->name('databases.recycle.download');
    Route::put('/databases/{name}/password', [DatabaseController::class, 'changePassword'])->name('databases.password');
    Route::post('/databases/{name}/import', [DatabaseController::class, 'import'])->name('databases.import');
    Route::get('/databases/{name}/permission', [DatabaseController::class, 'permission'])->name('databases.permission');
    Route::post('/databases/{name}/grant', [DatabaseController::class, 'grant'])->name('databases.grant');
    Route::get('/databases-pma', [DatabaseController::class, 'pmaLogin'])->name('databases.pma');
    Route::get('/databases/{name}/pma', [DatabaseController::class, 'pmaLogin'])->name('databases.pma.db');
    Route::get('/databases/{name}/backup', [DatabaseController::class, 'backup'])->name('databases.backup');
    Route::get('/databases/{name}/backups', [DatabaseController::class, 'backups'])->name('databases.backups');
    Route::post('/databases/{name}/backups', [DatabaseController::class, 'createBackup'])->name('databases.backups.create');
    Route::post('/databases/{name}/backups/restore', [DatabaseController::class, 'restoreBackup'])->name('databases.backups.restore');
    Route::post('/databases/{name}/backups/delete', [DatabaseController::class, 'deleteBackup'])->name('databases.backups.delete');
    Route::get('/databases/{name}/backups/download', [DatabaseController::class, 'downloadBackup'])->name('databases.backups.download');
    Route::delete('/databases/{name}', [DatabaseController::class, 'destroy'])->name('databases.destroy');

    // SSL Certificates (Phase 3)
    Route::get('/ssl', [SSLController::class, 'index'])->name('ssl.index');
    Route::post('/ssl/issue', [SSLController::class, 'issue'])->name('ssl.issue');
    Route::post('/ssl/renew', [SSLController::class, 'renew'])->name('ssl.renew');
    Route::post('/ssl/revoke', [SSLController::class, 'revoke'])->name('ssl.revoke');

    // File Manager (Phase 3)
    Route::get('/files', [FileManagerController::class, 'index'])->name('files.index');
    Route::get('/files/list', [FileManagerController::class, 'list'])->name('files.list');
    Route::get('/files/search', [FileManagerController::class, 'search'])->name('files.search');
    Route::get('/files/trash', [FileManagerController::class, 'trash'])->name('files.trash');
    Route::post('/files/trash/restore', [FileManagerController::class, 'trashRestore'])->name('files.trash.restore');
    Route::post('/files/trash/purge', [FileManagerController::class, 'trashPurge'])->name('files.trash.purge');
    Route::get('/files/read', [FileManagerController::class, 'read'])->name('files.read');
    Route::get('/files/download', [FileManagerController::class, 'download'])->name('files.download');
    Route::post('/files/save', [FileManagerController::class, 'save'])->name('files.save');
    Route::post('/files/create', [FileManagerController::class, 'create'])->name('files.create');
    Route::post('/files/rename', [FileManagerController::class, 'rename'])->name('files.rename');
    Route::post('/files/delete', [FileManagerController::class, 'delete'])->name('files.delete');
    Route::post('/files/upload', [FileManagerController::class, 'upload'])->name('files.upload');
    Route::get('/files/info', [FileManagerController::class, 'info'])->name('files.info');
    Route::post('/files/chmod', [FileManagerController::class, 'chmod'])->name('files.chmod');
    Route::post('/files/transfer', [FileManagerController::class, 'transfer'])->name('files.transfer');
    Route::post('/files/compress', [FileManagerController::class, 'compress'])->name('files.compress');
    Route::post('/files/extract', [FileManagerController::class, 'extract'])->name('files.extract');

    // Cron Jobs (Phase 3)
    Route::get('/cron', [CronController::class, 'index'])->name('cron.index');
    Route::post('/cron', [CronController::class, 'store'])->name('cron.store');
    Route::post('/cron/{id}/toggle', [CronController::class, 'toggle'])->name('cron.toggle');
    Route::post('/cron/{id}/run', [CronController::class, 'run'])->name('cron.run');
    Route::delete('/cron/{id}', [CronController::class, 'destroy'])->name('cron.destroy');

    // Security — Firewall
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::get('/security/firewall', [SecurityController::class, 'state'])->name('security.firewall.state');
    Route::post('/security/firewall/toggle', [SecurityController::class, 'toggle'])->name('security.firewall.toggle');
    Route::post('/security/firewall/port', [SecurityController::class, 'addPort'])->name('security.firewall.port');
    Route::post('/security/firewall/ip', [SecurityController::class, 'addIp'])->name('security.firewall.ip');
    Route::delete('/security/firewall/{n}', [SecurityController::class, 'deleteRule'])->name('security.firewall.delete');

    // Logs
    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('/logs/operations', [LogController::class, 'operations'])->name('logs.operations');
    Route::delete('/logs/operations', [LogController::class, 'clearOperations'])->name('logs.operations.clear');
    Route::get('/logs/run', [LogController::class, 'run'])->name('logs.run');
    Route::delete('/logs/run', [LogController::class, 'clearRun'])->name('logs.run.clear');
    Route::get('/logs/cron', [LogController::class, 'cronList'])->name('logs.cron');
    Route::get('/logs/cron/{key}', [LogController::class, 'cronShow'])->name('logs.cron.show');
    Route::delete('/logs/cron/{key}', [LogController::class, 'clearCron'])->name('logs.cron.clear');

    // Docker (Tools)
    Route::get('/docker', [DockerController::class, 'index'])->name('docker.index');
    Route::get('/docker/data', [DockerController::class, 'data'])->name('docker.data');
    Route::post('/docker/action', [DockerController::class, 'action'])->name('docker.action');
    Route::post('/docker/logs', [DockerController::class, 'logs'])->name('docker.logs');
    Route::post('/docker/pull', [DockerController::class, 'pull'])->name('docker.pull');
    Route::post('/docker/image/remove', [DockerController::class, 'removeImage'])->name('docker.image.remove');

    // WordPress (Tools)
    Route::get('/wordpress', [WordPressController::class, 'index'])->name('wordpress.index');
    Route::post('/wordpress/install', [WordPressController::class, 'install'])->name('wordpress.install');

    // Web Terminal (Phase 3)
    Route::get('/terminal', [TerminalController::class, 'index'])->name('terminal.index');
    Route::post('/api/terminal/exec', [TerminalController::class, 'exec'])->name('terminal.exec');

    // Notifications (Phase 4)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('/notifications/test', [NotificationController::class, 'test'])->name('notifications.test');

    // Service Control (Phase 1)
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/api/services/action', [ServiceController::class, 'action'])->name('services.action');

    // AI Assistant (Phase 2)
    Route::get('/ai', [AIController::class, 'index'])->name('ai.index');
    Route::post('/api/ai/chat', [AIController::class, 'chat'])->name('ai.chat');
    Route::post('/api/ai/execute', [AIController::class, 'execute'])->name('ai.execute');
    Route::get('/api/ai/history', [AIController::class, 'history'])->name('ai.history');
    Route::get('/api/ai/sessions', [AIController::class, 'sessions'])->name('ai.sessions');
    Route::delete('/api/ai/session', [AIController::class, 'deleteSession'])->name('ai.deleteSession');
    Route::post('/api/ai/new-session', [AIController::class, 'newSession'])->name('ai.newSession');

    // Panel self-update (from the UI)
    Route::get('/api/update/check', [UpdateController::class, 'check'])->name('update.check');
    Route::get('/api/update/status', [UpdateController::class, 'status'])->name('update.status');
    Route::post('/api/update/run', [UpdateController::class, 'run'])->name('update.run');

    // Settings (with tabs)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/panel', [SettingsController::class, 'updatePanel'])->name('settings.updatePanel');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.updatePassword');

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Dashboard API (metrics)
Route::middleware(['auth'])->get('/api/metrics', [DashboardController::class, 'metrics']);

require __DIR__.'/auth.php';
