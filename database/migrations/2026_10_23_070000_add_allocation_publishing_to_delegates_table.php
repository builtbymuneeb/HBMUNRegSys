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
        Schema::table('delegates', function (Blueprint $table) {
            $table->foreignId('allocated_by')->nullable()->after('allocated_portfolio')->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable()->after('allocated_by');
            $table->timestamp('allocation_published_at')->nullable()->after('allocated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delegates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allocated_by');
            $table->dropColumn(['allocated_at', 'allocation_published_at']);
        });
    }
};
