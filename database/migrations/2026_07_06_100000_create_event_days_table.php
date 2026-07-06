<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dias operacionais do evento (spec 012): 1..3 por evento. Situação é DERIVADA;
// colunas aqui só registram as AÇÕES auditadas (finalização/bloqueio/reabertura).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->unsignedTinyInteger('day_number'); // 1, 2 ou 3
            $table->date('event_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('label', 60)->nullable();

            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users');
            $table->dateTime('blocked_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users');
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users');
            $table->string('reopen_reason', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->unique(['event_id', 'day_number']);
            $table->index(['event_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
