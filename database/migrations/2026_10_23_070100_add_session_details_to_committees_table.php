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
        Schema::table('committees', function (Blueprint $table) {
            $table->text('agenda')->nullable()->after('portfolio_type');
            $table->string('meeting_url')->nullable()->after('agenda');
            $table->text('meeting_notes')->nullable()->after('meeting_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn(['agenda', 'meeting_url', 'meeting_notes']);
        });
    }
};
