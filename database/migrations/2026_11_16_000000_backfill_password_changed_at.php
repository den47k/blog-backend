<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Users without a linked provider can only have registered with a password.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('password_changed_at')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('oauth_identities')
                ->whereColumn('oauth_identities.user_id', 'users.id'))
            ->update(['password_changed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        //
    }
};
