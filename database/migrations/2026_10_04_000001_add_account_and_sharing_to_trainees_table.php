<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            // Профиль прогресса принадлежит аккаунту; у гостя user_id остаётся пустым.
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->cascadeOnDelete();

            // Пока токен не выдан, прогресс закрыт: публичной страницы просто нет.
            $table->string('share_token', 32)->nullable()->unique()->after('target_level');
            $table->timestamp('shared_at')->nullable()->after('share_token');
        });
    }

    public function down(): void
    {
        Schema::table('trainees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['share_token', 'shared_at']);
        });
    }
};
