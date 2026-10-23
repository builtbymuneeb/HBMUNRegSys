<?php

use App\Support\DefaultPortfolios;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->json('portfolios')->nullable()->after('portfolio_type');
            $table->boolean('portfolios_public')->default(true)->after('portfolios');
        });

        foreach (DB::table('committees')->get(['id', 'code']) as $committee) {
            DB::table('committees')->where('id', $committee->id)->update([
                'portfolios' => json_encode(DefaultPortfolios::for($committee->code)),
                'portfolios_public' => ! in_array($committee->code, DefaultPortfolios::HIDDEN, true),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropColumn(['portfolios', 'portfolios_public']);
        });
    }
};
