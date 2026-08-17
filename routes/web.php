<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Portal\BkashPaymentController;
use App\Http\Controllers\Api\Portal\NagadPaymentController;
use App\Http\Controllers\Api\Portal\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// ---------------------------------------------------------------------------
// Payment gateway callbacks (browser redirects from bKash / Nagad)
// ---------------------------------------------------------------------------
// These are intentionally unauthenticated: the merchant gateways redirect the
// customer's browser here, so no Bearer token exists. Settlement safety comes
// from verifying the payment server-side with the gateway before applying it.
Route::get('/portal/payments/bkash/callback', [BkashPaymentController::class, 'callback']);
Route::get('/portal/payments/nagad/callback', [NagadPaymentController::class, 'callback']);

// SSLCommerz callbacks (browser redirects + IPN server notification).
// SSLCommerz POSTs form data here; settlement is verified server-side with
// the gateway before being applied (same trust model as bKash / Nagad).
Route::post('/portal/payments/sslcommerz/success', [PaymentController::class, 'sslcommerzSuccess']);
Route::post('/portal/payments/sslcommerz/fail', [PaymentController::class, 'sslcommerzFail']);
Route::post('/portal/payments/sslcommerz/cancel', [PaymentController::class, 'sslcommerzCancel']);
Route::post('/portal/payments/sslcommerz/ipn', [PaymentController::class, 'sslcommerzIpn']);
