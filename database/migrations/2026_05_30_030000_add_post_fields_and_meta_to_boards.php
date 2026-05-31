<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boards') && ! Schema::hasColumn('boards', 'post_fields')) {
            Schema::table('boards', function (Blueprint $table) {
                $table->json('post_fields')->nullable()->after('settings')->comment('게시글 추가 필드 정의');
            });
        }

        foreach ($this->boardPostTables() as $tableName) {
            if (! Schema::hasColumn($tableName, 'meta')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->json('meta')->nullable()->after('options')->comment('게시글 추가 메타데이터');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->boardPostTables() as $tableName) {
            if (Schema::hasColumn($tableName, 'meta')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('meta');
                });
            }
        }

        if (Schema::hasTable('boards') && Schema::hasColumn('boards', 'post_fields')) {
            Schema::table('boards', function (Blueprint $table) {
                $table->dropColumn('post_fields');
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function boardPostTables(): array
    {
        if (! Schema::hasTable('boards')) {
            return [];
        }

        return DB::table('boards')
            ->pluck('slug')
            ->filter(fn ($slug) => is_string($slug) && preg_match('/^[a-z0-9_]+$/', $slug))
            ->map(fn ($slug) => "board_posts_{$slug}")
            ->filter(fn ($tableName) => Schema::hasTable($tableName))
            ->values()
            ->all();
    }
};
