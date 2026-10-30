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
        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->timestamp('welcome_emailed_at')->nullable()->after('flag_reason');
        });

        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->foreignId('campus_ambassador_id')->nullable()->after('delegate_id')->constrained()->cascadeOnDelete();
            $table->index(['campus_ambassador_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->dropIndex(['campus_ambassador_id', 'kind']);
            $table->dropConstrainedForeignId('campus_ambassador_id');
        });

        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->dropColumn('welcome_emailed_at');
        });
    }
};
