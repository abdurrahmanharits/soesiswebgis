<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editorial (curation) state and ISO 19115-lite descriptive metadata.
     * Kept separate from the ingestion `status`, which stays technical.
     */
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('curation_status')->default('pending')->index();

            $table->text('description')->nullable();
            $table->jsonb('keywords')->nullable();
            $table->string('topic_category')->nullable();

            $table->text('source')->nullable();
            $table->string('source_organization')->nullable();
            $table->string('license')->nullable();
            $table->string('license_other')->nullable();
            $table->text('usage_constraints')->nullable();

            $table->text('lineage')->nullable();

            $table->date('temporal_start')->nullable();
            $table->date('temporal_end')->nullable();
            $table->string('geographic_description')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
        });

        // Append-only audit trail of curation actions.
        Schema::create('dataset_curation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('dataset_id')->constrained('datasets')->cascadeOnDelete();
            $table->string('action');
            $table->string('reviewer_name');
            $table->text('note')->nullable();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->jsonb('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['dataset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dataset_curation_events');

        Schema::table('datasets', function (Blueprint $table) {
            $table->dropIndex(['curation_status']);
            $table->dropColumn([
                'curation_status',
                'description',
                'keywords',
                'topic_category',
                'source',
                'source_organization',
                'license',
                'license_other',
                'usage_constraints',
                'lineage',
                'temporal_start',
                'temporal_end',
                'geographic_description',
                'contact_name',
                'contact_email',
            ]);
        });
    }
};
