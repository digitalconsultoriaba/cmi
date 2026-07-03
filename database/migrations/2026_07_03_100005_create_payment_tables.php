<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo 5 (pagamento — estrutura; fluxo na spec 005) — specs/001-fundacao/data-model.md
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->decimal('amount', 10, 2);
            $table->string('method', 10); // pix|boleto|card|manual
            $table->string('provider', 20); // sicoob|card_gateway|manual
            $table->string('provider_charge_id')->nullable();
            $table->foreignId('status_id')->constrained('payment_statuses');
            $table->text('pix_qrcode')->nullable();
            $table->text('pix_qrcode_image')->nullable();
            $table->string('boleto_line')->nullable();
            $table->string('boleto_pdf_url')->nullable();
            $table->string('boleto_barcode')->nullable();
            $table->string('card_brand')->nullable(); // nunca PAN/CVV
            $table->string('card_last4', 4)->nullable();
            $table->unsignedTinyInteger('installments')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users');
            $table->json('raw_response')->nullable();
            $table->text('note')->nullable();
            $table->timestamps(); // registro financeiro: sem soft delete
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            // Idempotência do ponto único de baixa (constituição, princípio III)
            $table->unique(['provider', 'provider_charge_id']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20); // sicoob|card_gateway
            $table->string('external_id')->nullable();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->string('result', 10)->nullable(); // ok|ignored|error
            $table->timestamps();
            $table->unique(['provider', 'external_id']); // dedupe
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
    }
};
