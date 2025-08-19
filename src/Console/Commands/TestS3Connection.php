<?php

namespace SiteManager\Console\Commands;

use App\Services\FileUploadService;
use Illuminate\Console\Command;

class TestS3Connection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:test-s3 {--show-config : Show current S3 configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test S3 connection and show configuration status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('S3 Storage Connection Test');
        $this->newLine();

        if ($this->option('show-config')) {
            $this->showConfiguration();
            $this->newLine();
        }

        $service = new FileUploadService();
        
        // Current status
        $this->info('Current Status:');
        $this->line("Using S3: " . ($service->isUsingS3() ? '<fg=green>YES</>' : '<fg=yellow>NO</>'));
        $this->line("Current Disk: " . $service->getDisk());
        $this->newLine();

        // Test connection if S3 is configured
        if ($service->isUsingS3()) {
            $this->info('Testing S3 Connection...');
            $result = $service->testS3Connection();
            
            if ($result['success']) {
                $this->line('<fg=green>✓</> ' . $result['message']);
                $this->line("Bucket: {$result['bucket']}");
                $this->line("Region: {$result['region']}");
            } else {
                $this->line('<fg=red>✗</> ' . $result['message']);
                if (isset($result['error'])) {
                    $this->line("Error: {$result['error']}");
                }
                if (isset($result['details'])) {
                    $this->line("Details: {$result['details']}");
                }
            }
        } else {
            $this->line('<fg=yellow>⚠</> S3 is not configured. Using local storage.');
            $this->newLine();
            $this->info('To enable S3, add these to your .env file:');
            $this->line('AWS_ACCESS_KEY_ID=your_access_key');
            $this->line('AWS_SECRET_ACCESS_KEY=your_secret_key');
            $this->line('AWS_DEFAULT_REGION=your_region');
            $this->line('AWS_BUCKET=your_bucket_name');
        }

        return 0;
    }

    private function showConfiguration()
    {
        $this->info('Current Configuration:');
        
        $configs = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
            'AWS_ENDPOINT' => config('filesystems.disks.s3.endpoint'),
        ];

        foreach ($configs as $key => $value) {
            if ($key === 'AWS_SECRET_ACCESS_KEY' && !empty($value)) {
                $displayValue = str_repeat('*', strlen($value));
            } else {
                $displayValue = $value ?: '<fg=red>NOT SET</>';
            }
            
            $status = !empty($value) ? '<fg=green>SET</>' : '<fg=red>MISSING</>';
            $this->line("{$key}: {$displayValue} ({$status})");
        }
    }
}
