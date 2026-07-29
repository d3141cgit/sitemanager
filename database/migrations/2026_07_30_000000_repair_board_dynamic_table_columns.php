<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 이미 생성된 게시판 동적 테이블(board_posts_*, board_comments_*)의 누락 컬럼을 보정한다.
 *
 * 배경 — createBoardTables() 와 모델 선언이 어긋나 있었다:
 *
 *  1) board_posts_*.status enum 에 'pending' 이 없는데 BoardService::createPost() 는
 *     비회원 글에 status='pending' 을 쓴다. → 비회원 글 작성이 전부
 *     "Data truncated for column 'status'" 로 실패했다.
 *
 *  2) board_comments_* 에 ip_address / user_agent / meta 컬럼이 없는데
 *     BoardComment 모델은 세 개를 모두 $fillable 로 선언한다. → 대입 시
 *     "Unknown column" 이 발생했다.
 *
 * 신규 게시판은 수정된 createBoardTables() 가 처리하므로, 이 마이그레이션은
 * **기존 게시판만** 보정한다.
 *
 * 안전성:
 *  - 멱등하다. 이미 반영된 테이블은 건너뛴다.
 *  - 기존 행의 값을 바꾸지 않는다. enum 값 추가와 nullable 컬럼 추가뿐이다.
 *  - enum 변경은 MySQL 계열에서만 수행한다(SQLite 는 enum 을 varchar+check 로 만들어
 *    ALTER 로 값을 추가할 수 없고, 테스트 환경이라 비회원 플로를 쓰지 않는다).
 *  - 게시판 행은 있지만 테이블이 없는 경우를 건너뛴다.
 */
return new class extends Migration
{
    /** 기존 enum 값 + 추가할 값. 순서를 보존해야 기존 값의 의미가 유지된다. */
    private const POST_STATUSES = ['draft', 'published', 'private', 'pending'];

    public function up(): void
    {
        // boards 테이블이 아직 없는 설치(순서상 앞선 경우)는 할 일이 없다.
        if (! Schema::hasTable('boards')) {
            return;
        }

        foreach (DB::table('boards')->pluck('slug') as $slug) {
            $this->repairPostsTable("board_posts_{$slug}");
            $this->repairCommentsTable("board_comments_{$slug}");
        }
    }

    /**
     * 되돌리지 않는다.
     *
     * enum 에서 'pending' 을 제거하면 그 상태로 남아 있는 비회원 글의 값이 잘려 나가고,
     * 컬럼을 되돌리면 그동안 기록된 IP/UA/meta 가 소실된다. 스키마와 모델 선언을
     * 일치시키는 보정이므로 되돌릴 이유도 없다.
     */
    public function down(): void
    {
        // 의도적으로 비워 둔다.
    }

    private function repairPostsTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (! $this->isMySql()) {
            return;
        }

        $column = collect(DB::select("SHOW COLUMNS FROM `{$table}` WHERE Field = 'status'"))->first();

        if (! $column) {
            return;
        }

        $type = (string) ($column->Type ?? '');

        // 이미 pending 이 있으면 건너뛴다 (멱등).
        if (str_contains($type, "'pending'")) {
            return;
        }

        // 실제 컬럼에 있는 값들을 보존하면서 pending 만 덧붙인다.
        // 프로젝트가 임의로 enum 값을 늘려 놓았을 수 있어 하드코딩하지 않는다.
        preg_match_all("/'([^']+)'/", $type, $matches);
        $existing = $matches[1] ?? [];
        $values = array_values(array_unique(array_merge($existing ?: self::POST_STATUSES, ['pending'])));

        $default = $column->Default ?? 'published';
        $null = ($column->Null ?? 'NO') === 'YES' ? 'NULL' : 'NOT NULL';

        $list = collect($values)->map(fn ($v) => "'".addslashes($v)."'")->implode(',');

        DB::statement(
            "ALTER TABLE `{$table}` MODIFY COLUMN `status` ENUM({$list}) {$null} "
            ."DEFAULT '".addslashes((string) $default)."' COMMENT '게시 상태'"
        );

        Log::info("SiteManager: {$table}.status enum 에 'pending' 추가", ['values' => $values]);
    }

    private function repairCommentsTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $missing = [];

        // hasColumn 은 드라이버 무관하게 동작하므로 컬럼 추가는 모든 환경에서 수행한다.
        if (! Schema::hasColumn($table, 'ip_address')) {
            $missing[] = 'ip_address';
        }
        if (! Schema::hasColumn($table, 'user_agent')) {
            $missing[] = 'user_agent';
        }
        if (! Schema::hasColumn($table, 'meta')) {
            $missing[] = 'meta';
        }

        if (empty($missing)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($missing) {
            if (in_array('ip_address', $missing, true)) {
                $t->string('ip_address', 45)->nullable()->comment('작성자 IP (스팸 추적용)');
            }
            if (in_array('user_agent', $missing, true)) {
                $t->string('user_agent', 500)->nullable()->comment('작성자 User-Agent (스팸 추적용)');
            }
            if (in_array('meta', $missing, true)) {
                $t->json('meta')->nullable()->comment('댓글 추가 메타데이터');
            }
        });

        Log::info("SiteManager: {$table} 누락 컬럼 추가", ['columns' => $missing]);
    }

    private function isMySql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
