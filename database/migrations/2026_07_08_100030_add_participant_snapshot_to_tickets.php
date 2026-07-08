<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Snapshot da categoria/campos do participante no ingresso (spec 014) —
// imutável a mudanças posteriores de config.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('participant_category_key', 40)->nullable()->after('participant_document');
            $table->json('participant_fields')->nullable()->after('participant_category_key');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['participant_category_key', 'participant_fields']);
        });
    }
};
