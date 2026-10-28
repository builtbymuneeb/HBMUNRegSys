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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('visitor', 32);
            $table->string('source')->nullable();
            $table->string('device', 16);
            $table->string('browser', 32)->nullable();
            $table->date('viewed_on');
            $table->timestamp('created_at')->nullable();

            $table->index(['viewed_on', 'path']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
