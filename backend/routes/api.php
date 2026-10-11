<?php

use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SimulatorController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::get('meta', MetaController::class);

Route::get('chatbots', [ChatbotController::class, 'index']);
Route::post('chatbots', [ChatbotController::class, 'store']);
Route::get('chatbots/{chatbot}', [ChatbotController::class, 'show']);
Route::patch('chatbots/{chatbot}', [ChatbotController::class, 'update']);
Route::delete('chatbots/{chatbot}', [ChatbotController::class, 'destroy']);
Route::put('chatbots/{chatbot}/graph', [ChatbotController::class, 'saveGraph']);
Route::post('chatbots/validate', [ChatbotController::class, 'validateGraph']);
Route::post('chatbots/{chatbot}/publish', [ChatbotController::class, 'publish']);
Route::get('chatbots/{chatbot}/versions', [ChatbotController::class, 'versions']);
Route::put('chatbots/{chatbot}/channels', [ChatbotController::class, 'channels']);

Route::post('chatbots/{chatbot}/simulate', [SimulatorController::class, 'message'])->middleware('throttle:120,1');
Route::post('chatbots/{chatbot}/simulate/reset', [SimulatorController::class, 'reset']);

Route::get('chatbots/{chatbot}/sessions', [SessionController::class, 'index']);
Route::get('sessions/{session}', [SessionController::class, 'show']);
Route::post('sessions/{session}/close', [SessionController::class, 'close']);

Route::post('channels/{channel}/webhook', WebhookController::class)
    ->middleware([VerifyWebhookSignature::class, 'throttle:300,1']);
