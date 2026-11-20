<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages an admin has opened up to this team member on top of their role,
     * as {"schedule": "edit", "registrations": "view"}.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('page_access')->nullable()->after('requested_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('page_access');
        });
    }
};
