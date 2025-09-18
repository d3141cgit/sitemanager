<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupCommand extends Command
{
    protected $signature = 'sitemanager:cleanup 
                            {--force : Force cleanup without confirmation}';
    
    protected $description = 'Clean up published SiteManager migration files';

    public function handle()
    {
        $this->info('🧹 SiteManager Cleanup Tool');
        $this->newLine();
        
        $this->cleanupMigrationFiles();
        
        $this->newLine();
        $this->info('✅ Cleanup completed!');
        
        return 0;
    }

    /**
     * 발행된 SiteManager 마이그레이션 파일들을 정리합니다.
     */
    protected function cleanupMigrationFiles(): void
    {
        $migrationPath = database_path('migrations');
        
        if (!is_dir($migrationPath)) {
            $this->line('📁 No migration folder found.');
            return;
        }
        
        // SiteManager 마이그레이션 파일들 식별
        $siteManagerMigrations = array_merge(
            glob($migrationPath . '/2025_08_*_create_*_table.php'),
            glob($migrationPath . '/2025_09_*_create_*_table.php')
        );
        
        if (empty($siteManagerMigrations)) {
            $this->line('✅ No SiteManager migration files found to clean up.');
            return;
        }
        
        $this->line('📋 Found ' . count($siteManagerMigrations) . ' SiteManager migration files:');
        foreach ($siteManagerMigrations as $file) {
            $this->line('   • ' . basename($file));
        }
        $this->newLine();
        
        if ($this->option('force') || $this->confirm('🗑️  Remove these migration files?', true)) {
            $deletedCount = 0;
            
            foreach ($siteManagerMigrations as $file) {
                if (File::delete($file)) {
                    $deletedCount++;
                    $this->line('   ✅ Deleted: ' . basename($file));
                } else {
                    $this->line('   ❌ Failed to delete: ' . basename($file));
                }
            }
            
            $this->newLine();
            $this->info("🎉 Successfully deleted {$deletedCount} migration files");
            $this->line('💡 Database tables remain intact - only temporary files removed');
            
            // migrations 폴더가 비어있다면 초기화
            $remainingFiles = File::files($migrationPath);
            if (empty($remainingFiles)) {
                File::deleteDirectory($migrationPath);
                File::makeDirectory($migrationPath, 0755, true);
                $this->line('🔄 Recreated empty migrations folder');
            }
        } else {
            $this->line('❌ Cleanup cancelled.');
        }
    }
}