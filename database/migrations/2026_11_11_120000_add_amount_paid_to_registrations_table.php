<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fees are no longer fixed (some registrations get discounts), so staff record the amount
     * actually received when they verify. Registrations already verified are backfilled with
     * their full fee, which is what was charged before.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->unsignedInteger('amount_paid')->nullable()->after('amount_due');
        });

        DB::table('registrations')
            ->where('status', 'verified')
            ->update(['amount_paid' => DB::raw('amount_due')]);
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('amount_paid');
        });
    }
};
