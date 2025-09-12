<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RestoreLanguageCommand extends Command
{
    protected $signature = 'sitemanager:restore-languages 
                            {--force : Force the operation without confirmation}';
    
    protected $description = 'Restore language data from SQL dump file';

    public function handle()
    {
        $this->info('🌍 Restoring SiteManager language data...');
        $this->newLine();
        
        $sqlPath = dirname(__DIR__, 3) . '/database/sql/languages.sql';
        
        if (!file_exists($sqlPath)) {
            $this->error('❌ Language SQL file not found: ' . $sqlPath);
            return 1;
        }
        
        // 현재 언어 데이터 개수 확인
        $currentCount = DB::table('languages')->count();
        
        if ($currentCount > 0 && !$this->option('force')) {
            if (!$this->confirm("🗂️  Found {$currentCount} existing language records. Replace them?", true)) {
                $this->info('❌ Language data restoration cancelled.');
                return 0;
            }
        }
        
        try {
            $sql = file_get_contents($sqlPath);
            
            if (empty($sql)) {
                $this->error('❌ Language SQL file is empty');
                return 1;
            }
            
            $this->info('🔄 Executing SQL dump...');
            
            // mysqldump 형식의 SQL 파일을 처리하기 위해 mysql 명령어 사용
            $dbConfig = config('database.connections.' . config('database.default'));
            $command = sprintf(
                'mysql -h%s -P%s -u%s -p%s %s < %s',
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['username'],
                $dbConfig['password'],
                $dbConfig['database'],
                $sqlPath
            );
            
            // 보안상 패스워드가 노출되지 않도록 처리
            $result = null;
            $output = [];
            exec($command . ' 2>&1', $output, $result);
            
            if ($result !== 0) {
                // mysql 명령어 실행 실패시 DB::unprepared로 시도
                $this->warn('⚠️  MySQL command failed, trying with Laravel DB...');
                
                // 여러 줄의 SQL을 분리하여 실행
                $statements = $this->parseSqlStatements($sql);
                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        DB::unprepared($statement);
                    }
                }
            }
            
            // 언어 키 개수 확인
            $languageCount = DB::table('languages')->count();
            
            $this->newLine();
            $this->info('✅ Language data restored successfully!');
            $this->line('📊 Total language keys: ' . $languageCount);
            $this->newLine();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to restore language data: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * SQL 파일을 개별 명령문으로 파싱합니다.
     */
    private function parseSqlStatements($sql): array
    {
        // 주석 제거
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // 빈 줄 제거
        $sql = preg_replace('/^\s*$/m', '', $sql);
        
        // 세미콜론으로 명령문 분리
        $statements = explode(';', $sql);
        
        return array_filter($statements, function($statement) {
            return !empty(trim($statement));
        });
    }
}
