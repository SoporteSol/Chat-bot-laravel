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
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instancia_id')->nullable()->constrained('instancias')->onDelete('cascade');
            $table->string('whatsapp_id')->nullable()->index();
            $table->string('from')->nullable()->index();
            $table->text('body')->nullable();
            $table->string('type')->default('text');
            $table->string('direction')->default('inbound');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes');
    }
};
