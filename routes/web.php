<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mock-photo-payment', function (Request $request) {
    $intentId = $request->query('intent');
    $photoId = $request->query('photo');
    $amount = $request->query('amount');
    $currency = $request->query('currency', 'USD');
    $returnUrl = $request->query('return_url');
    $cancelUrl = $request->query('cancel_url');

    return view('mock-photo-payment', [
        'intentId' => $intentId,
        'photoId' => $photoId,
        'amount' => $amount,
        'currency' => $currency,
        'returnUrl' => $returnUrl,
        'cancelUrl' => $cancelUrl,
    ]);
});
