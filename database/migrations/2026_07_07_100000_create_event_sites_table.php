<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Site do evento (spec 013): 1:1 com o evento. Publicação e visibilidade pública
// são DERIVADAS (published_at + event.visible_on_site); nada de coluna "público".
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events');
            $table->string('slug')->unique();
            $table->json('theme')->nullable();          // design tokens
            $table->json('identity')->nullable();       // logo/selos/marca-d'água/eventName
            $table->dateTime('countdown_at')->nullable(); // data-alvo (UTC)
            $table->json('seo')->nullable();            // title/description por idioma + og
            $table->json('active_languages')->nullable(); // ⊆ pt,en,es (pt sempre)

            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sites');
    }
};
