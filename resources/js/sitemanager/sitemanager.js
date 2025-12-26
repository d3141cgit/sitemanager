/**
 * 관리자 페이지 JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // === 페이지 로더 (colorAdmin에서 가져온 기능) ===
    function initPageLoader() {
        // 페이지 로드 시 로더 표시
        document.body.classList.add('loading');
        
        // 페이지 로드 완료 시 로더 숨기기
        window.addEventListener('load', function() {
            const loader = document.getElementById('page-loader');
            if (loader) {
                setTimeout(function() {
                    loader.style.opacity = '0';
                    setTimeout(function() {
                        loader.style.display = 'none';
                        document.body.classList.remove('loading');
                    }, 200);
                }, 300); // 0.3초 후 페이드 아웃
            }
        });
    }

    // === Bootstrap 드롭다운 초기화 ===
    function initDropdowns() {
        // Bootstrap 5 드롭다운 수동 초기화
        const dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
        if (typeof bootstrap !== 'undefined') {
            const dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
        }
    }

    // === Mobile Menu Toggle ===
    function initMobileMenu() {
        const hamburgerBtn = document.getElementById('mobile-menu-toggle');
        const mobileMenuSlide = document.getElementById('mobile-menu-slide');
        const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
        const body = document.body;

        function openMenu() {
            hamburgerBtn?.classList.add('active');
            mobileMenuSlide?.classList.add('active');
            mobileMenuOverlay?.classList.add('active');
            body.style.overflow = 'hidden';
        }

        function closeMenu() {
            hamburgerBtn?.classList.remove('active');
            mobileMenuSlide?.classList.remove('active');
            mobileMenuOverlay?.classList.remove('active');
            body.style.overflow = '';
        }

        // Hamburger button click
        hamburgerBtn?.addEventListener('click', function() {
            if (mobileMenuSlide?.classList.contains('active')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        // Overlay click
        mobileMenuOverlay?.addEventListener('click', closeMenu);

        // Close menu when clicking on a link (but not on dropdown toggles)
        const mobileMenuLinks = mobileMenuSlide?.querySelectorAll('.mobile-menu-list > li > a:not([data-bs-toggle])');
        mobileMenuLinks?.forEach(function(link) {
            link.addEventListener('click', function() {
                // Small delay to allow navigation
                setTimeout(closeMenu, 100);
            });
        });

        // Close menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenuSlide?.classList.contains('active')) {
                closeMenu();
            }
        });

        // Close menu when window is resized to desktop size
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 992) {
                    closeMenu();
                }
            }, 250);
        });
    }

    // === 초기화 실행 ===
    initPageLoader();
    initDropdowns();
    initMobileMenu();

});
