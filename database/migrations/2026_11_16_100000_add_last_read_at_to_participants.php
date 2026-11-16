<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read state used to live in Redis and is not carried over:
     * every existing conversation starts out as read.
     */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->timestamp('last_read_at', 6)->nullable();
        });

        DB::table('participants')->update(['last_read_at' => now()->format('Y-m-d H:i:s.u')]);
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropColumn('last_read_at');
        });
    }
};
