<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupLanguageCommand extends Command
{
    protected $signature = 'sitemanager:backup-languages';

    protected $description = 'Backup language data to SQL dump file';

    public function handle()
    {
        $this->info('🌍 Backing up SiteManager language data...');
        $this->newLine();

        $sqlPath = dirname(__DIR__, 3) . '/database/sql/languages.sql';

        try {
            $languages = DB::table('languages')->orderBy('id')->get();

            if ($languages->isEmpty()) {
                $this->error('❌ No language data found to backup');
                return 1;
            }

            $this->info('🔄 Generating SQL dump...');

            $sql = "-- Languages backup\n";
            $sql .= "-- Generated: " . now()->format('Y-m-d H:i:s') . "\n";
            $sql .= "-- Total rows: " . $languages->count() . "\n\n";
            $sql .= "TRUNCATE TABLE languages;\n\n";

            foreach ($languages as $row) {
                $id = $row->id;
                $key = addslashes($row->key);
                $ko = addslashes($row->ko ?? '');
                $tw = addslashes($row->tw ?? '');
                $location = addslashes($row->location ?? '');

                $sql .= "INSERT INTO languages (id, `key`, ko, tw, location) VALUES ({$id}, '{$key}', '{$ko}', '{$tw}', '{$location}');\n";
            }

            // 디렉토리가 없으면 생성
            $dir = dirname($sqlPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($sqlPath, $sql);

            $this->newLine();
            $this->info('✅ Language data backed up successfully!');
            $this->line('📊 Total language keys: ' . $languages->count());
            $this->line('📁 File: ' . $sqlPath);
            $this->newLine();

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Failed to backup language data: ' . $e->getMessage());
            return 1;
        }
    }
}
