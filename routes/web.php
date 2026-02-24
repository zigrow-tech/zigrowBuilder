<?php

use App\Http\Controllers\CreditsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PayuController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\PlanPaymentController;
use App\Http\Controllers\PlanStatusController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\WebsiteController;
use App\Models\Template;
use App\Models\User;
use App\Models\PublishedDomain;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\ZohoController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormLeadController;
use App\Http\Controllers\UserTokenController;
use App\Http\Controllers\AiWriterController;


// ✅ Get CSRF cookie for Sanctum
Route::get('/_internal/builder-auth', function () {
    if (!Auth::check()) {
        abort(401);
    }
    return response('OK', 204); // 204 is clean for auth_request
});
Route::get('/fix-template', [TemplateController::class, 'get_fixed_template']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/register', [RegisterController::class, 'store']); // <== ADD THIS
Route::post('/check-email', [RegisterController::class, 'checkEmail'])->name('check.email');
Route::post('/forgot-password', [AuthController::class, 'forgot_password']);
Route::get('/reset-password/{token}', [AuthController::class, 'reset_password_token'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'reset_password'])->middleware('throttle:5,1');

// ✅ Authenticated routes
Route::middleware('auth')->group(function () {
	Route::post('/user/get_user', [AuthController::class, 'get_loggedin_user']);
	Route::get('/profile', fn() => response()->json(['user' => Auth::user()]));
	Route::put('/profile/update', [ProfileController::class, 'update']);
	Route::put('/profile/update-password', [ProfileController::class, 'updatePassword']);
	Route::post('/template/save', [TemplateController::class, 'save']);
	Route::get('/template/load', [TemplateController::class, 'load']);
	Route::delete('/templates/delete', [TemplateController::class, 'delete']);
	Route::post('/templates/delete', [TemplateController::class, 'delete']);
	Route::get('/user/saved-template', [TemplateController::class, 'saved_template']);
	Route::post('/leads', [LeadController::class, 'store']);
	Route::get('/leads', [LeadController::class, 'getMarketingLeads']);
	Route::get('/leads/disabled', [LeadController::class, 'disabledOffers']);
	Route::post('/initiate-payment', [PayuController::class, 'initiatePayment']);
	Route::post('/payu/plan/initiate', [PlanPaymentController::class, 'initiate']);
	Route::get('/user/plan-payments', [PlanPaymentController::class, 'get_plan_payments']);
	Route::get('/user/hire-us-payments', [PayuController::class, 'getHireUsPayments']);
	Route::get('/user/plan-status', [PlanStatusController::class, 'check']);
	Route::get('/user/domain', [DomainController::class, 'get_user_domain']);
	Route::post('/user/create-subdomain', [DomainController::class, 'create']);
	Route::post('/user/custom-domain', [DomainController::class, 'saveCustom']);
	Route::post('/user/save-seo', [TemplateController::class, 'saveSeo']);
	Route::get('/user/seo', [TemplateController::class, 'getSeo']);
	Route::get('/user/check-subdomain', [DomainController::class, 'checkAvailability']);
	Route::get('/check-subdomain', [DomainController::class, 'checkAvailability']);
	Route::post('/user/verify-custom-domain', [DomainController::class, 'verifyDNS']);
	Route::delete('/user/custom-domain', [DomainController::class, 'deleteCustomDomain']);
	Route::get('/user/template-today-count', [AnalyticsController::class, 'getTodayVisitCount']);
	Route::get('/user/monthly-visits', [AnalyticsController::class, 'getMonthlyVisitData']);
	Route::get('/user/media-list', [MediaController::class, 'list']);
	Route::post('/user/media-upload', [MediaController::class, 'upload']);
	Route::post('/user/media-delete', [MediaController::class, 'delete']);
    Route::post('/user/media-rename', [MediaController::class, 'rename']) ->name('media.rename');
	Route::post('/user/media-meta', [MediaController::class,'updateMeta'])->name('media.meta');
	Route::get('/user/media-ai-list', [MediaController::class, 'listAi']);
	Route::get('/media/stock/search', [MediaController::class, 'searchStockImages'])->name('media.stock.search');
	Route::get('/user/secure-preview-link', [TemplateController::class, 'getPreviewLink']);
	Route::post('/import-template', [TemplateController::class, 'importAI']);

	Route::post('/user/get_user', [AuthController::class, 'get_loggedin_user']);
	Route::get('/credits/get_remaining', [CreditsController::class, 'remaining_credits']);
	Route::post('/credits/change_remaining', [CreditsController::class, 'change_remaining']);
	 Route::get('/user/leads', [FormLeadController::class, 'index']);   
	 Route::get('/user/forms-today-count', [FormLeadController::class, 'todayCount']); 
	 Route::get('/template/ai-load', [TemplateController::class, 'aiLoad'])->middleware('auth');
	 Route::get('/user/tokens/status', [UserTokenController::class, 'status']);
    Route::post('/user/tokens/consume', [UserTokenController::class, 'consume']);
    // optional (template token stats only)
    Route::post('/user/tokens/record-template', [UserTokenController::class, 'recordTemplateTokens']);
	Route::post('/user/ai-writer/generate', [AiWriterController::class, 'generate']);


});
Route::get('/zoho/authorize', [ZohoController::class, 'authorizeRedirect']);
Route::get('/zoho/callback', [ZohoController::class, 'handleCallback']);
Route::domain('{subdomain}.zigrow.com')->group(function () {
	Route::get('/', [WebsiteController::class, 'showTemplate']);
});
Route::get('/', [WebsiteController::class, 'showTemplate']);
Route::get('/auth/redirect/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/callback/google', [GoogleController::class, 'callback'])->name('google.callback');

Route::get('/auth/redirect/facebook', [GoogleController::class, 'redirectFacebook']);
Route::get('/auth/callback/facebook', [GoogleController::class, 'callbackFacebook']);

