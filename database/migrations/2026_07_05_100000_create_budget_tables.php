<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Aba Orçamento / previsão financeira do evento (spec 011). Planejamento —
// distinto do Financeiro real (spec 010). Estado (totais) é derivado, nunca coluna.
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
        // Cabeçalho do orçamento — 1:1 com o evento (criado sob demanda).
        Schema::create('budget_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events');
            $table->unsignedInteger('expected_paying')->default(0);
            $table->unsignedInteger('expected_courtesy')->default(0);
            $table->unsignedInteger('expected_guests')->default(0);
            $table->unsignedInteger('expected_staff')->default(0);
            $table->unsignedInteger('expected_speakers')->default(0);
            $table->decimal('other_revenue', 10, 2)->default(0);
            $table->decimal('safety_margin_pct', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $this->audit($table);
        });

        // Itens de custo previstos (a "planilha").
        Schema::create('budget_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans');
            $table->string('description');
            $table->string('category');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2); // valor efetivo do item
            $table->string('supplier_name')->nullable();
            $table->string('status', 20)->default('planned'); // planned|quoted|approved|contracted|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('financial_entry_id')->nullable()->constrained('financial_entries');
            $this->audit($table);
        });

        // Lotes de ingresso PREVISTOS (planejamento — distintos dos lotes reais).
        Schema::create('budget_ticket_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans');
            $table->string('name');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('expected_paying')->nullable();
            $table->text('notes')->nullable();
            $this->audit($table);
        });

        // Cotas de patrocínio PREVISTAS.
        Schema::create('budget_sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans');
            $table->string('name');
            $table->decimal('unit_value', 10, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 20)->default('planned'); // planned|negotiating|confirmed|received|lost|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('financial_entry_id')->nullable()->constrained('financial_entries');
            $this->audit($table);
        });

        // Cenários what-if (até 3 por plano).
        Schema::create('budget_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_plan_id')->constrained('budget_plans');
            $table->string('key', 20); // conservative|realistic|optimistic
            $table->unsignedInteger('paying')->default(0);
            $table->decimal('avg_ticket', 10, 2)->default(0);
            $table->decimal('sponsorship', 10, 2)->default(0);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('other_revenue', 10, 2)->default(0);
            $this->audit($table);
            $table->unique(['budget_plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_scenarios');
        Schema::dropIfExists('budget_sponsorships');
        Schema::dropIfExists('budget_ticket_lots');
        Schema::dropIfExists('budget_cost_items');
        Schema::dropIfExists('budget_plans');
    }
};
