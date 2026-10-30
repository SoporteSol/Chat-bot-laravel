<?php

namespace App\Http\Controllers;

use App\Models\Instancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MetaOnboardingController extends Controller
{
    // Formato del token de invitación
    protected function isValidTokenFormat($token): bool
    {
        return is_string($token) && (bool) preg_match('/^[a-zA-Z0-9]{32,128}$/', $token);
    }

    // Genera token temporal y QR para invitar al cliente a conectar su WhatsApp
    public function generarToken(Request $request)
    {
        $token = Str::random(64);
        $url = route('meta.onboarding.landing', ['token' => $token]);

        Cache::put("meta_onboarding_{$token}", [
            'status'     => 'pending',
            'created_at' => now()->toIso8601String(),
        ], now()->addHours(3));

        return response()->json([
            'success' => true,
            'token'   => $token,
            'url'     => $url,
            'qr_url'  => 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' . urlencode($url),
        ]);
    }

    // Polling del estado del onboarding desde el modal del admin
    public function verificarEstado($token)
    {
        if (!$this->isValidTokenFormat($token)) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'Token de invitación inválido.',
            ], 404);
        }

        $session = Cache::get("meta_onboarding_{$token}");

        if (!$session) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'La sesión de invitación ha expirado o no existe.',
            ]);
        }

        if ($session['status'] === 'completed') {
            return response()->json([
                'status'         => 'completed',
                'instancia_id'   => $session['instancia_id'],
                'nombre_empresa' => $session['nombre_empresa'] ?? 'Empresa Conectada',
                'telefono'       => $session['telefono'] ?? null,
                'redirect_url'   => route('instancias.show', $session['instancia_id']),
            ]);
        }

        return response()->json([
            'status'     => 'pending',
            'created_at' => $session['created_at'],
        ]);
    }

    // Pantalla pública donde el cliente realiza el login con Facebook / WhatsApp
    public function landing(Request $request, $token)
    {
        $session = $this->isValidTokenFormat($token) ? Cache::get("meta_onboarding_{$token}") : null;
        $errorMessage = null;

        if ($session && ($session['status'] ?? '') !== 'completed' && $request->has('code')) {
            $response = $this->completar($request, $token);
            $session = Cache::get("meta_onboarding_{$token}");

            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $data = $response->getData(true);
                if (empty($data['success'])) {
                    $errorMessage = $data['message'] ?? 'No se pudo completar la vinculación automática.';
                }
            }
        }

        $isExpired = !$session;
        $isCompleted = $session && ($session['status'] ?? '') === 'completed';

        $metaAppId = config('services.meta.app_id', '');
        $metaConfigId = config('services.meta.config_id', '');

        return response()->view('meta.onboarding', compact(
            'token',
            'isExpired',
            'isCompleted',
            'metaAppId',
            'metaConfigId',
            'errorMessage'
        ), $isExpired ? 404 : 200);
    }

    // Callback donde recibimos los tokens de Meta una vez conectado el cliente
    public function completar(Request $request, $token)
    {
        if (!$this->isValidTokenFormat($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Token de invitación inválido.',
            ], 404);
        }

        $session = Cache::get("meta_onboarding_{$token}");

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'La sesión de invitación ha expirado o no existe.',
            ], 404);
        }

        // Si ya se usó este token, no permitimos reutilizarlo
        if (($session['status'] ?? '') === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Este enlace de invitación ya fue utilizado y no admite modificaciones.',
            ], 409);
        }

        $code = $request->input('code');
        $phoneId = $request->input('phone_number_id');
        $wabaId = $request->input('waba_id');
        $nombreEmpresa = $request->input('nombre_empresa');
        $telefono = $request->input('telefono');
        $accessToken = $request->input('access_token');

        // Necesitamos code o accessToken para validar contra la Graph API
        if (empty($code) && empty($accessToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Se requiere autorización válida de Meta (code o access_token) para completar la vinculación.',
            ], 422);
        }

        $metaAppId = config('services.meta.app_id', '');
        $metaAppSecret = config('services.meta.app_secret', '');
        $apiVersion = config('services.whatsapp.api_version', 'v22.0');

        if ($code && $metaAppSecret) {
            try {
                $tokenResponse = Http::timeout(4)->get("https://graph.facebook.com/{$apiVersion}/oauth/access_token", [
                    'client_id'     => $metaAppId,
                    'client_secret' => $metaAppSecret,
                    'code'          => $code,
                ]);

                if ($tokenResponse->successful()) {
                    $tokenData = $tokenResponse->json();
                    $accessToken = $tokenData['access_token'] ?? $accessToken;
                    Log::info('Token de acceso de usuario obtenido exitosamente.');
                } else {
                    Log::warning('Fallo al intercambiar code de Meta por token: ' . $tokenResponse->body());
                }
            } catch (\Exception $e) {
                Log::warning('Error intercambiando code de Meta: ' . $e->getMessage());
            }
        } elseif ($code && empty($metaAppSecret) && empty($accessToken) && empty($phoneId)) {
            Log::warning("Se recibió 'code' de autorización de Meta, pero META_APP_SECRET está vacío.");
            return response()->json([
                'success' => false,
                'message' => 'Para procesar la autorización se requiere configurar META_APP_SECRET en el archivo .env.',
            ], 422);
        }

        $tokenToUse = $accessToken ?: config('services.whatsapp.access_token');

        if (empty($phoneId) && !empty($wabaId) && $tokenToUse) {
            try {
                $wabaNumbersResp = Http::timeout(4)->withToken($tokenToUse)->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers");
                if ($wabaNumbersResp->successful()) {
                    $numbersData = $wabaNumbersResp->json('data');
                    if (!empty($numbersData) && is_array($numbersData)) {
                        $firstNumber = $numbersData[0];
                        $phoneId = $firstNumber['id'] ?? null;
                        if (empty($telefono) && !empty($firstNumber['display_phone_number'])) {
                            $telefono = preg_replace('/[^0-9]/', '', $firstNumber['display_phone_number']);
                        }
                        if (empty($nombreEmpresa) && !empty($firstNumber['verified_name'])) {
                            $nombreEmpresa = $firstNumber['verified_name'];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Error buscando números para WABA {$wabaId}: " . $e->getMessage());
            }
        }

        if (empty($phoneId) && $accessToken) {
            try {
                $debugResp = Http::timeout(4)->get("https://graph.facebook.com/{$apiVersion}/debug_token", [
                    'input_token'  => $accessToken,
                    'access_token' => config('services.whatsapp.access_token') ?: $accessToken,
                ]);

                if ($debugResp->successful()) {
                    $granularScopes = $debugResp->json('data.granular_scopes') ?? [];
                    foreach ($granularScopes as $scopeItem) {
                        if (($scopeItem['scope'] ?? '') === 'whatsapp_business_management' && !empty($scopeItem['target_ids'])) {
                            $discoveredWabaId = $scopeItem['target_ids'][0];
                            $wabaId = $wabaId ?: $discoveredWabaId;

                            $wabaNumbersResp = Http::timeout(4)->withToken($tokenToUse)->get("https://graph.facebook.com/{$apiVersion}/{$discoveredWabaId}/phone_numbers");
                            if ($wabaNumbersResp->successful()) {
                                $numbersData = $wabaNumbersResp->json('data');
                                if (!empty($numbersData) && is_array($numbersData)) {
                                    $firstNumber = $numbersData[0];
                                    $phoneId = $firstNumber['id'] ?? null;
                                    if (empty($telefono) && !empty($firstNumber['display_phone_number'])) {
                                        $telefono = preg_replace('/[^0-9]/', '', $firstNumber['display_phone_number']);
                                    }
                                    if (empty($nombreEmpresa) && !empty($firstNumber['verified_name'])) {
                                        $nombreEmpresa = $firstNumber['verified_name'];
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error en auto-discovery de WABA: ' . $e->getMessage());
            }
        }

        if (empty($phoneId) && empty($wabaId) && $tokenToUse) {
            try {
                $clientWabas = Http::timeout(4)->withToken($tokenToUse)->get("https://graph.facebook.com/{$apiVersion}/me/client_whatsapp_business_accounts");
                if ($clientWabas->successful() && !empty($clientWabas->json('data'))) {
                    $firstWaba = $clientWabas->json('data')[0];
                    $wabaId = $firstWaba['id'] ?? null;
                    if ($wabaId) {
                        $wabaNumbersResp = Http::timeout(4)->withToken($tokenToUse)->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/phone_numbers");
                        if ($wabaNumbersResp->successful() && !empty($wabaNumbersResp->json('data'))) {
                            $firstNum = $wabaNumbersResp->json('data')[0];
                            $phoneId = $firstNum['id'] ?? null;
                            if (empty($telefono) && !empty($firstNum['display_phone_number'])) {
                                $telefono = preg_replace('/[^0-9]/', '', $firstNum['display_phone_number']);
                            }
                            if (empty($nombreEmpresa) && !empty($firstNum['verified_name'])) {
                                $nombreEmpresa = $firstNum['verified_name'];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error consultando me/client_whatsapp_business_accounts: ' . $e->getMessage());
            }
        }

        if (empty($phoneId) && ($accessToken || $code)) {
            $devPhoneId = config('services.whatsapp.phone_number_id');
            if ($devPhoneId) {
                $phoneId = $devPhoneId;
                Log::info("Onboarding: Usando WHATSAPP_PHONE_NUMBER_ID ({$devPhoneId}) como número para la instancia.");
            }
        }

        $verifiedByGraph = false;
        if ($phoneId && $tokenToUse) {
            try {
                $phoneResponse = Http::timeout(4)->withToken($tokenToUse)->get("https://graph.facebook.com/{$apiVersion}/{$phoneId}");
                if ($phoneResponse->successful()) {
                    $verifiedByGraph = true;
                    $phoneData = $phoneResponse->json();
                    if (empty($telefono) && !empty($phoneData['display_phone_number'])) {
                        $telefono = preg_replace('/[^0-9]/', '', $phoneData['display_phone_number']);
                    }
                    if (empty($nombreEmpresa) && !empty($phoneData['verified_name'])) {
                        $nombreEmpresa = $phoneData['verified_name'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Error consultando Graph API para phone_id {$phoneId}: " . $e->getMessage());
            }
        }

        if (empty($nombreEmpresa)) {
            $nombreEmpresa = 'Empresa ' . ($telefono ? "+{$telefono}" : substr($phoneId ?? 'WA', -4));
        }

        if (empty($phoneId) || !preg_match('/^[0-9]{5,30}$/', (string) $phoneId)) {
            Log::error('Onboarding falló: No se recibió ni descubrió un Phone Number ID válido de WhatsApp.');

            return response()->json([
                'success' => false,
                'message' => 'No se recibió el Phone Number ID de Meta. Por favor verifica los permisos concedidos o completa los pasos en la ventana emergente.',
            ], 422);
        }

        // Evitamos pisar una instancia existente si el token no viene de Meta
        $existente = Instancia::where('phone_number_id', $phoneId)->first();
        if ($existente && !$verifiedByGraph) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo verificar la propiedad del número de WhatsApp en Meta Graph API.',
            ], 403);
        }

        if ($wabaId && $tokenToUse) {
            try {
                Http::timeout(4)->withToken($tokenToUse)->post("https://graph.facebook.com/{$apiVersion}/{$wabaId}/subscribed_apps");
            } catch (\Exception $e) {
                Log::warning("Error suscribiendo webhook de WABA {$wabaId}: " . $e->getMessage());
            }
        }

        $instancia = Instancia::updateOrCreate(
            ['phone_number_id' => $phoneId],
            [
                'nombre_empresa' => $existente?->nombre_empresa ?: $nombreEmpresa,
                'telefono'       => $telefono ?: $existente?->telefono,
                'waba_id'        => $wabaId ?: $existente?->waba_id,
                'access_token'   => $accessToken ?: $existente?->access_token,
                'is_active'      => true,
                'system_prompt'  => $existente?->system_prompt,
            ]
        );

        Cache::put("meta_onboarding_{$token}", [
            'status'         => 'completed',
            'instancia_id'   => $instancia->id,
            'nombre_empresa' => $instancia->nombre_empresa,
            'telefono'       => $instancia->telefono,
            'completed_at'   => now()->toIso8601String(),
        ], now()->addHours(3));

        Log::info('Instancia de WhatsApp guardada exitosamente:', [
            'id'       => $instancia->id,
            'nombre'   => $instancia->nombre_empresa,
            'phone_id' => $instancia->phone_number_id,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => '¡Empresa vinculada con éxito!',
            'instancia' => [
                'id'             => $instancia->id,
                'nombre_empresa' => $instancia->nombre_empresa,
                'telefono'       => $instancia->telefono,
            ],
        ]);
    }
}
