<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Drop the old foreign key first
            $table->dropForeign(['last_message_id']);

            // Re-add with nullOnDelete()
            $table->foreign('last_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Drop the modified FK
            $table->dropForeign(['last_message_id']);

            // Re-add the old one (no onDelete behavior)
            $table->foreign('last_message_id')
                ->references('id')
                ->on('messages');
        });
    }
};
