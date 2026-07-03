<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grupo 2 (lookups) — specs/001-fundacao/data-model.md
return new class extends Migration
{
    public function up(): void
    {
        foreach (['event_statuses', 'order_statuses', 'ticket_statuses', 'payment_statuses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->unsignedInteger('sort')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
        foreach (['payment_statuses', 'ticket_statuses', 'order_statuses', 'event_statuses'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
