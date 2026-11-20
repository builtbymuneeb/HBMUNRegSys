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
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day');
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->string('activity', 120);
            $table->string('link', 500)->nullable();
            $table->boolean('link_visible')->default(false);
            $table->timestamps();

            $table->index(['day', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
