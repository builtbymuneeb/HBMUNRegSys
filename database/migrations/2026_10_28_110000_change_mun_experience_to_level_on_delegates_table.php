<?php

use App\Enums\MunExperience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Experience was free text; it is now one of the MunExperience levels.
     * Old free-text answers can't be mapped to a level, so they are cleared.
     */
    public function up(): void
    {
        DB::table('delegates')
            ->whereNotNull('mun_experience')
            ->whereNotIn('mun_experience', array_column(MunExperience::cases(), 'value'))
            ->update(['mun_experience' => null]);

        Schema::table('delegates', function (Blueprint $table) {
            $table->string('mun_experience', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->text('mun_experience')->nullable()->change();
        });
    }
};
