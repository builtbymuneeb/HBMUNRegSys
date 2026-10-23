<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The registrations team now handles allocations too, so fold the old role into it.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'allocations')->update(['role' => 'registrations']);
        DB::table('users')->where('requested_role', 'allocations')->update(['requested_role' => 'registrations']);
    }

    /**
     * The merge can't be told apart afterwards, so there is nothing to undo.
     */
    public function down(): void
    {
        //
    }
};
