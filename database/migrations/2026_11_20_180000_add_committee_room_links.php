<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each committee gets one meeting room link used for all its sessions, and schedule
     * rows can point delegates at their own committee's room instead of a shared link.
     */
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->string('meeting_url', 500)->nullable()->after('agenda');
        });

        Schema::table('schedule_items', function (Blueprint $table) {
            $table->boolean('uses_committee_link')->default(false)->after('link');
        });

        DB::table('schedule_items')->where('activity', 'like', 'Session%')->update(['uses_committee_link' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            $table->dropColumn('uses_committee_link');
        });

        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn('meeting_url');
        });
    }
};
