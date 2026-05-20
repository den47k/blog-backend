<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index()->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->timestamp('password_changed_at')->nullable()->after('email_verified_at');
            $table->string('name')->nullable()->change();
            $table->string('tag')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'phone', 'password_changed_at']);
            $table->string('name')->nullable(false)->change();
            $table->string('tag')->nullable(false)->change();
        });
    }
};
