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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->string('type');
            $table->string('status')->default('pending')->index();
            $table->string('organisation_name')->nullable();
            $table->string('city');
            $table->string('contact_name');
            $table->string('contact_cnic');
            $table->string('contact_phone');
            $table->string('contact_email')->index();
            $table->unsignedInteger('amount_due');
            $table->string('payment_screenshot_path');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
