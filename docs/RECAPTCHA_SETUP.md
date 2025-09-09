# reCAPTCHA 설정 가이드

이 파일은 reCAPTCHA 설정 방법을 안내합니다.

## 1. Google reCAPTCHA 키 발급

1. https://www.google.com/recaptcha/admin 방문
2. 새 사이트 등록:
   - 사이트 라벨: 사이트명 입력
   - reCAPTCHA 타입: reCAPTCHA v2 선택 (I'm not a robot 체크박스)
   - 도메인: 실제 도메인 추가 (예: example.com, localhost)
3. 사이트 키와 비밀 키 복사

## 2. .env 파일에 추가할 환경 변수

```env
# reCAPTCHA 설정
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
RECAPTCHA_VERSION=v2
RECAPTCHA_SCORE_THRESHOLD=0.5

# SiteManager 보안 설정
SITEMANAGER_RECAPTCHA_ENABLED=true
SITEMANAGER_EMAIL_VERIFICATION_ENABLED=true
SITEMANAGER_GUEST_POSTING_ENABLED=true
SITEMANAGER_GUEST_EMAIL_VERIFICATION_REQUIRED=true
SITEMANAGER_GUEST_CAPTCHA_REQUIRED=true
```

## 3. 선택적 설정

### 개발 환경에서 reCAPTCHA 비활성화
```env
SITEMANAGER_RECAPTCHA_ENABLED=false
SITEMANAGER_GUEST_CAPTCHA_REQUIRED=false
```

### 이메일 인증 토큰 유효시간 조정 (시간 단위)
```env
# .env에는 추가하지 않고, config/sitemanager.php에서 직접 수정
# 기본값: 초기 인증 24시간, 수정/삭제 인증 1시간
```

### 게스트 포스팅 완전 비활성화
```env
SITEMANAGER_GUEST_POSTING_ENABLED=false
```

## 4. 캐시 설정 필요

이메일 인증 토큰을 저장하기 위해 캐시 드라이버가 필요합니다.

### Redis 사용 (권장)
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 데이터베이스 캐시 사용
```env
CACHE_DRIVER=database
```

데이터베이스 캐시 사용 시 아래 명령어 실행:
```bash
php artisan cache:table
php artisan migrate
```

## 5. 메일 설정

이메일 인증을 위해 메일 발송 설정이 필요합니다.

### Gmail SMTP 사용
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 다른 메일 서비스 사용
- AWS SES
- SendGrid
- Mailgun
- Postmark

## 6. 테스트

설정 완료 후 테스트:

1. 익명 사용자로 게시글/댓글 작성
2. reCAPTCHA 정상 작동 확인
3. 이메일 인증 링크 수신 확인
4. 수정/삭제 시 이메일 인증 작동 확인

## 문제 해결

### reCAPTCHA가 표시되지 않는 경우
- 사이트 키가 올바른지 확인
- 도메인이 등록되었는지 확인
- HTTPS 사용 권장

### 이메일이 발송되지 않는 경우
- 메일 설정 확인
- Laravel 로그 확인: `storage/logs/laravel.log`
- 캐시 드라이버 설정 확인

### 토큰이 만료되는 경우
- 캐시가 정상 작동하는지 확인
- 설정된 만료 시간 확인
