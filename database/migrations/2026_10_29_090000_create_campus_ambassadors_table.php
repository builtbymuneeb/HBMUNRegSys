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
        Schema::create('campus_ambassadors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('code_key', 50)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('institution')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('campus_ambassador_id')->nullable()->after('referral_code')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campus_ambassador_id');
        });

        Schema::dropIfExists('campus_ambassadors');
    }
};
