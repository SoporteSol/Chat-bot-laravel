<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            if (!Schema::hasColumn('instancias', 'catalogo_productos')) {
                $table->json('catalogo_productos')->nullable()->after('system_prompt');
            }
            if (!Schema::hasColumn('instancias', 'plantillas_rapidas')) {
                $table->json('plantillas_rapidas')->nullable()->after('catalogo_productos');
            }
            if (!Schema::hasColumn('instancias', 'pipeline_automations')) {
                $table->json('pipeline_automations')->nullable()->after('plantillas_rapidas');
            }
            if (!Schema::hasColumn('instancias', 'campanas_difusion')) {
                $table->json('campanas_difusion')->nullable()->after('pipeline_automations');
            }
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_contexts', 'empresa_cliente')) {
                $table->string('empresa_cliente', 160)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('user_contexts', 'email')) {
                $table->string('email', 190)->nullable()->after('empresa_cliente');
            }
            if (!Schema::hasColumn('user_contexts', 'productos_cotizados')) {
                $table->json('productos_cotizados')->nullable()->after('tareas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            $cols = array_filter(
                ['catalogo_productos', 'plantillas_rapidas', 'pipeline_automations', 'campanas_difusion'],
                fn ($c) => Schema::hasColumn('instancias', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            $cols = array_filter(
                ['empresa_cliente', 'email', 'productos_cotizados'],
                fn ($c) => Schema::hasColumn('user_contexts', $c)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

