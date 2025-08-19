<?php

namespace SiteManager\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FileUploadService;
use SiteManager\Models\Member;
use SiteManager\Models\Menu;
use Illuminate\Support\Facades\DB;

class MigrateImagesToS3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:migrate-to-s3 
                            {--dry-run : Run without making actual changes}
                            {--force : Force migration even if S3 is not configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all images from local storage to S3';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // S3 설정 확인
        if (!FileUploadService::isS3Configured() && !$force) {
            $this->error('S3 is not configured. Please check your AWS settings in .env file.');
            $this->info('Required settings:');
            $this->info('- AWS_ACCESS_KEY_ID');
            $this->info('- AWS_SECRET_ACCESS_KEY');
            $this->info('- AWS_DEFAULT_REGION');
            $this->info('- AWS_BUCKET');
            $this->newLine();
            $this->info('Use --force to run anyway (for testing purposes).');
            return 1;
        }

        if ($dryRun) {
            $this->info('DRY RUN MODE - No actual changes will be made');
            $this->newLine();
        }

        $this->info('Starting image migration to S3...');
        $this->newLine();

        // 멤버 프로필 사진 마이그레이션
        $this->migrateMemberPhotos($dryRun);

        // 메뉴 이미지 마이그레이션
        $this->migrateMenuImages($dryRun);

        $this->newLine();
        $this->info('Image migration completed!');

        return 0;
    }

    /**
     * 멤버 프로필 사진 마이그레이션
     */
    private function migrateMemberPhotos(bool $dryRun)
    {
        $this->info('Migrating member profile photos...');

        $members = Member::whereNotNull('profile_photo')->get();
        $bar = $this->output->createProgressBar($members->count());

        foreach ($members as $member) {
            if ($dryRun) {
                $this->comment("Would migrate: {$member->profile_photo}");
            } else {
                try {
                    $migrated = FileUploadService::migrate('public', 's3', $member->profile_photo);
                    if ($migrated) {
                        $this->comment("Migrated: {$member->profile_photo}");
                    } else {
                        $this->error("Failed to migrate: {$member->profile_photo}");
                    }
                } catch (\Exception $e) {
                    $this->error("Error migrating {$member->profile_photo}: " . $e->getMessage());
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * 메뉴 이미지 마이그레이션
     */
    private function migrateMenuImages(bool $dryRun)
    {
        $this->info('Migrating menu images...');

        $menus = Menu::whereNotNull('images')->get();
        $bar = $this->output->createProgressBar($menus->count());

        foreach ($menus as $menu) {
            if (!is_array($menu->images)) {
                $bar->advance();
                continue;
            }

            $needsUpdate = false;
            $updatedImages = $menu->images;

            foreach ($menu->images as $category => $imageData) {
                if (is_array($imageData) && isset($imageData['url'])) {
                    $path = $imageData['url'];
                } elseif (is_string($imageData)) {
                    $path = $imageData;
                } else {
                    continue;
                }

                if ($dryRun) {
                    $this->comment("Would migrate menu image: {$path}");
                } else {
                    try {
                        $migrated = FileUploadService::migrate('public', 's3', $path);
                        if ($migrated) {
                            // 새로운 구조로 업데이트
                            $updatedImages[$category] = [
                                'url' => $migrated,
                                'uploaded_at' => is_array($imageData) && isset($imageData['uploaded_at']) 
                                    ? $imageData['uploaded_at'] 
                                    : now()->toISOString(),
                            ];
                            $needsUpdate = true;
                            $this->comment("Migrated menu image: {$path}");
                        } else {
                            $this->error("Failed to migrate menu image: {$path}");
                        }
                    } catch (\Exception $e) {
                        $this->error("Error migrating menu image {$path}: " . $e->getMessage());
                    }
                }
            }

            if ($needsUpdate && !$dryRun) {
                $menu->update(['images' => $updatedImages]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }
}
