<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->string('type', 24);
            $table->string('difficulty', 16);
            $table->text('prompt');
            $table->text('answer');
            $table->text('explanation')->nullable();
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('correct_option')->nullable();
            $table->json('follow_ups')->nullable();
            $table->json('checklist')->nullable();
            $table->json('red_flags')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedSmallInteger('estimated_seconds')->default(120);
            $table->timestamps();

            $table->index(['type', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
