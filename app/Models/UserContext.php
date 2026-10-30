<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserContext extends Model
{
    public const ETAPAS = [
        'nuevo'      => ['label' => 'Nuevos Leads', 'nombre' => 'Nuevos Leads', 'icon' => 'fas fa-inbox', 'color' => '#3b82f6'],
        'calificado' => ['label' => 'En Atención (Bot)', 'nombre' => 'En Atención (Bot)', 'icon' => 'fas fa-robot', 'color' => '#6366f1'],
        'cotizacion' => ['label' => 'En Cotización', 'nombre' => 'En Cotización', 'icon' => 'fas fa-file-invoice-dollar', 'color' => '#f59e0b'],
        'asesor'     => ['label' => 'Requiere Asesor', 'nombre' => 'Requiere Asesor', 'icon' => 'fas fa-user-headset', 'color' => '#ef4444'],
        'ganado'     => ['label' => 'Venta Ganada', 'nombre' => 'Venta Ganada', 'icon' => 'fas fa-check-circle', 'color' => '#10b981'],
        'perdido'    => ['label' => 'Perdido / Cerrado', 'nombre' => 'Perdido / Cerrado', 'icon' => 'fas fa-times-circle', 'color' => '#64748b'],
    ];

    protected $fillable = [
        'instancia_id',
        'phone_number',
        'nombre',
        'empresa_cliente',
        'email',
        'current_node',
        'etapa',
        'motivo_perdida',
        'estado_conversacion',
        'mensajes_sin_leer',
        'opt_in',
        'valor_estimado',
        'etiquetas',
        'notas',
        'tareas',
        'productos_cotizados',
        'campos_personalizados',
        'resumen_ia',
        'asignado_a',
        'ai_history',
        'is_bot_active',
        'bot_paused_until',
        'ultimo_mensaje_cliente_at',
        'timeline_actividades',
        'origen_utm',
        'cotizaciones_historial',
    ];

    protected $casts = [
        'ai_history'                => 'array',
        'etiquetas'                 => 'array',
        'notas'                     => 'array',
        'tareas'                    => 'array',
        'productos_cotizados'       => 'array',
        'campos_personalizados'     => 'array',
        'timeline_actividades'      => 'array',
        'origen_utm'                => 'array',
        'cotizaciones_historial'    => 'array',
        'valor_estimado'            => 'decimal:2',
        'mensajes_sin_leer'         => 'integer',
        'opt_in'                    => 'boolean',
        'is_bot_active'             => 'boolean',
        'bot_paused_until'          => 'datetime',
        'ultimo_mensaje_cliente_at' => 'datetime',
    ];

    // Agrega un evento al historial de actividad del contacto
    public function registrarActividad(string $tipo, string $descripcion, ?string $autor = null, array $meta = []): void
    {
        $timeline = is_array($this->timeline_actividades) ? $this->timeline_actividades : [];
        $timeline[] = [
            'id'          => uniqid('act_', true),
            'tipo'        => $tipo, // 'etapa_cambiada', 'cotizacion', 'tarea', 'nota', 'mensaje', 'creado', 'opt_in'
            'descripcion' => $descripcion,
            'autor'       => $autor ?: 'Sistema',
            'meta'        => $meta,
            'fecha'       => now()->format('Y-m-d H:i:s'),
            'fecha_human' => now()->diffForHumans(),
        ];
        $this->timeline_actividades = $timeline;
        $this->save();
    }

    public function instancia()
    {
        return $this->belongsTo(Instancia::class);
    }

    public function asesorAsignado()
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }
}
