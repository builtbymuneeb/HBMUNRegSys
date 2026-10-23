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
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('to');
            $table->string('subject')->nullable();
            $table->string('notification')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['to', 'notification']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
