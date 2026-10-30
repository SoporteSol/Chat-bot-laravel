<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('chatbot_nodes', 'media_url')) {
                $table->string('media_url', 1000)->nullable()->after('mensaje');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chatbot_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('chatbot_nodes', 'media_url')) {
                $table->dropColumn('media_url');
            }
        });
    }
};

