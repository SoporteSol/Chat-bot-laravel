<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Etapas personalizadas, campos custom y tiempo de pausa del bot
        Schema::table('instancias', function (Blueprint $table) {
            if (!Schema::hasColumn('instancias', 'etapas_embudo')) {
                $table->json('etapas_embudo')->nullable()->after('system_prompt');
            }
            if (!Schema::hasColumn('instancias', 'campos_personalizados_definicion')) {
                $table->json('campos_personalizados_definicion')->nullable()->after('etapas_embudo');
            }
            if (!Schema::hasColumn('instancias', 'bot_pause_hours')) {
                $table->integer('bot_pause_hours')->default(12)->after('campanas_difusion');
            }
        });

        // Campos del lead: motivo de pérdida, estado de conversación, sin leer y opt-in
        Schema::table('user_contexts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_contexts', 'motivo_perdida')) {
                $table->string('motivo_perdida', 255)->nullable()->after('etapa');
            }
            if (!Schema::hasColumn('user_contexts', 'estado_conversacion')) {
                $table->string('estado_conversacion', 30)->default('abierta')->after('motivo_perdida');
            }
            if (!Schema::hasColumn('user_contexts', 'mensajes_sin_leer')) {
                $table->unsignedInteger('mensajes_sin_leer')->default(0)->after('estado_conversacion');
            }
            if (!Schema::hasColumn('user_contexts', 'opt_in')) {
                $table->boolean('opt_in')->default(true)->after('mensajes_sin_leer');
            }
            if (!Schema::hasColumn('user_contexts', 'campos_personalizados')) {
                $table->json('campos_personalizados')->nullable()->after('productos_cotizados');
            }
            if (!Schema::hasColumn('user_contexts', 'ultimo_mensaje_cliente_at')) {
                $table->timestamp('ultimo_mensaje_cliente_at')->nullable()->after('bot_paused_until');
            }
        });

        // Estados de entrega para los checks de WhatsApp (enviado, recibido, leído)
        Schema::table('mensajes', function (Blueprint $table) {
            if (!Schema::hasColumn('mensajes', 'status')) {
                $table->string('status', 30)->default('sent')->after('direction');
            }
            if (!Schema::hasColumn('mensajes', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
        });

        // Roles internos: supervisores y vendedores (con filtro de solo sus leads)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sub_rol')) {
                $table->string('sub_rol', 30)->default('supervisor')->after('instancia_id');
            }
            if (!Schema::hasColumn('users', 'solo_asignados')) {
                $table->boolean('solo_asignados')->default(false)->after('sub_rol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            $cols = array_filter(
                ['etapas_embudo', 'campos_personalizados_definicion', 'bot_pause_hours'],
                fn ($c) => Schema::hasColumn('instancias', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            $cols = array_filter(
                [
                    'motivo_perdida',
                    'estado_conversacion',
                    'mensajes_sin_leer',
                    'opt_in',
                    'campos_personalizados',
                    'ultimo_mensaje_cliente_at',
                ],
                fn ($c) => Schema::hasColumn('user_contexts', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('mensajes', function (Blueprint $table) {
            $cols = array_filter(
                ['status', 'error_message'],
                fn ($c) => Schema::hasColumn('mensajes', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['sub_rol', 'solo_asignados'],
                fn ($c) => Schema::hasColumn('users', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
