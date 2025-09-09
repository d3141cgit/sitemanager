# SiteManager 통합 알림 및 모달 시스템 사용 가이드

## 개요
SiteManager 시스템에서 일관된 사용자 경험을 위해 알림과 모달을 통합 관리하는 시스템입니다.
SweetAlert2를 기본으로 하며, fallback으로 기본 브라우저 알림을 사용합니다.

## 설치 및 설정

### 1. 리소스 로드
```blade
@push('head')
    {!! resource('sitemanager::js/notifications.js') !!}
@endpush
```

### 2. SweetAlert2 의존성 (권장)
프로젝트의 layout 파일에서 SweetAlert2가 로드되어야 합니다:
```blade
{!! setResources(['jquery', 'bootstrap', 'sweetalert']) !!}
```

## 알림 시스템 사용법

### 기본 알림

#### 성공 메시지
```javascript
SiteManager.notifications.success('Data saved successfully!');
SiteManager.notifications.success('Data saved successfully!', 'Custom Title');
```

#### 에러 메시지
```javascript
SiteManager.notifications.error('Something went wrong!');
SiteManager.notifications.error('Validation failed!', 'Error Details');
```

#### 경고 메시지
```javascript
SiteManager.notifications.warning('Please check your input!');
SiteManager.notifications.warning('Session will expire soon!', 'Warning');
```

#### 정보 메시지
```javascript
SiteManager.notifications.info('Welcome to the system!');
SiteManager.notifications.info('New feature available!', 'Information');
```

### 확인 대화상자

#### 기본 확인
```javascript
SiteManager.notifications.confirm('Are you sure?').then((confirmed) => {
    if (confirmed) {
        // 사용자가 확인을 눌렀을 때
        console.log('User confirmed');
    }
});
```

#### 커스텀 확인
```javascript
SiteManager.notifications.confirm(
    'This action cannot be undone. Continue?',
    'Confirm Action',
    {
        confirmButtonText: 'Yes, Continue',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745'
    }
).then((confirmed) => {
    if (confirmed) {
        // 실행할 코드
    }
});
```

#### 삭제 확인 (특화)
```javascript
SiteManager.notifications.confirmDelete('this post').then((confirmed) => {
    if (confirmed) {
        // 삭제 실행
    }
});

// 기본값 사용
SiteManager.notifications.confirmDelete().then((confirmed) => {
    if (confirmed) {
        // 삭제 실행
    }
});
```

### 로딩 표시

```javascript
// 로딩 시작
SiteManager.notifications.showLoading('Saving data...');

// 작업 수행
await someAsyncOperation();

// 로딩 종료
SiteManager.notifications.hideLoading();
```

### 토스트 알림 (우상단)

```javascript
// 기본 토스트
SiteManager.notifications.toast('Data saved!', 'success');

// 커스텀 지속시간
SiteManager.notifications.toast('Warning message!', 'warning', 3000);

// 다양한 타입
SiteManager.notifications.toast('Success!', 'success');
SiteManager.notifications.toast('Error!', 'error');
SiteManager.notifications.toast('Warning!', 'warning');
SiteManager.notifications.toast('Info!', 'info');
```

## 모달 시스템 사용법

### 이미지 미리보기 모달

```javascript
// 기본 사용
SiteManager.modals.showImagePreview('https://example.com/image.jpg');

// 전체 옵션
SiteManager.modals.showImagePreview(
    'https://example.com/image.jpg',
    'My Image',
    'https://example.com/download/image.jpg'
);
```

### 커스텀 모달

```javascript
// 기본 모달
SiteManager.modals.showCustomModal({
    title: 'Custom Modal',
    body: '<p>This is custom content</p>'
});

// 고급 옵션
const modal = SiteManager.modals.showCustomModal({
    id: 'my-custom-modal',
    title: 'Advanced Modal',
    body: `
        <form id="myForm">
            <div class="mb-3">
                <label>Name:</label>
                <input type="text" class="form-control" name="name">
            </div>
        </form>
    `,
    size: 'lg', // '', 'sm', 'lg', 'xl'
    footer: true,
    closeButton: true,
    backdrop: true
});

// 모달과 상호작용
modal.show();
modal.hide();
```

## 하위 호환성 함수들

기존 코드와의 호환성을 위해 제공되는 함수들:

```javascript
// 기존 showAlert 함수 대체
showAlert('Message', 'success');  // SiteManager.notifications.success 호출
showAlert('Message', 'error');    // SiteManager.notifications.error 호출
showAlert('Message', 'warning');  // SiteManager.notifications.warning 호출
showAlert('Message', 'info');     // SiteManager.notifications.info 호출

// 기존 showImageModal 함수 대체
showImageModal(imageUrl, imageName, downloadUrl);

// 기존 confirm 함수 대체
confirmDelete('this item').then((confirmed) => {
    if (confirmed) {
        // 삭제 코드
    }
});

// 토스트 편의 함수
showToast('Message', 'success', 5000);
```

## 실제 사용 예시

### 1. 폼 제출 시 사용

```javascript
document.getElementById('myForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    SiteManager.notifications.showLoading('Saving...');
    
    try {
        const response = await fetch('/api/save', {
            method: 'POST',
            body: new FormData(this)
        });
        
        const data = await response.json();
        
        SiteManager.notifications.hideLoading();
        
        if (data.success) {
            SiteManager.notifications.toast('Data saved successfully!', 'success');
        } else {
            SiteManager.notifications.error(data.message || 'Save failed!');
        }
    } catch (error) {
        SiteManager.notifications.hideLoading();
        SiteManager.notifications.error('Network error occurred!');
    }
});
```

### 2. 삭제 버튼 이벤트

```javascript
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const itemName = this.dataset.itemName || 'this item';
        
        SiteManager.notifications.confirmDelete(itemName).then((confirmed) => {
            if (confirmed) {
                // 실제 삭제 수행
                this.closest('form').submit();
            }
        });
    });
});
```

### 3. 이미지 클릭 시 미리보기

```javascript
document.querySelectorAll('.preview-image').forEach(img => {
    img.addEventListener('click', function() {
        SiteManager.modals.showImagePreview(
            this.src,
            this.alt,
            this.dataset.downloadUrl
        );
    });
});
```

## 마이그레이션 가이드

### 기존 코드에서 새 시스템으로 변경

#### Before (기존)
```javascript
alert('Error occurred!');
if (confirm('Delete this?')) {
    // 삭제 코드
}

showAlert('Success!', 'success');
showImageModal(url, name);
```

#### After (새 시스템)
```javascript
SiteManager.notifications.error('Error occurred!');
SiteManager.notifications.confirmDelete('this item').then((confirmed) => {
    if (confirmed) {
        // 삭제 코드
    }
});

SiteManager.notifications.toast('Success!', 'success');
SiteManager.modals.showImagePreview(url, name);
```

## 주의사항

1. **SweetAlert2 의존성**: 가능한 한 SweetAlert2를 로드하여 최상의 사용자 경험을 제공하세요.
2. **모달 정리**: 커스텀 모달은 자동으로 DOM에서 제거되므로 수동 정리가 필요하지 않습니다.
3. **Promise 기반**: 확인 대화상자는 Promise를 반환하므로 async/await 또는 .then()을 사용하세요.
4. **네임스페이스**: 전역 네임스페이스 오염을 방지하기 위해 `SiteManager` 네임스페이스를 사용합니다.

## 브라우저 지원

- 모던 브라우저 (ES6+ 지원)
- Internet Explorer 11+ (폴리필 필요할 수 있음)
- SweetAlert2가 없는 환경에서는 기본 브라우저 알림으로 fallback
