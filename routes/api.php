<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OpenaiController;
use App\Http\Controllers\whatsappController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });
});

Route::prefix('webhook')->middleware('throttle:120,1')->group(function () {
    Route::get('/', [whatsappController::class, 'verificarWebhook']);
    Route::post('/', [whatsappController::class, 'recibirWebhook']);
});
Route::get('/webhook', [whatsappController::class, 'verificarWebhook'])->middleware('throttle:120,1');
Route::post('/webhook', [whatsappController::class, 'recibirWebhook'])->middleware('throttle:120,1');

Route::middleware(['throttle:60,1', 'auth:api'])->get('/user', [AuthController::class, 'me']);

Route::middleware(['throttle:60,1', 'auth:api'])->group(function () {
    Route::post('/enviar-mensaje', [whatsappController::class, 'enviarMensaje']);
});

Route::middleware(['throttle:15,1', 'auth:api'])->group(function () {
    Route::post('/openai/text', [OpenaiController::class, 'funRespuestaIA']);
});

// API para integraciones externas (Zapier, Make, landings, etc.)
Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::get('/leads', [App\Http\Controllers\Api\CrmApiController::class, 'listarLeads']);
    Route::post('/leads', [App\Http\Controllers\Api\CrmApiController::class, 'crearOActualizarLead']);
    Route::post('/mensajes', [App\Http\Controllers\Api\CrmApiController::class, 'enviarMensaje']);
});


