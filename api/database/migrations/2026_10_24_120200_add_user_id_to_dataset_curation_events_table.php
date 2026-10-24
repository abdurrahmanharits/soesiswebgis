<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account behind each curation event. Nullable: events recorded before
     * authentication only have the typed reviewer_name, which stays as a snapshot.
     */
    public function up(): void
    {
        Schema::table('dataset_curation_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('dataset_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dataset_curation_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
