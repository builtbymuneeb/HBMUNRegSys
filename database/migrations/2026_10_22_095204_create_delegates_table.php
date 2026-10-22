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
        Schema::create('delegates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('cnic');
            $table->string('email')->index();
            $table->string('phone');
            $table->foreignId('first_committee_id')->constrained('committees');
            $table->foreignId('second_committee_id')->constrained('committees');
            $table->string('country_preference')->nullable();
            $table->string('personality_preference')->nullable();
            $table->foreignId('allocated_committee_id')->nullable()->constrained('committees')->nullOnDelete();
            $table->string('allocated_portfolio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegates');
    }
};
