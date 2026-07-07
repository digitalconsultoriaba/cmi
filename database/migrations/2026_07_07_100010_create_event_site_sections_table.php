<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seções do Site (spec 013): uma linha por seção, com payload JSON por tipo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_site_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_site_id')->constrained('event_sites');
            $table->string('type', 20);
            $table->integer('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('payload')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->index(['event_site_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_site_sections');
    }
};
