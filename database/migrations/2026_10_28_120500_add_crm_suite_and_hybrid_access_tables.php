<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rol 'empresa' para asesores que solo ven su propio embudo
        if (Schema::hasTable('roles')) {
            DB::table('roles')->updateOrInsert(
                ['name' => 'empresa'],
                [
                    'display_name' => 'Empresa / Asesor CRM',
                    'description'  => 'Acceso exclusivo al Embudo de Ventas e Inbox Conversacional de su instancia asignada.',
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }

        // Relación del usuario con su instancia asignada
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'instancia_id')) {
                $table->foreignId('instancia_id')
                    ->nullable()
                    ->after('role_id')
                    ->constrained('instancias')
                    ->nullOnDelete();
            }
        });

        // Campos para el CRM: etapas del embudo, notas, tareas y resumen del copiloto
        Schema::table('user_contexts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_contexts', 'etapa')) {
                $table->string('etapa', 40)->default('nuevo')->after('current_node');
            }
            if (!Schema::hasColumn('user_contexts', 'valor_estimado')) {
                $table->decimal('valor_estimado', 12, 2)->default(0)->after('etapa');
            }
            if (!Schema::hasColumn('user_contexts', 'etiquetas')) {
                $table->json('etiquetas')->nullable()->after('valor_estimado');
            }
            if (!Schema::hasColumn('user_contexts', 'notas')) {
                $table->json('notas')->nullable()->after('etiquetas');
            }
            if (!Schema::hasColumn('user_contexts', 'tareas')) {
                $table->json('tareas')->nullable()->after('notas');
            }
            if (!Schema::hasColumn('user_contexts', 'resumen_ia')) {
                $table->text('resumen_ia')->nullable()->after('tareas');
            }
            if (!Schema::hasColumn('user_contexts', 'asignado_a')) {
                $table->foreignId('asignado_a')
                    ->nullable()
                    ->after('resumen_ia')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Para que un nodo del flujo pueda mover al cliente de etapa o etiquetarlo solo
        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('chatbot_nodes', 'target_etapa')) {
                $table->string('target_etapa', 40)->nullable()->after('mensaje');
            }
            if (!Schema::hasColumn('chatbot_nodes', 'auto_tags')) {
                $table->string('auto_tags', 255)->nullable()->after('target_etapa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('chatbot_nodes', 'auto_tags')) {
                $table->dropColumn('auto_tags');
            }
            if (Schema::hasColumn('chatbot_nodes', 'target_etapa')) {
                $table->dropColumn('target_etapa');
            }
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            if (Schema::hasColumn('user_contexts', 'asignado_a')) {
                $table->dropForeign(['asignado_a']);
                $table->dropColumn('asignado_a');
            }
            foreach (['resumen_ia', 'tareas', 'notas', 'etiquetas', 'valor_estimado', 'etapa'] as $col) {
                if (Schema::hasColumn('user_contexts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'instancia_id')) {
                $table->dropForeign(['instancia_id']);
                $table->dropColumn('instancia_id');
            }
        });
    }
};
