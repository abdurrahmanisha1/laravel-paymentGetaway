<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BkashPaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::get('/bkash/payment/init', [BkashPaymentController::class, 'index']);
Route::post('bkash/payment/create', [BkashPaymentController::class, 'createPayment']);
Route::post('/bkash/payment/callback', [BkashPaymentController::class, 'callBack'])->name('bkash.payment.callback');
