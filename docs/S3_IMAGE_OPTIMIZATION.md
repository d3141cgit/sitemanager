# S3 이미지 성능 최적화 가이드

## 현재 문제점
- S3 이미지에 Cache-Control 헤더가 설정되지 않음
- 브라우저 캐싱이 제대로 되지 않아 매번 새로 다운로드
- 이미지 최적화 및 리사이징이 되지 않음

## 해결 방안

### 1. S3 버킷 정책 설정

#### A. Cache-Control 헤더 설정
```json
{
    "Rules": [
        {
            "ApplyServerSideEncryptionByDefault": {
                "SSEAlgorithm": "AES256"
            },
            "Filter": {
                "Prefix": "editor/images/"
            },
            "Status": "Enabled",
            "Transitions": [],
            "NoncurrentVersionTransitions": [],
            "NoncurrentVersionExpiration": {
                "NoncurrentDays": 30
            },
            "AbortIncompleteMultipartUpload": {
                "DaysAfterInitiation": 7
            }
        }
    ]
}
```

#### B. CORS 설정
```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "HEAD"],
        "AllowedOrigins": ["*"],
        "ExposeHeaders": ["ETag"],
        "MaxAgeSeconds": 86400
    }
]
```

### 2. CloudFront 설정 (권장)

#### A. CloudFront 배포 생성
```yaml
Origin: img.edmkorean.com.s3.ap-northeast-2.amazonaws.com
Cache Behaviors:
  - Path: /editor/images/*
    Cache Policy: Managed-CachingOptimized
    Origin Request Policy: Managed-UserAgentRefererHeaders
    TTL: 
      Min: 86400 (1일)
      Default: 31536000 (1년)
      Max: 31536000 (1년)
```

#### B. 캐시 헤더 설정
```
Cache-Control: public, max-age=31536000, immutable
```

### 3. 이미지 업로드 시 메타데이터 설정

FileUploadService에서 S3 업로드 시 캐시 헤더를 설정:

```php
$result = $s3->putObject([
    'Bucket' => $bucket,
    'Key' => $key,
    'Body' => $fileContents,
    'ContentType' => $mimeType,
    'CacheControl' => 'public, max-age=31536000, immutable',
    'Expires' => gmdate('D, d M Y H:i:s T', strtotime('+1 year')),
    'Metadata' => [
        'optimized' => 'true',
        'uploaded-at' => date('c')
    ]
]);
```

### 4. 이미지 최적화 서비스 연동

#### A. Lambda@Edge 함수 (고급)
이미지 리사이징 및 포맷 최적화:

```javascript
exports.handler = async (event) => {
    const request = event.Records[0].cf.request;
    const uri = request.uri;
    
    // 이미지 요청인지 확인
    if (uri.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
        // 쿼리 파라미터로 리사이징 지원
        // ?w=800&h=600&f=webp
        const params = request.querystring;
        // 리사이징 로직 구현
    }
    
    return request;
};
```

#### B. 클라이언트 사이드 WebP 지원
```javascript
// WebP 지원 여부 확인 후 이미지 포맷 변경
function supportsWebP() {
    return new Promise(resolve => {
        const webP = new Image();
        webP.onload = webP.onerror = () => resolve(webP.height === 2);
        webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
    });
}
```

### 5. 성능 모니터링

#### A. 로딩 시간 측정
```javascript
const startTime = performance.now();
img.onload = () => {
    const loadTime = performance.now() - startTime;
    console.log(`Image loaded in ${loadTime.toFixed(2)}ms`);
};
```

#### B. 네트워크 상태 확인
```javascript
if (navigator.connection) {
    const connection = navigator.connection;
    const effectiveType = connection.effectiveType; // '4g', '3g', etc.
    
    // 느린 연결에서는 저화질 이미지 로드
    if (effectiveType === '2g' || effectiveType === 'slow-2g') {
        // 압축률 높은 이미지 사용
    }
}
```

## 즉시 적용 가능한 개선사항

### 1. S3 객체 메타데이터 일괄 업데이트
```bash
aws s3 cp s3://img.edmkorean.com/editor/images/ s3://img.edmkorean.com/editor/images/ \
  --recursive \
  --metadata-directive REPLACE \
  --cache-control "public, max-age=31536000, immutable" \
  --expires "$(date -d '+1 year' -u +'%a, %d %b %Y %H:%M:%S GMT')"
```

### 2. 기존 이미지 WebP 변환 (선택사항)
```bash
# ImageMagick 사용
find ./images -name "*.jpg" -exec magick {} -quality 80 {}.webp \;
```

### 3. CDN 설정
- CloudFlare 등 CDN 서비스 사용
- 전 세계 엣지 서버에서 이미지 캐싱
- 자동 이미지 최적화 기능 활용

## 예상 성능 개선 효과

- **첫 방문**: 변화 없음 (최초 다운로드)
- **재방문**: 80-90% 로딩 시간 단축
- **동일 페이지 내 이미지**: 즉시 로딩 (캐시 활용)
- **대역폭 절약**: 월 30-50% 절약 예상

## 모니터링 지표

1. **TTFB (Time To First Byte)**: 150ms 이하 목표
2. **이미지 로딩 시간**: 현재 대비 50% 단축 목표
3. **캐시 히트율**: 85% 이상 목표
4. **대역폭 사용량**: 월 30% 절감 목표
