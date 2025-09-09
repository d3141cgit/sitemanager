/**
 * S3 이미지 최적화 및 lazy loading 시스템
 */
(function() {
    'use strict';
    
    // 이미 로드되었는지 확인
    if (window.ImageOptimizer) {
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
        
        this.optimizedImages = new Set(); // 이미 처리된 이미지 추적
        this.init();
    }

    init() {
        // DOM이 로드된 후 실행
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.optimizeImages());
        } else {
            this.optimizeImages();
        }
    }

    /**
     * 페이지의 모든 S3 이미지들을 최적화
     */
    optimizeImages() {
        // body 전체에서 S3 이미지만 선택
        const images = document.querySelectorAll('img[src*="amazonaws.com"]');
        
        images.forEach(img => {
            // 이미 처리된 이미지는 스킵
            if (this.optimizedImages.has(img.src)) {
                return;
            }
            
            this.optimizedImages.add(img.src);
            this.optimizeImage(img);
            this.addLazyLoading(img);
            this.addImagePreview(img);
            this.addErrorHandling(img);
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

        // 캐시 최적화를 위한 속성 추가
        img.crossOrigin = 'anonymous'; // CORS 에러 방지를 위해 임시 주석처리
        img.decoding = 'async';
        img.loading = 'lazy';
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
        if (!('IntersectionObserver' in window)) {
            return; // 지원하지 않으면 기본 로딩
        }

        // 이미 로딩된 이미지는 스킵
        if (img.complete && img.naturalHeight !== 0) {
            return;
        }

        // 로딩 플레이스홀더 추가
        this.addLoadingPlaceholder(img);

        // Intersection Observer로 지연 로딩 구현
        const intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    intersectionObserver.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '50px' // 뷰포트 50px 전에 미리 로드
        });

        intersectionObserver.observe(img);
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
     * 이미지 로딩 실행
     */
    loadImage(img) {
        img.classList.add('image-loading');
        
        const tempImg = new Image();
        tempImg.onload = () => {
            img.src = tempImg.src;
            img.classList.remove('image-loading');
            img.style.backgroundColor = '';
            img.style.backgroundImage = '';
            img.style.animation = '';
        };
        
        tempImg.onerror = () => {
            this.handleImageError(img);
        };
        
        tempImg.src = img.dataset.originalSrc || img.src;
    }

    /**
     * 이미지 미리보기 기능 추가
     */
    addImagePreview(img) {
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
    }

    /**
     * 이미지 에러 처리
     */
    addErrorHandling(img) {
        img.addEventListener('error', () => {
            this.handleImageError(img);
        });
    }

    /**
     * 이미지 로딩 에러 처리
     */
    handleImageError(img) {
        img.classList.remove('image-loading');
        img.style.backgroundColor = '#f8f9fa';
        img.style.backgroundImage = 'none';
        img.style.animation = 'none';
        
        // 에러 플레이스홀더 생성
        const placeholder = document.createElement('div');
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
            placeholder.remove();
            this.loadImage(img);
        };
        placeholder.querySelector('div').appendChild(retryBtn);
        
        img.parentNode.insertBefore(placeholder, img);
        img.style.display = 'none';
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
            setTimeout(() => {
                if (ImageOptimizer.instance) {
                    ImageOptimizer.instance.optimizeImages();
                } else {
                    new ImageOptimizer();
                }
            }, 100);
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