<?php

namespace SiteManager\Services;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SiteManager 통합 보안 서비스
 * - 이메일 인증
 * - Rate Limiting
 * - reCAPTCHA 검증  
 * - Honeypot 검증
 * - 폼 보안 검증
 * - 스팸 차단
 */
class SecurityService
{
    /**
     * 이메일 인증 토큰 생성 및 발송
     */
    public function sendVerificationEmail(string $email, string $type, int $id, string $boardSlug): string
    {
        // 고유한 토큰 생성
        $token = Str::random(60);
        
        // 캐시에 토큰 정보 저장 (설정된 시간만큼 유효)
        $expiresHours = config('sitemanager.security.email_verification.verification_token_expires', 24);
        $tokenData = [
            'email' => $email,
            'type' => $type, // 'post' or 'comment'
            'id' => $id,
            'board_slug' => $boardSlug,
            'created_at' => now()
        ];
        
        Cache::put("email_verification:{$token}", $tokenData, now()->addHours($expiresHours));
        
        // 인증 URL 생성
        $verificationUrl = URL::signedRoute('board.email.verify', [
            'token' => $token
        ]);
        
        // 이메일 발송
        try {
            Mail::send('sitemanager::emails.email-verification', [
                'verificationUrl' => $verificationUrl,
                'type' => $type,
                'boardSlug' => $boardSlug
            ], function ($message) use ($email) {
                $message->to($email)
                        ->subject('이메일 인증을 완료해주세요');
            });
            
            Log::info('Email verification sent', [
                'email' => $email,
                'type' => $type,
                'id' => $id,
                'token' => $token
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $token;
    }
    
    /**
     * 이메일 인증 토큰 검증 및 처리
     */
    public function verifyEmail(string $token): bool
    {
        $tokenData = Cache::get("email_verification:{$token}");
        
        if (!$tokenData) {
            Log::warning('Invalid or expired verification token', ['token' => $token]);
            return false;
        }
        
        try {
            if ($tokenData['type'] === 'post') {
                $this->verifyPostEmail($tokenData);
            } elseif ($tokenData['type'] === 'comment') {
                $this->verifyCommentEmail($tokenData);
            }
            
            // 토큰 삭제 (일회용)
            Cache::forget("email_verification:{$token}");
            
            Log::info('Email verified successfully', $tokenData);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Email verification failed', [
                'token_data' => $tokenData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 게시글 이메일 인증 처리
     */
    private function verifyPostEmail(array $tokenData): void
    {
        $postModelClass = BoardPost::forBoard($tokenData['board_slug']);
        $post = $postModelClass::findOrFail($tokenData['id']);
        
        $post->update([
            'email_verified_at' => now()
        ]);
    }
    
    /**
     * 댓글 이메일 인증 처리
     */
    private function verifyCommentEmail(array $tokenData): void
    {
        $commentModelClass = BoardComment::forBoard($tokenData['board_slug']);
        $comment = $commentModelClass::findOrFail($tokenData['id']);
        
        $comment->update([
            'email_verified_at' => now()
        ]);
    }
    
    /**
     * 수정/삭제용 인증 토큰 생성 및 발송
     */
    public function sendEditVerificationEmail(string $email, string $type, int $id, string $boardSlug, string $action = 'edit'): string
    {
        // 고유한 토큰 생성
        $token = Str::random(60);
        
        // 캐시에 토큰 정보 저장 (설정된 시간만큼 유효)
        $expiresHours = config('sitemanager.security.email_verification.edit_token_expires', 1);
        $tokenData = [
            'email' => $email,
            'type' => $type,
            'id' => $id,
            'board_slug' => $boardSlug,
            'action' => $action, // 'edit' or 'delete'
            'created_at' => now()
        ];
        
        Cache::put("edit_verification:{$token}", $tokenData, now()->addHours($expiresHours));
        
        // 인증 URL 생성
        $verificationUrl = URL::signedRoute('board.email.edit-verify', [
            'token' => $token
        ]);
        
        // 이메일 발송
        try {
            Mail::send('sitemanager::emails.edit-verification', [
                'verificationUrl' => $verificationUrl,
                'type' => $type,
                'action' => $action,
                'boardSlug' => $boardSlug
            ], function ($message) use ($email, $action) {
                $message->to($email)
                        ->subject($action === 'delete' ? '삭제 인증을 완료해주세요' : '수정 인증을 완료해주세요');
            });
            
        } catch (\Exception $e) {
            Log::error('Failed to send edit verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $token;
    }
    
    /**
     * 수정/삭제 인증 토큰 검증
     */
    public function verifyEditToken(string $token): array|false
    {
        $tokenData = Cache::get("edit_verification:{$token}");
        
        if (!$tokenData) {
            return false;
        }
        
        // 토큰 삭제 (일회용)
        Cache::forget("edit_verification:{$token}");
        
        return $tokenData;
    }
    
    /**
     * 캡챠 검증 (reCAPTCHA v2/v3 지원)
     */
    public function verifyCaptcha(string $captchaResponse, string $userIp = null, string $action = null): bool
    {
        // 캡챠가 비활성화된 경우 통과
        if (!config('sitemanager.security.recaptcha.enabled', false)) {
            return true;
        }
        
        $secretKey = config('sitemanager.security.recaptcha.secret_key') 
                   ?? config('services.recaptcha.secret_key');
        
        if (!$secretKey) {
            Log::warning('reCAPTCHA secret key not configured');
            return true; // 설정되지 않은 경우 통과
        }
        
        $version = config('sitemanager.security.recaptcha.version', 'v2');
        
        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secretKey,
                'response' => $captchaResponse,
                'remoteip' => $userIp
            ]);
            
            $result = $response->json();
            
            Log::info('reCAPTCHA verification result', [
                'success' => $result['success'] ?? false,
                'score' => $result['score'] ?? null,
                'action' => $result['action'] ?? null,
                'version' => $version,
                'ip' => $userIp
            ]);
            
            $isValid = $result['success'] ?? false;
            
            // v3인 경우 점수 기반 검증 추가
            if ($version === 'v3' && $isValid) {
                $score = $result['score'] ?? 0.0;
                $threshold = config('sitemanager.security.recaptcha.score_threshold', 0.5);
                
                if ($score < $threshold) {
                    Log::warning('reCAPTCHA v3 score too low', [
                        'score' => $score,
                        'threshold' => $threshold,
                        'action' => $action,
                        'ip' => $userIp
                    ]);
                    return false;
                }
                
                // 액션 검증 (v3인 경우)
                if ($action && isset($result['action']) && $result['action'] !== $action) {
                    Log::warning('reCAPTCHA v3 action mismatch', [
                        'expected' => $action,
                        'received' => $result['action'],
                        'ip' => $userIp
                    ]);
                    return false;
                }
            }
            
            return $isValid;
            
        } catch (\Exception $e) {
            Log::error('reCAPTCHA verification failed', [
                'error' => $e->getMessage(),
                'ip' => $userIp
            ]);
            return false;
        }
    }
    
    /**
     * 이메일 형식 검증
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * 이메일 도메인 블랙리스트 검사
     */
    public function isBlockedEmailDomain(string $email): bool
    {
        $blockedDomains = config('sitemanager.security.blocked_email_domains', [
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com'
        ]);
        
        $domain = substr(strrchr($email, '@'), 1);
        
        return in_array(strtolower($domain), array_map('strtolower', $blockedDomains));
    }
    
    /**
     * IP 기반 요청 제한 (Rate Limiting)
     */
    public function checkRateLimit(string $ip, string $action = 'post'): bool
    {
        // 개발 환경에서는 rate limiting 완화
        if (app()->environment('local')) {
            return true;
        }
        
        $key = "rate_limit:{$action}:{$ip}";
        $maxAttempts = config('sitemanager.security.rate_limiting.max_attempts', 5);
        $decayMinutes = config('sitemanager.security.rate_limiting.decay_minutes', 60);
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            Log::warning('Rate limit exceeded', [
                'ip' => $ip,
                'action' => $action,
                'attempts' => $attempts
            ]);
            return false;
        }
        
        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));
        return true;
    }
    
    /**
     * 의심스러운 내용 검사 (간단한 스팸 필터)
     */
    public function isSpamContent(string $content, string $title = '', string $email = ''): bool
    {
        // 스팸 키워드 목록
        $spamKeywords = config('sitemanager.security.spam_keywords', [
            'casino', 'poker', 'viagra', 'cialis', 'loan', 'mortgage',
            'bitcoin', 'crypto', 'investment', 'forex', 'trading',
            'weight loss', 'diet pill', 'sex', 'adult', 'porn'
        ]);
        
        $text = strtolower($content . ' ' . $title);
        
        foreach ($spamKeywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                Log::warning('Spam keyword detected', [
                    'keyword' => $keyword,
                    'email' => $email,
                    'content_preview' => substr($content, 0, 100)
                ]);
                return true;
            }
        }
        
        // URL 개수 확인 (과도한 링크)
        $urlCount = preg_match_all('/(http|https):\/\/[^\s]+/', $content);
        $maxUrls = config('sitemanager.security.max_urls_per_post', 3);
        
        if ($urlCount > $maxUrls) {
            Log::warning('Too many URLs detected', [
                'url_count' => $urlCount,
                'max_allowed' => $maxUrls,
                'email' => $email
            ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * 허니팟 검증 (봇 탐지)
     */
    public function verifyHoneypot(array $formData): bool
    {
        $honeypotFields = ['website', 'url', 'homepage', 'phone_number'];
        
        foreach ($honeypotFields as $field) {
            if (!empty($formData[$field])) {
                Log::warning('Honeypot field filled', [
                    'field' => $field,
                    'value' => $formData[$field]
                ]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 제출 시간 검증 (너무 빠른 제출 방지)
     */
    public function verifySubmissionTime(string $formToken): bool
    {
        $minTime = config('sitemanager.security.min_form_time', 3); // 최소 3초
        $tokenData = Cache::get("form_token:{$formToken}");
        
        if (!$tokenData) {
            Log::warning('Invalid form token for submission time verification', [
                'form_token' => substr($formToken, 0, 8) . '...'
            ]);
            return false;
        }
        
        $elapsed = $tokenData['created_at']->diffInSeconds(now(), false); // false = 항상 양수 반환
        
        // 디버깅 로그 추가
        Log::info('Form submission time verification', [
            'form_token' => substr($formToken, 0, 8) . '...',
            'created_at' => $tokenData['created_at']->toDateTimeString(),
            'current_time' => now()->toDateTimeString(),
            'elapsed_seconds' => $elapsed,
            'min_required' => $minTime,
            'is_valid' => $elapsed >= $minTime
        ]);
        
        if ($elapsed < $minTime) {
            Log::warning('Form submitted too quickly', [
                'elapsed_seconds' => $elapsed,
                'min_required' => $minTime,
                'token_created' => $tokenData['created_at']->toDateTimeString(),
                'current_time' => now()->toDateTimeString()
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * 폼 토큰 생성 (제출 시간 검증용)
     */
    public function generateFormToken(): string
    {
        $token = Str::random(32);
        
        Cache::put("form_token:{$token}", [
            'created_at' => now()
        ], now()->addHours(1));
        
        return $token;
    }
    
    /**
     * 비회원 댓글의 이메일과 비밀번호로 인증 확인
     */
    public function verifyGuestCredentials(string $email, string $password, string $type, int $id, string $boardSlug): bool
    {
        try {
            if ($type === 'comment') {
                $commentModelClass = BoardComment::forBoard($boardSlug);
                $comment = $commentModelClass::where('id', $id)
                    ->where('author_email', $email)
                    ->whereNotNull('email_verified_at') // 이메일 인증이 완료된 댓글만
                    ->whereNull('member_id') // 비회원 댓글만
                    ->first();
                
                if (!$comment) {
                    return false;
                }
                
                // 저장된 해시된 비밀번호와 입력된 비밀번호 비교
                return Hash::check($password, $comment->email_verification_token);
            }
            
            // 다른 타입 (post 등)도 필요시 추가
            return false;
            
        } catch (\Exception $e) {
            Log::error('Guest credentials verification failed', [
                'email' => $email,
                'type' => $type,
                'id' => $id,
                'board_slug' => $boardSlug,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 이메일 인증과 비밀번호 설정을 함께 처리
     */
    public function verifyEmailAndSetPassword(string $token, string $password): bool
    {
        $tokenData = Cache::get("email_verification:{$token}");
        
        if (!$tokenData) {
            Log::warning('Invalid or expired verification token', ['token' => $token]);
            return false;
        }
        
        try {
            $hashedPassword = Hash::make($password);
            
            if ($tokenData['type'] === 'post') {
                $this->verifyPostEmailWithPassword($tokenData, $hashedPassword);
            } elseif ($tokenData['type'] === 'comment') {
                $this->verifyCommentEmailWithPassword($tokenData, $hashedPassword);
            }
            
            Log::info('Email verified and password set successfully', $tokenData);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Email verification with password failed', [
                'token_data' => $tokenData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 게시글 이메일 인증 및 비밀번호 설정
     */
    private function verifyPostEmailWithPassword(array $tokenData, string $hashedPassword): void
    {
        $postModelClass = BoardPost::forBoard($tokenData['board_slug']);
        $post = $postModelClass::findOrFail($tokenData['id']);
        
        // 이메일 확인
        if ($post->author_email !== $tokenData['email']) {
            throw new \Exception('이메일이 일치하지 않습니다.');
        }
        
        // 이메일 인증 완료 및 비밀번호 설정
        $post->update([
            'email_verified_at' => now(),
            'email_verification_token' => $hashedPassword, // 비밀번호를 토큰 필드에 저장
            'status' => 'approved'
        ]);
    }
    
    /**
     * 댓글 이메일 인증 및 비밀번호 설정
     */
    private function verifyCommentEmailWithPassword(array $tokenData, string $hashedPassword): void
    {
        $commentModelClass = BoardComment::forBoard($tokenData['board_slug']);
        $comment = $commentModelClass::findOrFail($tokenData['id']);
        
        // 이메일 확인
        if ($comment->author_email !== $tokenData['email']) {
            throw new \Exception('이메일이 일치하지 않습니다.');
        }
        
        // 이메일 인증 완료 및 비밀번호 설정
        $comment->update([
            'email_verified_at' => now(),
            'email_verification_token' => $hashedPassword, // 비밀번호를 토큰 필드에 저장
            'status' => 'approved'
        ]);
    }
    
    /**
     * ============================================================================
     * 통합 폼 보안 검증 메서드들
     * ============================================================================
     */
    
    /**
     * 종합 보안 검증 (원스톱 검증)
     */
    public function validateFormSecurity(array $data, string $ip, array $options = []): array
    {
        $defaultOptions = [
            'honeypot' => true,
            'timing' => true,
            'recaptcha' => true,
            'email_domain' => true,
            'spam_content' => true,
            'min_time' => 3, // 최소 폼 작성 시간 (초)
            'form_type' => 'general', // 폼 타입 (로깅용)
            'text_fields' => ['name', 'email', 'message'], // 스팸 검사할 필드들
        ];
        
        $options = array_merge($defaultOptions, $options);
        $errors = [];
        
        // 1. Honeypot 검증
        if ($options['honeypot']) {
            $honeypotResult = $this->validateHoneypotFields($data, $ip, $options['form_type']);
            if (!$honeypotResult['valid']) {
                $errors[] = $honeypotResult;
            }
        }
        
        // 2. 폼 제출 시간 검증
        if ($options['timing']) {
            $timingResult = $this->validateFormTiming($data, $ip, $options['min_time'], $options['form_type']);
            if (!$timingResult['valid']) {
                $errors[] = $timingResult;
            }
        }
        
        // 3. reCAPTCHA 검증
        if ($options['recaptcha']) {
            $recaptchaResult = $this->validateRecaptchaToken($data, $ip, $options['form_type']);
            if (!$recaptchaResult['valid']) {
                $errors[] = $recaptchaResult;
            }
        }
        
        // 4. 이메일 도메인 차단 검증
        if ($options['email_domain'] && !empty($data['email'])) {
            $emailResult = $this->validateEmailDomainBlocking($data['email'], $ip, $options['form_type']);
            if (!$emailResult['valid']) {
                $errors[] = $emailResult;
            }
        }
        
        // 5. 스팸 내용 검증
        if ($options['spam_content']) {
            $spamResult = $this->validateSpamContent($data, $options['text_fields'], $ip, $options['form_type']);
            if (!$spamResult['valid']) {
                $errors[] = $spamResult;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'error_message' => $errors[0]['message'] ?? null,
            'error_type' => $errors[0]['type'] ?? null
        ];
    }
    
    /**
     * Honeypot 필드 검증
     */
    public function validateHoneypotFields(array $data, string $ip, string $formType = 'general'): array
    {
        $honeypotFields = config('sitemanager.security.honeypot.fields', [
            'website', 'url', 'homepage', 'phone_number', 'company_phone'
        ]);
        
        foreach ($honeypotFields as $field) {
            if (!empty($data[$field])) {
                Log::warning('SiteManager Security: Honeypot field detected', [
                    'field' => $field,
                    'value' => $data[$field],
                    'ip' => $ip,
                    'form_type' => $formType,
                    'timestamp' => now()
                ]);
                
                return [
                    'valid' => false,
                    'type' => 'honeypot',
                    'message' => 'Invalid submission detected.',
                    'field' => $field
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * 폼 제출 시간 검증
     */
    public function validateFormTiming(array $data, string $ip, int $minTime = 3, string $formType = 'general'): array
    {
        if (empty($data['form_timestamp'])) {
            return ['valid' => true]; // 타임스탬프가 없으면 통과
        }
        
        $timeDiff = time() - (int)$data['form_timestamp'];
        
        if ($timeDiff < $minTime) {
            Log::warning('SiteManager Security: Form submitted too quickly', [
                'time_diff' => $timeDiff,
                'min_time' => $minTime,
                'ip' => $ip,
                'form_type' => $formType,
                'timestamp' => now()
            ]);
            
            return [
                'valid' => false,
                'type' => 'timing',
                'message' => 'Please take more time to fill out the form properly.',
                'time_diff' => $timeDiff,
                'min_time' => $minTime
            ];
        }
        
        // 너무 오래된 폼 (30분 초과)
        $maxTime = config('sitemanager.security.behavior_tracking.max_form_time', 1800);
        if ($timeDiff > $maxTime) {
            Log::warning('SiteManager Security: Form expired', [
                'time_diff' => $timeDiff,
                'max_time' => $maxTime,
                'ip' => $ip,
                'form_type' => $formType,
                'timestamp' => now()
            ]);
            
            return [
                'valid' => false,
                'type' => 'expired',
                'message' => 'Form has expired. Please refresh and try again.',
                'time_diff' => $timeDiff,
                'max_time' => $maxTime
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * reCAPTCHA 토큰 검증 (기존 verifyCaptcha를 래핑)
     */
    public function validateRecaptchaToken(array $data, string $ip, string $formType = 'general'): array
    {
        $recaptchaToken = $data['recaptcha_token'] ?? $data['g-recaptcha-response'] ?? null;
        
        if (empty($recaptchaToken)) {
            return ['valid' => true]; // 토큰이 없으면 통과 (선택적)
        }
        
        $isValid = $this->verifyCaptcha($recaptchaToken, $ip, $formType);
        
        if (!$isValid) {
            return [
                'valid' => false,
                'type' => 'recaptcha',
                'message' => 'reCAPTCHA verification failed. Please try again.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 이메일 도메인 차단 검증
     */
    public function validateEmailDomainBlocking(string $email, string $ip, string $formType = 'general'): array
    {
        $blockedDomains = config('sitemanager.blocked_email_domains', []);
        
        if (empty($blockedDomains)) {
            return ['valid' => true];
        }
        
        $emailDomain = substr(strrchr($email, "@"), 1);
        
        if (in_array(strtolower($emailDomain), array_map('strtolower', $blockedDomains))) {
            Log::warning('SiteManager Security: Blocked email domain detected', [
                'email' => $email,
                'domain' => $emailDomain,
                'ip' => $ip,
                'form_type' => $formType,
                'timestamp' => now()
            ]);
            
            return [
                'valid' => false,
                'type' => 'blocked_email',
                'message' => 'Email domain not allowed.',
                'domain' => $emailDomain
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 스팸 내용 검증
     */
    public function validateSpamContent(array $data, array $textFields, string $ip, string $formType = 'general'): array
    {
        $spamKeywords = config('sitemanager.spam_keywords', []);
        
        if (empty($spamKeywords)) {
            return ['valid' => true];
        }
        
        foreach ($textFields as $field) {
            if (!empty($data[$field])) {
                foreach ($spamKeywords as $keyword) {
                    if (stripos($data[$field], $keyword) !== false) {
                        Log::warning('SiteManager Security: Spam keyword detected', [
                            'field' => $field,
                            'keyword' => $keyword,
                            'value' => substr($data[$field], 0, 100) . '...', // 로그에는 일부만
                            'ip' => $ip,
                            'form_type' => $formType,
                            'timestamp' => now()
                        ]);
                        
                        return [
                            'valid' => false,
                            'type' => 'spam_content',
                            'message' => 'Your submission contains inappropriate content.',
                            'field' => $field,
                            'keyword' => $keyword
                        ];
                    }
                }
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * 보안 검증 규칙을 Validator 규칙에 추가
     */
    public function getSecurityValidationRules(): array
    {
        return [
            // 보안 필드들
            'g-recaptcha-response' => 'nullable|string',
            'form_token' => 'nullable|string',
            'user_behavior' => 'nullable|string',
            'recaptcha_token' => 'nullable|string',
            'form_timestamp' => 'nullable|numeric',
            // Honeypot 필드들 (비어있어야 함)
            'website' => 'nullable|string',
            'url' => 'nullable|string',
            'homepage' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'company_phone' => 'nullable|string',
        ];
    }
}
