<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('phase', 24)->default('tech');
            $table->text('answer_text')->nullable();
            $table->unsignedTinyInteger('selected_option')->nullable();
            $table->unsignedTinyInteger('self_rating')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('seconds_spent')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['interview_session_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_items');
    }
};
