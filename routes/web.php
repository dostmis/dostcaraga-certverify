<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CertificateAdminController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ParticipantIntakeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\CertificatePublicController;
use App\Http\Controllers\ParticipantIntakeController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\File;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', function () {
    $user = auth()->user();
    if ($user && ($user->isRegionalDirector() || $user->hasRole(\App\Models\User::ROLE_ORGANIZER))) {
        return redirect()->route('admin.certs.index');
    }

    return redirect()->route('admin.participant-intakes.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::middleware('role:regional_director,unit_supervisor,organizer')->group(function () {
        Route::get('/participant-intakes', [ParticipantIntakeAdminController::class, 'index'])->name('admin.participant-intakes.index');
        Route::get('/certificates', [CertificateAdminController::class, 'index'])->name('admin.certs.index');
        Route::get('/certificates/approvals', [CertificateAdminController::class, 'approvals'])->name('admin.certs.approvals');
        Route::get('/certificates/create', [CertificateAdminController::class, 'create'])->name('admin.certs.create');
        Route::post('/certificates/preview', [CertificateAdminController::class, 'preview'])->name('admin.certs.preview');
    });

    Route::middleware('role:regional_director,organizer')->group(function () {
        Route::get('/certificates/group-download', [CertificateAdminController::class, 'downloadGroup'])->name('admin.certs.group-download');
        Route::get('/certificates/{id}/download', [CertificateAdminController::class, 'download'])->name('admin.certs.download');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('admin.analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('admin.analytics.export');
    });

    Route::middleware('role:unit_supervisor,organizer')->group(function () {
        Route::post('/participant-intakes/export-selected', [ParticipantIntakeAdminController::class, 'exportSelected'])->name('admin.participant-intakes.export-selected');
        Route::post('/participant-intakes/events', [ParticipantIntakeAdminController::class, 'createEvent'])->name('admin.participant-intakes.events.create');
        Route::post('/participant-intakes/events/{event}/toggle', [ParticipantIntakeAdminController::class, 'toggleEvent'])->name('admin.participant-intakes.events.toggle');
        Route::delete('/participant-intakes/events/{event}', [ParticipantIntakeAdminController::class, 'deleteEvent'])->name('admin.participant-intakes.events.delete');
        Route::post('/certificates/endorse', [CertificateAdminController::class, 'endorse'])->name('admin.certs.endorse');
    });

    Route::middleware('role:regional_director')->group(function () {
        Route::post('/certificates', [CertificateAdminController::class, 'store'])->name('admin.certs.store');
        Route::post('/certificates/signatory', [CertificateAdminController::class, 'updateRegionalDirectorSignatory'])->name('admin.certs.signatory.update');
        Route::post('/certificates/endorsements/{id}/approve', [CertificateAdminController::class, 'approveEndorsement'])->name('admin.certs.endorsements.approve');
        Route::post('/certificates/endorsements/{id}/reject', [CertificateAdminController::class, 'rejectEndorsement'])->name('admin.certs.endorsements.reject');
        Route::get('/certificates/endorsements/{id}/uploaded-pdf', [CertificateAdminController::class, 'viewEndorsementTemplate'])->name('admin.certs.endorsements.template.view');
        Route::get('/certificates/endorsements/{id}/preview-pdf', [CertificateAdminController::class, 'previewEndorsement'])->name('admin.certs.endorsements.preview');
        Route::get('/certificates/endorsements/{id}/participants/download', [CertificateAdminController::class, 'downloadEndorsementParticipants'])->name('admin.certs.endorsements.participants.download');

        Route::get('/participant-intakes/export', [ParticipantIntakeAdminController::class, 'export'])->name('admin.participant-intakes.export');
        Route::delete('/participant-intakes/{intake}', [ParticipantIntakeAdminController::class, 'destroy'])->name('admin.participant-intakes.destroy');
        Route::post('/participant-intakes/bulk-delete', [ParticipantIntakeAdminController::class, 'bulkDelete'])->name('admin.participant-intakes.bulk-delete');
        Route::post('/participant-intakes/toggle', [ParticipantIntakeAdminController::class, 'toggleIntake'])->name('admin.participant-intakes.toggle');

        Route::get('/users', [UserAdminController::class, 'index'])->name('admin.users.index');
        Route::post('/users/{id}/role', [UserAdminController::class, 'updateRole'])->name('admin.users.role');
        Route::post('/users/{id}/approve', [UserAdminController::class, 'approve'])->name('admin.users.approve');
        Route::post('/users/{id}/reject', [UserAdminController::class, 'reject'])->name('admin.users.reject');
    });

});

Route::get('/verify', [CertificatePublicController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('cert.verify');

Route::get('/print', [CertificatePublicController::class, 'print'])
    ->middleware('throttle:30,1')
    ->name('cert.print');

Route::get('/download', [CertificatePublicController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('cert.download');

Route::get('/participant-intake/{token}', [ParticipantIntakeController::class, 'create'])
    ->middleware('throttle:60,1')
    ->name('participant.intake');
Route::post('/participant-intake/{token}', [ParticipantIntakeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('participant.intake.submit');

Route::post('/webhooks/telegram/{secret}', TelegramWebhookController::class)
    ->name('webhooks.telegram');

Route::get('/app/{any?}', function () {
    return File::get(public_path('app/index.html'));
})->where('any', '.*');

require __DIR__.'/auth.php';
