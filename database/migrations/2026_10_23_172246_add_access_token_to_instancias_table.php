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
        Schema::table('instancias', function (Blueprint $table) {
            $table->text('access_token')->nullable()->after('waba_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instancias', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
