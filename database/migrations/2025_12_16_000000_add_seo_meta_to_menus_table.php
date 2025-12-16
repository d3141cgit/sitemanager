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
        Schema::table('menus', function (Blueprint $table) {
            if (!Schema::hasColumn('menus', 'seo_meta')) {
                $table->json('seo_meta')
                    ->nullable()
                    ->after('images')
                    ->comment('SEO meta configuration (title, description, canonical, schema options, custom JSON-LD etc.)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (Schema::hasColumn('menus', 'seo_meta')) {
                $table->dropColumn('seo_meta');
            }
        });
    }
};


