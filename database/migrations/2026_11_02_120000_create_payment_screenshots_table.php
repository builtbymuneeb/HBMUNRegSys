<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A registration can carry several payment screenshots (one per receipt), and staff can add
     * more later. Each existing registration's screenshot is copied in as its first one. The old
     * payment_screenshot_path column stays as it was and keeps holding the first screenshot.
     */
    public function up(): void
    {
        Schema::create('payment_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('registrations')
            ->select('id', 'payment_screenshot_path', 'created_at', 'updated_at')
            ->orderBy('id')
            ->each(function (object $row) {
                DB::table('payment_screenshots')->insert([
                    'registration_id' => $row->id,
                    'path' => $row->payment_screenshot_path,
                    'created_at' => $row->updated_at ?? $row->created_at,
                    'updated_at' => $row->updated_at ?? $row->created_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_screenshots');
    }
};
