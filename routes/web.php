<?php

use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnalyticsDashboardController;
use App\Http\Controllers\Admin\CertificateAdminController;
use App\Http\Controllers\Admin\ParticipantIntakeAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Api\ParticipantSearchController;
use App\Http\Controllers\Api\PsgcController;
use App\Http\Controllers\CertificatePublicController;
use App\Http\Controllers\ParticipantIntakeController;
use App\Http\Controllers\PublicImpactController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// All staff roles land on the cross-filtered analytics dashboard after login.
Route::get('/dashboard', fn () => redirect()->route('admin.dashboard'))
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::middleware('role:regional_director,unit_supervisor,organizer')->group(function () {
        // New analytics dashboard + its single data endpoint: all staff roles.
        Route::get('/dashboard', [AnalyticsDashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/analytics/data', [AnalyticsDashboardController::class, 'data'])->name('admin.analytics.data');

        Route::get('/participant-intakes', [ParticipantIntakeAdminController::class, 'index'])->name('admin.participant-intakes.index');
        Route::get('/certificates', [CertificateAdminController::class, 'index'])->name('admin.certs.index');
        Route::get('/certificates/approvals', [CertificateAdminController::class, 'approvals'])->name('admin.certs.approvals');
        Route::get('/certificates/create', [CertificateAdminController::class, 'create'])->name('admin.certs.create');
        Route::post('/certificates/caption-suggest', [CertificateAdminController::class, 'suggestCaption'])->name('admin.certs.caption-suggest');
        Route::post('/certificates/live-preview', [CertificateAdminController::class, 'livePreview'])->name('admin.certs.live-preview');
        Route::post('/certificates/preview', [CertificateAdminController::class, 'preview'])->name('admin.certs.preview');
    });

    Route::middleware('role:regional_director,organizer')->group(function () {
        Route::get('/certificates/group-download', [CertificateAdminController::class, 'downloadGroup'])->name('admin.certs.group-download');
        Route::get('/certificates/{id}/download', [CertificateAdminController::class, 'download'])->name('admin.certs.download');
        // Legacy analytics route now redirects to the new dashboard.
        Route::get('/analytics', fn () => redirect()->route('admin.dashboard'))->name('admin.analytics.index');
        Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('admin.analytics.export');
    });

    Route::middleware('role:unit_supervisor,organizer')->group(function () {
        Route::post('/participant-intakes/export-selected', [ParticipantIntakeAdminController::class, 'exportSelected'])->name('admin.participant-intakes.export-selected');
        Route::post('/participant-intakes/events', [ParticipantIntakeAdminController::class, 'createEvent'])->name('admin.participant-intakes.events.create');
        Route::post('/participant-intakes/events/{event}/toggle', [ParticipantIntakeAdminController::class, 'toggleEvent'])->name('admin.participant-intakes.events.toggle');
        Route::delete('/participant-intakes/events/{event}', [ParticipantIntakeAdminController::class, 'deleteEvent'])->name('admin.participant-intakes.events.delete');
        Route::post('/certificates/endorse', [CertificateAdminController::class, 'endorse'])->name('admin.certs.endorse');
        Route::get('/certificates/matching-review', [CertificateAdminController::class, 'showMatchingReview'])->name('admin.certs.matching-review');
        Route::post('/certificates/matching-review/resolve', [CertificateAdminController::class, 'resolveMatching'])->name('admin.certs.matching-resolve');
    });

    Route::middleware('role:regional_director')->group(function () {
        Route::get('/account-settings', [AccountSettingsController::class, 'edit'])->name('admin.account-settings.edit');

        Route::post('/certificates', [CertificateAdminController::class, 'store'])->name('admin.certs.store');
        Route::post('/certificates/signatory', [CertificateAdminController::class, 'updateRegionalDirectorSignatory'])->name('admin.certs.signatory.update');
        Route::post('/certificates/endorsements/{id}/approve', [CertificateAdminController::class, 'approveEndorsement'])->name('admin.certs.endorsements.approve');
        Route::post('/certificates/endorsements/{id}/reject', [CertificateAdminController::class, 'rejectEndorsement'])->name('admin.certs.endorsements.reject');
        Route::get('/certificates/endorsements/{id}/generation-progress', [CertificateAdminController::class, 'endorsementGenerationProgress'])->name('admin.certs.endorsements.generation-progress');
        Route::get('/certificates/endorsements/{id}/uploaded-pdf', [CertificateAdminController::class, 'viewEndorsementTemplate'])->name('admin.certs.endorsements.template.view');
        Route::get('/certificates/endorsements/{id}/preview-pdf', [CertificateAdminController::class, 'previewEndorsement'])->name('admin.certs.endorsements.preview');
        Route::get('/certificates/endorsements/{id}/participants/download', [CertificateAdminController::class, 'downloadEndorsementParticipants'])->name('admin.certs.endorsements.participants.download');

        Route::get('/participant-intakes/export', [ParticipantIntakeAdminController::class, 'export'])->name('admin.participant-intakes.export');
        Route::delete('/participant-intakes/{intake}', [ParticipantIntakeAdminController::class, 'destroy'])->name('admin.participant-intakes.destroy');
        Route::post('/participant-intakes/bulk-delete', [ParticipantIntakeAdminController::class, 'bulkDelete'])->name('admin.participant-intakes.bulk-delete');
        Route::post('/participant-intakes/toggle', [ParticipantIntakeAdminController::class, 'toggleIntake'])->name('admin.participant-intakes.toggle');

        Route::get('/users', [UserAdminController::class, 'index'])->name('admin.users.index');
        Route::get('/users/claimed', [UserAdminController::class, 'claimedRecipients'])->name('admin.users.claimed');
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

Route::get('/preview', [CertificatePublicController::class, 'preview'])
    ->middleware('throttle:30,1')
    ->name('cert.preview');

Route::get('/download', [CertificatePublicController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('cert.download');

// Public "Caraga Reach Atlas" — aggregate-only, <5-suppressed, no PII.
Route::get('/impact', [PublicImpactController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('impact.index');
Route::get('/impact/data.json', [PublicImpactController::class, 'data'])
    ->middleware('throttle:60,1')
    ->name('impact.data');

Route::get('/participant-intake/{token}', [ParticipantIntakeController::class, 'create'])
    ->middleware('throttle:60,1')
    ->name('participant.intake');
Route::post('/participant-intake/{token}', [ParticipantIntakeController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('participant.intake.submit');

Route::post('/webhooks/telegram/{secret}', TelegramWebhookController::class)
    ->name('webhooks.telegram');

Route::get('/api/participants/search', [ParticipantSearchController::class, 'search'])
    ->middleware('throttle:participant-search');
Route::post('/api/participants/verify', [ParticipantSearchController::class, 'verify'])
    ->middleware('throttle:participant-verify')
    ->name('participants.verify');

Route::prefix('api/psgc')->group(function () {
    Route::get('/regions', [PsgcController::class, 'regions']);
    Route::get('/provinces', [PsgcController::class, 'provinces']);
    Route::get('/municipalities', [PsgcController::class, 'municipalities']);
    Route::get('/barangays', [PsgcController::class, 'barangays']);
});

Route::get('/app/{any?}', function () {
    $indexPath = public_path('app/index.html');
    abort_unless(File::exists($indexPath), 404);

    return File::get($indexPath);
})->where('any', '.*');

require __DIR__.'/auth.php';
