<?php

namespace App\Services;

use App\Models\ChatbotNode;
use App\Models\Instancia;
use App\Models\Mensaje;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function __construct(
        protected AiService $aiService
    ) {}

    public function enviar(string $numero, array $data, ?string $phoneNumberId = null): array
    {
        return $this->enviarCloud($numero, $data, $phoneNumberId);
    }

    // Envío por la Cloud API de WhatsApp (texto, imágenes, botones y listas interactivas)
    public function enviarCloud(string $number, array $data, ?string $phoneNumberId = null): array
    {
        $phoneId = $phoneNumberId ?: config('services.whatsapp.phone_number_id');
        $apiVersion = config('services.whatsapp.api_version', 'v22.0');
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages";

        $instancia = $phoneId
            ? Instancia::where('phone_number_id', $phoneId)->where('is_active', true)->first()
            : null;

        $tokenToUse = ($instancia && !empty($instancia->access_token))
            ? $instancia->access_token
            : config('services.whatsapp.access_token');

        if (empty($tokenToUse)) {
            Log::error("No hay token disponible para phone_number_id: {$phoneId}");
            return ['error' => 'Token no disponible'];
        }

        $type = $data['type'] ?? 'text';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $number,
            'type'              => $type,
        ];

        switch ($type) {
            case 'text':
                $payload['text'] = ['body' => (string) ($data['body'] ?? '')];
                break;

            case 'image':
                $imagePayload = [];
                if (!empty($data['id'])) {
                    $imagePayload['id'] = (string) $data['id'];
                } elseif (!empty($data['link'])) {
                    $imagePayload['link'] = (string) $data['link'];
                }
                if (isset($data['caption']) && $data['caption'] !== '') {
                    $imagePayload['caption'] = (string) $data['caption'];
                }
                $payload['image'] = $imagePayload;
                break;

            case 'location':
                $payload['location'] = [
                    'latitude'  => (float) ($data['latitude'] ?? 0),
                    'longitude' => (float) ($data['longitude'] ?? 0),
                    'name'      => $data['name'] ?? ($instancia?->nombre_empresa ?? 'Ubicación'),
                    'address'   => $data['address'] ?? '',
                ];
                break;

            case 'document':
                $payload['document'] = [
                    'link'     => $data['link'] ?? '',
                    'caption'  => $data['caption'] ?? '',
                    'filename' => $data['filename'] ?? 'archivo',
                ];
                break;

            case 'interactive':
                $payload['interactive'] = $data['interactive'] ?? [];
                break;

            case 'buttons':
                $payload['type'] = 'interactive';
                $interactiveButtons = [];
                foreach ($data['buttons'] ?? [] as $btn) {
                    if (isset($btn['type']) && $btn['type'] === 'reply') {
                        $interactiveButtons[] = $btn;
                    } else {
                        $interactiveButtons[] = [
                            'type'  => 'reply',
                            'reply' => [
                                'id'    => (string) ($btn['id'] ?? $btn['key'] ?? ('btn_' . count($interactiveButtons))),
                                'title' => mb_substr(trim((string) ($btn['title'] ?? $btn['text'] ?? '')), 0, 20),
                            ],
                        ];
                    }
                }
                $interactivePayload = [
                    'type'   => 'button',
                    'body'   => ['text' => mb_substr((string) ($data['body'] ?? ''), 0, 1024)],
                    'action' => ['buttons' => array_slice($interactiveButtons, 0, 3)],
                ];
                if (!empty($data['header'])) {
                    $interactivePayload['header'] = $data['header'];
                }
                if (!empty($data['footer'])) {
                    $interactivePayload['footer'] = ['text' => mb_substr((string) $data['footer'], 0, 60)];
                }
                $payload['interactive'] = $interactivePayload;
                break;

            case 'list':
                $payload['type'] = 'interactive';
                $interactiveList = [
                    'type'   => 'list',
                    'body'   => ['text' => mb_substr((string) ($data['body'] ?? ''), 0, 1024)],
                    'action' => [
                        'button'   => mb_substr((string) ($data['button_text'] ?? 'Ver opciones'), 0, 20),
                        'sections' => $data['sections'] ?? [],
                    ],
                ];
                if (!empty($data['header'])) {
                    $interactiveList['header'] = $data['header'];
                }
                if (!empty($data['footer'])) {
                    $interactiveList['footer'] = ['text' => mb_substr((string) $data['footer'], 0, 60)];
                }
                $payload['interactive'] = $interactiveList;
                break;
        }

        try {
            $response = Http::withToken($tokenToUse)->withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            if (!$response->successful() && in_array($type, ['buttons', 'list', 'interactive'], true) && !empty($data['body'])) {
                Log::warning('Fallo al enviar mensaje interactivo. Fallback a texto plano.');
                $fallbackPayload = [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $number,
                    'type'              => 'text',
                    'text'              => ['body' => (string) $data['body']],
                ];
                $response = Http::withToken($tokenToUse)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(15)->post($url, $fallbackPayload);
            }

            if (!$response->successful()) {
                $err = $response->json('error');
                $errMsg = $err['message'] ?? 'Error en la respuesta de Meta';
                $errCode = $err['code'] ?? $response->status();
                Log::error("Fallo al enviar mensaje WhatsApp Cloud (Code {$errCode}): {$errMsg}");

                try {
                    $bodyText = $data['body'] ?? ($data['caption'] ?? ('[' . $type . ']'));
                    if ($errCode == 131030) {
                        $errMsg = 'Meta Test: El número de tu amigo no está registrado en la lista de números autorizados en Meta Developers (#131030).';
                    }

                    Mensaje::create([
                        'instancia_id'  => $instancia?->id,
                        'whatsapp_id'   => null,
                        'from'          => $instancia?->telefono ?? $phoneId,
                        'to'            => $number,
                        'body'          => $bodyText,
                        'type'          => $type,
                        'media_url'     => $data['media_url'] ?? ($data['map_url'] ?? null),
                        'direction'     => 'outbound',
                        'status'        => 'failed',
                        'error_message' => $errMsg,
                    ]);
                } catch (\Throwable $ex) {
                    Log::error('Error guardando mensaje fallido en BD: ' . $ex->getMessage());
                }
            } else {
                try {
                    $resData = $response->json();
                    $whatsappMsgId = $resData['messages'][0]['id'] ?? null;
                    $bodyText = $data['body'] ?? '';

                    if ($type === 'location') {
                        $locName = $data['name'] ?? ($instancia?->nombre_empresa ?? 'Ubicación');
                        $locAddr = $data['address'] ?? '';
                        $mapUrl  = $data['map_url'] ?? null;
                        $bodyText = "{$locName}" . ($locAddr ? ": {$locAddr}" : '') . ($mapUrl ? "\n{$mapUrl}" : '');
                    } elseif (empty($bodyText)) {
                        if (isset($data['interactive']['body']['text'])) {
                            $bodyText = $data['interactive']['body']['text'];
                        } elseif (isset($data['caption'])) {
                            $bodyText = $data['caption'];
                        } else {
                            $bodyText = '[' . $type . ']';
                        }
                    }

                    Mensaje::create([
                        'instancia_id' => $instancia?->id,
                        'whatsapp_id'  => $whatsappMsgId,
                        'from'         => $instancia?->telefono ?? $phoneId,
                        'to'           => $number,
                        'body'         => $bodyText,
                        'type'         => $type,
                        'media_url'    => $data['media_url'] ?? ($data['map_url'] ?? null),
                        'direction'    => 'outbound',
                        'status'       => 'sent',
                    ]);
                } catch (\Throwable $ex) {
                    Log::error('Error guardando mensaje outbound en BD: ' . $ex->getMessage());
                }
            }

            return $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('Excepción enviando mensaje WhatsApp Cloud: ' . $e->getMessage());
            return ['error' => 'No se pudo completar el envío del mensaje'];
        }
    }

    // Envía un nodo adaptando el formato según la cantidad de opciones (botones si son <=3, lista si son más)
    public function enviarMensajeDinamico(string $numero, string $nodeId, ?string $phoneNumberId = null, ?int $instanciaId = null): void
    {
        $query = ChatbotNode::where('id', $nodeId);
        if ($instanciaId) {
            $query->forInstancia($instanciaId);
        } else {
            $query->with('opciones');
        }
        $nodo = $query->first();

        if (!$nodo) {
            return;
        }

        $opciones = $nodo->opciones ?? collect();

        // Si el nodo incluye una imagen adjunta
        if (!empty($nodo->media_url)) {
            // Si el nodo no tiene opciones interactivas, enviamos la imagen con el mensaje como caption
            if ($opciones->isEmpty()) {
                $this->enviarCloud($numero, [
                    'type'      => 'image',
                    'link'      => $nodo->media_url,
                    'caption'   => $nodo->mensaje,
                    'media_url' => $nodo->media_url,
                ], $phoneNumberId);
                return;
            }

            // Si tiene opciones interactivas, primero despachamos la imagen
            $this->enviarCloud($numero, [
                'type'      => 'image',
                'link'      => $nodo->media_url,
                'media_url' => $nodo->media_url,
            ], $phoneNumberId);
        }

        if ($opciones->isEmpty()) {
            $this->enviarCloud($numero, [
                'type' => 'text',
                'body' => $nodo->mensaje,
            ], $phoneNumberId);
            return;
        }

        if ($opciones->count() <= 3) {
            $buttons = [];
            foreach ($opciones as $opt) {
                $label = trim((string) ($opt->text ?: $opt->key));
                $buttons[] = [
                    'id'    => (string) $opt->id,
                    'title' => mb_substr($label, 0, 20),
                ];
            }

            $this->enviarCloud($numero, [
                'type'    => 'buttons',
                'body'    => $nodo->mensaje,
                'footer'  => 'Selecciona una opción:',
                'buttons' => $buttons,
            ], $phoneNumberId);
            return;
        }

        $rows = [];
        foreach ($opciones as $opt) {
            $label = trim((string) ($opt->text ?: $opt->key));
            $rows[] = [
                'id'          => (string) $opt->id,
                'title'       => mb_substr($label, 0, 24),
                'description' => mb_substr($opt->key ? "Opción {$opt->key}" : '', 0, 72),
            ];
        }

        $this->enviarCloud($numero, [
            'type'        => 'list',
            'body'        => $nodo->mensaje,
            'footer'      => 'Toca abajo para ver todas las opciones',
            'button_text' => 'Ver opciones',
            'sections'    => [
                [
                    'title' => 'Opciones disponibles',
                    'rows'  => array_slice($rows, 0, 10),
                ],
            ],
        ], $phoneNumberId);
    }

    // Subida de archivos binarios (imágenes, documentos) a los servidores de Meta WhatsApp Cloud
    public function subirMedia(string $filePath, string $mimeType, ?string $phoneNumberId = null): ?string
    {
        $phoneId = $phoneNumberId ?: config('services.whatsapp.phone_number_id');
        $apiVersion = config('services.whatsapp.api_version', 'v22.0');
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneId}/media";

        $instancia = $phoneId
            ? Instancia::where('phone_number_id', $phoneId)->where('is_active', true)->first()
            : null;

        $tokenToUse = ($instancia && !empty($instancia->access_token))
            ? $instancia->access_token
            : config('services.whatsapp.access_token');

        if (empty($tokenToUse) || !file_exists($filePath)) {
            Log::warning("No se puede subir media a Meta: token vacío o archivo no encontrado ({$filePath})");
            return null;
        }

        try {
            $response = Http::withToken($tokenToUse)
                ->timeout(30)
                ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'type'              => $mimeType,
                ]);

            if ($response->successful()) {
                $mediaId = $response->json('id');
                Log::info("Media subida exitosamente a Meta WhatsApp Cloud. ID: {$mediaId}");
                return $mediaId;
            }

            Log::error('Error subiendo multimedia a Meta: ' . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error('Excepción al subir multimedia a Meta: ' . $e->getMessage());
            return null;
        }
    }

    // Descarga de archivos multimedia (audios, fotos) desde los servidores de Meta
    public function descargarMedia(string $mediaId, ?Instancia $instancia = null): ?array
    {
        if (!$mediaId || !preg_match('/^[a-zA-Z0-9_\-]+$/', $mediaId)) {
            Log::warning('mediaId inválido o sospechoso: ' . substr($mediaId, 0, 50));
            return null;
        }

        try {
            $token = ($instancia && !empty($instancia->access_token))
                ? $instancia->access_token
                : config('services.whatsapp.access_token');

            if (empty($token)) {
                Log::warning("No hay token de WhatsApp configurado para descargar media ID {$mediaId}");
                return null;
            }

            $apiVersion = config('services.whatsapp.api_version', 'v22.0');
            $metaResponse = Http::withToken($token)
                ->timeout(15)
                ->get("https://graph.facebook.com/{$apiVersion}/{$mediaId}");

            if (!$metaResponse->successful() || !isset($metaResponse['url'])) {
                Log::error("Error obteniendo URL de media Meta ({$mediaId}): HTTP " . $metaResponse->status());
                return null;
            }

            $downloadUrl = (string) $metaResponse['url'];
            $mimeType = (string) ($metaResponse['mime_type'] ?? 'application/octet-stream');

            $host = strtolower((string) parse_url($downloadUrl, PHP_URL_HOST));
            $isMetaCdn = false;
            $allowedCdns = ['lookaside.fbsbx.com', 'scontent.whatsapp.net'];
            foreach ($allowedCdns as $allowed) {
                if ($host === $allowed || str_ends_with($host, '.' . $allowed) || str_ends_with($host, '.fbcdn.net')) {
                    $isMetaCdn = true;
                    break;
                }
            }

            if (!$isMetaCdn) {
                Log::error("Host no autorizado para descarga de multimedia Meta: {$host}");
                return null;
            }

            $fileResponse = Http::withToken($token)
                ->withHeaders(['User-Agent' => 'curl/7.64.1'])
                ->timeout(30)
                ->get($downloadUrl);

            if (!$fileResponse->successful()) {
                Log::error("Error descargando binario de media Meta ({$mediaId}): HTTP " . $fileResponse->status());
                return null;
            }

            $bodyBytes = $fileResponse->body();

            if (strlen($bodyBytes) > 25 * 1024 * 1024) {
                Log::error("Archivo multimedia excede límite de 25MB: mediaId {$mediaId}");
                return null;
            }

            $mimeMap = [
                'image/jpeg'      => 'jpg',
                'image/jpg'       => 'jpg',
                'image/png'       => 'png',
                'image/webp'      => 'webp',
                'audio/ogg'       => 'ogg',
                'audio/opus'      => 'ogg',
                'audio/mpeg'      => 'mp3',
                'audio/mp3'       => 'mp3',
                'video/mp4'       => 'mp4',
                'application/pdf' => 'pdf',
            ];

            $cleanMime = strtolower(trim(explode(';', $mimeType)[0]));
            $extension = $mimeMap[$cleanMime] ?? null;

            if (!$extension) {
                Log::warning("Mime-type no permitido para media de WhatsApp: {$cleanMime}");
                return null;
            }

            $storageDir = storage_path('app/public/whatsapp_media');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $safeMediaId = preg_replace('/[^a-zA-Z0-9_]/', '', $mediaId);
            $filename = 'wa_' . $safeMediaId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $fullPath = $storageDir . DIRECTORY_SEPARATOR . $filename;

            file_put_contents($fullPath, $bodyBytes);

            return [
                'filename'  => $filename,
                'path'      => $fullPath,
                'url'       => asset('storage/whatsapp_media/' . $filename),
                'mime_type' => $cleanMime,
                'extension' => $extension,
            ];
        } catch (\Throwable $e) {
            Log::error("Excepción descargando media de WhatsApp ({$mediaId}): " . $e->getMessage());
            return null;
        }
    }
}
