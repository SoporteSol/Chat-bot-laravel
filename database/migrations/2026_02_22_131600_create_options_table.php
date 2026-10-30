<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->string("chatbot_node_id");
            $table->string("key");
            $table->string("text");
            $table->string("respuesta")->nullable();
            $table->string("next_node_id")->nullable();

            $table->foreign("chatbot_node_id")->references("id")->on("chatbot_nodes");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
