<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The campus ambassador portal: ambassadors log in, do tasks staff set, upload proof,
     * and earn points on a leaderboard. Only adds tables and one nullable column;
     * nothing existing is changed or removed.
     */
    public function up(): void
    {
        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('portal_invited_at')->nullable()->after('welcome_emailed_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('ambassador_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('points');
            $table->string('audience', 20)->default('everyone');
            $table->boolean('repeatable')->default(false);
            $table->unsignedInteger('max_completions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ambassador_task_assignees', function (Blueprint $table) {
            $table->foreignId('ambassador_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_ambassador_id')->constrained()->cascadeOnDelete();
            $table->primary(['ambassador_task_id', 'campus_ambassador_id']);
        });

        Schema::create('ambassador_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_ambassador_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->string('link', 2048)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('points_awarded')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ambassador_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambassador_submission_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('ambassador_point_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_ambassador_id')->constrained()->cascadeOnDelete();
            $table->integer('points');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_point_adjustments');
        Schema::dropIfExists('ambassador_submission_files');
        Schema::dropIfExists('ambassador_submissions');
        Schema::dropIfExists('ambassador_task_assignees');
        Schema::dropIfExists('ambassador_tasks');
        Schema::dropIfExists('settings');

        Schema::table('campus_ambassadors', function (Blueprint $table) {
            $table->dropColumn('portal_invited_at');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
