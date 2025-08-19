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

    // === 초기화 실행 ===
    initPageLoader();
    initDropdowns();

});
