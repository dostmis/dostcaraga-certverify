<?php

use App\Http\Controllers\Recipient\Auth\RecipientAuthController;
use App\Http\Controllers\Recipient\CertificateController;
use App\Http\Controllers\Recipient\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('recipient')->name('recipient.')->group(function () {
    Route::middleware('guest:recipient')->group(function () {
        Route::get('login', function () {
            return redirect()->route('login');
        })->name('login');
        Route::post('login', [RecipientAuthController::class, 'login']);
        Route::get('register', [RecipientAuthController::class, 'showRegisterForm'])->name('register');
        Route::post('register', [RecipientAuthController::class, 'register']);
        Route::get('claim', [RecipientAuthController::class, 'showClaimLookup'])->name('claim.lookup');
        Route::post('claim/lookup', [RecipientAuthController::class, 'claimLookup'])->name('claim.lookup.submit');
        Route::post('claim/verify', [RecipientAuthController::class, 'claimVerify'])->name('claim.verify');
        Route::get('claim/{token}', [RecipientAuthController::class, 'showClaimForm'])->name('claim.form');
        Route::post('claim/{token}', [RecipientAuthController::class, 'claim'])->name('claim.submit');
    });

    Route::middleware('auth:recipient')->group(function () {
        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates');
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('profile/password', [ProfileController::class, 'editPassword'])->name('profile.password');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
        Route::post('logout', [RecipientAuthController::class, 'logout'])->name('logout');
    });
});
