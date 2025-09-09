<?php

namespace SiteManager\Services;

use SiteManager\Models\Board;
use SiteManager\Models\BoardPost;
use SiteManager\Models\BoardComment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailVerificationService
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
            Log::warning('Invalid form token');
            return false;
        }
        
        $elapsed = now()->diffInSeconds($tokenData['created_at']);
        
        if ($elapsed < $minTime) {
            Log::warning('Form submitted too quickly', [
                'elapsed_seconds' => $elapsed,
                'min_required' => $minTime
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
}
