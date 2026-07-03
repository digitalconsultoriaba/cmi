<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo 3 (evento e configuração) — specs/001-fundacao/data-model.md
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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('event_type_id')->constrained('event_types');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('location_map_url')->nullable();
            $table->string('banner_path')->nullable();
            $table->unsignedInteger('total_capacity')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->unsignedInteger('reservation_ttl_minutes')->default(30);
            $table->text('participation_rules')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('pricing_mode', 10)->default('paid'); // paid|free|mixed
            $table->boolean('allow_card')->default(true);
            $table->boolean('allow_boleto')->default(true);
            $table->boolean('allow_pix')->default(true);
            $table->boolean('allow_shirt_choice')->default(true);
            $table->boolean('requires_shirt')->default(false);
            $table->boolean('allow_kit')->default(false);
            $table->boolean('allow_transfer')->default(true);
            $table->boolean('allow_user_cancel')->default(true);
            $table->boolean('allow_refund_request')->default(true);
            $table->boolean('allow_courtesy')->default(false);
            $table->unsignedInteger('courtesy_paid_threshold')->nullable();
            $table->unsignedInteger('courtesy_grant_per_threshold')->default(1);
            $table->unsignedInteger('courtesy_limit_per_account')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->string('cancel_reason')->nullable();
            $table->foreignId('status_id')->constrained('event_statuses');
            $this->audit($table);
        });

        Schema::create('landing_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('type', 20); // hero|text|schedule|speakers|faq|location|cta
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('payload');
            $this->audit($table);
            $table->index(['event_id', 'sort']);
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('seats_per_ticket')->default(1);
            $table->boolean('is_couple')->default(false);
            $table->boolean('includes_shirt')->default(false);
            $table->boolean('includes_kit')->default(false);
            $table->boolean('is_courtesy')->default(false);
            $table->string('audience', 10)->default('any'); // any|adult|child|guest
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $this->audit($table);
        });

        Schema::create('ticket_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types');
            $table->string('name');
            $table->decimal('price_override', 10, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('sold_count')->default(0); // cache recalculável
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $this->audit($table);
            $table->index(['event_id', 'ticket_type_id']);
        });

        Schema::create('event_shirt_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->string('label');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $this->audit($table);
        });

        Schema::create('event_shirt_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events');
            $table->foreignId('shirt_model_id')->constrained('event_shirt_models');
            $table->string('label');
            $table->unsignedInteger('stock_quantity')->nullable(); // null = ilimitado
            $table->unsignedInteger('sold_count')->default(0); // cache recalculável
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $this->audit($table);
            $table->index(['event_id', 'shirt_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_shirt_sizes');
        Schema::dropIfExists('event_shirt_models');
        Schema::dropIfExists('ticket_lots');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('landing_blocks');
        Schema::dropIfExists('events');
    }
};
