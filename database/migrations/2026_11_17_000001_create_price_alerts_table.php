<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('notify_time', 5);
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->json('items');
            $table->boolean('is_active')->default(true);
            $table->date('last_sent_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'notify_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
