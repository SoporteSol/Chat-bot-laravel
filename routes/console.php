<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('whatsapp:setup-partner {waba_id} {token?}', function ($waba_id, $token = null) {
    $token = $token ?: env('WHATSAPP_ACCESS_TOKEN');
    if (!$token) {
        $this->error('Error: Debes proporcionar un access_token o tener WHATSAPP_ACCESS_TOKEN en el .env');
        return 1;
    }

    $this->info("=== PASO 3: Suscribiendo App a la WABA ($waba_id) ===");
    $resSub = \Illuminate\Support\Facades\Http::withToken($token)
        ->post("https://graph.facebook.com/v22.0/{$waba_id}/subscribed_apps");

    $this->line("Respuesta de suscripción: " . $resSub->body());
    if (!$resSub->successful()) {
        $this->warn("Aviso: No se pudo suscribir automáticamente. Verifica que el token tenga permisos sobre esa WABA.");
    } else {
        $this->info("✓ App suscrita exitosamente a la WABA!");
    }

    $this->info("\n=== PASO 4: Obteniendo números de teléfono de la WABA ===");
    $resPhones = \Illuminate\Support\Facades\Http::withToken($token)
        ->get("https://graph.facebook.com/v22.0/{$waba_id}/phone_numbers");

    $this->line("Respuesta: " . $resPhones->body());
    $data = $resPhones->json('data') ?? [];

    if (empty($data)) {
        $this->warn("No se encontraron números en esta WABA o el token no tiene permisos para leerlos.");
        return 0;
    }

    foreach ($data as $phone) {
        $phoneId = $phone['id'];
        $display = $phone['display_phone_number'] ?? $phoneId;
        $name = $phone['verified_name'] ?? 'Empresa';
        $this->info("\nProcesando número: $display (ID: $phoneId, Nombre: $name)");

        $this->info("=== PASO 5: Registrando número con Cloud API (PIN 123456) ===");
        $resReg = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://graph.facebook.com/v22.0/{$phoneId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => '123456'
            ]);
        $this->line("Resultado de registro: " . $resReg->body());

        // Actualizar o crear instancia en base de datos
        $cleanPhone = preg_replace('/\D+/', '', $display);
        $instancia = \App\Models\Instancia::updateOrCreate(
            ['phone_number_id' => $phoneId],
            [
                'nombre_empresa' => $name,
                'telefono'       => $cleanPhone,
                'waba_id'        => $waba_id,
                'access_token'   => $token,
                'is_active'      => true,
                'system_prompt'  => "Eres el asistente comercial y asesor virtual de {$name}. Responde de forma cordial, breve y profesional."
            ]
        );

        $this->info("✓ Instancia #{$instancia->id} vinculada y activa en la base de datos.");
    }

    $this->info("\n¡Configuración completada con éxito según la guía oficial!");
    return 0;
})->purpose('Automatiza los pasos 3, 4 y 5 de la guía oficial de Solution Partner de Meta');

