# FileUploadService 가이드

## 개요

SiteManager의 `FileUploadService`는 **통합 파일 관리 솔루션**입니다. 로컬 스토리지와 Amazon S3를 자동으로 감지하여 사용하며, 업로드/다운로드/프리뷰 등 모든 파일 작업을 통일된 인터페이스로 제공합니다.

## 핵심 특징

- **자동 스토리지 감지**: `.env` 설정에 따라 로컬 또는 S3 자동 선택
- **통일된 API**: 스토리지 방식에 관계없이 동일한 메서드 사용
- **자동 최적화**: S3 사용 시 캐시 헤더 및 메타데이터 자동 설정
- **보안**: 파일 타입 검증, 크기 제한, 경로 보안
- **프리뷰 지원**: 이미지/PDF 등 파일 타입별 프리뷰 URL 생성

## 환경 설정

### 로컬 스토리지 (기본값)

```env
# S3 설정이 없으면 자동으로 로컬 스토리지 사용
FILESYSTEM_DISK=local
```

### Amazon S3 설정

```env
# S3 설정이 있으면 자동으로 S3 사용
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-northeast-2
AWS_BUCKET=your-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false

# URL 스타일 설정 (선택사항)
AWS_URL_STYLE=virtual-hosted  # virtual-hosted (기본값) 또는 path-style

# 선택사항: CDN URL 설정
AWS_CDN_URL=https://cdn.yourdomain.com
```

#### URL 스타일 옵션

**S3 버킷의 URL 스타일 확인 방법:**
1. S3 콘솔에서 파일 업로드 후 생성되는 URL 확인
2. 또는 버킷 설정 > 속성 > 정적 웹사이트 호스팅에서 엔드포인트 확인

**Virtual-Hosted Style**
```env
AWS_URL_STYLE=virtual-hosted
```
- URL 형태: `https://bucket-name.s3.region.amazonaws.com/object-key`
- **사용 시기**: 2020년 9월 이후 생성된 대부분의 버킷

**Path Style**
```env
AWS_URL_STYLE=path-style
```
- URL 형태: `https://s3.region.amazonaws.com/bucket-name/object-key`
- **사용 시기**: 2020년 9월 이전 생성된 버킷 또는 특정 설정

**설정 방법:**
1. 실제 S3에서 파일 업로드 후 반환되는 URL 형태 확인
2. 해당 형태에 맞게 `AWS_URL_STYLE` 설정
3. 잘못 설정하면 파일 접근이 불가능함

**중요:** 이 설정은 개발자가 임의로 선택하는 것이 아니라, **실제 S3 버킷이 지원하는 URL 형태에 맞춰 설정**해야 합니다.

**자동 감지 로직:**
- S3 설정이 완전하면 → S3 사용
- S3 설정이 불완전하면 → 로컬 스토리지 사용

## 기본 사용법

### 1. 의존성 주입

```php
use SiteManager\Services\FileUploadService;

class MyController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
}
```

### 2. 파일 업로드

```php
public function store(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:jpg,png,pdf|max:2048'
    ]);

    try {
        $result = $this->fileUploadService->upload(
            $request->file('file'),           // UploadedFile 객체
            'documents/2024',                 // 저장 경로
            [                                 // 옵션 (선택사항)
                'filename' => 'custom-name',  // 커스텀 파일명
                'public' => true,             // 공개 파일 여부
                'optimize' => true            // 이미지 최적화 여부
            ]
        );

        // 성공 시 결과
        // $result = [
        //     'path' => 'documents/2024/filename.jpg',
        //     'url' => 'https://domain.com/storage/documents/2024/filename.jpg',
        //     'size' => 1024000,
        //     'mime_type' => 'image/jpeg',
        //     'original_name' => 'original.jpg'
        // ]

    } catch (\Exception $e) {
        // 에러 처리
        return back()->with('error', '파일 업로드 실패: ' . $e->getMessage());
    }
}
```

### 3. 파일 다운로드

```php
public function download($filePath)
{
    try {
        return $this->fileUploadService->download(
            $filePath,                        // 파일 경로
            'custom-filename.pdf',            // 다운로드 파일명 (선택사항)
            [                                 // 헤더 옵션
                'Content-Type' => 'application/pdf'
            ]
        );
    } catch (\Exception $e) {
        abort(404, '파일을 찾을 수 없습니다.');
    }
}
```

### 4. 파일 URL 생성

```php
// 프리뷰/표시용 URL
$previewUrl = $this->fileUploadService->getUrl($filePath);

// 다운로드용 URL (서명된 URL)
$downloadUrl = $this->fileUploadService->getDownloadUrl($filePath, '+1 hour');
```

### 5. 파일 삭제

```php
public function destroy($filePath)
{
    try {
        $this->fileUploadService->delete($filePath);
        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json(['error' => '삭제 실패'], 500);
    }
}
```

## 모듈별 구현 패턴

### 1. 설교(Sermon) 모듈 예시

```php
class SermonController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'preacher_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'sermon_notes' => 'nullable|file|mimes:pdf|max:10240',
            'bulletin_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        // 파일 업로드 처리
        $this->handleFileUploads($validated, $request);

        $sermon = Sermon::create($validated);
        
        return redirect()->route('sermons.show', $sermon->slug)
            ->with('success', '설교가 등록되었습니다.');
    }

    private function handleFileUploads(array &$validated, Request $request): void
    {
        $sermonDate = $validated['sermon_date'] ?? now();
        $basePath = 'sermons/' . Carbon::parse($sermonDate)->format('Y/m');

        // 설교자 사진 업로드
        if ($request->hasFile('preacher_photo')) {
            $result = $this->fileUploadService->upload(
                $request->file('preacher_photo'),
                $basePath,
                ['public' => true, 'optimize' => true]
            );
            $validated['preacher_photo'] = $result['path'];
        }

        // 설교노트 업로드
        if ($request->hasFile('sermon_notes')) {
            $result = $this->fileUploadService->upload(
                $request->file('sermon_notes'),
                $basePath,
                ['public' => false] // 비공개 파일
            );
            $validated['sermon_notes'] = $result['path'];
        }

        // 주보 이미지 업로드
        if ($request->hasFile('bulletin_image')) {
            $result = $this->fileUploadService->upload(
                $request->file('bulletin_image'),
                $basePath,
                ['public' => true, 'optimize' => true]
            );
            $validated['bulletin_image'] = $result['path'];
        }
    }

    public function downloadFile($slug, $type)
    {
        $sermon = Sermon::where('slug', $slug)->firstOrFail();
        
        $filePath = match($type) {
            'sermon_notes' => $sermon->sermon_notes,
            'sermon_notes_en' => $sermon->sermon_notes_en,
            'bulletin_image' => $sermon->bulletin_image,
            'preacher_photo' => $sermon->preacher_photo,
            default => null
        };

        if (!$filePath) {
            abort(404, '파일을 찾을 수 없습니다.');
        }

        $filename = match($type) {
            'sermon_notes' => $sermon->title . '_설교노트.pdf',
            'sermon_notes_en' => $sermon->title . '_SermonNotes.pdf',
            'bulletin_image' => $sermon->title . '_주보.jpg',
            'preacher_photo' => $sermon->preacher_name . '_사진.jpg',
        };

        return $this->fileUploadService->download($filePath, $filename);
    }
}
```

### 2. 음악(Music) 모듈 예시

```php
class MusicController extends Controller
{
    private function handleFileUploads(array &$validated, Request $request, ?Song $song = null): void
    {
        $basePath = 'music/' . date('Y/m');

        // 기존 파일 삭제 (수정 시)
        if ($song && $request->hasFile('song_file') && $song->file_path) {
            $this->fileUploadService->delete($song->file_path);
        }

        // 음악 파일 업로드
        if ($request->hasFile('song_file')) {
            $result = $this->fileUploadService->upload(
                $request->file('song_file'),
                $basePath,
                [
                    'public' => false,  // 음악 파일은 보통 비공개
                    'filename' => Str::slug($validated['title']) . '-' . time()
                ]
            );
            $validated['file_path'] = $result['path'];
            $validated['file_size'] = $result['size'];
        }
    }

    public function download(Song $song)
    {
        if (!$song->file_path) {
            abort(404, '음악 파일이 없습니다.');
        }

        $filename = $song->title . ' - ' . $song->artist . '.mp3';
        
        return $this->fileUploadService->download(
            $song->file_path, 
            $filename
        );
    }
}
```

### 3. 게시판(Board) 첨부파일 예시

```php
class BoardController extends Controller
{
    private function handleAttachments(Request $request, BoardPost $post): void
    {
        $basePath = 'boards/' . $post->board_slug . '/' . date('Y/m');

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $index => $file) {
                $description = $request->input('file_descriptions.' . $index);
                $customName = $request->input('file_names.' . $index);

                $result = $this->fileUploadService->upload(
                    $file,
                    $basePath,
                    [
                        'filename' => $customName,
                        'public' => true
                    ]
                );

                // 첨부파일 레코드 생성
                $post->attachments()->create([
                    'original_name' => $result['original_name'],
                    'stored_name' => basename($result['path']),
                    'file_path' => $result['path'],
                    'file_size' => $result['size'],
                    'mime_type' => $result['mime_type'],
                    'description' => $description,
                ]);
            }
        }
    }
}
```

## 고급 사용법

### 1. 배치 업로드

```php
public function uploadMultiple(Request $request)
{
    $files = $request->file('files');
    $results = [];

    foreach ($files as $file) {
        try {
            $result = $this->fileUploadService->upload(
                $file,
                'batch/' . date('Y-m-d'),
                ['public' => true]
            );
            $results[] = $result;
        } catch (\Exception $e) {
            // 개별 파일 실패 처리
            $results[] = ['error' => $e->getMessage()];
        }
    }

    return response()->json($results);
}
```

### 2. 이미지 최적화 옵션

```php
$result = $this->fileUploadService->upload(
    $file,
    'images/gallery',
    [
        'optimize' => true,           // 이미지 최적화 활성화
        'max_width' => 1920,         // 최대 너비
        'max_height' => 1080,        // 최대 높이
        'quality' => 85,             // JPEG 품질 (1-100)
        'format' => 'webp'           // 변환 포맷 (webp, jpg, png)
    ]
);
```

### 3. 임시 파일 처리

```php
// 임시 업로드 (1시간 후 자동 삭제)
$result = $this->fileUploadService->uploadTemporary(
    $file,
    'temp',
    3600  // TTL in seconds
);

// 임시 파일을 영구 파일로 이동
$permanentResult = $this->fileUploadService->makePermament(
    $result['path'],
    'permanent/documents'
);
```

### 4. 스토리지 정보 확인

```php
// 현재 사용 중인 스토리지 확인
$storageInfo = $this->fileUploadService->getStorageInfo();
// ['driver' => 's3', 'bucket' => 'my-bucket', 'region' => 'ap-northeast-2']

// 파일 존재 여부 확인
$exists = $this->fileUploadService->exists($filePath);

// 파일 정보 조회
$fileInfo = $this->fileUploadService->getFileInfo($filePath);
// ['size' => 1024, 'mime_type' => 'image/jpeg', 'last_modified' => '2024-01-01']
```

## 에러 처리

### 일반적인 예외

```php
try {
    $result = $this->fileUploadService->upload($file, $path);
} catch (\SiteManager\Exceptions\FileUploadException $e) {
    // 파일 업로드 관련 에러
    return back()->with('error', '업로드 실패: ' . $e->getMessage());
} catch (\SiteManager\Exceptions\InvalidFileTypeException $e) {
    // 허용되지 않은 파일 타입
    return back()->with('error', '지원하지 않는 파일 형식입니다.');
} catch (\SiteManager\Exceptions\FileSizeExceededException $e) {
    // 파일 크기 초과
    return back()->with('error', '파일 크기가 너무 큽니다.');
} catch (\Exception $e) {
    // 기타 에러
    Log::error('File upload error: ' . $e->getMessage());
    return back()->with('error', '파일 처리 중 오류가 발생했습니다.');
}
```

### 검증 규칙

```php
$request->validate([
    'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',          // 이미지 (2MB)
    'document' => 'nullable|file|mimes:pdf,doc,docx|max:10240',           // 문서 (10MB)
    'audio' => 'nullable|file|mimes:mp3,wav,m4a|max:51200',               // 오디오 (50MB)
    'video' => 'nullable|file|mimes:mp4,mov,avi|max:102400',              // 비디오 (100MB)
]);
```

## 보안 고려사항

### 1. 파일 타입 검증

```php
// MIME 타입과 확장자 이중 검증
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

// FileUploadService가 자동으로 검증하지만 Controller에서도 추가 검증 권장
```

### 2. 경로 보안

```php
// 안전한 경로 생성
$safePath = $this->fileUploadService->sanitizePath($userInput);

// 디렉토리 순회 공격 방지
// FileUploadService가 자동으로 처리하지만 사용자 입력 경로는 주의
```

### 3. 접근 권한

```php
// 비공개 파일 다운로드 시 권한 확인
public function downloadPrivateFile($fileId)
{
    $file = PrivateFile::findOrFail($fileId);
    
    // 권한 확인
    if (!auth()->user()->can('download', $file)) {
        abort(403, '다운로드 권한이 없습니다.');
    }
    
    return $this->fileUploadService->download($file->path);
}
```

## 성능 최적화

### 1. S3 설정 최적화

```php
// .env에서 S3 최적화 설정
AWS_BUCKET=your-bucket
AWS_REGION=ap-northeast-2        # 가까운 리전 선택
AWS_URL_STYLE=virtual-hosted     # 실제 버킷이 지원하는 스타일로 설정
AWS_CDN_URL=https://cdn.domain.com   # CloudFront CDN 사용
```

**URL 스타일 설정 주의사항:**
- 버킷 생성 시기에 따라 지원하는 URL 스타일이 다름
- edmkorean: `path-style`, hanurichurch: `virtual-hosted`
- 잘못 설정하면 파일 접근 불가

### 2. 캐시 헤더 활용

```php
// FileUploadService가 자동으로 설정하는 캐시 헤더
// - 이미지: Cache-Control: public, max-age=31536000
// - 문서: Cache-Control: private, max-age=3600
// - 임시파일: Cache-Control: no-cache
```

### 3. 비동기 처리

```php
use Illuminate\Support\Facades\Queue;

// 대용량 파일 처리를 큐로 위임
Queue::push(new ProcessLargeFileJob($filePath));
```

## 마이그레이션 가이드

### 기존 코드에서 FileUploadService로 전환

#### Before (직접 스토리지 사용)
```php
// 기존 방식
$path = $request->file('image')->store('images', 's3');
$url = Storage::disk('s3')->url($path);
```

#### After (FileUploadService 사용)
```php
// 새로운 방식
$result = $this->fileUploadService->upload(
    $request->file('image'),
    'images'
);
$path = $result['path'];
$url = $result['url'];
```

### 장점
- ✅ 로컬/S3 자동 감지
- ✅ 통일된 인터페이스
- ✅ 자동 최적화
- ✅ 에러 처리 개선
- ✅ 보안 강화

---

## 요약

FileUploadService는 SiteManager의 **핵심 파일 관리 서비스**입니다:

1. **자동 설정**: `.env` 기반 로컬/S3 자동 선택
2. **통일된 API**: 스토리지 방식에 관계없이 동일한 사용법
3. **최적화**: 자동 캐시 헤더, 이미지 최적화
4. **보안**: 파일 타입 검증, 경로 보안
5. **확장성**: 모든 모듈에서 동일한 패턴으로 사용

새로운 모듈 개발 시 이 가이드를 참고하여 FileUploadService를 활용하면 일관되고 안전한 파일 관리가 가능합니다.