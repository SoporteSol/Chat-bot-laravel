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
        Schema::table('user_contexts', function (Blueprint $table) {
            $table->string('current_node')->nullable()->default('main')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_contexts', function (Blueprint $table) {
            $table->string('current_node')->nullable(false)->default('main')->change();
        });
    }
};
