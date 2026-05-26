<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/order/create', [OrderController::class, 'create']);
Route::get('/order/{orderId}', [OrderController::class, 'get']);
Route::post('/order/{orderId}', [OrderController::class, 'cancel']);

Route::post('/webhook', [WebhookController::class, 'handle']);
