<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Categorias de participante do checkout (spec 014). Standalone: rótulos
// maçônicos vivem em `label`; o código usa "categoria" genérica.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('key', 40);
            $table->string('label', 120);
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->unique(['event_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_categories');
    }
};
