<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // menus 테이블이 존재하는지 확인
        if (!Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table) {
            // search_content 필드가 존재하지 않는 경우에만 추가
            if (!Schema::hasColumn('menus', 'search_content')) {
                $table->text('search_content')->nullable()->comment('검색용 컨텐츠 (뷰 파일에서 추출한 텍스트)');
            }
        });

        // fulltext 인덱스 추가 (MySQL 5.7+ 지원)
        if (Schema::hasTable('menus') && Schema::hasColumn('menus', 'search_content')) {
            try {
                // 기존 인덱스 확인 후 추가
                $indexExists = DB::select("SHOW INDEX FROM menus WHERE Key_name = 'menus_search_content_fulltext'");
                
                if (empty($indexExists)) {
                    Schema::table('menus', function (Blueprint $table) {
                        $table->fullText(['search_content'], 'menus_search_content_fulltext');
                    });
                }
            } catch (\Exception $e) {
                // 인덱스 생성 실패 시 로그만 남기고 계속 진행
                Log::warning('Failed to create fulltext index for menus.search_content: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // menus 테이블이 존재하지 않으면 아무것도 하지 않음
        if (!Schema::hasTable('menus')) {
            return;
        }

        Schema::table('menus', function (Blueprint $table) {
            // fulltext 인덱스 제거
            try {
                $indexExists = DB::select("SHOW INDEX FROM menus WHERE Key_name = 'menus_search_content_fulltext'");
                
                if (!empty($indexExists)) {
                    $table->dropFullText('menus_search_content_fulltext');
                }
            } catch (\Exception $e) {
                // 인덱스가 존재하지 않는 경우 무시
                Log::warning('Failed to drop fulltext index for menus.search_content: ' . $e->getMessage());
            }

            // search_content 필드가 존재하는 경우에만 제거
            if (Schema::hasColumn('menus', 'search_content')) {
                $table->dropColumn('search_content');
            }
        });
    }
};
