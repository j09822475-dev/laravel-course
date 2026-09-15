<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            // Допустимые варианты для упражнений с пропуском (cloze): слово или фраза,
            // которую кандидат вписывает сам — проверяется без учёта регистра и знаков.
            $table->json('accepted')->nullable()->after('correct_option');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('accepted');
        });
    }
};
