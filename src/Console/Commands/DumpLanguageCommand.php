<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use SiteManager\Models\Language;
use Illuminate\Support\Facades\File;

class DumpLanguageCommand extends Command
{
    protected $signature = 'sitemanager:dump-languages 
                            {--output= : Output file path (default: packages/sitemanager/database/sql/languages.sql)}';
    
    protected $description = 'Dump current language data to SQL file';

    public function handle()
    {
        $this->info('🌍 Dumping SiteManager language data...');
        $this->newLine();
        
        $languages = Language::all();
        
        if ($languages->isEmpty()) {
            $this->warn('⚠️  No language data found to dump.');
            return 0;
        }
        
        $outputPath = $this->option('output') ?: dirname(__DIR__, 3) . '/database/sql/languages.sql';
        
        $this->info('📊 Found ' . $languages->count() . ' language records');
        $this->info('💾 Output file: ' . $outputPath);
        $this->newLine();
        
        try {
            $sql = $this->generateSqlDump($languages);
            
            // 디렉토리 확인 및 생성
            File::ensureDirectoryExists(dirname($outputPath));
            
            // SQL 파일 저장
            File::put($outputPath, $sql);
            
            $this->info('✅ Language data dumped successfully!');
            $this->line('📁 File saved: ' . $outputPath);
            $this->line('📊 Total records: ' . $languages->count());
            $this->newLine();
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to dump language data: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * 언어 데이터를 SQL 덤프로 생성합니다.
     */
    protected function generateSqlDump($languages): string
    {
        $sql = '';
        
        // SQL 파일 헤더
        $sql .= '-- Language data dump for SiteManager' . PHP_EOL;
        $sql .= '-- Generated on: ' . date('Y-m-d H:i:s') . PHP_EOL;
        $sql .= '-- Total records: ' . $languages->count() . PHP_EOL . PHP_EOL;
        
        $sql .= 'DELETE FROM languages;' . PHP_EOL . PHP_EOL;
        
        foreach ($languages as $language) {
            $key = addslashes($language->key);
            $ko = $language->ko ? "'" . addslashes($language->ko) . "'" : 'NULL';
            $tw = $language->tw ? "'" . addslashes($language->tw) . "'" : 'NULL';
            $location = $language->location ? "'" . addslashes($language->location) . "'" : 'NULL';
            
            $sql .= "INSERT INTO languages (id, \`key\`, ko, tw, location) VALUES ({$language->id}, '{$key}', {$ko}, {$tw}, {$location});" . PHP_EOL;
        }
        
        return $sql;
    }
}
