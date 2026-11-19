<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a user to schedule several alerts per day: one row per
     * (chat, time) instead of a single row per chat.
     */
    public function up(): void
    {
        Schema::table('price_alerts', function (Blueprint $table) {
            $table->dropUnique(['chat_id']);
            $table->unique(['chat_id', 'notify_time']);
        });
    }

    /**
     * Reverse the migrations. Duplicates are pruned so the single-row
     * per chat constraint can be restored.
     */
    public function down(): void
    {
        $kept = [];

        foreach (DB::table('price_alerts')->orderBy('id')->get() as $row) {
            if (isset($kept[$row->chat_id])) {
                DB::table('price_alerts')->where('id', $row->id)->delete();

                continue;
            }

            $kept[$row->chat_id] = true;
        }

        Schema::table('price_alerts', function (Blueprint $table) {
            $table->dropUnique(['chat_id', 'notify_time']);
            $table->unique(['chat_id']);
        });
    }
};
