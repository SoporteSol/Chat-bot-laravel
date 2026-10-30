<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_contexts', function (Blueprint $table) {
            $table->foreignId('instancia_id')->nullable()->after('id')->constrained('instancias')->onDelete('cascade');
            $table->boolean('is_bot_active')->default(true)->after('current_node');
            $table->timestamp('bot_paused_until')->nullable()->after('is_bot_active');
            
            // El teléfono es único por instancia (un mismo número puede hablar con varios bots)
            $table->dropUnique(['phone_number']);
            $table->unique(['instancia_id', 'phone_number']);
        });

        Schema::table('mensajes', function (Blueprint $table) {
            $table->string('to')->nullable()->after('from')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropColumn('to');
        });

        Schema::table('user_contexts', function (Blueprint $table) {
            $table->dropUnique(['instancia_id', 'phone_number']);
            $table->unique(['phone_number']);
            $table->dropForeign(['instancia_id']);
            $table->dropColumn(['instancia_id', 'is_bot_active', 'bot_paused_until']);
        });
    }
};
