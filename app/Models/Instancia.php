<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instancia extends Model
{
    use HasFactory;

    protected $table = 'instancias';

    protected $fillable = [
        'nombre_empresa',
        'telefono',
        'phone_number_id',
        'waba_id',
        'access_token',
        'system_prompt',
        'etapas_embudo',
        'campos_personalizados_definicion',
        'catalogo_productos',
        'plantillas_rapidas',
        'pipeline_automations',
        'campanas_difusion',
        'bot_pause_hours',
        'horario_atencion',
        'reglas_inactividad',
        'base_conocimiento',
        'webhook_saliente',
        'api_token',
        'plan',
        'limite_mensajes_ia_mes',
        'mensajes_ia_consumidos',
        'datos_fiscales',
        'is_active',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'is_active'                        => 'boolean',
        'etapas_embudo'                    => 'array',
        'campos_personalizados_definicion' => 'array',
        'catalogo_productos'               => 'array',
        'plantillas_rapidas'               => 'array',
        'pipeline_automations'             => 'array',
        'campanas_difusion'                => 'array',
        'bot_pause_hours'                  => 'integer',
        'horario_atencion'                 => 'array',
        'reglas_inactividad'               => 'array',
        'base_conocimiento'                => 'array',
        'webhook_saliente'                 => 'array',
        'datos_fiscales'                   => 'array',
        'limite_mensajes_ia_mes'           => 'integer',
        'mensajes_ia_consumidos'           => 'integer',
    ];

    protected function accessToken(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (empty($value)) return null;
                try {
                    return \Illuminate\Support\Facades\Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    return $value;
                }
            },
            set: function ($value) {
                if (empty($value)) return null;
                try {
                    \Illuminate\Support\Facades\Crypt::decryptString($value);
                    return $value;
                } catch (\Throwable $e) {
                    return \Illuminate\Support\Facades\Crypt::encryptString($value);
                }
            }
        );
    }

    // Devuelve las etapas del embudo (personalizadas o base), asegurando 'ganado' y 'perdido'
    public function obtenerEtapas(): array
    {
        $custom = $this->etapas_embudo;
        if (!is_array($custom) || empty($custom)) {
            return UserContext::ETAPAS;
        }

        $normalizadas = [];
        foreach ($custom as $key => $meta) {
            if (!is_array($meta)) continue;
            $label = trim((string) ($meta['label'] ?? $meta['nombre'] ?? $key));
            $normalizadas[$key] = [
                'label'  => $label,
                'nombre' => $label,
                'icon'   => $meta['icon'] ?? 'fas fa-columns',
                'color'  => $meta['color'] ?? '#3b82f6',
            ];
        }

        if (!isset($normalizadas['ganado'])) {
            $normalizadas['ganado'] = UserContext::ETAPAS['ganado'];
        }
        if (!isset($normalizadas['perdido'])) {
            $normalizadas['perdido'] = UserContext::ETAPAS['perdido'];
        }

        return $normalizadas ?: UserContext::ETAPAS;
    }

    // Revisa si estamos dentro del horario de atención de la empresa
    public function estaEnHorarioAtencion(?\DateTimeInterface $dateTime = null): bool
    {
        $cfg = $this->horario_atencion;
        if (!is_array($cfg) || empty($cfg['activo'])) {
            return true; // Si no hay horario activo, atiende 24/7
        }

        $tz = $cfg['timezone'] ?? 'America/Bogota';
        $now = $dateTime ? \Carbon\Carbon::instance($dateTime)->setTimezone($tz) : \Carbon\Carbon::now($tz);
        $diasMapa = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
        ];

        $diaNombre = $diasMapa[$now->dayOfWeek] ?? 'lunes';
        $diasConfig = $cfg['dias'] ?? [];
        $diaConfig = $diasConfig[$diaNombre] ?? null;

        if (!$diaConfig || empty($diaConfig['activo'])) {
            return false; // Día no laborable
        }

        $inicio = $diaConfig['inicio'] ?? '08:00';
        $fin = $diaConfig['fin'] ?? '18:00';
        $horaActual = $now->format('H:i');

        return ($horaActual >= $inicio && $horaActual <= $fin);
    }

    // Mensaje de respuesta automática fuera de horario
    public function obtenerMensajeFueraHorario(): ?string
    {
        $cfg = $this->horario_atencion;
        if (!is_array($cfg) || empty($cfg['activo'])) {
            return null;
        }

        return !empty($cfg['mensaje_fuera_horario'])
            ? trim((string) $cfg['mensaje_fuera_horario'])
            : 'En este momento nuestro equipo está fuera de horario comercial. Hemos recibido tu mensaje y te responderemos a primera hora laboral.';
    }

    // Verifica si la instancia no ha superado el cupo mensual de mensajes de IA
    public function puedeConsumirIa(): bool
    {
        $limite = $this->limite_mensajes_ia_mes ?? 2000;
        $consumidos = $this->mensajes_ia_consumidos ?? 0;
        return $consumidos < $limite;
    }

    public function incrementarConsumoIa(int $cantidad = 1): void
    {
        $this->increment('mensajes_ia_consumidos', $cantidad);
    }

    public function generarApiToken(): string
    {
        $token = 'crm_' . bin2hex(random_bytes(24));
        $this->update(['api_token' => $token]);
        return $token;
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class);
    }

    public function userContexts()
    {
        return $this->hasMany(UserContext::class);
    }

    public function chatbotNodes()
    {
        return $this->hasMany(ChatbotNode::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
