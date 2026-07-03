<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo 6 (cortesia, patrocínio, suporte) — specs/001-fundacao/data-model.md
return new class extends Migration
{
    private function audit(Blueprint $table, bool $soft = true): void
    {
        $table->timestamps();
        if ($soft) {
            $table->softDeletes();
        }
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('updated_by')->nullable()->constrained('users');
    }

    public function up(): void
    {
        Schema::create('courtesy_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('code')->unique();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types');
            $table->string('status', 15)->default('available'); // available|distributed|redeemed
            $table->dateTime('distributed_at')->nullable();
            $table->foreignId('distributed_by')->nullable()->constrained('users');
            $table->dateTime('redeemed_at')->nullable();
            $table->foreignId('redeemed_ticket_id')->nullable()->constrained('tickets');
            $table->text('note')->nullable();
            $this->audit($table);
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('company_name');
            $table->string('contact')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method')->nullable();
            $table->unsignedInteger('installments_count')->default(1);
            $table->string('status', 10)->default('pending'); // pending|partial|paid|cancelled
            $table->text('notes')->nullable();
            $this->audit($table);
        });

        Schema::create('sponsorship_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsorship_id')->constrained('sponsorships')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->decimal('amount', 10, 2);
            $table->dateTime('due_date')->nullable();
            $table->string('status', 10)->default('pending'); // pending|paid
            $table->dateTime('paid_at')->nullable();
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->string('method')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users');
            $table->text('note')->nullable();
            $this->audit($table, soft: false);
            $table->unique(['sponsorship_id', 'number']);
        });

        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 15); // refund|question|shirt_change|other
            $table->string('status', 10)->default('open'); // open|finished|reopened
            $table->string('subject')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $this->audit($table);
            $table->index(['event_id', 'user_id']);
        });

        Schema::create('support_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_case_id')->constrained('support_cases')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users');
            $table->text('body');
            $table->boolean('visible_to_attendee')->default(true);
            $table->boolean('from_attendee')->default(false);
            $this->audit($table, soft: false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_case_notes');
        Schema::dropIfExists('support_cases');
        Schema::dropIfExists('sponsorship_installments');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('courtesy_vouchers');
    }
};
