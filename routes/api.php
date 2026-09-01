<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Help\TicketController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/password/email',  [AuthController::class, 'passwordEmail']);
Route::get('/auth/password/verify/token/{token}',  [AuthController::class, 'verifyToken'])->name('api.auth.password.verify.token');
Route::post('/auth/resend/otp', [AuthController::class, 'resendOtp']);

Route::post('/search/location', [LocationController::class, 'searchLocation']);
Route::post('/check/location', [LocationController::class, 'checkLocation']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/register/verify', [AuthController::class, 'verifyRegister']);
Route::post('/auth/register/change', [AuthController::class, 'changeNumber']);
Route::post('/auth/check/email', [AuthController::class, 'checkEmail']);

Route::post('/join/waiting-list', [WaitingListController::class, 'joinWaitingList']);

Route::post('/rider/register', [RiderController::class, 'register']);
Route::post('/rider/register/verify', [RiderController::class, 'verifyRegisterRider']);
Route::post('/rider/register/change', [RiderController::class, 'changeNumber']);

Route::post('/merchant/search/outlet', [MerchantController::class, 'searchOutlet']);
Route::post('/merchant/register', [MerchantController::class, 'register']);
Route::post('/merchant/register/verify', [MerchantController::class, 'verifyRegisterMerchant']);
Route::post('/merchant/register/change', [MerchantController::class, 'changeNumber']);

Route::post('/banks/index', [\App\Http\Controllers\Api\BankController::class, 'index']);
Route::post('/country/index', [\App\Http\Controllers\Api\CountryController::class, 'index']);
Route::post('/state/index', [\App\Http\Controllers\Api\StateController::class, 'index']);
Route::post('/color/index', [\App\Http\Controllers\Api\ColorController::class, 'index']);

Route::post('/auth/register2', [AuthController::class, 'register2']);
Route::post('/setting', [SettingController::class, 'setting']);
Route::post('/subscription/plans', [SubscriptionPlanController::class, 'index']);

// Onboarding carousel — shown on the very first screen, before login,
// so this can't sit behind auth:sanctum like the dashboard version of
// this same endpoint further down. Same controller/method either way;
// which banners come back depends entirely on the `target` sent.
Route::post('/onboarding/banners', [\App\Http\Controllers\Api\BannerController::class, 'index']);

Route::middleware('auth:sanctum')->group( function () {
	Route::post('/profile/device', [ProfileController::class, 'saveDevice']);
	Route::post('/profile/logout', [ProfileController::class, 'logout']);
	Route::post('/profile/me', [ProfileController::class, 'me']);
	Route::post('/profile/token', [ProfileController::class, 'token']);
	Route::post('/profile/iagree', [ProfileController::class, 'iAgree']);
	Route::post('/profile/password/update', [ProfileController::class, 'updatePassword']);
	Route::post('/profile/mobile/update', [ProfileController::class, 'mobileUpdate']);
	Route::post('/profile/mobile/verify', [ProfileController::class, 'mobileVerify']);
	Route::post('/profile/activity/detail', [ProfileController::class, 'activityDetail']);

	Route::post('/profile/covered/locations', [ProfileController::class, 'locationIndex']);
	Route::post('/profile/covered/locations/lists', [ProfileController::class, 'locationList']);
	Route::post('/profile/covered/locations/update', [ProfileController::class, 'locationUpdate']);
	Route::post('/profile/covered/locations/delete', [ProfileController::class, 'locationDelete']);

	Route::post('/city/list', [CityController::class, 'listCity']);
	Route::post('/city/confirm', [CityController::class, 'confirmCity']);

	Route::post('/help/ticket', [TicketController::class, 'index']);
	Route::post('/help/ticket/detail', [TicketController::class, 'detail']);
	Route::post('/help/ticket/order/lists', [TicketController::class, 'orderLists']);
	Route::post('/help/ticket/store', [TicketController::class, 'store']);
	Route::post('/help/ticket/reply', [TicketController::class, 'reply']);

	Route::post('/notification/index', [NotificationController::class, 'index']);
	Route::post('/notification/unread', [NotificationController::class, 'unread']);
	Route::post('/notification/read', [NotificationController::class, 'read']);
	Route::post('/notification/read_all', [NotificationController::class, 'read_all']);
	Route::post('/notification/delete', [NotificationController::class, 'delete']);

	Route::post('/banners', [\App\Http\Controllers\Api\BannerController::class, 'index']);

	// role customer
	Route::group([
	    'prefix' => 'customer',
	    'middleware' => ['role:customer']
	], function () {
		Route::post('/home', [\App\Http\Controllers\Api\Customer\HomeController::class, 'home']);

		// service coverage check + waiting list
		Route::post('/coverage/check', [\App\Http\Controllers\Api\Customer\CoverageController::class, 'check']);
		Route::post('/coverage/waiting-list', [\App\Http\Controllers\Api\Customer\CoverageController::class, 'joinWaitingList']);

		// announcements
		Route::post('/home/announcements', [\App\Http\Controllers\Api\Customer\AnnouncementController::class, 'announcements']);

		Route::post('/profile', [\App\Http\Controllers\Api\Customer\ProfileController::class, 'profile']);
		Route::post('/profile/update', [\App\Http\Controllers\Api\Customer\ProfileController::class, 'profileUpdate']);
		Route::post('/profile/verify', [\App\Http\Controllers\Api\Customer\ProfileController::class, 'verifyUpdate']);

		Route::post('/profile/address/store', [\App\Http\Controllers\Api\Customer\AddressController::class, 'saveAddress']);
		Route::post('/profile/address/update', [\App\Http\Controllers\Api\Customer\AddressController::class, 'updateAddress']);
		Route::post('/profile/address/delete', [\App\Http\Controllers\Api\Customer\AddressController::class, 'deleteAddress']);

		// retrieve qrcode
		Route::post('/profile/bag/qrcode', [\App\Http\Controllers\Api\Customer\BagController::class, 'bagQrcode']);

		// scan qrcode
		Route::post('/profile/bag/scan', [\App\Http\Controllers\Api\Customer\BagController::class, 'bagScan']);

		// purchased bag
		Route::post('/profile/bag/purchased', [\App\Http\Controllers\Api\Customer\BagController::class, 'bagPurchased']);

		// qrcodes of purchased bag
		Route::post('/profile/bag/assigned', [\App\Http\Controllers\Api\Customer\BagController::class, 'bagAssigned']);
		
		// purchase bag
		Route::post('/order/bag/placeorder', [\App\Http\Controllers\Api\Customer\OrderBagController::class, 'placeOrder']);

		// subscription
		Route::post('/subscription/placeorder', [\App\Http\Controllers\Api\Customer\SubscriptionController::class, 'placeOrder']);
		Route::post('/subscription/cancel', [\App\Http\Controllers\Api\Customer\SubscriptionController::class, 'cancelSubscription']);
		Route::post('/subscription/update', [\App\Http\Controllers\Api\Customer\SubscriptionController::class, 'updateSubscription']);
		Route::post('/subscription/upgrade', [\App\Http\Controllers\Api\Customer\SubscriptionController::class, 'upgrade']);
		Route::post('/subscription/history', [\App\Http\Controllers\Api\Customer\SubscriptionController::class, 'history']);
		Route::post('/notifications', [\App\Http\Controllers\Api\Customer\NotificationController::class, 'index']);
		Route::post('/notifications/read', [\App\Http\Controllers\Api\Customer\NotificationController::class, 'markRead']);
		
		// assign qrcodes of purchased bag
		Route::post('/qrcode/assign', [\App\Http\Controllers\Api\Customer\QrcodeController::class, 'assignQrcode']);

		// booking
		Route::post('/booking/calculate/rate', [\App\Http\Controllers\Api\Customer\BookingController::class, 'calculateRate']);		
		Route::post('/booking/addon', [\App\Http\Controllers\Api\Customer\BookingController::class, 'getAddOn']);		
		Route::post('/booking/addon/lists', [\App\Http\Controllers\Api\Customer\BookingController::class, 'addOnList']);		
		Route::post('/booking/voucher', [\App\Http\Controllers\Api\Customer\BookingController::class, 'checkVoucher']);		
		Route::post('/booking/voucher/lists', [\App\Http\Controllers\Api\Customer\BookingController::class, 'voucherList']);		
		Route::post('/booking/qrcodes', [\App\Http\Controllers\Api\Customer\BookingController::class, 'qrcodeList']);		
		Route::post('/booking/schedule', [\App\Http\Controllers\Api\Customer\BookingController::class, 'schedule']);		
		Route::post('/booking/instructions', [\App\Http\Controllers\Api\Customer\BookingController::class, 'instructions']);
		
		// check birthday
		Route::post('/booking/birthday/check', [\App\Http\Controllers\Api\Customer\BookingController::class, 'checkBirthday']);

		// check addon discount
		Route::post('/booking/addon/check-discount', [\App\Http\Controllers\Api\Customer\BookingController::class, 'checkAddonDiscount']);

		// check insurance
		Route::post('/booking/insurance/check', [\App\Http\Controllers\Api\Customer\BookingController::class, 'checkInsurance']);
		
		// orders
		Route::post('/order/active', [\App\Http\Controllers\Api\Customer\OrderController::class, 'orderActive']);
		Route::post('/order/upcoming', [\App\Http\Controllers\Api\Customer\OrderController::class, 'orderUpcoming']);

		Route::post('/order/detail', [\App\Http\Controllers\Api\Customer\OrderController::class, 'orderDetail']);
		Route::post('/order/rating', [\App\Http\Controllers\Api\Customer\OrderController::class, 'orderRating']);
	});	

	// role rider
	Route::group([
	    'prefix' => 'rider',
	    'as' => 'api.rider.',
	    'middleware' => ['role:rider']
	], function () {

		// home
		Route::post('/home', [\App\Http\Controllers\Api\Rider\HomeController::class, 'home']);
		Route::post('/home/duty', [\App\Http\Controllers\Api\Rider\HomeController::class, 'updateDuty']);
		Route::post('/activity/history', [\App\Http\Controllers\Api\Rider\HomeController::class, 'activityHistory']);

		// profile
		Route::post('/profile', [\App\Http\Controllers\Api\Rider\ProfileController::class, 'profile']);
		Route::post('/profile/update', [\App\Http\Controllers\Api\Rider\ProfileController::class, 'profileUpdate']);

		// lists of qrcodes
		Route::post('/order/qrcodes', [\App\Http\Controllers\Api\Rider\OrderController::class, 'listQrcodes']);
		
		// accept order
		Route::post('/order/accept', [\App\Http\Controllers\Api\Rider\OrderController::class, 'acceptOrder']);
		
		// pickup order
		Route::post('/order/pickup', [\App\Http\Controllers\Api\Rider\PickupController::class, 'pickupOrder']);

		// pickup bag from wash outlet
		Route::post('/order/pickup/outlet', [\App\Http\Controllers\Api\Rider\PickupOutletController::class, 'pickupWashOutletConfirm']);

		// delivery to customer
		Route::post('/order/delivery', [\App\Http\Controllers\Api\Rider\DeliveryController::class, 'deliveryConfirm']);

		// upload delivery
		Route::post('/order/delivery/upload', [\App\Http\Controllers\Api\Rider\DeliveryController::class, 'deliveryUpload']);

		// scan qrcode
		Route::post('/scan/qrcode', [\App\Http\Controllers\Api\Rider\ScanQrcodeController::class, 'scanQrcode']);

		// order details
		Route::post('/order/detail', [\App\Http\Controllers\Api\Rider\OrderController::class, 'orderDetail']);

		// re-apply
		Route::post('/re-apply/update', [\App\Http\Controllers\Api\Rider\ReapplyController::class, 'reApplyUpdate']);
	});	

	// role merchant
	Route::group([
	    'prefix' => 'merchant',
	    'as' => 'api.merchant.',
	    'middleware' => ['role:merchant']
	], function () {

		// home
		Route::post('/home', [\App\Http\Controllers\Api\Merchant\HomeController::class, 'home']);
		Route::post('/home/duty', [\App\Http\Controllers\Api\Merchant\HomeController::class, 'updateDuty']);
		Route::post('/activity/history', [\App\Http\Controllers\Api\Merchant\HomeController::class, 'activityHistory']);

		// select cities
		Route::post('/home/city', [\App\Http\Controllers\Api\Merchant\HomeController::class, 'selectCity']);

		// profile
		Route::post('/profile', [\App\Http\Controllers\Api\Merchant\ProfileController::class, 'profile']);
		Route::post('/profile/update', [\App\Http\Controllers\Api\Merchant\ProfileController::class, 'profileUpdate']);

		// lists of qrcodes
		Route::post('/order/qrcodes', [\App\Http\Controllers\Api\Merchant\OrderController::class, 'listQrcodes']);
		
		// accept order
		Route::post('/order/accept', [\App\Http\Controllers\Api\Merchant\OrderController::class, 'acceptOrder']);
		
		// receive bag
		Route::post('/bag/receive', [\App\Http\Controllers\Api\Merchant\BagController::class, 'bagReceive']);

		// wash complete
		Route::post('/wash/complete', [\App\Http\Controllers\Api\Merchant\WashController::class, 'washComplete']);
		
		// scan qrcode
		Route::post('/scan/qrcode', [\App\Http\Controllers\Api\Merchant\ScanQrcodeController::class, 'scanQrcode']);

		// order details
		Route::post('/order/detail', [\App\Http\Controllers\Api\Merchant\OrderController::class, 'orderDetail']);

		// re-apply
		Route::post('/re-apply/update', [\App\Http\Controllers\Api\Merchant\ReapplyController::class, 'reApplyUpdate']);
	});	

	// payment
	Route::get('/payment', [\App\Http\Controllers\Api\PaymentController::class, 'makePayment'])->name('api.payment');

});

// Route::post('/auth/password/reset',  [AuthController::class, 'resetPassword']);
// Route::post('/profile/me', [ProfileController::class, 'me']);
// Route::post('/profile/token', [ProfileController::class, 'token']);


