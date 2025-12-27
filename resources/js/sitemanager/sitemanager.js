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

    // === Sidebar Toggle ===
    function initSidebar() {
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const body = document.body;
        const STORAGE_KEY = 'sitemanager-sidebar-collapsed';

        function isMobile() {
            return window.innerWidth < 992;
        }

        function saveSidebarState(collapsed) {
            if (!isMobile()) {
                try {
                    localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false');
                } catch (e) {
                    console.warn('Failed to save sidebar state to localStorage:', e);
                }
            }
        }

        function loadSidebarState() {
            if (!isMobile()) {
                try {
                    const saved = localStorage.getItem(STORAGE_KEY);
                    return saved === 'true';
                } catch (e) {
                    console.warn('Failed to load sidebar state from localStorage:', e);
                }
            }
            return false; // Default: open on desktop, closed on mobile
        }

        // Auto-expand active dropdown menus
        function expandActiveMenus() {
            const activeMenuItems = sidebar?.querySelectorAll('.sidebar-menu-item.active, .sidebar-submenu-item.active');
            if (activeMenuItems && activeMenuItems.length > 0) {
                activeMenuItems.forEach(function(item) {
                    // Find the closest dropdown parent
                    const dropdown = item.closest('.sidebar-menu-dropdown');
                    if (dropdown) {
                        const toggle = dropdown.querySelector('.sidebar-menu-item[data-bs-toggle="collapse"]');
                        const targetId = toggle?.getAttribute('data-bs-target');
                        if (targetId && typeof bootstrap !== 'undefined') {
                            const collapseElement = document.querySelector(targetId);
                            if (collapseElement) {
                                const collapse = new bootstrap.Collapse(collapseElement, {
                                    toggle: false
                                });
                                collapse.show();
                            }
                        }
                    }
                });
            }
        }

        function openSidebar() {
            sidebarToggle?.classList.add('active');
            sidebar?.classList.add('active');
            if (isMobile()) {
                sidebarOverlay?.classList.add('active');
                body.style.overflow = 'hidden';
                // Move button to right edge of sidebar when sidebar opens on mobile
                if (sidebarToggle) {
                    sidebarToggle.style.left = '270px';
                }
            } else {
                body.classList.remove('sidebar-collapsed');
                saveSidebarState(false);
                // Move button to right edge of sidebar when sidebar is open
                if (sidebarToggle) {
                    sidebarToggle.style.left = '270px';
                }
            }
        }

        function closeSidebar() {
            sidebarToggle?.classList.remove('active');
            sidebar?.classList.remove('active');
            sidebarOverlay?.classList.remove('active');
            body.style.overflow = '';
            if (!isMobile()) {
                body.classList.add('sidebar-collapsed');
                saveSidebarState(true);
                // Move button to left edge when sidebar is closed
                if (sidebarToggle) {
                    sidebarToggle.style.left = '-10px';
                }
            } else {
                // Keep button visible on the left when sidebar closes on mobile
                if (sidebarToggle) {
                    sidebarToggle.style.left = '-10px';
                }
            }
        }

        function toggleSidebar() {
            if (isMobile()) {
                // Mobile: toggle overlay and sidebar
                if (sidebar?.classList.contains('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            } else {
                // Desktop: toggle collapsed state
                if (body.classList.contains('sidebar-collapsed')) {
                    openSidebar();
                } else {
                    closeSidebar();
                }
            }
        }

        // Hamburger button click
        sidebarToggle?.addEventListener('click', toggleSidebar);

        // Overlay click (mobile only)
        sidebarOverlay?.addEventListener('click', function() {
            if (isMobile()) {
                closeSidebar();
            }
        });

        // Close sidebar on escape key (mobile only)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar?.classList.contains('active') && isMobile()) {
                closeSidebar();
            }
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const nowMobile = isMobile();
                if (nowMobile) {
                    // Mobile: always close sidebar and button on left
                    body.classList.remove('sidebar-collapsed');
                    sidebar?.classList.remove('active');
                    sidebarOverlay?.classList.remove('active');
                    body.style.overflow = '';
                    if (sidebarToggle) {
                        sidebarToggle.style.left = '-10px';
                        sidebarToggle.classList.remove('active');
                    }
                } else {
                    // Desktop: restore state from localStorage
                    sidebarOverlay?.classList.remove('active');
                    body.style.overflow = '';
                    const isCollapsed = loadSidebarState();
                    if (isCollapsed) {
                        body.classList.add('sidebar-collapsed');
                        sidebar?.classList.remove('active');
                        if (sidebarToggle) {
                            sidebarToggle.style.left = '-10px';
                            sidebarToggle.classList.remove('active');
                        }
                    } else {
                        body.classList.remove('sidebar-collapsed');
                        sidebar?.classList.add('active');
                        if (sidebarToggle) {
                            sidebarToggle.style.left = '270px';
                            sidebarToggle.classList.add('active');
                        }
                    }
                }
            }, 250);
        });
        
        // Initialize sidebar state on load
        if (isMobile()) {
            // Mobile: always start with sidebar closed
            if (sidebarToggle) {
                sidebarToggle.style.left = '-10px';
                sidebarToggle.classList.remove('active');
            }
            if (sidebar) {
                sidebar.classList.remove('active');
            }
        } else {
            // Desktop: load state from localStorage or default to open
            const isCollapsed = loadSidebarState();
            if (isCollapsed) {
                body.classList.add('sidebar-collapsed');
                if (sidebarToggle) {
                    sidebarToggle.style.left = '-10px';
                    sidebarToggle.classList.remove('active');
                }
                if (sidebar) {
                    sidebar.classList.remove('active');
                }
            } else {
                body.classList.remove('sidebar-collapsed');
                if (sidebarToggle) {
                    sidebarToggle.style.left = '270px';
                    sidebarToggle.classList.add('active');
                }
                if (sidebar) {
                    sidebar.classList.add('active');
                }
            }
        }

        // Close sidebar on mobile when clicking a link (but not dropdown toggles)
        const sidebarLinks = sidebar?.querySelectorAll('.sidebar-menu-item:not([data-bs-toggle]), .sidebar-submenu-item');
        sidebarLinks?.forEach(function(link) {
            link.addEventListener('click', function() {
                // Small delay to allow navigation
                setTimeout(function() {
                    if (isMobile()) {
                        closeSidebar();
                    }
                }, 100);
            });
        });

        // Auto-expand active menus on load
        expandActiveMenus();
    }

    // === 초기화 실행 ===
    initPageLoader();
    initDropdowns();
    initSidebar();

});
