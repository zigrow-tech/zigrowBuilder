<?php


use App\Http\Controllers\PayuController;
use App\Http\Controllers\PlanPaymentController;


use App\Http\Controllers\FormController;

use App\Http\Controllers\TemplateController;
use App\Http\Controllers\MainWebsiteLeadsController;






Route::post('/support/contact', [MainWebsiteLeadsController::class, 'contactformleads']);


Route::post('/payment-success', [PayuController::class, 'paymentSuccess']);
    Route::post('/payment-failure', [PayuController::class, 'paymentFailure']);
    Route::post('/plan/payment-success', [PlanPaymentController::class, 'paymentSuccess'])->name('plan.payment.success');
Route::post('/plan/payment-failure', [PlanPaymentController::class, 'paymentFailure'])->name('plan.payment.failure');

Route::get('/invoice/{txnid}', [PlanPaymentController::class, 'showInvoice']);

// For createing copy of generated template by AI

// In routes/api.php
Route::post('/test-cors', function () {
    return response()->json(['msg' => 'CORS test OK']);
});


Route::get('/debug-cors-config', function () {
    return response()->json([
        'allowed_origins' => config('cors.allowed_origins'),
        'allowed_origins_patterns' => config('cors.allowed_origins_patterns'),
        'supports_credentials' => config('cors.supports_credentials'),
        'env' => app()->environment(),
    ]);
});

Route::get('/debug-cors', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'allowed_origins' => config('cors.allowed_origins'),
        'allowed_origins_patterns' => config('cors.allowed_origins_patterns'),
        'supports_credentials' => config('cors.supports_credentials'),
        'actual_origin' => $request->header('Origin'),
        'env' => app()->environment(),
    ]);
});

Route::post('/newsletter/store', [MainWebsiteLeadsController::class, 'newsletterMails']);
Route::post('/custom_help/store', [MainWebsiteLeadsController::class, 'customForm']);

Route::post('/forms/submit', [FormController::class, 'submit'])->name('forms.submit');
