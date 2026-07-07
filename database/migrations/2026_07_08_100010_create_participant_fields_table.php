<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campos de uma categoria de participante (spec 014). type: text|affiliation|
// country|city|conditional. `config` guarda ex.: { question, reveals } do condicional.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_category_id')->constrained('participant_categories');
            $table->string('key', 40);
            $table->string('label', 120);
            $table->string('type', 20)->default('text');
            $table->boolean('required')->default(false);
            $table->integer('sort')->default(0);
            $table->json('config')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->unique(['participant_category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_fields');
    }
};
