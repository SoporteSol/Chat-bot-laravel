<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstanciaController;
use App\Http\Controllers\MetaOnboardingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Redirección inicial a la vista principal de Instancias
Route::get('/', function () {
    return Auth::check() ? redirect()->route('instancias.index') : redirect('/login');
});

// Rutas públicas de Onboarding con Meta (protegidas con Rate Limiting)
Route::get('/conectar-meta/{token}', [MetaOnboardingController::class, 'landing'])
    ->middleware('throttle:15,1')
    ->name('meta.onboarding.landing');
Route::post('/conectar-meta/{token}/completar', [MetaOnboardingController::class, 'completar'])
    ->middleware('throttle:5,1')
    ->name('meta.onboarding.completar');

// Rutas de autenticación (sin registro abierto)
Auth::routes(['register' => false]);

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
    // Consola principal para el Administrador
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Endpoints compartidos para admin y asesores de empresa
    Route::middleware(['role:admin,empresa'])->prefix('instancias')->name('instancias.')->group(function () {
        Route::get('/', [InstanciaController::class, 'index'])->name('index');
        Route::get('/{id}', [InstanciaController::class, 'show'])->whereNumber('id')->name('show');

        // Operaciones de contactos, chat, notas, tareas y catálogos
        Route::post('/{id}/contactos', [InstanciaController::class, 'crearContactoManual'])->name('contactos.store');
        Route::get('/{id}/contactos/exportar', [InstanciaController::class, 'exportarContactosCsv'])->name('contactos.exportar');
        Route::post('/{id}/contactos/{contactoId}/toggle-bot', [InstanciaController::class, 'toggleBot'])->name('contactos.toggleBot');
        Route::get('/{id}/contactos/{contactoId}/historial', [InstanciaController::class, 'chatHistorial'])->name('contactos.historial');
        Route::post('/{id}/contactos/{contactoId}/enviar', [InstanciaController::class, 'enviarMensajeDirecto'])->name('contactos.enviar');
        Route::patch('/{id}/contactos/{contactoId}/crm', [InstanciaController::class, 'actualizarCrm'])->name('contactos.crm');
        Route::post('/{id}/contactos/{contactoId}/notas', [InstanciaController::class, 'agregarNota'])->name('contactos.notas');
        Route::post('/{id}/contactos/{contactoId}/tareas', [InstanciaController::class, 'gestionarTarea'])->name('contactos.tareas');
        Route::post('/{id}/contactos/{contactoId}/ia-resumen', [InstanciaController::class, 'iaResumen'])->name('contactos.iaResumen');
        Route::post('/{id}/contactos/{contactoId}/ia-sugerir', [InstanciaController::class, 'iaSugerir'])->name('contactos.iaSugerir');
        Route::post('/{id}/contactos/{contactoId}/ia-tono', [InstanciaController::class, 'iaTono'])->name('contactos.iaTono');
        Route::post('/{id}/catalogo', [InstanciaController::class, 'gestionarCatalogo'])->name('catalogo.manage');
        Route::post('/{id}/plantillas', [InstanciaController::class, 'gestionarPlantillas'])->name('plantillas.manage');
        Route::post('/{id}/etapas', [InstanciaController::class, 'guardarEtapasEmbudo'])->name('etapas.save');
        Route::post('/{id}/campos-personalizados', [InstanciaController::class, 'gestionarCamposPersonalizados'])->name('campos.manage');
        Route::post('/{id}/automatizaciones', [InstanciaController::class, 'guardarAutomatizacionesEmbudo'])->name('automatizaciones.save');
        Route::post('/{id}/difusion', [InstanciaController::class, 'enviarDifusionMasiva'])->name('difusion.send');

        // Cotizaciones, horarios de atención, webhooks y API
        Route::get('/{id}/contactos/{contactoId}/cotizacion/pdf', [InstanciaController::class, 'generarCotizacionPdf'])->name('contactos.cotizacion.pdf');
        Route::post('/{id}/contactos/{contactoId}/cotizacion/enviar', [InstanciaController::class, 'enviarCotizacionWhatsApp'])->name('contactos.cotizacion.enviar');
        Route::post('/{id}/contactos/importar', [InstanciaController::class, 'importarContactosCsv'])->name('contactos.importar');
        Route::post('/{id}/horario', [InstanciaController::class, 'guardarHorarioAtencion'])->name('horario.save');
        Route::post('/{id}/base-conocimiento', [InstanciaController::class, 'gestionarBaseConocimiento'])->name('base-conocimiento.manage');
        Route::post('/{id}/datos-fiscales', [InstanciaController::class, 'guardarDatosFiscales'])->name('datos-fiscales.save');
        Route::post('/{id}/webhook-saliente', [InstanciaController::class, 'guardarWebhookSaliente'])->name('webhook-saliente.save');
        Route::post('/{id}/api-token', [InstanciaController::class, 'generarApiToken'])->name('api-token.generate');
    });

    // Rutas exclusivas de administración: crear instancias, prompt IA y usuarios
    Route::middleware(['role:admin'])->group(function () {
        // Generador y verificador de sesiones de onboarding QR
        Route::post('/meta/onboarding-token', [MetaOnboardingController::class, 'generarToken'])->name('meta.onboarding.token');
        Route::get('/meta/onboarding-status/{token}', [MetaOnboardingController::class, 'verificarEstado'])->name('meta.onboarding.status');
        Route::prefix('instancias')->name('instancias.')->group(function () {
            Route::post('/', [InstanciaController::class, 'store'])->name('store');
            Route::post('/generar-demo', [InstanciaController::class, 'generarDemo'])->name('demo');
            Route::put('/{id}', [InstanciaController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [InstanciaController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/test-prompt', [InstanciaController::class, 'testPrompt'])->whereNumber('id')->name('testPrompt');
            Route::post('/{id}/usuarios', [InstanciaController::class, 'crearUsuarioEmpresa'])->whereNumber('id')->name('usuarios.store');
            Route::delete('/{id}/usuarios/{userId}', [InstanciaController::class, 'eliminarUsuarioEmpresa'])->whereNumber('id')->name('usuarios.destroy');
        });

        // Redirección para URLs antiguas de contactos
        Route::get('/panel-contactos', function () {
            return redirect()->route('instancias.index');
        })->name('contactos.index');

        // Endpoints para guardar y editar nodos y ramas del chatbot
        Route::prefix('chatbot')->name('chatbot.')->group(function () {
            Route::get('/', [ChatbotController::class, 'index'])->name('index');
            Route::get('/node/{id}', [ChatbotController::class, 'show'])->name('show');
            Route::post('/node', [ChatbotController::class, 'storeNode'])->name('node.store');
            Route::put('/node/{id}', [ChatbotController::class, 'updateNode'])->name('node.update');
            Route::delete('/node/{id}', [ChatbotController::class, 'destroyNode'])->name('node.destroy');
            Route::post('/node/upload-media', [ChatbotController::class, 'uploadNodeMedia'])->name('node.upload_media');
            Route::post('/option', [ChatbotController::class, 'storeOption'])->name('option.store');
            Route::put('/option/{id}', [ChatbotController::class, 'updateOption'])->name('option.update');
            Route::delete('/option/{id}', [ChatbotController::class, 'deleteOption'])->name('option.delete');
        });
    });
});
