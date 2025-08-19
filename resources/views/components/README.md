# File Upload Component

재사용 가능한 드래그 앤 드롭 파일 업로드 컴포넌트입니다.

## 파일 구성

- `resources/css/file-upload.css` - 파일 업로드 컴포넌트 스타일
- `resources/js/file-upload.js` - 파일 업로드 JavaScript 클래스
- `resources/views/components/file-upload.blade.php` - Blade 컴포넌트

## 사용법

### 1. 기본 사용법

```blade
<x-file-upload 
    name="files[]"
    id="files"
    :multiple="true"
    label="파일 첨부"
/>
```

### 2. 고급 설정

```blade
<x-file-upload 
    name="documents[]"
    id="document-files"
    :multiple="true"
    :max-file-size="5120"
    :max-files="5"
    :allowed-types="['pdf', 'doc', 'docx']"
    :enable-preview="false"
    :enable-edit="true"
    :show-file-info="true"
    :show-guidelines="true"
    :required="true"
    label="문서 첨부"
    icon="bi-file-earmark"
    :errors="$errors"
/>
```

### 3. 기존 첨부파일이 있는 편집 모드

```blade
<x-file-upload 
    name="files[]"
    id="files"
    :multiple="true"
    :existing-attachments="$post->attachments"
    :enable-edit="true"
    label="첨부파일"
    :errors="$errors"
/>
```

## 컴포넌트 옵션

| 속성 | 타입 | 기본값 | 설명 |
|------|------|--------|------|
| `name` | string | 'files[]' | input 필드의 name 속성 |
| `id` | string | 'files' | input 필드의 id 속성 |
| `multiple` | boolean | true | 다중 파일 선택 허용 |
| `maxFileSize` | integer | 10240 | 최대 파일 크기 (KB) |
| `maxFiles` | integer | 10 | 최대 파일 개수 |
| `allowedTypes` | array | ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'] | 허용된 파일 확장자 |
| `enablePreview` | boolean | true | 이미지 미리보기 활성화 |
| `enableEdit` | boolean | true | 파일명/설명 편집 활성화 |
| `showFileInfo` | boolean | true | 파일 정보 표시 |
| `showGuidelines` | boolean | true | 업로드 가이드라인 표시 |
| `existingAttachments` | Collection | null | 기존 첨부파일 (편집 모드) |
| `label` | string | 'Attachments' | 필드 라벨 |
| `icon` | string | 'bi-paperclip' | 라벨 아이콘 |
| `required` | boolean | false | 필수 필드 여부 |
| `errors` | MessageBag | null | 유효성 검사 오류 |
| `class` | string | '' | 추가 CSS 클래스 |
| `wrapperClass` | string | 'mb-3' | 래퍼 CSS 클래스 |

## JavaScript API

### FileUploadComponent 클래스

```javascript
// 수동 초기화
const fileUpload = new FileUploadComponent({
    dropZoneSelector: '.file-drop-zone',
    fileInputSelector: '#files',
    fileListSelector: '.file-list',
    fileItemsSelector: '.file-items',
    maxFileSize: 10240, // KB
    maxFiles: 10,
    allowedTypes: ['jpg', 'jpeg', 'png', 'pdf'],
    enablePreview: true,
    enableEdit: true,
    showFileInfo: true
});

// 메서드
fileUpload.getFileCount();     // 선택된 파일 개수
fileUpload.clearFiles();       // 모든 파일 제거
fileUpload.getFiles();         // 선택된 파일 배열
fileUpload.removeFile(index);  // 특정 파일 제거
fileUpload.removeAttachment(id); // 기존 첨부파일 제거
```

### 이벤트 리스너

```javascript
// 파일 목록 업데이트 이벤트
document.addEventListener('fileUpload:fileListUpdated', function(e) {
    console.log('Files updated:', e.detail.files);
});

// 첨부파일 제거 이벤트
document.addEventListener('fileUpload:attachmentRemoved', function(e) {
    console.log('Attachment removed:', e.detail.attachmentId);
});
```

## 스타일 커스터마이징

CSS 변수를 사용하여 스타일을 커스터마이징할 수 있습니다:

```css
.file-upload-wrapper {
    --drop-zone-bg: #f8f9fa;
    --drop-zone-border: #dee2e6;
    --drop-zone-hover-bg: #e9ecef;
    --drop-zone-active-bg: #cff4fc;
    --drop-zone-active-border: #0dcaf0;
}
```

## 다른 모듈에서 사용하기

### 1. 뷰에서 직접 사용

```blade
@extends('layouts.app')

@section('content')
<form method="POST" action="/upload" enctype="multipart/form-data">
    @csrf
    
    <x-file-upload 
        name="attachments[]"
        id="attachments"
        :max-file-size="20480"
        :allowed-types="['jpg', 'png', 'pdf', 'zip']"
        label="프로젝트 파일"
        icon="bi-folder"
        :required="true"
        :errors="$errors"
    />
    
    <button type="submit" class="btn btn-primary">업로드</button>
</form>
@endsection
```

### 2. 컨트롤러에서 처리

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'attachments.*' => 'required|file|max:20480|mimes:jpg,png,pdf,zip'
        ]);
        
        foreach ($request->file('attachments') as $file) {
            $file->store('uploads');
        }
        
        return redirect()->back()->with('success', '파일이 업로드되었습니다.');
    }
}
```

### 3. 모델에서 첨부파일 관계 정의

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
```

## 요구사항

- Laravel 8+
- Bootstrap 5
- Bootstrap Icons
- 모던 브라우저 (드래그 앤 드롭 API 지원)

## 브라우저 지원

- Chrome 4+
- Firefox 3.6+
- Safari 6+
- Edge 12+
- IE 10+

## 라이선스

MIT License
