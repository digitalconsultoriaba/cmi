<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Visibilidade pública do evento (spec ajustes): marcado → aparece no site para
// inscrição; desmarcado → oculto (não listado / 404 no público). Independente
// do status do ciclo de vida.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('visible_on_site')->default(true)->after('status_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('visible_on_site');
        });
    }
};
