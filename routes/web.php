<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Webhook\FiuuController;
// use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Qrcode;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::impersonate();

Route::get('auth/password/reset/{id}', [VerificationController::class, 'showResetPassword'])->name('auth.password.reset');
Route::post('auth/password/reset', [VerificationController::class, 'resetPassword'])->name('auth.password.update');
Route::get('auth/password/reset-success', [VerificationController::class, 'redirectSuccess'])->name('auth.password.success');

// Route::get('email/resend', [AuthController::class, 'resend'])->name('verification.resend');
Route::get('send/sms', [TestController::class, 'sendSms'])->name('send.sms');


// webhook
Route::group([
    'prefix' => 'webhook',
    'as' => 'webhook.'
], function() {
    Route::post('/fiuu/return', [FiuuController::class, 'getReturn'])->name('fiuu.return');
    Route::post('/fiuu/notification', [FiuuController::class, 'getNotification'])->name('fiuu.notification');
    Route::post('/fiuu/callback', [FiuuController::class, 'getCallback'])->name('fiuu.callback');
    Route::post('/fiuu/test', [FiuuController::class, 'getTest'])->name('fiuu.test');
});

Route::get('/qrcode/print/{series_no}', function ($series_no) {
    $record = Qrcode::where('series_no', $series_no)->firstOrFail();
    return view('qr-modal', [
        'qrCode' => $record->series_no,
    ]);
})->name('qrcode.print');

// Serves the T&C PDF server-side rather than requiring the S3 object to
// be public — see PublicDocumentController's doc comment for why.
Route::get('/documents/terms-conditions', [\App\Http\Controllers\PublicDocumentController::class, 'termsConditions'])
    ->name('documents.terms-conditions');

// Same reasoning, for order handoff photos (rider pickup, merchant
// wash, delivery, etc.) — same S3 bucket, same Block Public Access
// restriction.
Route::get('/documents/step-photo/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'stepPhoto'])
    ->name('documents.step-photo');
Route::get('/documents/pickup-photo/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'pickupPhoto'])
    ->name('documents.pickup-photo');
Route::get('/documents/landmark-picture/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'landmarkPicture'])
    ->name('documents.landmark-picture');
Route::get('/documents/banner-image/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'bannerImage'])
    ->name('documents.banner-image');
Route::get('/documents/ticket-image/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'ticketImage'])
    ->name('documents.ticket-image');
Route::get('/documents/announcement-image/{hashslug}', [\App\Http\Controllers\PublicDocumentController::class, 'announcementImage'])
    ->name('documents.announcement-image');

// Traditional (non-Livewire) file upload for admin settings — see
// SettingUploadController's doc comment for why this exists: Filament's
// Livewire FileUpload goes through a special AJAX endpoint
// (/livewire/upload-file) that Cloudflare was rejecting with a 401,
// even with no Zero Trust or explicit security rules enabled. This is
// a plain form POST instead, same pattern as the rider/merchant
// document uploads that already work reliably.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/settings/upload-terms', [\App\Http\Controllers\Admin\SettingUploadController::class, 'uploadTerms'])
        ->name('admin.settings.upload-terms');
});