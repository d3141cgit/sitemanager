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
        // 기본 comments 테이블에 컬럼 추가
        if (Schema::hasTable('comments')) {
            Schema::table('comments', function (Blueprint $table) {
                if (!Schema::hasColumn('comments', 'is_verified')) {
                    $table->boolean('is_verified')->default(true)->after('status');
                }
                if (!Schema::hasColumn('comments', 'email_verification_token')) {
                    $table->string('email_verification_token')->nullable()->after('is_verified');
                }
            });
        }

        // 게시판별 동적 테이블들에도 적용
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            $tableName = "{$board->slug}_comments";
            
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'is_verified')) {
                        $table->boolean('is_verified')->default(true)->after('status');
                    }
                    if (!Schema::hasColumn($tableName, 'email_verification_token')) {
                        $table->string('email_verification_token')->nullable()->after('is_verified');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 기본 comments 테이블에서 컬럼 제거
        if (Schema::hasTable('comments')) {
            Schema::table('comments', function (Blueprint $table) {
                if (Schema::hasColumn('comments', 'email_verification_token')) {
                    $table->dropColumn('email_verification_token');
                }
                if (Schema::hasColumn('comments', 'is_verified')) {
                    $table->dropColumn('is_verified');
                }
            });
        }

        // 게시판별 동적 테이블들에서도 제거
        $boards = \SiteManager\Models\Board::all();
        
        foreach ($boards as $board) {
            $tableName = "{$board->slug}_comments";
            
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'email_verification_token')) {
                        $table->dropColumn('email_verification_token');
                    }
                    if (Schema::hasColumn($tableName, 'is_verified')) {
                        $table->dropColumn('is_verified');
                    }
                });
            }
        }
    }
};
