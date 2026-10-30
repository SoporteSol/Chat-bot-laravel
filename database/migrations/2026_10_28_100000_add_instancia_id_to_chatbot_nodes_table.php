<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('chatbot_nodes', 'instancia_id')) {
                $table->foreignId('instancia_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('instancias')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('options', function (Blueprint $table) {
            $table->dropForeign(['chatbot_node_id']);
            $table->foreign('chatbot_node_id')
                ->references('id')
                ->on('chatbot_nodes')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('options', function (Blueprint $table) {
            $table->dropForeign(['chatbot_node_id']);
            $table->foreign('chatbot_node_id')
                ->references('id')
                ->on('chatbot_nodes');
        });

        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('chatbot_nodes', 'instancia_id')) {
                $table->dropForeign(['instancia_id']);
                $table->dropColumn('instancia_id');
            }
        });
    }
};

