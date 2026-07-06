<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Presença de um ingresso num dia (spec 012). Único por (ticket, event_day) —
// unicidade garantida no serviço sob lock (soft delete impede índice único físico).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_day_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('event_day_id')->constrained('event_days');
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->dateTime('checked_in_at');
            $table->foreignId('operator_id')->nullable()->constrained('users');
            $table->string('origin', 15)->default('qr'); // qr|manual|admin_adjust
            $table->string('note', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->index('event_id');
            $table->index('event_day_id');
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_day_checkins');
    }
};
