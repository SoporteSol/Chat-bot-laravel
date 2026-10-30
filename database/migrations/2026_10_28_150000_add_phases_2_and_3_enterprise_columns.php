<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Phase 2 (Sales Productivity & Automation) and Phase 3 (RAG AI & SaaS Scale).
     */
    public function up(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            if (!Schema::hasColumn('instancias', 'horario_atencion')) {
                $table->json('horario_atencion')->nullable();
            }
            if (!Schema::hasColumn('instancias', 'reglas_inactividad')) {
                $table->json('reglas_inactividad')->nullable();
            }
            if (!Schema::hasColumn('instancias', 'base_conocimiento')) {
                $table->json('base_conocimiento')->nullable();
            }
            if (!Schema::hasColumn('instancias', 'webhook_saliente')) {
                $table->json('webhook_saliente')->nullable();
            }
            if (!Schema::hasColumn('instancias', 'api_token')) {
                $table->string('api_token', 80)->nullable()->unique();
            }
            if (!Schema::hasColumn('instancias', 'plan')) {
                $table->string('plan', 50)->default('pro');
            }
            if (!Schema::hasColumn('instancias', 'limite_mensajes_ia_mes')) {
                $table->integer('limite_mensajes_ia_mes')->default(2000);
            }
            if (!Schema::hasColumn('instancias', 'mensajes_ia_consumidos')) {
                $table->integer('mensajes_ia_consumidos')->default(0);
            }
            if (!Schema::hasColumn('instancias', 'datos_fiscales')) {
                $table->json('datos_fiscales')->nullable();
            }
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_contexts', 'timeline_actividades')) {
                $table->json('timeline_actividades')->nullable();
            }
            if (!Schema::hasColumn('user_contexts', 'origen_utm')) {
                $table->json('origen_utm')->nullable();
            }
            if (!Schema::hasColumn('user_contexts', 'cotizaciones_historial')) {
                $table->json('cotizaciones_historial')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            $table->dropColumn([
                'horario_atencion',
                'reglas_inactividad',
                'base_conocimiento',
                'webhook_saliente',
                'api_token',
                'plan',
                'limite_mensajes_ia_mes',
                'mensajes_ia_consumidos',
                'datos_fiscales',
            ]);
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            $table->dropColumn([
                'timeline_actividades',
                'origen_utm',
                'cotizaciones_historial',
            ]);
        });
    }
};

