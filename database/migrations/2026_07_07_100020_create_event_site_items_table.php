<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Itens das seções dinâmicas (spec 013): N itens ordenáveis, com um nível de
// aninhamento via parent_item_id (dia→entradas, categoria→contatos, grupo→logos).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_site_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_site_section_id')->constrained('event_site_sections');
            $table->foreignId('parent_item_id')->nullable()->constrained('event_site_items');
            $table->integer('sort')->default(0);
            $table->json('payload')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->index(['event_site_section_id', 'parent_item_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_site_items');
    }
};
