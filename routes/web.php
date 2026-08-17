<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Portal\BkashPaymentController;
use App\Http\Controllers\Api\Portal\NagadPaymentController;

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
