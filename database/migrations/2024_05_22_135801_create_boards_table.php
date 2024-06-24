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
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('capacity')->default(2);
            $table->string('code', 10);
            $table->timestamps();
        });

        // Ajout de la contrainte de suppression en cascade
        Schema::table('boards', function (Blueprint $table) {
            // Si une board est supprimée, les joueurs associés seront supprimés également
            $table->foreign('board_user')->constrained()->onDelete('cascade');

            // Si une board est supprimée, les chats associés seront supprimés également
            // $table->foreign('chat_id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
