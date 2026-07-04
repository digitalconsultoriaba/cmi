<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campos maçônicos do inscrito + contato de suporte por evento (spec 010, ajustes).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('potencia')->nullable()->after('phone');
            $table->string('loja')->nullable()->after('potencia');
            $table->string('grau', 30)->nullable()->after('loja'); // aprendiz|companheiro|mestre|mestre_instalado
            $table->string('cargo_loja')->nullable()->after('grau');
            $table->string('cargo_potencia')->nullable()->after('cargo_loja');
            $table->string('endereco')->nullable()->after('cargo_potencia');
            $table->string('cidade')->nullable()->after('endereco');
            $table->string('pais')->nullable()->after('cidade');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('support_whatsapp')->nullable()->after('cancel_reason');
            $table->string('support_email')->nullable()->after('support_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['potencia', 'loja', 'grau', 'cargo_loja', 'cargo_potencia', 'endereco', 'cidade', 'pais']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['support_whatsapp', 'support_email']);
        });
    }
};
