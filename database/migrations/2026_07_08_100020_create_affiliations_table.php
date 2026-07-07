<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lista gerenciável de afiliações ("lojas") por evento (spec 014), fonte do
// campo de seleção/autocomplete. Standalone: só uma lista de nomes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('name', 160);
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->index(['event_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliations');
    }
};
