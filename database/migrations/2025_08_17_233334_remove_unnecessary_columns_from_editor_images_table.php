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
        Schema::table('editor_images', function (Blueprint $table) {
            // Remove unnecessary columns
            $table->dropColumn(['width', 'height', 'uploaded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editor_images', function (Blueprint $table) {
            // Add back removed columns
            $table->integer('width')->nullable()->after('mime_type');
            $table->integer('height')->nullable()->after('width');
            $table->timestamp('uploaded_at')->nullable()->after('uploaded_by');
        });
    }
};
