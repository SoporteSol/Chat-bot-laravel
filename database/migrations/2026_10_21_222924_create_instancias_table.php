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
        Schema::create('instancias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa');
            $table->string('telefono')->nullable();
            $table->string('phone_number_id')->unique();
            $table->string('waba_id')->nullable();
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instancias');
    }
};
