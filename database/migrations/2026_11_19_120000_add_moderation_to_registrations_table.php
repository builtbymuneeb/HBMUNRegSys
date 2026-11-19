<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff can flag a suspicious registration, and block, archive or bin it. The status it
     * had before is kept so it can be restored exactly, for example after an appeal.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->after('reviewed_at');
            $table->text('flag_note')->nullable()->after('flagged_at');
            $table->foreignId('flagged_by')->nullable()->after('flag_note')->constrained('users')->nullOnDelete();
            $table->string('status_before_moderation')->nullable()->after('flagged_by');
            $table->text('moderation_reason')->nullable()->after('status_before_moderation');
            $table->foreignId('moderated_by')->nullable()->after('moderation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->text('appeal_message')->nullable()->after('moderated_at');
            $table->timestamp('appealed_at')->nullable()->after('appeal_message');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn([
                'flagged_at', 'flag_note', 'status_before_moderation', 'moderation_reason',
                'moderated_at', 'appeal_message', 'appealed_at',
            ]);
        });
    }
};
