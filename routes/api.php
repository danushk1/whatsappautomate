<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WhatsAppBridgeController;
use App\Http\Controllers\NodeBridgeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Option A: WhatsApp Cloud API Webhook
Route::get('/whatsapp/webhook', [WebhookController::class, 'verifyWebhook']);
Route::post('/whatsapp/webhook', [WebhookController::class, 'processIncomingWebhook']);

// Option B: whatsapp-web.js Automation Webhook (Node.js Bridge sends messages here)
Route::post('/whatsapp/webhook/automation', [WebhookController::class, 'processAutomationWebhook'])
    ->middleware('node.bridge.auth');

// Resolve real phone number from WhatsApp contact (fixes LID format contacts)
Route::post('/whatsapp/resolve-contact', [WebhookController::class, 'resolveContact'])
    ->middleware('node.bridge.auth');

// Node Bridge Management (QR, Session, Status)
Route::prefix('node-bridge')->middleware('node.bridge.auth')->group(function () {
    Route::post('/send-message', [NodeBridgeController::class, 'sendMessage']);
    Route::post('/update-qr-status', [NodeBridgeController::class, 'updateQRStatus']);
    Route::post('/update-connection-status', [NodeBridgeController::class, 'updateConnectionStatus']);
    Route::post('/update-session', [NodeBridgeController::class, 'updateSession']);
});

// Internal callback — Python calls this after background AI processing to deduct credits
Route::post('/internal/deduct-credits', [WebhookController::class, 'deductCredits']);