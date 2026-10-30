<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Instancia;
use App\Models\Mensaje;
use App\Models\UserContext;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CrmApiController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService
    ) {}

    // Valida el api_token de la instancia
    protected function autenticarInstancia(Request $request): ?Instancia
    {
        $token = $request->bearerToken() ?: $request->header('X-API-Key') ?: $request->input('api_token');
        if (empty($token) || strlen($token) < 20) {
            return null;
        }

        return Instancia::where('api_token', $token)->where('is_active', true)->first();
    }

    // Lista prospectos con filtros por etapa o término de búsqueda
    public function listarLeads(Request $request)
    {
        $instancia = $this->autenticarInstancia($request);
        if (!$instancia) {
            return response()->json(['error' => 'No autorizado. Token de API inválido o revocado.'], 401);
        }

        $query = $instancia->userContexts()->latest('updated_at');

        if ($request->filled('etapa')) {
            $query->where('etapa', $request->input('etapa'));
        }

        if ($request->filled('buscar')) {
            $buscar = trim($request->input('buscar'));
            $query->where(function ($q) use ($buscar) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($buscar) . '%'])
                  ->orWhere('phone_number', 'LIKE', "%{$buscar}%")
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . mb_strtolower($buscar) . '%']);
            });
        }

        $leads = $query->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return response()->json([
            'success' => true,
            'empresa' => $instancia->nombre_empresa,
            'data'    => $leads->items(),
            'meta'    => [
                'current_page' => $leads->currentPage(),
                'total'        => $leads->total(),
                'per_page'     => $leads->perPage(),
                'last_page'    => $leads->lastPage(),
            ],
        ]);
    }

    // Alta o actualización de prospectos desde formularios web, Zapier o Make
    public function crearOActualizarLead(Request $request)
    {
        $instancia = $this->autenticarInstancia($request);
        if (!$instancia) {
            return response()->json(['error' => 'No autorizado. Token de API inválido o revocado.'], 401);
        }

        $validated = $request->validate([
            'phone_number'          => 'required|string|max:30',
            'nombre'                => 'nullable|string|max:120',
            'empresa_cliente'       => 'nullable|string|max:160',
            'email'                 => 'nullable|email|max:190',
            'etapa'                 => 'nullable|string|max:60',
            'valor_estimado'        => 'nullable|numeric|min:0',
            'etiquetas'             => 'nullable|array',
            'etiquetas.*'           => 'string|max:40',
            'campos_personalizados' => 'nullable|array',
            'origen_utm'            => 'nullable|array',
            'mensaje_inicial'       => 'nullable|string|max:2000',
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $validated['phone_number']);
        if (strlen($cleanPhone) < 7 || strlen($cleanPhone) > 17) {
            return response()->json(['error' => 'El número de teléfono proporcionado no tiene un formato válido.'], 422);
        }

        $etapasValidas = array_keys($instancia->obtenerEtapas());
        $etapa = $validated['etapa'] ?? array_key_first($instancia->obtenerEtapas());
        if (!in_array($etapa, $etapasValidas)) {
            $etapa = array_key_first($instancia->obtenerEtapas());
        }

        $contacto = UserContext::where('instancia_id', $instancia->id)
            ->where('phone_number', $cleanPhone)
            ->first();

        $esNuevo = false;
        if (!$contacto) {
            $esNuevo = true;
            $contacto = new UserContext();
            $contacto->instancia_id = $instancia->id;
            $contacto->phone_number = $cleanPhone;
            $contacto->current_node = 'main';
            $contacto->opt_in = true;
            $contacto->estado_conversacion = 'abierta';
        }

        if (!empty($validated['nombre'])) {
            $contacto->nombre = trim($validated['nombre']);
        } elseif ($esNuevo) {
            $contacto->nombre = 'Lead ' . substr($cleanPhone, -4);
        }

        if (isset($validated['empresa_cliente'])) {
            $contacto->empresa_cliente = trim($validated['empresa_cliente']);
        }
        if (isset($validated['email'])) {
            $contacto->email = trim($validated['email']);
        }
        if (isset($validated['valor_estimado'])) {
            $contacto->valor_estimado = (float) $validated['valor_estimado'];
        }
        $contacto->etapa = $etapa;

        if (!empty($validated['etiquetas'])) {
            $currentTags = is_array($contacto->etiquetas) ? $contacto->etiquetas : [];
            $contacto->etiquetas = array_values(array_unique(array_merge($currentTags, $validated['etiquetas'])));
        }

        if (!empty($validated['campos_personalizados'])) {
            $currentCampos = is_array($contacto->campos_personalizados) ? $contacto->campos_personalizados : [];
            $contacto->campos_personalizados = array_merge($currentCampos, $validated['campos_personalizados']);
        }

        if (!empty($validated['origen_utm'])) {
            $contacto->origen_utm = $validated['origen_utm'];
        }

        $contacto->save();

        // Registrar en timeline
        $descripcionActividad = $esNuevo
            ? 'Prospecto captado a través de API REST Externa'
            : 'Prospecto actualizado a través de API REST Externa';
        $contacto->registrarActividad('api_rest', $descripcionActividad, 'API Integración', [
            'utm' => $validated['origen_utm'] ?? null,
        ]);

        // Si viene con mensaje inicial, lo mandamos por WhatsApp
        if (!empty($validated['mensaje_inicial']) && !empty($instancia->access_token) && !empty($instancia->phone_number_id)) {
            $this->whatsAppService->enviarCloud($cleanPhone, [
                'type' => 'text',
                'body' => $validated['mensaje_inicial'],
            ], $instancia->phone_number_id);
        }

        return response()->json([
            'success' => true,
            'accion'  => $esNuevo ? 'creado' : 'actualizado',
            'lead'    => $contacto,
        ], $esNuevo ? 201 : 200);
    }

    // Envío de mensajes de texto, imagen o documentos por WhatsApp
    public function enviarMensaje(Request $request)
    {
        $instancia = $this->autenticarInstancia($request);
        if (!$instancia) {
            return response()->json(['error' => 'No autorizado. Token de API inválido o revocado.'], 401);
        }

        $validated = $request->validate([
            'to'       => 'required|string|max:30',
            'type'     => 'nullable|string|in:text,image,document',
            'body'     => 'required_if:type,text|nullable|string|max:4000',
            'link'     => 'required_unless:type,text|nullable|url',
            'caption'  => 'nullable|string|max:1000',
            'filename' => 'nullable|string|max:120',
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $validated['to']);
        $type = $validated['type'] ?? 'text';

        $data = ['type' => $type];
        if ($type === 'text') {
            $data['body'] = $validated['body'];
        } elseif ($type === 'image') {
            $data['link'] = $validated['link'];
            $data['caption'] = $validated['caption'] ?? '';
        } elseif ($type === 'document') {
            $data['link'] = $validated['link'];
            $data['caption'] = $validated['caption'] ?? '';
            $data['filename'] = $validated['filename'] ?? 'archivo.pdf';
        }

        $res = $this->whatsAppService->enviarCloud($cleanPhone, $data, $instancia->phone_number_id);

        $msgId = $res['messages'][0]['id'] ?? null;
        if ($msgId) {
            Mensaje::create([
                'instancia_id' => $instancia->id,
                'direction'    => 'outbound',
                'sender'       => $instancia->phone_number_id,
                'recipient'    => $cleanPhone,
                'type'         => $type,
                'body'         => $data['body'] ?? ($data['caption'] ?: "[{$type}]"),
                'status'       => 'sent',
                'meta_id'      => $msgId,
            ]);
        }

        return response()->json([
            'success'   => !empty($msgId),
            'meta_id'   => $msgId,
            'respuesta' => $res,
        ]);
    }
}

