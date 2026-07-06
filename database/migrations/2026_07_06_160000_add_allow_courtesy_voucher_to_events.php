<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cortesia por VOUCHER (códigos) separada da cortesia automática (regra X→Y).
// A aba Cortesias aparece se qualquer uma estiver habilitada.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('allow_courtesy_voucher')->default(false)->after('allow_courtesy');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('allow_courtesy_voucher');
        });
    }
};
