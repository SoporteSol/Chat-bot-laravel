<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asociamos cada opción a su instancia
        Schema::table('options', function (Blueprint $table) {
            if (!Schema::hasColumn('options', 'instancia_id')) {
                $table->foreignId('instancia_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('instancias')
                    ->cascadeOnDelete();
            }
        });

        // Si habían nodos viejos sin instancia, los movemos a la primera
        $firstInstanciaId = DB::table('instancias')->orderBy('id')->value('id');
        if ($firstInstanciaId) {
            DB::table('chatbot_nodes')
                ->whereNull('instancia_id')
                ->update(['instancia_id' => $firstInstanciaId]);
        } else {
            DB::table('options')->delete();
            DB::table('chatbot_nodes')->whereNull('instancia_id')->delete();
        }

        // Copiamos la instancia del nodo padre hacia sus opciones hijas
        DB::statement('
            UPDATE options
            SET instancia_id = chatbot_nodes.instancia_id
            FROM chatbot_nodes
            WHERE options.chatbot_node_id = chatbot_nodes.id
              AND options.instancia_id IS NULL
        ');

        DB::table('options')->whereNull('instancia_id')->delete();

        // Clave primaria compuesta para que cada empresa tenga su propio 'main', 'servicios', etc.
        DB::statement('ALTER TABLE options DROP CONSTRAINT IF EXISTS options_chatbot_node_id_foreign');
        DB::statement('ALTER TABLE chatbot_nodes ALTER COLUMN instancia_id SET NOT NULL');
        DB::statement('ALTER TABLE options ALTER COLUMN instancia_id SET NOT NULL');
        DB::statement('ALTER TABLE chatbot_nodes DROP CONSTRAINT IF EXISTS chatbot_nodes_pkey');
        DB::statement('ALTER TABLE chatbot_nodes ADD CONSTRAINT chatbot_nodes_pkey PRIMARY KEY (instancia_id, id)');
        DB::statement('
            ALTER TABLE options
            ADD CONSTRAINT options_instancia_node_foreign
            FOREIGN KEY (instancia_id, chatbot_node_id)
            REFERENCES chatbot_nodes (instancia_id, id)
            ON DELETE CASCADE
            ON UPDATE CASCADE
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE options DROP CONSTRAINT IF EXISTS options_instancia_node_foreign');
        DB::statement('ALTER TABLE chatbot_nodes DROP CONSTRAINT IF EXISTS chatbot_nodes_pkey');
        DB::statement('ALTER TABLE chatbot_nodes ADD CONSTRAINT chatbot_nodes_pkey PRIMARY KEY (id)');
        DB::statement('
            ALTER TABLE options
            ADD CONSTRAINT options_chatbot_node_id_foreign
            FOREIGN KEY (chatbot_node_id)
            REFERENCES chatbot_nodes (id)
            ON DELETE CASCADE
        ');

        Schema::table('options', function (Blueprint $table) {
            if (Schema::hasColumn('options', 'instancia_id')) {
                $table->dropForeign(['instancia_id']);
                $table->dropColumn('instancia_id');
            }
        });
    }
};

