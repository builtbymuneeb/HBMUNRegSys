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
        Schema::create('committee_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('meeting_url', 500)->nullable();
            $table->timestamps();

            $table->index(['committee_id', 'starts_at']);
        });

        // A committee now has one meeting link per session instead of one overall link and a free-text schedule.
        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn(['meeting_url', 'meeting_notes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->string('meeting_url')->nullable()->after('agenda');
            $table->text('meeting_notes')->nullable()->after('meeting_url');
        });

        Schema::dropIfExists('committee_sessions');
    }
};
