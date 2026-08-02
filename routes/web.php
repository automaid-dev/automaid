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