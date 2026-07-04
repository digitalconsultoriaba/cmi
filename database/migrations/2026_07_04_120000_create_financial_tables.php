<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Módulo financeiro central (spec 010) — contas a pagar e a receber.
// specs/010-fluxo-caixa/data-model.md
return new class extends Migration
{
    private function audit(Blueprint $table): void
    {
        $table->timestamps();
        $table->softDeletes();
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('updated_by')->nullable()->constrained('users');
    }

    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // income|expense
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $this->audit($table);
        });

        Schema::create('financial_people', function (Blueprint $table) {
            $table->id();
            // supplier|customer|sponsor|participant|provider|other
            $table->string('kind', 15)->default('supplier');
            $table->string('name');
            $table->string('document')->nullable(); // CPF/CNPJ
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('financial_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('financial_recurrences', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // payable|receivable
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->foreignId('category_id')->nullable()->constrained('financial_categories');
            $table->foreignId('person_id')->nullable()->constrained('financial_people');
            $table->foreignId('event_id')->nullable()->constrained('events');
            $table->foreignId('payment_method_id')->nullable()->constrained('financial_payment_methods');
            $table->string('frequency', 10); // weekly|monthly|yearly
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->date('last_generated_on')->nullable();
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // payable|receivable
            $table->string('description');
            $table->decimal('amount', 10, 2);            // valor original
            $table->decimal('settled_amount', 10, 2)->default(0); // cache recontável
            $table->foreignId('category_id')->nullable()->constrained('financial_categories');
            $table->foreignId('payment_method_id')->nullable()->constrained('financial_payment_methods');
            $table->foreignId('event_id')->nullable()->constrained('events'); // null = geral
            $table->foreignId('person_id')->nullable()->constrained('financial_people');
            $table->date('due_date');
            $table->string('origin', 20)->default('manual');
            // Espelho de ingresso/patrocínio — dedupe (FR-020)
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->uuid('installment_group')->nullable();
            $table->unsignedInteger('installment_number')->nullable();
            $table->unsignedInteger('installment_total')->nullable();
            $table->foreignId('recurrence_id')->nullable()->constrained('financial_recurrences');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $this->audit($table);
            $table->unique(['source_type', 'source_id']); // zero duplicidade
            $table->index(['direction', 'due_date']);
            $table->index('event_id');
        });

        Schema::create('financial_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('financial_entries')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('kind', 10); // payment|receipt|reversal
            $table->date('settled_on');
            $table->foreignId('payment_method_id')->nullable()->constrained('financial_payment_methods');
            $table->string('bank_account')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });

        Schema::create('financial_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('financial_entries')->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 15)->default('other'); // receipt|invoice|contract|boleto|other
            $table->string('original_name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_attachments');
        Schema::dropIfExists('financial_settlements');
        Schema::dropIfExists('financial_entries');
        Schema::dropIfExists('financial_recurrences');
        Schema::dropIfExists('financial_payment_methods');
        Schema::dropIfExists('financial_people');
        Schema::dropIfExists('financial_categories');
    }
};
