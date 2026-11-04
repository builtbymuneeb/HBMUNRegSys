<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give each committee choice its own preference, so a delegate can ask for France in UNW and
     * Japan in UNSC. Existing delegates get their shared country or personality copied into each
     * choice it applied to. The old columns stay as they were.
     */
    public function up(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->string('first_preference')->nullable()->after('personality_preference');
            $table->string('second_preference')->nullable()->after('first_preference');
        });

        foreach (['first' => 'first_committee_id', 'second' => 'second_committee_id'] as $slot => $column) {
            DB::table('delegates')
                ->join('committees', 'committees.id', '=', "delegates.{$column}")
                ->select('delegates.id', 'committees.portfolio_type', 'delegates.country_preference', 'delegates.personality_preference')
                ->orderBy('delegates.id')
                ->each(function (object $row) use ($slot) {
                    $preference = $row->portfolio_type === 'character' ? $row->personality_preference : $row->country_preference;

                    if ($preference !== null) {
                        DB::table('delegates')->where('id', $row->id)->update(["{$slot}_preference" => $preference]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->dropColumn(['first_preference', 'second_preference']);
        });
    }
};
