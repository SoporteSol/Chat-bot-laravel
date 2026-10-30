<?php

namespace App\Http\Controllers;

use App\Models\ChatbotNode;
use App\Models\Instancia;
use App\Models\Mensaje;
use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use App\Models\UserContext;
use App\Services\AiService;
use App\Services\CotizacionService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstanciaController extends Controller
{
    public function __construct(
        protected WhatsAppService $whatsAppService,
        protected AiService $aiService,
        protected CotizacionService $cotizacionService
    ) {
        $this->middleware('auth');
    }

    // Valida que el usuario tenga acceso a la instancia (admin o asignado)
    protected function autorizarInstancia(int $instanciaId): Instancia
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->canAccessInstancia($instanciaId)) {
            abort(403, 'No tienes autorización para acceder a esta empresa.');
        }

        return Instancia::withCount(['userContexts', 'mensajes'])->findOrFail($instanciaId);
    }

    // Listado de instancias. Si es usuario de empresa, lo manda directo a su panel
    public function index()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            if ($user->instancia_id) {
                return redirect()->route('instancias.show', $user->instancia_id);
            }
            abort(403, 'Tu cuenta de empresa aún no tiene una instancia asignada.');
        }

        $instancias = Instancia::withCount(['userContexts', 'mensajes'])->latest()->get();
        $totalInstancias = $instancias->count();
        $totalBotsActivos = $instancias->where('is_active', true)->count();
        $totalMensajes = Mensaje::count();
        $metaAppId = config('services.meta.app_id', '');
        $metaConfigId = config('services.meta.config_id', '');

        return view('instancias.index', compact(
            'instancias',
            'totalInstancias',
            'totalBotsActivos',
            'totalMensajes',
            'metaAppId',
            'metaConfigId'
        ));
    }

    // Vista principal del CRM: métricas, kanban, chat en vivo y configuración
    public function show(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $buscar = trim((string) $request->input('buscar'));

        $query = $instancia->userContexts()->latest('updated_at');

        if ($buscar !== '') {
            $query->where(function ($q) use ($buscar) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%' . mb_strtolower($buscar) . '%'])
                    ->orWhere('phone_number', 'LIKE', "%{$buscar}%");
            });
        }

        $contactos = $query->paginate(20)->appends(['buscar' => $buscar])->fragment('chats');

        $etapas = $instancia->obtenerEtapas();

        $leadsQuery = $instancia->userContexts()
            ->with('asesorAsignado:id,name,email')
            ->latest('updated_at');

        // Si es vendedor con restricción, solo le mostramos los leads que tiene asignados
        if (!$isAdmin && $user->sub_rol === 'vendedor' && $user->solo_asignados) {
            $leadsQuery->where(function ($q) use ($user) {
                $q->where('asignado_a', $user->id)
                    ->orWhereNull('asignado_a');
            });
        }

        // Contactos y mapa de últimos mensajes para triage multichat
        $contactsCollection = $leadsQuery->get();
        $phoneNumbers = $contactsCollection->pluck('phone_number')->filter()->unique()->values();

        $ultimosMensajesMap = collect();
        if ($phoneNumbers->isNotEmpty()) {
            $ultimosMensajesMap = Mensaje::where('instancia_id', $instancia->id)
                ->where(function ($q) use ($phoneNumbers) {
                    $q->whereIn('from', $phoneNumbers)
                        ->orWhereIn('to', $phoneNumbers);
                })
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(function ($m) {
                    return $m->direction === 'inbound' ? $m->from : $m->to;
                })
                ->map(function ($group) {
                    return $group->first();
                });
        }

        // Contactos formateados para el kanban, inbox y directorio
        $todosContactos = $contactsCollection
            ->map(function (UserContext $c) use ($etapas, $ultimosMensajesMap) {
                $botPausado = !$c->is_bot_active || ($c->bot_paused_until && now()->lt($c->bot_paused_until));
                $lastInbound = $c->ultimo_mensaje_cliente_at;
                $ventanaActiva = $lastInbound ? now()->diffInHours($lastInbound, false) > -24 && $lastInbound->gt(now()->subHours(24)) : false;
                $horasRestantes = ($ventanaActiva && $lastInbound)
                    ? max(0, round(now()->diffInMinutes($lastInbound->copy()->addHours(24), false) / 60, 1))
                    : 0;

                $lastMsg = $ultimosMensajesMap->get($c->phone_number);

                return [
                    'id'                         => $c->id,
                    'nombre'                     => $c->nombre ?: 'Cliente WhatsApp',
                    'empresa_cliente'            => $c->empresa_cliente ?: '',
                    'email'                      => $c->email ?: '',
                    'phone_number'               => $c->phone_number,
                    'current_node'               => $c->current_node ?: 'main',
                    'etapa'                      => array_key_exists($c->etapa ?? '', $etapas) ? $c->etapa : array_key_first($etapas),
                    'motivo_perdida'             => $c->motivo_perdida ?: '',
                    'estado_conversacion'        => $c->estado_conversacion ?: 'abierta',
                    'mensajes_sin_leer'          => (int) ($c->mensajes_sin_leer ?? 0),
                    'opt_in'                     => (bool) ($c->opt_in ?? true),
                    'valor_estimado'             => (float) ($c->valor_estimado ?? 0),
                    'etiquetas'                  => is_array($c->etiquetas) ? array_values($c->etiquetas) : [],
                    'notas'                      => is_array($c->notas) ? array_values($c->notas) : [],
                    'tareas'                     => is_array($c->tareas) ? array_values($c->tareas) : [],
                    'productos_cotizados'        => is_array($c->productos_cotizados) ? array_values($c->productos_cotizados) : [],
                    'campos_personalizados'      => is_array($c->campos_personalizados) ? $c->campos_personalizados : [],
                    'timeline_actividades'       => is_array($c->timeline_actividades) ? array_values($c->timeline_actividades) : [],
                    'cotizaciones_historial'     => is_array($c->cotizaciones_historial) ? array_values($c->cotizaciones_historial) : [],
                    'origen_utm'                 => is_array($c->origen_utm) ? $c->origen_utm : null,
                    'resumen_ia'                 => $c->resumen_ia,
                    'asignado_a'                 => $c->asignado_a,
                    'asesor_nombre'              => $c->asesorAsignado?->name,
                    'is_bot_active'              => !$botPausado,
                    'ventana_24h_activa'         => $ventanaActiva,
                    'ventana_24h_horas'          => $horasRestantes,
                    'ultimo_mensaje_cliente_iso' => $lastInbound ? $lastInbound->toIso8601String() : null,
                    'ultimo_mensaje_id'          => $lastMsg ? $lastMsg->id : 0,
                    'ultimo_mensaje_body'        => $lastMsg ? $lastMsg->body : null,
                    'ultimo_mensaje_direction'   => $lastMsg ? $lastMsg->direction : null,
                    'ultimo_mensaje_type'        => $lastMsg ? $lastMsg->type : null,
                    'ultimo_mensaje_status'      => $lastMsg ? $lastMsg->status : null,
                    'ultimo_mensaje_human'       => $lastMsg && $lastMsg->created_at ? $lastMsg->created_at->diffForHumans(null, true) : null,
                    'ultimo_mensaje_iso'         => $lastMsg && $lastMsg->created_at ? $lastMsg->created_at->toIso8601String() : null,
                    'updated_human'              => $c->updated_at ? $c->updated_at->diffForHumans() : 'Reciente',
                    'updated_iso'                => $c->updated_at ? $c->updated_at->toIso8601String() : null,
                ];
            })
            ->values();

        // Conversaciones activas ordenadas para triage prioritario del Dashboard
        $conversacionesActivas = $todosContactos
            ->filter(function ($c) {
                return !empty($c['ultimo_mensaje_body']) || $c['mensajes_sin_leer'] > 0 || $c['estado_conversacion'] !== 'resuelta';
            })
            ->sortByDesc(function ($c) {
                $priority = 0;
                if ($c['mensajes_sin_leer'] > 0) $priority += 10000000;
                if (!$c['is_bot_active']) $priority += 5000000;
                $priority += (int) ($c['ultimo_mensaje_id'] ?? 0);
                return $priority;
            })
            ->values();

        $usuariosEmpresa = User::where('instancia_id', $instancia->id)
            ->select('id', 'name', 'email', 'sub_rol', 'solo_asignados', 'created_at')
            ->orderBy('name')
            ->get();

        $camposPersonalizadosDef = is_array($instancia->campos_personalizados_definicion)
            ? array_values($instancia->campos_personalizados_definicion)
            : [];
        $catalogoProductos = is_array($instancia->catalogo_productos) ? array_values($instancia->catalogo_productos) : [];
        $plantillasRapidas = is_array($instancia->plantillas_rapidas) ? array_values($instancia->plantillas_rapidas) : [];
        $pipelineAutomations = is_array($instancia->pipeline_automations) ? $instancia->pipeline_automations : [];
        $campanasDifusion = is_array($instancia->campanas_difusion) ? array_values($instancia->campanas_difusion) : [];

        $totalInbound = $instancia->mensajes()->where('direction', 'inbound')->count();
        $totalOutbound = $instancia->mensajes()->where('direction', 'outbound')->count();
        $mensajesRecientes = $instancia->mensajes()->latest()->take(25)->get();

        $nodes = ChatbotNode::forInstancia($instancia->id)->get();
        $allNodeIds = $nodes->pluck('id')->values();

        $horarioAtencion = is_array($instancia->horario_atencion) ? $instancia->horario_atencion : [
            'activo'                => false,
            'timezone'              => 'America/Guatemala',
            'mensaje_fuera_horario' => '¡Hola! Gracias por comunicarte. En este momento nos encontramos fuera de nuestro horario de atención comercial. Te responderemos a la brevedad.',
            'dias'                  => [
                'lunes'     => ['activo' => true, 'desde' => '09:00', 'hasta' => '18:00'],
                'martes'    => ['activo' => true, 'desde' => '09:00', 'hasta' => '18:00'],
                'miercoles' => ['activo' => true, 'desde' => '09:00', 'hasta' => '18:00'],
                'jueves'    => ['activo' => true, 'desde' => '09:00', 'hasta' => '18:00'],
                'viernes'   => ['activo' => true, 'desde' => '09:00', 'hasta' => '18:00'],
                'sabado'    => ['activo' => false, 'desde' => '09:00', 'hasta' => '13:00'],
                'domingo'   => ['activo' => false, 'desde' => '00:00', 'hasta' => '00:00'],
            ],
        ];
        $baseConocimiento = is_array($instancia->base_conocimiento) ? array_values($instancia->base_conocimiento) : [];
        $webhookSaliente = is_array($instancia->webhook_saliente) ? $instancia->webhook_saliente : [
            'activo'     => false,
            'url'        => '',
            'secret_key' => '',
            'eventos'    => ['lead.creado', 'lead.etapa_cambiada'],
        ];
        $datosFiscales = is_array($instancia->datos_fiscales) ? $instancia->datos_fiscales : [
            'razon_social'         => $instancia->nombre_empresa,
            'nit_rfc'              => '',
            'direccion'            => '',
            'email_contacto'       => '',
            'moneda'               => 'GTQ',
            'porcentaje_impuesto'  => 12.0,
            'validez_dias_default' => 15,
            'terminos_condiciones' => 'Esta cotización es válida por 15 días calendario. Sujeta a términos y condiciones generales del servicio.',
        ];
        $apiToken = $instancia->api_token;
        $planInfo = [
            'plan'                   => $instancia->plan ?: 'Enterprise',
            'limite_mensajes_ia_mes' => (int) ($instancia->limite_mensajes_ia_mes ?: 10000),
            'mensajes_ia_consumidos' => (int) ($instancia->mensajes_ia_consumidos ?: 0),
        ];

        return view('instancias.show', compact(
            'instancia',
            'isAdmin',
            'contactos',
            'todosContactos',
            'conversacionesActivas',
            'etapas',
            'usuariosEmpresa',
            'camposPersonalizadosDef',
            'catalogoProductos',
            'plantillasRapidas',
            'pipelineAutomations',
            'campanasDifusion',
            'totalInbound',
            'totalOutbound',
            'mensajesRecientes',
            'nodes',
            'allNodeIds',
            'buscar',
            'horarioAtencion',
            'baseConocimiento',
            'webhookSaliente',
            'datosFiscales',
            'apiToken',
            'planInfo'
        ));
    }

    // Crea o activa una instancia con las credenciales de prueba del .env
    public function generarDemo()
    {
        $testPhoneId = config('services.whatsapp.phone_number_id');
        $testToken   = config('services.whatsapp.access_token');
        $testWabaId  = config('services.meta.waba_id');
        $testPhone   = config('services.whatsapp.test_phone');

        if (empty($testPhoneId)) {
            return redirect()->route('instancias.index')
                ->with('error', 'Configura WHATSAPP_PHONE_NUMBER_ID en tu archivo .env para crear la instancia de prueba.');
        }

        $existente = Instancia::where('phone_number_id', $testPhoneId)->first();

        if ($existente) {
            $existente->update([
                'is_active'    => true,
                'access_token' => $testToken ?: $existente->access_token,
                'telefono'     => $testPhone ?: $existente->telefono,
                'waba_id'      => $testWabaId ?: $existente->waba_id,
            ]);
            $instancia = $existente;
        } else {
            $instancia = Instancia::create([
                'nombre_empresa'  => 'Meta Test',
                'telefono'        => $testPhone,
                'phone_number_id' => $testPhoneId,
                'waba_id'         => $testWabaId,
                'access_token'    => $testToken,
                'system_prompt'   => null,
                'is_active'       => true,
            ]);
        }

        return redirect()->route('instancias.show', $instancia->id)
            ->with('success', "Instancia '{$instancia->nombre_empresa}' lista. Configura aquí tus nodos y tu prompt.");
    }

    // Alta de nueva instancia
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_empresa'  => 'required|string|max:255',
            'telefono'        => 'nullable|string|max:50',
            'phone_number_id' => 'required|string|max:100|unique:instancias,phone_number_id',
            'waba_id'         => 'nullable|string|max:100',
            'system_prompt'   => 'nullable|string',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $instancia = Instancia::create($validated);

        return redirect()->route('instancias.show', $instancia->id)
            ->with('success', "Empresa '{$instancia->nombre_empresa}' creada exitosamente. Ya puedes configurar y entrenar su bot.");
    }

    // Guarda cambios de la instancia (nombre, prompt, tiempo de pausa...)
    public function update(Request $request, $id)
    {
        $instancia = Instancia::findOrFail($id);

        $validated = $request->validate([
            'nombre_empresa'  => 'required|string|max:255',
            'telefono'        => 'nullable|string|max:50',
            'phone_number_id' => 'required|string|max:100|unique:instancias,phone_number_id,' . $instancia->id,
            'waba_id'         => 'nullable|string|max:100',
            'access_token'    => 'nullable|string',
            'system_prompt'   => 'nullable|string',
            'bot_pause_hours' => 'nullable|integer|min:1|max:720',
            'is_active'       => 'nullable|boolean',
        ]);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        } else {
            unset($validated['is_active']);
        }

        $instancia->update($validated);

        return redirect()->back()->with('success', 'Configuración de la empresa y bot actualizada correctamente.');
    }

    // Elimina la instancia y sus datos relacionados
    public function destroy($id)
    {
        $instancia = Instancia::findOrFail($id);
        $nombre = $instancia->nombre_empresa;
        $instancia->delete();

        return redirect()->route('instancias.index')->with('success', "Empresa '{$nombre}' eliminada correctamente.");
    }

    // Pausa o reanuda el bot para que un asesor pueda tomar la conversación
    public function toggleBot($id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $pauseHours = max(1, (int) ($instancia->bot_pause_hours ?: 12));
        $nuevoEstado = !$contacto->is_bot_active;
        $contacto->update([
            'is_bot_active'    => $nuevoEstado,
            'bot_paused_until' => $nuevoEstado ? null : now()->addHours($pauseHours),
        ]);

        if (request()->wantsJson()) {
            return response()->json([
                'success'       => true,
                'is_bot_active' => $contacto->is_bot_active,
                'message'       => $nuevoEstado ? 'Bot activado para este contacto' : 'Bot pausado para este contacto (Intervención humana activa)',
            ]);
        }

        return redirect()->back()->with('success', $nuevoEstado ? 'Bot reactivado para el cliente.' : 'Bot pausado. Ahora un asesor humano puede intervenir libremente.');
    }

    // Historial de mensajes para el chat en vivo (soporta after_id para polling incremental)
    public function chatHistorial(Request $request, $id, $contactoId)
    {
        if (session()->isStarted()) {
            session()->save();
        }

        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        // Al abrir la conversación marcamos los mensajes como leídos
        if ((int) $contacto->mensajes_sin_leer > 0) {
            $contacto->update(['mensajes_sin_leer' => 0]);
        }

        $afterId = $request->query('after_id');

        $query = Mensaje::where('instancia_id', $instancia->id)
            ->where(function ($q) use ($contacto) {
                $q->where('from', $contacto->phone_number)
                    ->orWhere('to', $contacto->phone_number);
            })
            ->select('id', 'direction', 'body', 'type', 'media_url', 'status', 'error_message', 'created_at');

        $opcionesMap = Option::where('instancia_id', $instancia->id)->pluck('text', 'id');
        $transformMsg = function ($msg) use ($opcionesMap) {
            if ($msg->direction === 'inbound' && is_numeric($msg->body) && isset($opcionesMap[$msg->body])) {
                $msg->body = $opcionesMap[$msg->body];
            }
            return $msg;
        };

        $botPausado = !$contacto->is_bot_active || ($contacto->bot_paused_until && now()->lt($contacto->bot_paused_until));
        $lastInbound = $contacto->ultimo_mensaje_cliente_at;
        $ventanaActiva = $lastInbound ? $lastInbound->gt(now()->subHours(24)) : false;
        $horasRestantes = ($ventanaActiva && $lastInbound)
            ? max(0, round(now()->diffInMinutes($lastInbound->copy()->addHours(24), false) / 60, 1))
            : 0;

        if ($afterId && is_numeric($afterId)) {
            $mensajes = $query->where('id', '>', (int) $afterId)
                ->orderBy('id', 'asc')
                ->get()
                ->transform($transformMsg);

            return response()->json([
                'success'            => true,
                'incremental'        => true,
                'is_bot_active'      => !$botPausado,
                'etapa'              => $contacto->etapa ?: 'nuevo',
                'ventana_24h_activa' => $ventanaActiva,
                'ventana_24h_horas'  => $horasRestantes,
                'mensajes'           => $mensajes,
            ]);
        }

        $mensajes = $query->orderBy('id', 'desc')
            ->take(100)
            ->get()
            ->reverse()
            ->values()
            ->transform($transformMsg);

        return response()->json([
            'success'     => true,
            'incremental' => false,
            'contacto'    => [
                'id'                    => $contacto->id,
                'nombre'                => $contacto->nombre ?: 'Cliente WhatsApp',
                'phone_number'          => $contacto->phone_number,
                'current_node'          => $contacto->current_node ?: 'main',
                'etapa'                 => $contacto->etapa ?: 'nuevo',
                'motivo_perdida'        => $contacto->motivo_perdida ?: '',
                'estado_conversacion'   => $contacto->estado_conversacion ?: 'abierta',
                'mensajes_sin_leer'     => 0,
                'opt_in'                => (bool) ($contacto->opt_in ?? true),
                'valor_estimado'        => (float) ($contacto->valor_estimado ?? 0),
                'etiquetas'             => is_array($contacto->etiquetas) ? array_values($contacto->etiquetas) : [],
                'notas'                 => is_array($contacto->notas) ? array_values($contacto->notas) : [],
                'tareas'                => is_array($contacto->tareas) ? array_values($contacto->tareas) : [],
                'productos_cotizados'   => is_array($contacto->productos_cotizados) ? array_values($contacto->productos_cotizados) : [],
                'campos_personalizados' => is_array($contacto->campos_personalizados) ? $contacto->campos_personalizados : [],
                'resumen_ia'            => $contacto->resumen_ia,
                'asignado_a'            => $contacto->asignado_a,
                'is_bot_active'         => !$botPausado,
                'ventana_24h_activa'    => $ventanaActiva,
                'ventana_24h_horas'     => $horasRestantes,
            ],
            'mensajes'    => $mensajes,
        ]);
    }

    // Envío de mensaje desde el chat o guardado de nota interna
    public function enviarMensajeDirecto(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $request->validate([
            'mensaje'         => 'nullable|string|max:4000',
            'es_nota_interna' => 'nullable|boolean',
            'archivo'         => 'nullable|file|mimes:jpeg,png,jpg,webp,gif|max:16384',
        ]);

        $hasFile = $request->hasFile('archivo');
        $texto = trim((string) $request->input('mensaje'));

        if (!$hasFile && empty($texto)) {
            return response()->json([
                'success' => false,
                'error'   => 'Debes ingresar un mensaje de texto o adjuntar una imagen.',
            ], 422);
        }

        // Las notas internas no se mandan a WhatsApp, solo quedan en el historial
        if ($request->boolean('es_nota_interna')) {
            /** @var User|null $autor */
            $autor = Auth::user();
            $autorNombre = $autor?->name ?? 'Asesor';
            $textoNota = $texto;
            $mediaUrl = null;

            if ($hasFile) {
                $file = $request->file('archivo');
                $ext = $file->getClientOriginalExtension() ?: 'jpg';
                $filename = 'note_' . time() . '_' . Str::random(8) . '.' . $ext;
                $storageDir = storage_path('app/public/whatsapp_media');
                if (!file_exists($storageDir)) {
                    mkdir($storageDir, 0755, true);
                }
                $file->move($storageDir, $filename);
                $mediaUrl = asset('storage/whatsapp_media/' . $filename);
            }

            Mensaje::create([
                'instancia_id' => $instancia->id,
                'from'         => $autorNombre,
                'to'           => $contacto->phone_number,
                'body'         => "[Nota interna - {$autorNombre}]: " . ($textoNota ?: ($mediaUrl ? '[Imagen adjunta]' : '')),
                'type'         => $hasFile ? 'image' : 'note',
                'media_url'    => $mediaUrl,
                'direction'    => 'internal_note',
                'status'       => 'read',
            ]);

            $notas = is_array($contacto->notas) ? array_values($contacto->notas) : [];
            array_unshift($notas, [
                'id'        => 'n_' . Str::random(10),
                'autor'     => $autorNombre,
                'texto'     => $textoNota ?: 'Imagen adjunta',
                'media_url' => $mediaUrl,
                'fecha'     => now()->format('d/m/Y H:i'),
            ]);
            $contacto->update(['notas' => array_slice($notas, 0, 50)]);

            return response()->json([
                'success'       => true,
                'is_bot_active' => $contacto->is_bot_active,
                'notas'         => $contacto->notas,
                'message'       => 'Nota interna registrada en el hilo',
            ]);
        }

        $pauseHours = max(1, (int) ($instancia->bot_pause_hours ?: 12));

        // Al responder un humano, pausamos el bot para no pisar la conversación
        $contacto->update([
            'is_bot_active'    => false,
            'bot_paused_until' => now()->addHours($pauseHours),
        ]);

        if ($hasFile) {
            $file = $request->file('archivo');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'wa_out_' . time() . '_' . Str::random(8) . '.' . $ext;
            $storageDir = storage_path('app/public/whatsapp_media');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            $file->move($storageDir, $filename);
            $fullPath = $storageDir . DIRECTORY_SEPARATOR . $filename;
            $publicUrl = asset('storage/whatsapp_media/' . $filename);
            $mime = $file->getClientMimeType() ?: 'image/jpeg';

            // Subir binario a los servidores de Meta WhatsApp Cloud para obtener un media_id válido
            $metaMediaId = $this->whatsAppService->subirMedia($fullPath, $mime, $instancia->phone_number_id);

            $payload = [
                'type'      => 'image',
                'media_url' => $publicUrl,
            ];
            if ($metaMediaId) {
                $payload['id'] = $metaMediaId;
            } else {
                $payload['link'] = $publicUrl;
            }
            if (!empty($texto)) {
                $payload['caption'] = $texto;
            }

            $res = $this->whatsAppService->enviarCloud($contacto->phone_number, $payload, $instancia->phone_number_id);
        } else {
            $res = $this->whatsAppService->enviarCloud($contacto->phone_number, [
                'type' => 'text',
                'body' => $texto,
            ], $instancia->phone_number_id);
        }

        $hasError = is_array($res) && isset($res['error']);
        $errorMessage = $hasError
            ? (is_array($res['error']) ? ($res['error']['message'] ?? json_encode($res['error'])) : (string) $res['error'])
            : null;

        if (!$request->wantsJson() && !$request->ajax()) {
            if ($hasError) {
                return redirect()->back()->with('error', 'Error enviando mensaje: ' . $errorMessage);
            }
            return redirect()->back()->with('success', 'Mensaje enviado correctamente al cliente.');
        }

        if ($hasError) {
            return response()->json([
                'success'  => false,
                'error'    => $errorMessage,
                'response' => $res,
            ], 422);
        }

        return response()->json([
            'success'       => true,
            'is_bot_active' => false,
            'message'       => 'Mensaje enviado correctamente al cliente',
            'response'      => $res,
        ]);
    }

    // Actualiza datos del lead (etapa kanban, valor estimado, asesor, etc.)
    public function actualizarCrm(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $etapasPermitidas = array_keys($instancia->obtenerEtapas());

        $request->validate([
            'etapa'                 => 'sometimes|required|string|in:' . implode(',', $etapasPermitidas),
            'motivo_perdida'        => 'sometimes|nullable|string|max:255',
            'estado_conversacion'   => 'sometimes|required|string|in:abierta,pendiente,resuelta',
            'opt_in'                => 'sometimes|boolean',
            'valor_estimado'        => 'sometimes|nullable|numeric|min:0|max:999999999',
            'nombre'                => 'sometimes|required|string|max:120',
            'empresa_cliente'       => 'sometimes|nullable|string|max:160',
            'email'                 => 'sometimes|nullable|email|max:190',
            'etiquetas'             => 'sometimes|nullable|array',
            'etiquetas.*'           => 'string|max:40',
            'productos_cotizados'   => 'sometimes|nullable|array',
            'campos_personalizados' => 'sometimes|nullable|array',
            'asignado_a'            => 'sometimes|nullable|integer|exists:users,id',
        ]);

        $etapaAnterior = $contacto->etapa;
        $updates = [];
        $pauseHours = max(1, (int) ($instancia->bot_pause_hours ?: 12));

        if ($request->has('etapa')) {
            $updates['etapa'] = $request->input('etapa');
            if ($updates['etapa'] === 'asesor') {
                $updates['is_bot_active'] = false;
                $updates['bot_paused_until'] = now()->addHours($pauseHours);
            }
            if ($updates['etapa'] !== 'perdido' && !$request->has('motivo_perdida')) {
                $updates['motivo_perdida'] = null;
            }
        }

        if ($request->has('motivo_perdida')) {
            $updates['motivo_perdida'] = trim((string) $request->input('motivo_perdida')) ?: null;
        }

        if ($request->has('estado_conversacion')) {
            $updates['estado_conversacion'] = $request->input('estado_conversacion');
        }

        if ($request->has('opt_in')) {
            $updates['opt_in'] = $request->boolean('opt_in');
        }

        if ($request->has('valor_estimado')) {
            $updates['valor_estimado'] = (float) ($request->input('valor_estimado') ?? 0);
        }

        if ($request->has('nombre')) {
            $updates['nombre'] = trim((string) $request->input('nombre'));
        }

        if ($request->has('empresa_cliente')) {
            $updates['empresa_cliente'] = trim((string) $request->input('empresa_cliente')) ?: null;
        }

        if ($request->has('email')) {
            $updates['email'] = trim((string) $request->input('email')) ?: null;
        }

        if ($request->has('etiquetas')) {
            $rawTags = is_array($request->input('etiquetas')) ? $request->input('etiquetas') : [];
            $cleanTags = array_values(array_unique(array_filter(array_map(fn($t) => mb_substr(trim((string) $t), 0, 35), $rawTags))));
            $updates['etiquetas'] = $cleanTags;
        }

        if ($request->has('productos_cotizados')) {
            $rawProds = is_array($request->input('productos_cotizados')) ? $request->input('productos_cotizados') : [];
            $cleanProds = [];
            $sumTotal = 0.0;
            foreach ($rawProds as $p) {
                if (!is_array($p) || empty($p['nombre'])) continue;
                $precio = (float) ($p['precio'] ?? 0);
                $cant = max(1, (int) ($p['cantidad'] ?? 1));
                $cleanProds[] = [
                    'producto_id' => (string) ($p['producto_id'] ?? ''),
                    'nombre'      => mb_substr(trim((string) $p['nombre']), 0, 120),
                    'precio'      => $precio,
                    'cantidad'    => $cant,
                ];
                $sumTotal += ($precio * $cant);
            }
            $updates['productos_cotizados'] = $cleanProds;
            if (!$request->has('valor_estimado')) {
                $updates['valor_estimado'] = $sumTotal;
            }
        }

        if ($request->has('campos_personalizados')) {
            $rawCampos = is_array($request->input('campos_personalizados')) ? $request->input('campos_personalizados') : [];
            $cleanCampos = [];
            foreach ($rawCampos as $k => $v) {
                $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $k);
                if ($safeKey !== '') {
                    $cleanCampos[$safeKey] = is_bool($v) ? $v : mb_substr(trim((string) $v), 0, 500);
                }
            }
            $updates['campos_personalizados'] = $cleanCampos;
        }

        if ($request->has('asignado_a')) {
            $updates['asignado_a'] = $request->input('asignado_a') ?: null;
        }

        $contacto->update($updates);

        // Si cambió de etapa, disparamos las reglas configuradas y auditamos
        if (isset($updates['etapa']) && $updates['etapa'] !== $etapaAnterior) {
            $this->ejecutarAutomatizacionEtapa($instancia, $contacto, $updates['etapa']);
            $etapaLabels = $instancia->obtenerEtapas();
            $nomAnterior = $etapaLabels[$etapaAnterior]['nombre'] ?? $etapaAnterior;
            $nomNueva = $etapaLabels[$updates['etapa']]['nombre'] ?? $updates['etapa'];
            $contacto->registrarActividad('cambio_etapa', "Etapa cambiada de '{$nomAnterior}' a '{$nomNueva}'", Auth::user()?->name ?? 'Sistema', [
                'etapa_anterior' => $etapaAnterior,
                'etapa_nueva'    => $updates['etapa'],
            ]);
            $this->dispararWebhookSaliente($instancia, 'lead.etapa_cambiada', [
                'contacto_id'    => $contacto->id,
                'phone_number'   => $contacto->phone_number,
                'nombre'         => $contacto->nombre,
                'etapa_anterior' => $etapaAnterior,
                'etapa_nueva'    => $updates['etapa'],
                'valor_estimado' => $contacto->valor_estimado,
            ]);
            $contacto->refresh();
        }

        if (isset($updates['asignado_a'])) {
            $contacto->load('asesorAsignado:id,name,email');
            $nomAsesor = $contacto->asesorAsignado?->name ?? 'Sin asignar';
            $contacto->registrarActividad('asignacion', "Lead asignado a {$nomAsesor}", Auth::user()?->name ?? 'Sistema');
            $this->dispararWebhookSaliente($instancia, 'lead.asignado', [
                'contacto_id'  => $contacto->id,
                'phone_number' => $contacto->phone_number,
                'asesor'       => $nomAsesor,
            ]);
        }

        if (isset($updates['productos_cotizados']) || (isset($updates['valor_estimado']) && !isset($updates['etapa']))) {
            $contacto->registrarActividad('cotizacion_actualizada', "Productos / Valor estimado actualizado a $" . number_format($contacto->valor_estimado, 2), Auth::user()?->name ?? 'Sistema');
        }

        $contacto->load('asesorAsignado:id,name,email');
        $botPausado = !$contacto->is_bot_active || ($contacto->bot_paused_until && now()->lt($contacto->bot_paused_until));

        return response()->json([
            'success'       => true,
            'contacto'      => array_merge($contacto->toArray(), [
                'asesor_nombre' => $contacto->asesorAsignado?->name,
            ]),
            'is_bot_active' => !$botPausado,
        ]);
    }

    // Ejecuta las acciones automáticas al entrar a una etapa (tags, tareas, mensajes)
    public function ejecutarAutomatizacionEtapa(Instancia $instancia, UserContext $contacto, string $etapa): void
    {
        $automations = is_array($instancia->pipeline_automations) ? $instancia->pipeline_automations : [];
        $regla = $automations[$etapa] ?? null;
        if (!is_array($regla)) {
            return;
        }

        $updates = [];

        // Tags automáticos
        if (!empty($regla['auto_tag'])) {
            $tagsActuales = is_array($contacto->etiquetas) ? $contacto->etiquetas : [];
            $nuevosTags = array_filter(array_map('trim', explode(',', (string) $regla['auto_tag'])));
            $updates['etiquetas'] = array_values(array_unique(array_merge($tagsActuales, $nuevosTags)));
        }

        // Tarea automática
        if (!empty($regla['crear_tarea'])) {
            $tareasActuales = is_array($contacto->tareas) ? array_values($contacto->tareas) : [];
            $tareasActuales[] = [
                'id'          => 't_' . Str::random(10),
                'titulo'      => trim((string) $regla['crear_tarea']),
                'tipo'        => 'seguimiento',
                'vencimiento' => 'Hoy',
                'completada'  => false,
            ];
            $updates['tareas'] = array_slice($tareasActuales, -40);
        }

        // Pausar o activar bot
        $pauseHours = max(1, (int) ($instancia->bot_pause_hours ?: 12));
        if (!empty($regla['accion_bot'])) {
            if ($regla['accion_bot'] === 'pausar') {
                $updates['is_bot_active'] = false;
                $updates['bot_paused_until'] = now()->addHours($pauseHours);
            } elseif ($regla['accion_bot'] === 'activar') {
                $updates['is_bot_active'] = true;
                $updates['bot_paused_until'] = null;
            }
        }

        if (!empty($updates)) {
            $contacto->update($updates);
        }

        // Mensaje automático por WhatsApp
        if (!empty($regla['mensaje_whatsapp']) && !empty($contacto->phone_number)) {
            $texto = str_replace(
                ['{{nombre}}', '{{empresa}}', '{{telefono}}'],
                [$contacto->nombre ?: 'Cliente', $instancia->nombre_empresa, $contacto->phone_number],
                trim((string) $regla['mensaje_whatsapp'])
            );

            try {
                $this->whatsAppService->enviarCloud(
                    $contacto->phone_number,
                    ['type' => 'text', 'body' => $texto],
                    $instancia->phone_number_id
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Error enviando mensaje de automatización de etapa: ' . $e->getMessage());
            }
        }
    }

    // Agrega o elimina notas internas en la ficha del cliente
    public function agregarNota(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $notas = is_array($contacto->notas) ? array_values($contacto->notas) : [];

        if ($request->input('accion') === 'eliminar' && $request->filled('nota_id')) {
            $notaId = (string) $request->input('nota_id');
            $notas = array_values(array_filter($notas, fn($n) => ($n['id'] ?? '') !== $notaId));
            $contacto->update(['notas' => $notas]);

            return response()->json([
                'success' => true,
                'notas'   => $notas,
            ]);
        }

        $request->validate([
            'texto' => 'required|string|max:1500',
        ]);

        /** @var User|null $user */
        $user = Auth::user();
        array_unshift($notas, [
            'id'    => 'n_' . Str::random(10),
            'autor' => $user?->name ?? 'Asesor',
            'texto' => trim((string) $request->input('texto')),
            'fecha' => now()->format('d/m/Y H:i'),
        ]);

        $contacto->update(['notas' => array_slice($notas, 0, 50)]);

        return response()->json([
            'success' => true,
            'notas'   => $contacto->notas,
        ]);
    }

    // Gestión de tareas y recordatorios vinculados al lead
    public function gestionarTarea(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $tareas = is_array($contacto->tareas) ? array_values($contacto->tareas) : [];
        $accion = $request->input('accion', 'crear');

        if ($accion === 'toggle' && $request->filled('tarea_id')) {
            $tareaId = (string) $request->input('tarea_id');
            foreach ($tareas as &$t) {
                if (($t['id'] ?? '') === $tareaId) {
                    $t['completada'] = empty($t['completada']);
                }
            }
            unset($t);
            $contacto->update(['tareas' => $tareas]);

            return response()->json(['success' => true, 'tareas' => $tareas]);
        }

        if ($accion === 'eliminar' && $request->filled('tarea_id')) {
            $tareaId = (string) $request->input('tarea_id');
            $tareas = array_values(array_filter($tareas, fn($t) => ($t['id'] ?? '') !== $tareaId));
            $contacto->update(['tareas' => $tareas]);

            return response()->json(['success' => true, 'tareas' => $tareas]);
        }

        $request->validate([
            'titulo'      => 'required|string|max:200',
            'tipo'        => 'nullable|string|in:llamada,reunion,seguimiento,cotizacion',
            'vencimiento' => 'nullable|string|max:40',
        ]);

        $tareas[] = [
            'id'          => 't_' . Str::random(10),
            'titulo'      => trim((string) $request->input('titulo')),
            'tipo'        => $request->input('tipo', 'seguimiento'),
            'vencimiento' => trim((string) ($request->input('vencimiento') ?: 'Hoy')),
            'completada'  => false,
        ];

        $contacto->update(['tareas' => array_slice($tareas, -40)]);

        return response()->json([
            'success' => true,
            'tareas'  => $contacto->tareas,
        ]);
    }

    // Copiloto: genera un resumen de los últimos mensajes del cliente
    public function iaResumen(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $historial = Mensaje::where('instancia_id', $instancia->id)
            ->where('direction', '!=', 'internal_note')
            ->where(function ($q) use ($contacto) {
                $q->where('from', $contacto->phone_number)
                    ->orWhere('to', $contacto->phone_number);
            })
            ->latest('id')
            ->take(30)
            ->get()
            ->reverse()
            ->values();

        $resumen = $this->aiService->generarResumenChat(
            $historial,
            $instancia->nombre_empresa,
            $contacto->nombre ?: 'Cliente'
        );

        $contacto->update(['resumen_ia' => $resumen]);

        return response()->json([
            'success'    => true,
            'resumen_ia' => $resumen,
        ]);
    }

    // Copiloto: sugiere una respuesta comercial según el historial
    public function iaSugerir(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $historial = Mensaje::where('instancia_id', $instancia->id)
            ->where(function ($q) use ($contacto) {
                $q->where('from', $contacto->phone_number)
                    ->orWhere('to', $contacto->phone_number);
            })
            ->latest('id')
            ->take(12)
            ->get()
            ->reverse()
            ->values();

        $sugerencia = $this->aiService->sugerirRespuestaAgente(
            $historial,
            $instancia->system_prompt,
            $instancia->nombre_empresa,
            $contacto->nombre ?: 'Cliente'
        );

        return response()->json([
            'success'    => true,
            'sugerencia' => $sugerencia,
        ]);
    }

    // Copiloto: reescribe el borrador con el tono pedido (ventas, formal, etc.)
    public function iaTono(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $request->validate([
            'borrador' => 'required|string|max:1500',
            'tono'     => 'required|string|in:ventas,formal,amigable,conciso',
        ]);

        $mejorado = $this->aiService->reescribirTonoMensaje(
            (string) $request->input('borrador'),
            (string) $request->input('tono'),
            $instancia->nombre_empresa
        );

        return response()->json([
            'success'  => true,
            'mejorado' => $mejorado,
        ]);
    }

    // Crea un usuario para que la empresa acceda solo a su propio CRM
    public function crearUsuarioEmpresa(Request $request, $id)
    {
        $instancia = Instancia::findOrFail($id);

        $validated = $request->validate([
            'name'           => 'required|string|max:120',
            'email'          => 'required|email|max:190|unique:users,email',
            'password'       => 'required|string|min:8|max:100',
            'sub_rol'        => 'nullable|string|in:supervisor,vendedor',
            'solo_asignados' => 'nullable|boolean',
        ]);

        $roleEmpresa = Role::firstOrCreate(
            ['name' => 'empresa'],
            [
                'display_name' => 'Empresa / Asesor CRM',
                'description'  => 'Acceso exclusivo al Embudo de Ventas e Inbox Conversacional de su instancia asignada.',
            ]
        );

        $nuevoUsuario = new User([
            'name'     => $validated['name'],
            'email'    => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
        ]);
        $nuevoUsuario->role_id = $roleEmpresa->id;
        $nuevoUsuario->instancia_id = $instancia->id;
        $nuevoUsuario->sub_rol = $validated['sub_rol'] ?? 'supervisor';
        $nuevoUsuario->solo_asignados = $request->boolean('solo_asignados', false);
        $nuevoUsuario->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'usuario' => [
                    'id'             => $nuevoUsuario->id,
                    'name'           => $nuevoUsuario->name,
                    'email'          => $nuevoUsuario->email,
                    'sub_rol'        => $nuevoUsuario->sub_rol,
                    'solo_asignados' => $nuevoUsuario->solo_asignados,
                ],
            ]);
        }

        return redirect()->back()->with('success', "Cuenta de acceso creada para {$nuevoUsuario->email}.");
    }

    // Da de baja un usuario de la empresa
    public function eliminarUsuarioEmpresa(Request $request, $id, $userId)
    {
        $instancia = Instancia::findOrFail($id);
        $usuario = User::where('instancia_id', $instancia->id)->findOrFail($userId);

        if ($usuario->isAdmin()) {
            abort(403, 'No se puede eliminar una cuenta de administrador desde este módulo.');
        }

        $usuario->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Cuenta de acceso de la empresa eliminada.');
    }

    // Prueba rápida del prompt contra la IA sin enviar mensajes por WhatsApp
    public function testPrompt(Request $request, $id)
    {
        $instancia = Instancia::findOrFail($id);

        $request->validate([
            'mensaje'              => 'required|string',
            'prompt'               => 'nullable|string',
            'prompt_personalizado' => 'nullable|string',
        ]);

        $systemPrompt = $request->input('prompt')
            ?: $request->input('prompt_personalizado')
            ?: $instancia->system_prompt;

        // Si la empresa tiene catálogo, se lo adjuntamos al prompt para que conozca precios
        $catalogo = is_array($instancia->catalogo_productos) ? $instancia->catalogo_productos : [];
        if (!empty($catalogo)) {
            $lineasCatalogo = array_map(
                fn($p) => "- " . ($p['nombre'] ?? 'Producto') . " (SKU: " . ($p['sku'] ?? 'N/A') . "): $" . number_format((float) ($p['precio'] ?? 0), 2) . (!empty($p['descripcion']) ? " — " . $p['descripcion'] : ""),
                $catalogo
            );
            $systemPrompt = trim((string) $systemPrompt) . "\n\nCATÁLOGO OFICIAL DE PRODUCTOS/SERVICIOS:\n" . implode("\n", $lineasCatalogo);
        }

        $respuesta = $this->aiService->obtenerRespuesta(
            (string) $request->input('mensaje'),
            $systemPrompt,
            [],
            $instancia->nombre_empresa
        );

        return response()->json([
            'success'   => true,
            'respuesta' => $respuesta,
        ]);
    }

    // Alta manual de prospecto desde el tablero
    public function crearContactoManual(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'nombre'          => 'required|string|max:120',
            'phone_number'    => 'required|string|max:30',
            'empresa_cliente' => 'nullable|string|max:160',
            'email'           => 'nullable|email|max:190',
            'etapa'           => 'nullable|string|in:' . implode(',', array_keys(UserContext::ETAPAS)),
            'valor_estimado'  => 'nullable|numeric|min:0|max:999999999',
            'etiquetas'       => 'nullable|string|max:250',
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', $validated['phone_number']);
        if (empty($cleanPhone)) {
            return response()->json(['success' => false, 'error' => 'Ingresa un número de teléfono válido.'], 422);
        }

        $tags = [];
        if (!empty($validated['etiquetas'])) {
            $tags = array_values(array_unique(array_filter(array_map('trim', explode(',', $validated['etiquetas'])))));
        }

        $etapaInicial = $validated['etapa'] ?? 'nuevo';

        $contacto = UserContext::updateOrCreate(
            [
                'instancia_id' => $instancia->id,
                'phone_number' => $cleanPhone,
            ],
            [
                'nombre'          => trim($validated['nombre']),
                'empresa_cliente' => !empty($validated['empresa_cliente']) ? trim($validated['empresa_cliente']) : null,
                'email'           => !empty($validated['email']) ? trim($validated['email']) : null,
                'etapa'           => $etapaInicial,
                'valor_estimado'  => (float) ($validated['valor_estimado'] ?? 0),
                'etiquetas'       => $tags,
                'current_node'    => 'main',
                'is_bot_active'   => $etapaInicial !== 'asesor',
            ]
        );

        $this->ejecutarAutomatizacionEtapa($instancia, $contacto, $etapaInicial);
        $contacto->registrarActividad('creado', 'Lead registrado en el CRM', Auth::user()?->name ?? 'Asesor');
        $this->dispararWebhookSaliente($instancia, 'lead.creado', [
            'contacto_id'    => $contacto->id,
            'phone_number'   => $contacto->phone_number,
            'nombre'         => $contacto->nombre,
            'etapa'          => $contacto->etapa,
            'valor_estimado' => $contacto->valor_estimado,
        ]);
        $contacto->refresh();

        $botPausado = !$contacto->is_bot_active || ($contacto->bot_paused_until && now()->lt($contacto->bot_paused_until));

        return response()->json([
            'success' => true,
            'lead'    => [
                'id'                  => $contacto->id,
                'nombre'              => $contacto->nombre,
                'empresa_cliente'     => $contacto->empresa_cliente ?: '',
                'email'               => $contacto->email ?: '',
                'phone_number'        => $contacto->phone_number,
                'current_node'        => $contacto->current_node ?: 'main',
                'etapa'               => $contacto->etapa ?: 'nuevo',
                'valor_estimado'      => (float) ($contacto->valor_estimado ?? 0),
                'etiquetas'           => is_array($contacto->etiquetas) ? array_values($contacto->etiquetas) : [],
                'notas'               => is_array($contacto->notas) ? array_values($contacto->notas) : [],
                'tareas'              => is_array($contacto->tareas) ? array_values($contacto->tareas) : [],
                'productos_cotizados' => is_array($contacto->productos_cotizados) ? array_values($contacto->productos_cotizados) : [],
                'resumen_ia'          => $contacto->resumen_ia,
                'is_bot_active'       => !$botPausado,
                'updated_human'       => 'Hace un momento',
            ],
        ]);
    }

    // Exporta los prospectos a CSV con formato compatible para Excel
    public function exportarContactosCsv(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $leads = $instancia->userContexts()->latest('updated_at')->get();
        $etapas = UserContext::ETAPAS;

        $filename = 'leads_' . Str::slug($instancia->nombre_empresa ?: 'instancia') . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($leads, $etapas) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para apertura directa en Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID',
                'Nombre',
                'Empresa',
                'Teléfono WhatsApp',
                'Correo Electrónico',
                'Etapa del Embudo',
                'Valor Estimado ($)',
                'Etiquetas',
                'Estado Bot',
                'Tareas Pendientes',
                'Última Actividad',
            ]);

            foreach ($leads as $l) {
                $etapaLabel = $etapas[$l->etapa]['label'] ?? $l->etapa ?? 'Nuevo';
                $tagsStr = is_array($l->etiquetas) ? implode(', ', $l->etiquetas) : '';
                $tareasPend = is_array($l->tareas)
                    ? count(array_filter($l->tareas, fn($t) => empty($t['completada'])))
                    : 0;

                fputcsv($out, [
                    $l->id,
                    $l->nombre ?: 'Cliente WhatsApp',
                    $l->empresa_cliente ?: '',
                    '+' . $l->phone_number,
                    $l->email ?: '',
                    $etapaLabel,
                    number_format((float) ($l->valor_estimado ?? 0), 2, '.', ''),
                    $tagsStr,
                    $l->is_bot_active ? 'Bot Activo' : 'Atención Humana',
                    $tareasPend,
                    $l->updated_at ? $l->updated_at->format('Y-m-d H:i:s') : '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // CRUD del catálogo de productos y servicios
    public function gestionarCatalogo(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $catalogo = is_array($instancia->catalogo_productos) ? array_values($instancia->catalogo_productos) : [];
        $accion = $request->input('accion', 'crear');

        if ($accion === 'eliminar' && $request->filled('producto_id')) {
            $prodId = (string) $request->input('producto_id');
            $catalogo = array_values(array_filter($catalogo, fn($p) => ($p['id'] ?? '') !== $prodId));
            $instancia->update(['catalogo_productos' => $catalogo]);

            return response()->json(['success' => true, 'catalogo' => $catalogo]);
        }

        $validated = $request->validate([
            'sku'         => 'nullable|string|max:40',
            'nombre'      => 'required|string|max:120',
            'categoria'   => 'nullable|string|max:80',
            'precio'      => 'required|numeric|min:0|max:99999999',
            'descripcion' => 'nullable|string|max:400',
        ]);

        $catalogo[] = [
            'id'          => 'prod_' . Str::random(8),
            'sku'         => trim((string) ($validated['sku'] ?? 'SKU-' . strtoupper(Str::random(4)))),
            'nombre'      => trim($validated['nombre']),
            'categoria'   => trim((string) ($validated['categoria'] ?? 'General')),
            'precio'      => (float) $validated['precio'],
            'descripcion' => trim((string) ($validated['descripcion'] ?? '')),
        ];

        $instancia->update(['catalogo_productos' => array_slice($catalogo, -200)]);

        return response()->json([
            'success'  => true,
            'catalogo' => $instancia->catalogo_productos,
        ]);
    }

    // Atajos de texto rápido para el asesor (/atajo)
    public function gestionarPlantillas(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $plantillas = is_array($instancia->plantillas_rapidas) ? array_values($instancia->plantillas_rapidas) : [];
        $accion = $request->input('accion', 'crear');

        if ($accion === 'eliminar' && $request->filled('plantilla_id')) {
            $tplId = (string) $request->input('plantilla_id');
            $plantillas = array_values(array_filter($plantillas, fn($p) => ($p['id'] ?? '') !== $tplId));
            $instancia->update(['plantillas_rapidas' => $plantillas]);

            return response()->json(['success' => true, 'plantillas' => $plantillas]);
        }

        $validated = $request->validate([
            'atajo'     => 'required|string|max:30',
            'titulo'    => 'required|string|max:80',
            'contenido' => 'required|string|max:1200',
        ]);

        $atajoClean = '/' . ltrim(strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['atajo'])), '/');

        $plantillas[] = [
            'id'        => 'tpl_' . Str::random(8),
            'atajo'     => $atajoClean,
            'titulo'    => trim($validated['titulo']),
            'contenido' => trim($validated['contenido']),
        ];

        $instancia->update(['plantillas_rapidas' => array_slice($plantillas, -100)]);

        return response()->json([
            'success'    => true,
            'plantillas' => $instancia->plantillas_rapidas,
        ]);
    }

    // Configuración de las columnas del embudo por empresa
    public function guardarEtapasEmbudo(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        if ($request->boolean('restaurar_defecto')) {
            $instancia->update(['etapas_embudo' => null]);
            return response()->json([
                'success' => true,
                'etapas'  => $instancia->obtenerEtapas(),
            ]);
        }

        $request->validate([
            'etapas' => 'required|array|min:2',
        ]);

        $raw = $request->input('etapas', []);
        $clean = [];

        foreach ($raw as $item) {
            if (!is_array($item)) continue;
            $label = trim((string) ($item['label'] ?? $item['nombre'] ?? ''));
            if ($label === '') continue;

            $key = !empty($item['key'])
                ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $item['key']))
                : Str::slug($label, '_');

            if ($key === '') continue;

            $clean[$key] = [
                'label'  => mb_substr($label, 0, 50),
                'nombre' => mb_substr($label, 0, 50),
                'icon'   => trim((string) ($item['icon'] ?? 'fas fa-columns')) ?: 'fas fa-columns',
                'color'  => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($item['color'] ?? '')) ? $item['color'] : '#3b82f6',
            ];
        }

        // Las etapas 'ganado' y 'perdido' son fijas para poder calcular conversiones
        if (!isset($clean['ganado'])) {
            $clean['ganado'] = UserContext::ETAPAS['ganado'];
        }
        if (!isset($clean['perdido'])) {
            $clean['perdido'] = UserContext::ETAPAS['perdido'];
        }

        $instancia->update(['etapas_embudo' => $clean]);

        return response()->json([
            'success' => true,
            'etapas'  => $instancia->obtenerEtapas(),
        ]);
    }

    // Campos extra personalizados definidos por la empresa
    public function gestionarCamposPersonalizados(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $campos = is_array($instancia->campos_personalizados_definicion)
            ? array_values($instancia->campos_personalizados_definicion)
            : [];
        $accion = $request->input('accion', 'crear');

        if ($accion === 'eliminar' && $request->filled('campo_id')) {
            $campoId = (string) $request->input('campo_id');
            $campos = array_values(array_filter($campos, fn($c) => ($c['id'] ?? '') !== $campoId));
            $instancia->update(['campos_personalizados_definicion' => $campos]);

            return response()->json(['success' => true, 'campos' => $campos]);
        }

        $validated = $request->validate([
            'nombre'   => 'required|string|max:80',
            'tipo'     => 'required|string|in:texto,numero,fecha,lista,casilla',
            'opciones' => 'nullable|string|max:400',
        ]);

        $clave = Str::slug($validated['nombre'], '_');
        if ($clave === '') {
            $clave = 'campo_' . strtolower(Str::random(5));
        }

        $listaOpciones = [];
        if ($validated['tipo'] === 'lista' && !empty($validated['opciones'])) {
            $listaOpciones = array_values(array_filter(array_map('trim', explode(',', $validated['opciones']))));
        }

        $campos[] = [
            'id'       => 'cf_' . Str::random(8),
            'clave'    => $clave,
            'nombre'   => trim($validated['nombre']),
            'tipo'     => $validated['tipo'],
            'opciones' => $listaOpciones,
        ];

        $instancia->update(['campos_personalizados_definicion' => array_slice($campos, -50)]);

        return response()->json([
            'success' => true,
            'campos'  => $instancia->campos_personalizados_definicion,
        ]);
    }

    // Reglas de automatización por etapa
    public function guardarAutomatizacionesEmbudo(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $request->validate([
            'automatizaciones' => 'required|array',
        ]);

        $raw = $request->input('automatizaciones', []);
        $clean = [];

        foreach (array_keys($instancia->obtenerEtapas()) as $stageKey) {
            if (isset($raw[$stageKey]) && is_array($raw[$stageKey])) {
                $clean[$stageKey] = [
                    'mensaje_whatsapp' => trim((string) ($raw[$stageKey]['mensaje_whatsapp'] ?? '')),
                    'auto_tag'         => trim((string) ($raw[$stageKey]['auto_tag'] ?? '')),
                    'crear_tarea'      => trim((string) ($raw[$stageKey]['crear_tarea'] ?? '')),
                    'accion_bot'       => in_array($raw[$stageKey]['accion_bot'] ?? '', ['ninguna', 'pausar', 'activar'], true)
                        ? $raw[$stageKey]['accion_bot']
                        : 'ninguna',
                ];
            }
        }

        $instancia->update(['pipeline_automations' => $clean]);

        return response()->json([
            'success'          => true,
            'automatizaciones' => $instancia->pipeline_automations,
        ]);
    }

    // Envío de mensaje masivo respetando la lista de bajas (opt-out)
    public function enviarDifusionMasiva(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'nombre_campana' => 'required|string|max:120',
            'filtro_etapa'   => 'nullable|string',
            'filtro_tag'     => 'nullable|string|max:50',
            'mensaje'        => 'required|string|max:1500',
        ]);

        $leads = $instancia->userContexts()->get();
        $excluidosOptOut = 0;

        $destinatarios = $leads->filter(function (UserContext $c) use ($validated, &$excluidosOptOut) {
            if (!empty($validated['filtro_etapa']) && $validated['filtro_etapa'] !== 'all') {
                if (($c->etapa ?: 'nuevo') !== $validated['filtro_etapa']) {
                    return false;
                }
            }
            if (!empty($validated['filtro_tag'])) {
                $buscado = mb_strtolower(trim($validated['filtro_tag']));
                $tags = is_array($c->etiquetas) ? array_map('mb_strtolower', $c->etiquetas) : [];
                if (!in_array($buscado, $tags, true)) {
                    return false;
                }
            }
            if (empty($c->phone_number)) {
                return false;
            }
            // Salteamos a los contactos que pidieron no recibir promociones
            if ($c->opt_in === false) {
                $excluidosOptOut++;
                return false;
            }
            return true;
        });

        $enviados = 0;
        $fallidos = 0;

        foreach ($destinatarios as $contacto) {
            $textoPersonalizado = str_replace(
                ['{{nombre}}', '{{empresa}}', '{{telefono}}'],
                [$contacto->nombre ?: 'Cliente', $instancia->nombre_empresa, $contacto->phone_number],
                trim($validated['mensaje'])
            );

            try {
                $res = $this->whatsAppService->enviarCloud(
                    $contacto->phone_number,
                    ['type' => 'text', 'body' => $textoPersonalizado],
                    $instancia->phone_number_id
                );

                if (isset($res['error'])) {
                    $fallidos++;
                } else {
                    $enviados++;
                }
            } catch (\Throwable $e) {
                $fallidos++;
                \Illuminate\Support\Facades\Log::warning('Error en difusión a ' . $contacto->phone_number . ': ' . $e->getMessage());
            }
        }

        $campanas = is_array($instancia->campanas_difusion) ? array_values($instancia->campanas_difusion) : [];
        array_unshift($campanas, [
            'id'                => 'camp_' . Str::random(8),
            'nombre'            => trim($validated['nombre_campana']),
            'filtro_etapa'      => $validated['filtro_etapa'] ?: 'all',
            'filtro_tag'        => $validated['filtro_tag'] ?: '',
            'mensaje'           => trim($validated['mensaje']),
            'enviados'          => $enviados,
            'fallidos'          => $fallidos,
            'excluidos_opt_out' => $excluidosOptOut,
            'fecha'             => now()->format('d/m/Y H:i'),
        ]);

        $instancia->update(['campanas_difusion' => array_slice($campanas, 0, 50)]);

        return response()->json([
            'success'           => true,
            'enviados'          => $enviados,
            'fallidos'          => $fallidos,
            'excluidos_opt_out' => $excluidosOptOut,
            'campanas'          => $instancia->campanas_difusion,
        ]);
    }

    // Genera y descarga el PDF formal de cotización
    public function generarCotizacionPdf(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        $opciones = $request->only(['folio', 'fecha', 'validez_dias', 'porcentaje_impuesto', 'notas']);
        $datos = $this->cotizacionService->prepararDatos($instancia, $contacto, $opciones);
        $pdf = $this->cotizacionService->renderPdf($datos);

        $safeFolio = Str::slug($datos['folio']);
        return $pdf->download("Cotizacion_{$safeFolio}.pdf");
    }

    // Genera PDF formal y lo envía directamente al cliente por WhatsApp Cloud API
    public function enviarCotizacionWhatsApp(Request $request, $id, $contactoId)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $contacto = UserContext::where('instancia_id', $instancia->id)->findOrFail($contactoId);

        if (empty($contacto->phone_number)) {
            return response()->json(['error' => 'El contacto no tiene un teléfono válido registrado.'], 422);
        }

        $opciones = $request->only(['folio', 'fecha', 'validez_dias', 'porcentaje_impuesto', 'notas']);
        $registro = $this->cotizacionService->guardarYRegistrar($instancia, $contacto, $opciones);

        $caption = "*Cotización formal #{$registro['folio']}*\n"
            . "Estimado/a {$contacto->nombre}, le enviamos la cotización de los productos o servicios solicitados por un total de *"
            . number_format($registro['total'], 2) . " {$registro['moneda']}*.\n"
            . "Quedamos a su total disposición para cualquier consulta.";

        $resApi = $this->whatsAppService->enviarCloud($contacto->phone_number, [
            'type'     => 'document',
            'link'     => $registro['url'],
            'filename' => "Cotizacion_{$registro['folio']}.pdf",
            'caption'  => $caption,
        ], $instancia->phone_number_id);

        Mensaje::create([
            'instancia_id' => $instancia->id,
            'direction'    => 'outbound',
            'sender'       => $instancia->phone_number_id,
            'recipient'    => $contacto->phone_number,
            'type'         => 'document',
            'body'         => "[PDF Cotización #{$registro['folio']}] {$caption}",
            'status'       => isset($resApi['messages'][0]['id']) ? 'sent' : 'failed',
            'meta_id'      => $resApi['messages'][0]['id'] ?? null,
        ]);

        $contacto->registrarActividad(
            'cotizacion_enviada',
            "Cotización #{$registro['folio']} enviada por WhatsApp (Total: $" . number_format($registro['total'], 2) . " {$registro['moneda']})",
            Auth::user()?->name ?? 'Asesor CRM',
            ['folio' => $registro['folio'], 'url' => $registro['url'], 'total' => $registro['total']]
        );

        $this->dispararWebhookSaliente($instancia, 'cotizacion.enviada', [
            'contacto_id'  => $contacto->id,
            'phone_number' => $contacto->phone_number,
            'folio'        => $registro['folio'],
            'total'        => $registro['total'],
            'url'          => $registro['url'],
        ]);

        return response()->json([
            'success'  => true,
            'mensaje'  => "Cotización #{$registro['folio']} generada y enviada exitosamente por WhatsApp.",
            'registro' => $registro,
            'contacto' => $contacto->fresh(),
        ]);
    }

    // Importación masiva de contactos CSV con autodetección y mapeo
    public function importarContactosCsv(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $request->validate([
            'archivo_csv' => 'required|file|mimes:csv,txt|max:10240',
            'modo'        => 'nullable|string|in:preview,import',
            'mapeo'       => 'nullable|string',
        ]);

        $file = $request->file('archivo_csv');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (!$handle) {
            return response()->json(['error' => 'No se pudo abrir el archivo CSV.'], 422);
        }

        $delimiter = ',';
        $firstLine = fgets($handle);
        rewind($handle);
        if (str_contains($firstLine, ';') && substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        }

        $header = fgetcsv($handle, 0, $delimiter);
        if (!$header || count($header) < 1) {
            fclose($handle);
            return response()->json(['error' => 'El archivo CSV está vacío o no contiene encabezados válidos.'], 422);
        }

        $headerClean = array_map(fn($col) => trim((string) preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $col)), $header);

        if ($request->input('modo') === 'preview') {
            $previewRows = [];
            $rowCount = 0;
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $rowCount < 5) {
                $previewRows[] = array_map(fn($v) => mb_substr(trim((string) $v), 0, 100), $row);
                $rowCount++;
            }
            fclose($handle);

            return response()->json([
                'success'  => true,
                'columnas' => $headerClean,
                'preview'  => $previewRows,
            ]);
        }

        $mapeoRaw = $request->input('mapeo');
        $mapeo = is_string($mapeoRaw) ? json_decode($mapeoRaw, true) : ($mapeoRaw ?: []);
        if (!is_array($mapeo)) {
            $mapeo = [];
        }

        $etapasValidas = array_keys($instancia->obtenerEtapas());
        $etapaDefault = array_key_first($instancia->obtenerEtapas()) ?: 'captura';

        $colTelefono = $mapeo['telefono'] ?? null;
        $colNombre = $mapeo['nombre'] ?? null;
        $colEmpresa = $mapeo['empresa_cliente'] ?? null;
        $colEmail = $mapeo['email'] ?? null;
        $colEtiqueta = $mapeo['etiqueta'] ?? null;
        $colValor = $mapeo['valor_estimado'] ?? null;
        $colEtapa = $mapeo['etapa'] ?? null;

        $idxTelefono = $colTelefono !== null ? array_search($colTelefono, $headerClean) : false;
        $idxNombre = $colNombre !== null ? array_search($colNombre, $headerClean) : false;
        $idxEmpresa = $colEmpresa !== null ? array_search($colEmpresa, $headerClean) : false;
        $idxEmail = $colEmail !== null ? array_search($colEmail, $headerClean) : false;
        $idxEtiqueta = $colEtiqueta !== null ? array_search($colEtiqueta, $headerClean) : false;
        $idxValor = $colValor !== null ? array_search($colValor, $headerClean) : false;
        $idxEtapa = $colEtapa !== null ? array_search($colEtapa, $headerClean) : false;

        if ($idxTelefono === false) {
            foreach ($headerClean as $idx => $h) {
                $lh = mb_strtolower($h);
                if (str_contains($lh, 'tel') || str_contains($lh, 'phone') || str_contains($lh, 'cel') || str_contains($lh, 'movil') || str_contains($lh, 'whatsapp')) {
                    $idxTelefono = $idx;
                    break;
                }
            }
        }

        if ($idxTelefono === false) {
            fclose($handle);
            return response()->json(['error' => 'Es obligatorio mapear la columna de Teléfono / WhatsApp para importar los contactos.'], 422);
        }

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rawPhone = isset($row[$idxTelefono]) ? trim((string) $row[$idxTelefono]) : '';
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);

            if (strlen($cleanPhone) < 7 || strlen($cleanPhone) > 17) {
                $omitidos++;
                continue;
            }

            $nombre = ($idxNombre !== false && isset($row[$idxNombre])) ? trim((string) $row[$idxNombre]) : '';
            $empresa = ($idxEmpresa !== false && isset($row[$idxEmpresa])) ? trim((string) $row[$idxEmpresa]) : null;
            $email = ($idxEmail !== false && isset($row[$idxEmail])) ? trim((string) $row[$idxEmail]) : null;
            $etiqueta = ($idxEtiqueta !== false && isset($row[$idxEtiqueta])) ? trim((string) $row[$idxEtiqueta]) : null;
            $valor = ($idxValor !== false && isset($row[$idxValor])) ? (float) preg_replace('/[^0-9.]/', '', $row[$idxValor]) : 0.0;
            $etapa = ($idxEtapa !== false && isset($row[$idxEtapa])) ? trim((string) $row[$idxEtapa]) : $etapaDefault;

            if (!in_array($etapa, $etapasValidas)) {
                $etapa = $etapaDefault;
            }

            $contacto = UserContext::where('instancia_id', $instancia->id)
                ->where('phone_number', $cleanPhone)
                ->first();

            if ($contacto) {
                $updates = [];
                if (empty($contacto->nombre) && !empty($nombre)) $updates['nombre'] = $nombre;
                if (empty($contacto->empresa_cliente) && !empty($empresa)) $updates['empresa_cliente'] = $empresa;
                if (empty($contacto->email) && !empty($email)) $updates['email'] = $email;
                if ($etiqueta) {
                    $currentTags = is_array($contacto->etiquetas) ? $contacto->etiquetas : [];
                    if (!in_array($etiqueta, $currentTags)) {
                        $currentTags[] = $etiqueta;
                        $updates['etiquetas'] = array_values(array_unique($currentTags));
                    }
                }
                if ($updates) {
                    $contacto->update($updates);
                    $contacto->registrarActividad('importado_csv', 'Datos enriquecidos desde importación masiva CSV', Auth::user()?->name ?? 'Importador');
                    $actualizados++;
                } else {
                    $omitidos++;
                }
            } else {
                $tags = $etiqueta ? [$etiqueta] : ['importado_csv'];
                $nuevo = UserContext::create([
                    'instancia_id'        => $instancia->id,
                    'phone_number'        => $cleanPhone,
                    'nombre'              => $nombre ?: ('Lead ' . substr($cleanPhone, -4)),
                    'empresa_cliente'     => $empresa,
                    'email'               => $email,
                    'etapa'               => $etapa,
                    'valor_estimado'      => $valor,
                    'etiquetas'           => $tags,
                    'estado_conversacion' => 'abierta',
                    'opt_in'              => true,
                    'current_node'        => 'main',
                ]);
                $nuevo->registrarActividad('creado', 'Contacto creado desde archivo CSV', Auth::user()?->name ?? 'Importador');
                $creados++;
            }
        }
        fclose($handle);

        return response()->json([
            'success'      => true,
            'creados'      => $creados,
            'actualizados' => $actualizados,
            'omitidos'     => $omitidos,
            'total'        => ($creados + $actualizados + $omitidos),
        ]);
    }

    // Configura y guarda los horarios comerciales de atención
    public function guardarHorarioAtencion(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'activo'                => 'required|boolean',
            'timezone'              => 'required|string|max:60',
            'mensaje_fuera_horario' => 'required|string|max:1000',
            'dias'                  => 'required|array',
        ]);

        $instancia->update(['horario_atencion' => $validated]);

        return response()->json([
            'success'          => true,
            'horario_atencion' => $instancia->horario_atencion,
        ]);
    }

    // Gestiona artículos y FAQs de la Base de Conocimiento RAG para IA
    public function gestionarBaseConocimiento(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'accion'         => 'required|string|in:agregar,actualizar,eliminar',
            'id'             => 'nullable|string|max:60',
            'titulo'         => 'required_unless:accion,eliminar|nullable|string|max:160',
            'categoria'      => 'nullable|string|max:60',
            'contenido'      => 'required_unless:accion,eliminar|nullable|string|max:5000',
            'palabras_clave' => 'nullable|array',
        ]);

        $base = is_array($instancia->base_conocimiento) ? $instancia->base_conocimiento : [];
        $accion = $validated['accion'];

        if ($accion === 'agregar') {
            $itemId = 'rag_' . Str::random(8);
            $base[] = [
                'id'             => $itemId,
                'titulo'         => trim($validated['titulo']),
                'categoria'      => trim((string) ($validated['categoria'] ?? 'General')),
                'contenido'      => trim($validated['contenido']),
                'palabras_clave' => $validated['palabras_clave'] ?? [],
                'created_at'     => now()->toIso8601String(),
            ];
        } elseif ($accion === 'actualizar') {
            $itemId = $validated['id'];
            foreach ($base as &$art) {
                if (($art['id'] ?? '') === $itemId) {
                    $art['titulo'] = trim($validated['titulo']);
                    $art['categoria'] = trim((string) ($validated['categoria'] ?? 'General'));
                    $art['contenido'] = trim($validated['contenido']);
                    $art['palabras_clave'] = $validated['palabras_clave'] ?? [];
                    $art['updated_at'] = now()->toIso8601String();
                    break;
                }
            }
        } elseif ($accion === 'eliminar') {
            $itemId = $validated['id'];
            $base = array_values(array_filter($base, fn($art) => ($art['id'] ?? '') !== $itemId));
        }

        $instancia->update(['base_conocimiento' => $base]);

        return response()->json([
            'success'           => true,
            'base_conocimiento' => $base,
        ]);
    }

    // Guarda los datos fiscales para cotizaciones
    public function guardarDatosFiscales(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'razon_social'         => 'required|string|max:180',
            'nit_rfc'              => 'nullable|string|max:40',
            'direccion'            => 'nullable|string|max:250',
            'email_contacto'       => 'nullable|email|max:120',
            'moneda'               => 'required|string|size:3',
            'porcentaje_impuesto'  => 'required|numeric|min:0|max:100',
            'validez_dias_default' => 'required|integer|min:1|max:365',
            'terminos_condiciones' => 'nullable|string|max:2000',
        ]);

        $instancia->update(['datos_fiscales' => $validated]);

        return response()->json([
            'success'        => true,
            'datos_fiscales' => $instancia->datos_fiscales,
        ]);
    }

    // Guarda la configuración del webhook saliente para eventos externos
    public function guardarWebhookSaliente(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);

        $validated = $request->validate([
            'activo'     => 'required|boolean',
            'url'        => 'nullable|url|max:255',
            'secret_key' => 'nullable|string|max:120',
            'eventos'    => 'nullable|array',
        ]);

        $instancia->update(['webhook_saliente' => $validated]);

        return response()->json([
            'success'          => true,
            'webhook_saliente' => $instancia->webhook_saliente,
        ]);
    }

    // Genera un token nuevo para la API REST de la empresa
    public function generarApiToken(Request $request, $id)
    {
        $instancia = $this->autorizarInstancia((int) $id);
        $token = $instancia->generarApiToken();

        return response()->json([
            'success'   => true,
            'api_token' => $token,
        ]);
    }

    // Helper interno para despachar webhooks salientes asíncronos/seguros
    public function dispararWebhookSaliente(Instancia $instancia, string $evento, array $payload): void
    {
        $conf = is_array($instancia->webhook_saliente) ? $instancia->webhook_saliente : [];
        if (empty($conf['activo']) || empty($conf['url'])) {
            return;
        }

        $eventosPermitidos = is_array($conf['eventos'] ?? null) ? $conf['eventos'] : [];
        if (!empty($eventosPermitidos) && !in_array($evento, $eventosPermitidos)) {
            return;
        }

        try {
            $body = [
                'event'        => $evento,
                'timestamp'    => now()->toIso8601String(),
                'instancia_id' => $instancia->id,
                'empresa'      => $instancia->nombre_empresa,
                'data'         => $payload,
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'CRM-Webhook-Client/1.0',
            ];

            if (!empty($conf['secret_key'])) {
                $headers['X-Signature-SHA256'] = hash_hmac('sha256', json_encode($body), (string) $conf['secret_key']);
            }

            Http::timeout(3)->withHeaders($headers)->post($conf['url'], $body);
        } catch (\Throwable $e) {
            Log::warning("Error disparando webhook saliente para instancia {$instancia->id}: " . $e->getMessage());
        }
    }
}
