<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Everyone added so far signed up through the HB (Homebridge) form.
     */
    public function up(): void
    {
        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->string('type', 20)->default('hb')->after('code_key')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
