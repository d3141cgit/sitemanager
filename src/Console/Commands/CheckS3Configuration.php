<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;

class CheckS3Configuration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:check-s3';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check S3 configuration and test connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking S3 Configuration...');
        $this->newLine();

        // 설정 확인
        $configs = [
            'AWS_ACCESS_KEY_ID' => config('filesystems.disks.s3.key'),
            'AWS_SECRET_ACCESS_KEY' => config('filesystems.disks.s3.secret'),
            'AWS_DEFAULT_REGION' => config('filesystems.disks.s3.region'),
            'AWS_BUCKET' => config('filesystems.disks.s3.bucket'),
        ];

        $allConfigured = true;
        foreach ($configs as $key => $value) {
            $status = !empty($value) ? '✓' : '✗';
            $color = !empty($value) ? 'green' : 'red';
            $this->line("<fg={$color}>{$status}</> {$key}: " . ($value ? '***' : 'Not set'));
            
            if (empty($value)) {
                $allConfigured = false;
            }
        }

        $this->newLine();

        if (!$allConfigured) {
            $this->error('Some S3 configurations are missing. Please check your .env file.');
            return 1;
        }

        // S3 연결 테스트
        $this->info('Testing S3 connectivity...');
        
        try {
            // 간단한 연결 테스트
            $disk = Storage::disk('s3');
            $testFile = 'test-connection-' . time() . '.txt';
            $testContent = 'S3 connection test from Laravel';
            
            // 파일 업로드 테스트
            $disk->put($testFile, $testContent);
            $this->info('✓ File upload successful');
            
            // 파일 읽기 테스트
            $retrieved = $disk->get($testFile);
            if ($retrieved === $testContent) {
                $this->info('✓ File retrieval successful');
            } else {
                $this->error('✗ File retrieval failed - content mismatch');
            }
            
            // 파일 삭제 테스트
            $disk->delete($testFile);
            $this->info('✓ File deletion successful');
            
            $this->newLine();
            $this->info('<fg=green>S3 configuration is working correctly!</>');
            
            // FileUploadService 상태 확인
            if (FileUploadService::isS3Configured()) {
                $this->info('✓ FileUploadService is configured to use S3');
            } else {
                $this->warning('⚠ FileUploadService reports S3 as not configured');
            }
            
        } catch (\Exception $e) {
            $this->error('✗ S3 connection failed: ' . $e->getMessage());
            $this->newLine();
            $this->info('Common issues:');
            $this->info('- Check your AWS credentials');
            $this->info('- Verify bucket name and region');
            $this->info('- Ensure bucket permissions allow read/write operations');
            $this->info('- Check network connectivity');
            return 1;
        }

        return 0;
    }
}
