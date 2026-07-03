<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo 4 (pedidos e ingressos) — specs/001-fundacao/data-model.md
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // público, não sequencial
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->string('buyer_name'); // snapshot
            $table->string('buyer_email'); // snapshot
            $table->string('buyer_document')->nullable(); // snapshot
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->foreignId('status_id')->constrained('order_statuses');
            $table->dateTime('reserved_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('ticket_type_id')->constrained('ticket_types');
            $table->foreignId('ticket_lot_id')->nullable()->constrained('ticket_lots');
            $table->string('participant_name');
            $table->string('participant_email')->nullable();
            $table->string('participant_document')->nullable();
            $table->foreignId('participant_user_id')->nullable()->constrained('users');
            $table->boolean('is_guest')->default(false);
            $table->string('companion_name')->nullable(); // casal
            $table->foreignId('companion_shirt_model_id')->nullable()->constrained('event_shirt_models');
            $table->foreignId('companion_shirt_size_id')->nullable()->constrained('event_shirt_sizes');
            $table->foreignId('shirt_model_id')->nullable()->constrained('event_shirt_models');
            $table->foreignId('shirt_size_id')->nullable()->constrained('event_shirt_sizes');
            $table->decimal('unit_price', 10, 2); // snapshot
            $table->boolean('is_courtesy')->default(false);
            $table->foreignId('status_id')->constrained('ticket_statuses');
            $table->string('code')->unique(); // público, base do QR
            $table->dateTime('used_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users');
            $table->foreignId('cancel_requested_by')->nullable()->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancel_reason')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->foreignId('transferred_from_ticket_id')->nullable()->constrained('tickets');
            $table->foreignId('transferred_to_ticket_id')->nullable()->constrained('tickets');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('orders');
    }
};
