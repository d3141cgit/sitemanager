/**
 * S3 이미지 최적화 및 lazy loading 시스템
 */
(function() {
    'use strict';
    
    // 이미 로드되었는지 확인하되, 인스턴스가 있어도 새로운 이미지 처리는 허용
    if (window.ImageOptimizer && window.ImageOptimizer.instance) {
        // 기존 인스턴스가 있으면 새 이미지만 처리
        if (window.ImageOptimizer.instance.optimizeNewImages) {
            window.ImageOptimizer.instance.optimizeNewImages();
        }
        return;
    }

class ImageOptimizer {
    constructor() {
        // 중복 실행 방지
        if (ImageOptimizer.initialized) {
            return ImageOptimizer.instance;
        }
        
        ImageOptimizer.initialized = true;
        ImageOptimizer.instance = this;
        
        this.optimizedImages = new Map(); // URL과 상태를 함께 저장
        this.loadingImages = new Set(); // 현재 로딩 중인 이미지
        this.failedImages = new Set(); // 로딩 실패한 이미지
        this.intersectionObserver = null;
        this.init();
    }

    init() {
        // DOM이 로드된 후 실행
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.optimizeImages());
        } else {
            this.optimizeImages();
        }
        
        // Intersection Observer 초기화
        this.initIntersectionObserver();
    }

    /**
     * Intersection Observer 초기화 (재사용 가능)
     */
    initIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            return;
        }
        
        this.intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const imgSrc = img.src || img.dataset.src;
                    
                    // 이미 로딩 중이거나 완료된 이미지는 스킵
                    if (this.loadingImages.has(imgSrc) || 
                        (this.optimizedImages.has(imgSrc) && this.optimizedImages.get(imgSrc) === 'loaded')) {
                        this.intersectionObserver.unobserve(img);
                        return;
                    }
                    
                    this.loadImageWithRetry(img);
                }
            });
        }, {
            rootMargin: '50px', // 뷰포트 50px 전에 미리 로드
            threshold: 0.1 // 10%만 보여도 로딩 시작
        });
    }

    /**
     * 페이지의 모든 S3 이미지들을 최적화 (새로운 이미지만)
     */
    optimizeImages() {
        this.optimizeNewImages();
    }
    
    /**
     * 새로운 이미지들만 최적화 (기존 처리된 이미지 제외)
     */
    optimizeNewImages() {
        // body 전체에서 S3 이미지만 선택
        const images = document.querySelectorAll('img[src*="amazonaws.com"]');
        
        images.forEach(img => {
            const imgSrc = img.src;
            
            // 이미 처리된 이미지는 스킵 (실패한 이미지는 재처리 허용)
            if (this.optimizedImages.has(imgSrc) && 
                this.optimizedImages.get(imgSrc) !== 'failed') {
                return;
            }
            
            this.optimizedImages.set(imgSrc, 'processing');
            this.optimizeImage(img);
            // optimizeImage() 메서드에서 이미 addImagePreview()와 addErrorHandling()을 호출하므로 중복 제거
            this.addLazyLoading(img);
        });
    }

    /**
     * 개별 이미지 최적화
     */
    optimizeImage(img) {
        // S3 이미지인지 확인 (모든 S3 도메인 지원)
        if (!img.src.includes('amazonaws.com')) {
            return;
        }

        // 이미지 크기에 따른 최적화된 URL 생성
        const optimizedSrc = this.getOptimizedImageUrl(img.src, img);
        
        // 원본 URL 백업
        img.dataset.originalSrc = img.src;
        
        // 최적화된 URL로 변경 (필요한 경우)
        if (optimizedSrc !== img.src) {
            img.src = optimizedSrc;
        }

        // 캐시 최적화를 위한 속성 추가 (CORS 문제 방지)
        // img.crossOrigin = 'anonymous'; // CORS 에러 가능성 때문에 주석처리
        img.decoding = 'async';
        img.loading = 'lazy';
        
        // 로딩 상태 확인
        if (img.complete && img.naturalHeight !== 0) {
            // 이미 로드된 이미지
            this.optimizedImages.set(img.src, 'loaded');
            this.addImagePreview(img);
        } else {
            // 아직 로드되지 않은 이미지
            this.addLazyLoading(img);
        }
        
        this.addErrorHandling(img);
    }

    /**
     * 최적화된 이미지 URL 생성
     */
    getOptimizedImageUrl(originalUrl, imgElement) {
        // 현재는 원본 URL 그대로 반환
        // 향후 CloudFront나 이미지 리사이징 서비스 연동 시 여기서 처리
        return originalUrl;
    }

    /**
     * Lazy Loading 추가
     */
    addLazyLoading(img) {
        // Intersection Observer 지원 여부 확인
        if (!this.intersectionObserver) {
            // fallback: 바로 로딩
            this.loadImageWithRetry(img);
            return;
        }

        // 로딩 플레이스홀더 추가
        this.addLoadingPlaceholder(img);

        // Intersection Observer로 지연 로딩 구현
        this.intersectionObserver.observe(img);
    }

    /**
     * 로딩 플레이스홀더 추가
     */
    addLoadingPlaceholder(img) {
        // 이미지가 로딩 중일 때 스켈레톤 효과
        img.style.backgroundColor = '#f0f0f0';
        img.style.backgroundImage = `
            linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent)
        `;
        img.style.backgroundSize = '200px 100%';
        img.style.backgroundRepeat = 'no-repeat';
        img.style.animation = 'shimmer 1.5s infinite';
        
        // CSS 애니메이션 추가 (한 번만)
        if (!document.querySelector('#image-shimmer-styles')) {
            const style = document.createElement('style');
            style.id = 'image-shimmer-styles';
            style.textContent = `
                @keyframes shimmer {
                    0% { background-position: -200px 0; }
                    100% { background-position: calc(100% + 200px) 0; }
                }
                .image-loading {
                    position: relative;
                }
                .image-loading::after {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 40px;
                    height: 40px;
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #007bff;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                @keyframes spin {
                    0% { transform: translate(-50%, -50%) rotate(0deg); }
                    100% { transform: translate(-50%, -50%) rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * 이미지 로딩 실행 (재시도 로직 포함)
     */
    loadImageWithRetry(img, retryCount = 0) {
        const maxRetries = 3;
        const imgSrc = img.src;
        
        // 이미 로딩 중인 이미지는 스킵
        if (this.loadingImages.has(imgSrc)) {
            return;
        }
        
        this.loadingImages.add(imgSrc);
        img.classList.add('image-loading');
        
        const tempImg = new Image();
        
        // 타임아웃 설정 (30초)
        const timeoutId = setTimeout(() => {
            tempImg.onload = null;
            tempImg.onerror = null;
            this.handleImageLoadFailure(img, retryCount, maxRetries, 'timeout');
        }, 30000);
        
        tempImg.onload = () => {
            clearTimeout(timeoutId);
            this.loadingImages.delete(imgSrc);
            this.optimizedImages.set(imgSrc, 'loaded');
            
            img.src = tempImg.src;
            img.classList.remove('image-loading');
            this.removeLoadingPlaceholder(img);
            this.addImagePreview(img);
            
            // Intersection Observer에서 제거
            if (this.intersectionObserver) {
                this.intersectionObserver.unobserve(img);
            }
        };
        
        tempImg.onerror = () => {
            clearTimeout(timeoutId);
            this.handleImageLoadFailure(img, retryCount, maxRetries, 'error');
        };
        
        tempImg.src = img.dataset.originalSrc || img.src;
    }
    
    /**
     * 이미지 로딩 실패 처리
     */
    handleImageLoadFailure(img, retryCount, maxRetries, reason) {
        const imgSrc = img.src;
        this.loadingImages.delete(imgSrc);
        
        console.warn(`Image load failed (${reason}): ${imgSrc}, retry ${retryCount + 1}/${maxRetries}`);
        
        if (retryCount < maxRetries) {
            // 재시도 (지수 백오프)
            const delay = Math.pow(2, retryCount) * 1000; // 1s, 2s, 4s
            setTimeout(() => {
                this.loadImageWithRetry(img, retryCount + 1);
            }, delay);
        } else {
            // 최대 재시도 횟수 초과
            this.optimizedImages.set(imgSrc, 'failed');
            this.failedImages.add(imgSrc);
            this.handleImageError(img);
        }
    }

    /**
     * 이미지 로딩 실행 (기존 메서드 - 호환성 유지)
     */
    loadImage(img) {
        this.loadImageWithRetry(img);
    }
    
    /**
     * 로딩 플레이스홀더 제거
     */
    removeLoadingPlaceholder(img) {
        img.style.backgroundColor = '';
        img.style.backgroundImage = '';
        img.style.animation = '';
    }

    /**
     * 이미지 미리보기 기능 추가
     */
    addImagePreview(img) {
        // 댓글 첨부파일 이미지는 제외 (중복 이벤트 방지)
        if (img.classList.contains('comment-attachment-image')) {
            return;
        }
        
        // 이미 클릭 이벤트가 추가되었는지 확인
        if (img.dataset.hasClickListener === 'true') {
            return;
        }
        
        img.style.cursor = 'pointer';
        img.title = 'Click to view full size';
        
        img.addEventListener('click', (e) => {
            e.preventDefault();
            const originalSrc = img.dataset.originalSrc || img.src;
            const altText = img.alt || 'Image';
            
            // 통합 모달 시스템 사용
            if (window.SiteManager && window.SiteManager.modals) {
                SiteManager.modals.showImagePreview(originalSrc, altText, originalSrc);
            } else {
                // 기본 새 창으로 열기
                window.open(originalSrc, '_blank');
            }
        });
        
        // 클릭 이벤트 추가 플래그 설정
        img.dataset.hasClickListener = 'true';
    }

    /**
     * 이미지 에러 처리
     */
    addErrorHandling(img) {
        // 이미 에러 핸들러가 추가되었는지 확인
        if (img.dataset.hasErrorHandler === 'true') {
            return;
        }
        
        img.addEventListener('error', (e) => {
            const imgSrc = img.src;
            console.warn('Image error event:', imgSrc, e);
            
            // 이미 에러 처리되지 않았다면 처리
            if (!this.failedImages.has(imgSrc)) {
                this.handleImageLoadFailure(img, 0, 3, 'immediate_error');
            }
        });
        
        // 에러 핸들러 추가 플래그 설정
        img.dataset.hasErrorHandler = 'true';
    }

    /**
     * 이미지 로딩 에러 처리
     */
    handleImageError(img) {
        const imgSrc = img.src;
        
        img.classList.remove('image-loading');
        this.removeLoadingPlaceholder(img);
        this.loadingImages.delete(imgSrc);
        
        // 에러 플레이스홀더 생성
        const placeholder = document.createElement('div');
        placeholder.className = 'image-error-placeholder';
        placeholder.style.cssText = `
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border: 1px dashed #dee2e6;
            color: #6c757d;
            font-size: 14px;
            min-height: 200px;
            width: 100%;
        `;
        placeholder.innerHTML = `
            <div style="text-align: center;">
                <div style="font-size: 24px; margin-bottom: 8px;">📷</div>
                <div>이미지를 불러올 수 없습니다</div>
                <small style="color: #adb5bd;">네트워크 연결을 확인해주세요</small>
            </div>
        `;
        
        // 재시도 버튼 추가
        const retryBtn = document.createElement('button');
        retryBtn.textContent = '다시 시도';
        retryBtn.style.cssText = `
            margin-top: 10px;
            padding: 5px 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        `;
        
        retryBtn.onclick = () => {
            // 기존 실패 상태 초기화
            this.failedImages.delete(imgSrc);
            this.optimizedImages.delete(imgSrc);
            
            // 플레이스홀더 제거
            placeholder.remove();
            img.style.display = '';
            
            // 재시도
            this.loadImageWithRetry(img, 0);
        };
        
        placeholder.querySelector('div').appendChild(retryBtn);
        
        // 이미지 숨기고 플레이스홀더 표시
        if (img.parentNode) {
            img.parentNode.insertBefore(placeholder, img);
            img.style.display = 'none';
        }
    }

    /**
     * 이미지 사전 로딩 (선택적)
     */
    preloadImages() {
        // body 전체에서 S3 이미지 선택
        const images = document.querySelectorAll('img[src*="amazonaws.com"]');
        
        // 첫 3개 이미지만 사전 로딩
        Array.from(images).slice(0, 3).forEach(img => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = 'image';
            link.href = img.src;
            document.head.appendChild(link);
        });
    }
}

// 전역에서 사용할 수 있도록 설정
window.ImageOptimizer = ImageOptimizer;

// 자동 초기화 (중복 방지)
if (!window.imageOptimizerInitialized) {
    window.imageOptimizerInitialized = true;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            new ImageOptimizer();
        });
    } else {
        new ImageOptimizer();
    }
}

// 동적으로 추가된 콘텐츠에 대한 처리
if (typeof MutationObserver !== 'undefined' && !window.imageOptimizerObserverInitialized) {
    window.imageOptimizerObserverInitialized = true;
    
    const contentObserver = new MutationObserver((mutations) => {
        let hasNewImages = false;
        
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE && 
                        (node.tagName === 'IMG' || node.querySelector('img'))) {
                        hasNewImages = true;
                    }
                });
            }
        });
        
        if (hasNewImages) {
            // 지연 시간을 줄여서 깜빡임 최소화
            setTimeout(() => {
                if (ImageOptimizer.instance) {
                    ImageOptimizer.instance.optimizeNewImages();
                } else {
                    new ImageOptimizer();
                }
            }, 50); // 100ms → 50ms로 단축
        }
    });

    // DOM이 준비된 후에 관찰 시작
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            contentObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        });
    } else {
        contentObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
}

})(); // IIFE 종료