<?php

namespace App\Http\Controllers;

use App\Models\ChatbotNode;
use App\Models\Instancia;
use App\Models\Mensaje;
use App\Models\Option;
use App\Models\UserContext;
use App\Services\AiService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class whatsappController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected AiService $aiService
    ) {}

    public function enviarMensaje(Request $request)
    {
        $user = $request->user('api') ?? $request->user('web') ?? auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error'   => 'No autorizado para enviar mensajes',
            ], 403);
        }

        $numero = $request->input('numero');
        $mensajeData = $request->input('mensaje');
        $instanciaId = $request->input('instancia_id');

        if (!$numero || !$mensajeData || !is_array($mensajeData) || empty($mensajeData['type'])) {
            return response()->json(['success' => false, 'error' => 'Payload de mensaje inválido'], 422);
        }

        if (!preg_match('/^\+?[0-9]{7,15}$/', (string) $numero)) {
            return response()->json(['success' => false, 'error' => 'Número de teléfono inválido'], 422);
        }

        $allowedTypes = ['text', 'image', 'location', 'document', 'interactive', 'buttons', 'list'];
        if (!in_array($mensajeData['type'], $allowedTypes, true)) {
            return response()->json(['success' => false, 'error' => 'Tipo de mensaje no permitido'], 422);
        }

        if (!empty($instanciaId)) {
            $instancia = Instancia::where('id', $instanciaId)->where('is_active', true)->first();
            if (!$instancia) {
                return response()->json(['success' => false, 'error' => 'Instancia no encontrada o inactiva'], 404);
            }
        } else {
            $instancia = Instancia::where('is_active', true)->first();
        }

        try {
            $phoneNumberId = $instancia?->phone_number_id;
            $respuesta = $this->enviarMensajeWhatsapp((string) $numero, $mensajeData, $phoneNumberId);

            return response()->json(['success' => true, 'response' => $respuesta]);
        } catch (\Throwable $e) {
            Log::error('Error en enviarMensaje: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Error al procesar el envío del mensaje',
            ], 500);
        }
    }

    public function enviarMensajeWhatsapp($numero, array $data, $phoneNumberId = null): array
    {
        return $this->whatsAppService->enviarCloud((string) $numero, $data, $phoneNumberId);
    }

    public function verificarWebhook(Request $request)
    {
        $verifyToken = config('services.whatsapp.verify_token');

        if (empty($verifyToken)) {
            Log::critical('WHATSAPP_VERIFY_TOKEN no está configurado en el servidor.');
            return response('Service misconfigured', 503);
        }

        $mode = (string) $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        if ($mode === 'subscribe' && hash_equals((string) $verifyToken, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Verificación de webhook fallida desde IP: ' . $request->ip());
        return response('No se pudo verificar el token', 403);
    }

    public function recibirWebhook(Request $request)
    {
        $rawContent = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $appSecret = config('services.whatsapp.app_secret');

        $isLocalOrTesting = app()->environment('local', 'testing');
        $isPlaceholderSecret = empty($appSecret) || str_starts_with((string) $appSecret, 'meta_app_secret_');

        $isValidSignature = false;
        if (!empty($appSecret) && !empty($signature)) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawContent, (string) $appSecret);
            $isValidSignature = hash_equals($expectedSignature, $signature);
        }

        if (!$isValidSignature) {
            if ($isLocalOrTesting || $isPlaceholderSecret) {
                Log::warning('Firma X-Hub-Signature-256 no coincide o el secreto es provisional en entorno de desarrollo. Se permite el paso de la peticion para pruebas locales con ngrok.');
            } else {
                if (empty($appSecret)) {
                    Log::critical('WHATSAPP_APP_SECRET no esta configurado en el servidor. Rechazando webhook por fail-closed.');
                    return response('Webhook misconfigured', 503);
                }
                if (empty($signature)) {
                    Log::warning('Rechazada peticion webhook no autorizada: Falta cabecera X-Hub-Signature-256 de Meta.');
                    return response('Unauthorized: Missing signature', 401);
                }
                Log::warning('Rechazada peticion webhook no autorizada: Firma X-Hub-Signature-256 no coincide.');
                return response('Forbidden: Invalid signature', 403);
            }
        }

        // Cache de repeticion de eventos para prevenir procesamiento redundante
        $replayKey = 'wa_replay_' . md5($rawContent . ($signature ?? ''));
        if (Cache::has($replayKey)) {
            return response('DUPLICATE_EVENT_IGNORED', 200);
        }
        Cache::put($replayKey, true, now()->addSeconds(15));

        try {
            $phoneId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');

            // Webhooks de estado de entrega de Meta (enviado, recibido, leído, fallido)
            $statuses = $request->input('entry.0.changes.0.value.statuses');
            if (is_array($statuses) && !empty($statuses)) {
                foreach ($statuses as $st) {
                    $waMsgId = $st['id'] ?? null;
                    $estado = $st['status'] ?? null;
                    if ($waMsgId && $estado) {
                        $errMsg = null;
                        if ($estado === 'failed' && !empty($st['errors'][0])) {
                            $errObj = $st['errors'][0];
                            $errMsg = ($errObj['title'] ?? 'Error') . ': ' . ($errObj['message'] ?? ($errObj['error_data']['details'] ?? 'Fallo de entrega en Meta'));
                        }
                        Mensaje::where('whatsapp_id', $waMsgId)->update([
                            'status'        => $estado,
                            'error_message' => $errMsg,
                        ]);
                    }
                }
                return response('EVENT_RECEIVED', 200);
            }

            // Si alguien contestó desde la app de WhatsApp del celular, guardamos el mensaje y pausamos el bot
            $echoData = $request->input('entry.0.changes.0.value.message_echoes.0')
                ?? $request->input('entry.0.changes.0.value.smb_message_echoes.0');

            if ($echoData && $phoneId) {
                $instanciaEcho = Instancia::where('phone_number_id', $phoneId)->where('is_active', true)->first();
                $clienteDestino = $echoData['to'] ?? null;
                $echoId = $echoData['id'] ?? null;
                $echoTipo = $echoData['type'] ?? 'text';
                $echoBody = trim((string) ($echoData['text']['body'] ?? ($echoData[$echoTipo]['caption'] ?? "[{$echoTipo}]")));

                if ($instanciaEcho && $clienteDestino) {
                    if (empty($echoId) || !Mensaje::where('whatsapp_id', $echoId)->exists()) {
                        Mensaje::create([
                            'instancia_id' => $instanciaEcho->id,
                            'whatsapp_id'  => $echoId,
                            'from'         => $instanciaEcho->telefono ?? $phoneId,
                            'to'           => $clienteDestino,
                            'body'         => $echoBody,
                            'type'         => $echoTipo,
                            'direction'    => 'outbound',
                            'status'       => 'sent',
                        ]);
                    }

                    $pauseHours = max(1, (int) ($instanciaEcho->bot_pause_hours ?: 12));

                    UserContext::updateOrCreate(
                        [
                            'instancia_id' => $instanciaEcho->id,
                            'phone_number' => $clienteDestino,
                        ],
                        [
                            'etapa'            => 'asesor',
                            'is_bot_active'    => false,
                            'bot_paused_until' => now()->addHours($pauseHours),
                        ]
                    );
                }

                return response('EVENT_RECEIVED', 200);
            }

            $mensajeData = $request->input('entry.0.changes.0.value.messages.0');

            if (!$mensajeData) {
                return response('EVENT_RECEIVED', 200);
            }

            $remitente = $mensajeData['from'] ?? null;
            $tipo      = $mensajeData['type'] ?? 'unknown';
            $messageId = $mensajeData['id'] ?? null;
            $nombre    = $request->input('entry.0.changes.0.value.contacts.0.profile.name') ?? 'Cliente';

            if (!empty($messageId)) {
                if (Cache::has('wa_msg_' . $messageId)) {
                    return response('DUPLICATE_EVENT_IGNORED', 200);
                }
                Cache::put('wa_msg_' . $messageId, true, now()->addHours(24));
            }

            if (!$phoneId || !$remitente) {
                return response('EVENT_RECEIVED', 200);
            }

            $instancia = Instancia::where('phone_number_id', $phoneId)
                ->where('is_active', true)
                ->first()
                ?? Instancia::where('telefono', $phoneId)->where('is_active', true)->first();

            if (!$instancia) {
                Log::info("Mensaje recibido para phone_number_id no registrado o inactivo: {$phoneId}.");
                return response('EVENT_RECEIVED', 200);
            }

            $texto = '';
            $mediaUrl = null;

            switch ($tipo) {
                case 'text':
                    $texto = trim((string) ($mensajeData['text']['body'] ?? ''));
                    break;

                case 'interactive':
                    $interactiveTitle = $mensajeData['interactive']['button_reply']['title']
                        ?? $mensajeData['interactive']['list_reply']['title']
                        ?? null;
                    $interactiveId = $mensajeData['interactive']['button_reply']['id']
                        ?? $mensajeData['interactive']['list_reply']['id']
                        ?? null;

                    if (!empty($interactiveTitle)) {
                        $texto = trim((string) $interactiveTitle);
                    } elseif (!empty($interactiveId)) {
                        $opt = is_numeric($interactiveId)
                            ? Option::where('instancia_id', $instancia->id)->find($interactiveId)
                            : null;
                        $texto = ($opt && !empty($opt->text)) ? $opt->text : (string) $interactiveId;
                    }
                    break;

                case 'image':
                case 'video':
                    $caption = $mensajeData[$tipo]['caption'] ?? '';
                    $mediaId = $mensajeData[$tipo]['id'] ?? null;
                    if ($mediaId) {
                        $downloaded = $this->descargarMediaWhatsApp($mediaId, $instancia);
                        if ($downloaded) {
                            $mediaUrl = $downloaded['url'];
                        }
                    }
                    $texto = !empty($caption) ? $caption : ($tipo === 'image' ? '[Imagen]' : '[Video]');
                    break;

                case 'sticker':
                    $mediaId = $mensajeData['sticker']['id'] ?? null;
                    if ($mediaId) {
                        $downloaded = $this->descargarMediaWhatsApp($mediaId, $instancia);
                        if ($downloaded) {
                            $mediaUrl = $downloaded['url'];
                        }
                    }
                    $texto = '[Sticker]';
                    break;

                case 'audio':
                    $mediaId = $mensajeData['audio']['id'] ?? null;
                    $transcripcion = null;
                    if ($mediaId) {
                        $downloaded = $this->descargarMediaWhatsApp($mediaId, $instancia);
                        if ($downloaded) {
                            $mediaUrl = $downloaded['url'];
                            $transcripcion = $this->transcribirAudioGroq($downloaded['path']);
                        }
                    }
                    $texto = !empty($transcripcion) ? $transcripcion : '[Audio/Nota de voz]';
                    break;

                case 'document':
                    $filename = $mensajeData['document']['filename'] ?? 'archivo';
                    $mediaId = $mensajeData['document']['id'] ?? null;
                    if ($mediaId) {
                        $downloaded = $this->descargarMediaWhatsApp($mediaId, $instancia);
                        if ($downloaded) {
                            $mediaUrl = $downloaded['url'];
                        }
                    }
                    $texto = "[Documento: {$filename}]";
                    break;

                case 'location':
                    $lat   = $mensajeData['location']['latitude'] ?? '';
                    $lng   = $mensajeData['location']['longitude'] ?? '';
                    $texto = "[Ubicación: {$lat}, {$lng}]";
                    break;

                default:
                    $texto = "[Mensaje: {$tipo}]";
                    break;
            }

            $textoParaGuardar = $texto;
            if (is_numeric($textoParaGuardar) && strlen($textoParaGuardar) >= 2) {
                $optFound = Option::where('instancia_id', $instancia->id)->find($textoParaGuardar);
                if ($optFound && !empty($optFound->text)) {
                    $textoParaGuardar = $optFound->text;
                }
            }

            $mensajeGuardado = Mensaje::create([
                'instancia_id' => $instancia->id,
                'whatsapp_id'  => $messageId,
                'from'         => $remitente,
                'to'           => $instancia->telefono ?? $phoneId,
                'body'         => $textoParaGuardar,
                'type'         => $tipo,
                'media_url'    => $mediaUrl,
                'direction'    => 'inbound',
            ]);

            $nodeQuery = fn () => ChatbotNode::where('instancia_id', $instancia->id);

            $initialNode = (clone $nodeQuery())->whereIn('id', ['principal', 'main', 'principal_' . $instancia->id, 'main_' . $instancia->id])->first()
                ?? $nodeQuery()->orderBy('created_at', 'asc')->first();
            $startNodeId = $initialNode ? $initialNode->id : 'main';

            $context = UserContext::firstOrCreate(
                [
                    'instancia_id' => $instancia->id,
                    'phone_number' => $remitente,
                ],
                [
                    'nombre'                    => $nombre,
                    'current_node'              => $startNodeId,
                    'etapa'                     => 'nuevo',
                    'estado_conversacion'       => 'abierta',
                    'mensajes_sin_leer'         => 0,
                    'opt_in'                    => true,
                    'is_bot_active'             => true,
                    'ultimo_mensaje_cliente_at' => now(),
                ]
            );

            $contextUpdates = [
                'ultimo_mensaje_cliente_at' => now(),
                'estado_conversacion'       => 'abierta',
                'mensajes_sin_leer'         => ((int) $context->mensajes_sin_leer) + 1,
            ];
            if ($nombre && $context->nombre !== $nombre && $nombre !== 'Cliente') {
                $contextUpdates['nombre'] = $nombre;
            }
            $context->update($contextUpdates);

            if ($context->wasRecentlyCreated) {
                $context->registrarActividad('creado', 'Contacto registrado por primera vez a través de WhatsApp.', 'WhatsApp Webhook');
            }

            // Si está fuera del horario laboral, mandamos el mensaje automático (máximo una vez cada 12h por cliente)
            if (!$instancia->estaEnHorarioAtencion()) {
                $oooCacheKey = 'wa_ooo_' . $instancia->id . '_' . $remitente;
                if (!Cache::has($oooCacheKey)) {
                    Cache::put($oooCacheKey, true, now()->addHours(12));
                    $msgFueraHorario = $instancia->obtenerMensajeFueraHorario();
                    if ($msgFueraHorario) {
                        $this->enviarMensajeWhatsapp($remitente, [
                            'type' => 'text',
                            'body' => $msgFueraHorario,
                        ], $phoneId);
                    }
                }
            }

            $buttonId = $mensajeData['interactive']['button_reply']['id']
                ?? $mensajeData['interactive']['list_reply']['id']
                ?? null;
            $buttonTitle = $mensajeData['interactive']['button_reply']['title']
                ?? $mensajeData['interactive']['list_reply']['title']
                ?? null;

            $textoMinuscula = mb_strtolower(trim($texto));
            $pauseHours = max(1, (int) ($instancia->bot_pause_hours ?: 12));

            // Palabras clave para darse de baja o volver a recibir difusiones masivas
            if (in_array($textoMinuscula, ['baja', 'stop', 'cancelar suscripcion', 'cancelar suscripción', 'no molestar'], true)) {
                $context->update(['opt_in' => false]);
                $this->enviarMensajeWhatsapp($remitente, [
                    'type' => 'text',
                    'body' => "Has sido dado de baja de nuestras campañas de difusión de {$instancia->nombre_empresa}. Si deseas volver a suscribirte en el futuro, escribe *ALTA*.",
                ], $phoneId);
                return response('EVENT_RECEIVED', 200);
            }

            if (in_array($textoMinuscula, ['alta', 'suscribirme'], true)) {
                $context->update(['opt_in' => true]);
                $this->enviarMensajeWhatsapp($remitente, [
                    'type' => 'text',
                    'body' => "¡Gracias! Tu suscripción a las comunicaciones de {$instancia->nombre_empresa} está activa nuevamente.",
                ], $phoneId);
                return response('EVENT_RECEIVED', 200);
            }

            // Palabras clave para solicitar atencion de un asesor humano
            if (preg_match('/\b(humano|asesor|agente|persona|operador)\b/i', $textoMinuscula)
                || in_array($texto, ['opt_humano', 'hablar_asesor', 'btn_asesor'], true)
                || ($buttonId === 'btn_asesor')
            ) {
                $context->update([
                    'etapa'            => 'asesor',
                    'is_bot_active'    => false,
                    'bot_paused_until' => now()->addHours($pauseHours),
                ]);

                $mensajeHumano = "Entendido, te estamos transfiriendo con un asesor de {$instancia->nombre_empresa}. En breve uno de nuestros agentes te atenderá aquí mismo.\n\n_(Escribe *Bot* o presiona *Ver opciones* cuando desees volver a interactuar con el bot)._";
                $this->enviarMensajeWhatsapp($remitente, [
                    'type'    => 'buttons',
                    'body'    => $mensajeHumano,
                    'buttons' => [
                        ['id' => 'btn_menu', 'title' => 'Ver opciones'],
                    ],
                ], $phoneId);

                return response('EVENT_RECEIVED', 200);
            }

            // Verificacion de estado del bot (asesor humano activo vs bot automatico)
            $botPausado = !$context->is_bot_active || ($context->bot_paused_until && now()->lt($context->bot_paused_until));

            // Si el cliente pide explicitamente reactivar el bot mediante comando directo o boton
            $solicitaReactivarBot = in_array($textoMinuscula, ['bot', 'activar bot', 'iniciar bot', 'reiniciar bot', 'menu bot'], true)
                || in_array($buttonId, ['btn_menu', 'btn_bot'], true);

            if ($botPausado) {
                if (!$solicitaReactivarBot) {
                    Log::info("Bot en pausa para {$remitente} en instancia {$instancia->nombre_empresa}. Atendido manualmente por asesor humano. Mensaje guardado en el CRM.");
                    return response('EVENT_RECEIVED', 200);
                }

                // El cliente solicito explicitamente volver a interactuar con el bot
                $context->update([
                    'is_bot_active'    => true,
                    'bot_paused_until' => null,
                    'current_node'     => $startNodeId,
                ]);
            }

            // Saludos o reinicio de flujo (solo procesado si el bot esta activo)
            $isGreetingOrReset = in_array($textoMinuscula, [
                'hola', 'hola!', 'buenas', 'buenos dias', 'buenos días', 'buenas tardes', 'buenas noches',
                'opciones', 'opcion', 'catalogo', 'catálogo', 'menu', 'menú', 'inicio',
                'empezar', 'reiniciar', 'volver', 'principal', 'btn_menu', 'bot', 'activar bot',
            ], true)
                || ($buttonId === 'btn_menu')
                || preg_match('/^(hola\b|buenos d[ií]as|buenas tardes|buenas noches)/i', $textoMinuscula);

            if ($context->wasRecentlyCreated || $isGreetingOrReset) {
                $context->update([
                    'is_bot_active'    => true,
                    'bot_paused_until' => null,
                    'current_node'     => $startNodeId,
                ]);

                if ($initialNode) {
                    $this->enviarMensajeDinamico($remitente, $startNodeId, $phoneId, $instancia->id);
                    return response('EVENT_RECEIVED', 200);
                }
            }

            $currentNodeId = $context->current_node ?: $startNodeId;
            $opciones = Option::where('instancia_id', $instancia->id)
                ->where('chatbot_node_id', $currentNodeId)
                ->get();
            $opcion = null;

            foreach ($opciones as $cand) {
                if (($buttonId && (string) $cand->id === (string) $buttonId)
                    || ((string) $cand->id === (string) $texto)
                    || (strcasecmp(trim((string) $cand->key), trim($texto)) === 0)
                    || (strcasecmp(trim((string) $cand->text), trim($texto)) === 0)
                    || ($buttonTitle && strcasecmp(trim((string) $cand->text), trim((string) $buttonTitle)) === 0)
                ) {
                    $opcion = $cand;
                    break;
                }
            }

            if ($opcion) {
                $candText = mb_strtolower(trim($opcion->text . ' ' . $opcion->key));
                $isAiTrigger = ($opcion->next_node_id === 'ia' || $opcion->next_node_id === 'ai')
                    || (empty($opcion->next_node_id) && preg_match('/\b(otra|otro|ia|inteligencia|asesor|consulta|pregunta)\b/i', $candText));

                if ($isAiTrigger) {
                    $context->update(['current_node' => 'ia']);

                    $msgAiPrompt = '¡Con gusto! Escríbeme tu duda o consulta y con gusto te ayudo de inmediato:';
                    if (!empty($opcion->respuesta)) {
                        $msgAiPrompt = is_array($opcion->respuesta) ? ($opcion->respuesta['body'] ?? $msgAiPrompt) : $opcion->respuesta;
                    }

                    $this->enviarMensajeWhatsapp($remitente, [
                        'type'    => 'buttons',
                        'body'    => $msgAiPrompt,
                        'footer'  => 'Puedes volver cuando quieras:',
                        'buttons' => [
                            ['id' => 'btn_menu', 'title' => 'Ver opciones'],
                            ['id' => 'btn_asesor', 'title' => 'Hablar con asesor'],
                        ],
                    ], $phoneId);

                    return response('EVENT_RECEIVED', 200);
                }

                if ($opcion->respuesta) {
                    $this->enviarMensajeWhatsapp($remitente, $opcion->respuesta, $phoneId);
                }

                if ($opcion->next_node_id && ($targetNode = $nodeQuery()->where('id', $opcion->next_node_id)->first())) {
                    $updateCtx = [
                        'current_node' => $targetNode->id,
                        'etapa'        => $targetNode->target_etapa ?: (($context->etapa === 'nuevo' || empty($context->etapa)) ? 'calificado' : $context->etapa),
                    ];

                    if (!empty($targetNode->auto_tags)) {
                        $existingTags = is_array($context->etiquetas) ? $context->etiquetas : [];
                        $newTags = array_filter(array_map('trim', explode(',', $targetNode->auto_tags)));
                        $updateCtx['etiquetas'] = array_values(array_unique(array_merge($existingTags, $newTags)));
                    }

                    if ($targetNode->target_etapa === 'asesor') {
                        $updateCtx['is_bot_active'] = false;
                        $updateCtx['bot_paused_until'] = now()->addHours($pauseHours);
                    }

                    $context->update($updateCtx);
                    $this->enviarMensajeDinamico($remitente, $targetNode->id, $phoneId, $instancia->id);
                    return response('EVENT_RECEIVED', 200);
                }

                return response('EVENT_RECEIVED', 200);
            }

            if (!empty($texto)) {
                $historial = Mensaje::where('instancia_id', $instancia->id)
                    ->where('id', '<', $mensajeGuardado->id)
                    ->where(function ($q) use ($remitente) {
                        $q->where('from', $remitente)
                          ->orWhere('to', $remitente);
                    })
                    ->latest('id')
                    ->take(10)
                    ->get()
                    ->reverse()
                    ->values();

                $promptConCatalogo = $instancia->system_prompt;
                $catalogo = is_array($instancia->catalogo_productos) ? $instancia->catalogo_productos : [];
                if (!empty($catalogo)) {
                    $lineasCatalogo = array_map(
                        fn ($p) => "- " . ($p['nombre'] ?? 'Producto') . " (SKU: " . ($p['sku'] ?? 'N/A') . "): $" . number_format((float) ($p['precio'] ?? 0), 2) . (!empty($p['descripcion']) ? " — " . $p['descripcion'] : ""),
                        $catalogo
                    );
                    $promptConCatalogo = trim((string) $promptConCatalogo) . "\n\nCATÁLOGO OFICIAL DE PRODUCTOS/SERVICIOS:\n" . implode("\n", $lineasCatalogo);
                }

                $respuestaIA = $this->funRespuestaIA($texto, $promptConCatalogo, $historial, $instancia->nombre_empresa, $instancia);

                if (!empty($respuestaIA)) {
                    $historialSnapshot = $historial->map(fn ($m) => [
                        'role'    => $m->direction === 'inbound' ? 'user' : 'assistant',
                        'content' => $m->body,
                    ])->push(['role' => 'user', 'content' => $texto])
                      ->push(['role' => 'assistant', 'content' => $respuestaIA])
                      ->slice(-12)
                      ->values()
                      ->all();

                    $nuevaEtapa = $this->aiService->detectarEtapaPorIntencion($texto, $context->etapa ?: 'nuevo');

                    $context->update([
                        'ai_history' => $historialSnapshot,
                        'etapa'      => $nuevaEtapa,
                    ]);

                    $iaButtons = [];
                    if ($nodeQuery()->exists()) {
                        $iaButtons[] = ['id' => 'btn_menu', 'title' => 'Ver opciones'];
                    }
                    $iaButtons[] = ['id' => 'btn_asesor', 'title' => 'Hablar con asesor'];

                    $this->enviarMensajeWhatsapp($remitente, [
                        'type'    => 'buttons',
                        'body'    => $respuestaIA,
                        'footer'  => $instancia->nombre_empresa,
                        'buttons' => $iaButtons,
                    ], $phoneId);
                }
            }

            return response('EVENT_RECEIVED', 200);
        } catch (\Throwable $e) {
            Log::error('Error procesando recibirWebhook: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('EVENT_RECEIVED', 200);
        }
    }

    public function recibirMensaje(Request $request)
    {
        return $this->recibirWebhook($request);
    }

    public function enviarMensajeDinamico($numero, $nodeId, $phoneNumberId = null, ?int $instanciaId = null): void
    {
        $this->whatsAppService->enviarMensajeDinamico((string) $numero, (string) $nodeId, $phoneNumberId, $instanciaId);
    }

    public function funRespuestaIA($mensajeUser, $customPrompt = null, $historial = [], $empresa = null, $instancia = null): string
    {
        return $this->aiService->obtenerRespuesta(
            (string) $mensajeUser,
            $customPrompt,
            is_iterable($historial) ? $historial : [],
            $empresa,
            $instancia
        );
    }

    public function descargarMediaWhatsApp($mediaId, $instancia = null): ?array
    {
        return $this->whatsAppService->descargarMedia((string) $mediaId, $instancia);
    }

    public function transcribirAudioGroq($audioFilePath): ?string
    {
        return $this->aiService->transcribirAudio((string) $audioFilePath);
    }
}
