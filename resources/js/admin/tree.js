/**
 * Menu Tree Management Script
 * 메뉴 트리 관리 스크립트
 */
class MenuTreeManager {
    constructor(options = {}) {
        this.menuData = options.menuData || [];
        this.invalidRouteMenus = options.invalidRouteMenus || [];
        this.moveUrl = options.moveUrl || '';
        this.treeUrl = options.treeUrl || '';
        this.editBaseUrl = options.editBaseUrl || '';
        this.csrfToken = options.csrfToken || '';
        
        this.isCommandKeyPressed = false;
        this.isDragging = false;
        
        // Create lookup set for invalid route menu IDs for faster checking
        this.invalidRouteMenuIds = new Set(this.invalidRouteMenus.map(menu => menu.id));
        
        this.init();
    }
    
    init() {
        // SortableJS 라이브러리 확인
        if (typeof Sortable === 'undefined') {
            alert('SortableJS 라이브러리를 로드할 수 없습니다. 인터넷 연결을 확인해주세요.');
            return;
        }
        
        this.setupEventListeners();
        this.buildMenuTree();
    }
    
    setupEventListeners() {
        // 키보드 이벤트 리스너
        document.addEventListener('keydown', (e) => {
            if (e.metaKey || e.ctrlKey) { // macOS Command 또는 Windows/Linux Ctrl
                this.isCommandKeyPressed = true;
                if (this.isDragging) {
                    document.body.classList.add('command-mode');
                    this.disableOtherSortables();
                }
            }
        });
        
        document.addEventListener('keyup', (e) => {
            if (!e.metaKey && !e.ctrlKey) {
                this.isCommandKeyPressed = false;
                document.body.classList.remove('command-mode');
                this.enableAllSortables();
            }
        });
        
        // 전역 editMenu 함수 설정
        window.editMenu = (menuId) => {
            const editUrl = this.editBaseUrl.replace('/admin/menus', `/admin/menus/${menuId}/edit`);
            // window.open(editUrl, '_blank');
            window.location.href = editUrl;
        };
    }
    
    disableOtherSortables() {
        if (window.sortableInstances) {
            window.sortableInstances.forEach(instance => {
                try {
                    instance.option('disabled', true);
                } catch (e) {
                    /* warn removed */
                }
            });
        }
    }
    
    enableAllSortables() {
        if (window.sortableInstances) {
            window.sortableInstances.forEach(instance => {
                try {
                    instance.option('disabled', false);
                } catch (e) {
                    /* warn removed */
                }
            });
        }
    }
    
    buildMenuTree() {
        const treeContainer = document.getElementById('menu-tree');
        
        // 기존 Sortable 인스턴스들 제거
        if (window.sortableInstances) {
            window.sortableInstances.forEach(instance => {
                try {
                    instance.destroy();
                } catch (e) {
                    /* warn removed */
                }
            });
        }
        window.sortableInstances = [];
        
        // 컨테이너 초기화
        treeContainer.innerHTML = '';
        
        // 섹션별로 그룹화
        const sections = {};
        this.menuData.forEach(menu => {
            const section = menu.section || 0;
            if (!sections[section]) {
                sections[section] = [];
            }
            sections[section].push(menu);
        });
        
        // 각 섹션을 렌더링
        Object.keys(sections).sort((a, b) => a - b).forEach(section => {
            const sectionMenus = sections[section];
            const rootMenus = sectionMenus.filter(m => !m.parent_id);
            
            rootMenus.forEach(menu => {
                const menuElement = this.createMenuElement(menu, sectionMenus);
                treeContainer.appendChild(menuElement);
            });
        });
        
        // Sortable 초기화
        this.initializeSortable();
    }
    
    createMenuElement(menu, allMenus) {
        const escapeHtml = (str) => {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };
        
        const div = document.createElement('div');
        div.className = 'menu-item';
        div.setAttribute('data-id', menu.id);
        div.setAttribute('data-section', menu.section);
        
        // Hidden 메뉴인 경우 클래스 추가
        if (menu.hidden === 1 || menu.hidden === true) {
            div.classList.add('menu-hidden');
        }
        
        // List 권한(1)이 없는 경우 클래스 추가 (단, FullControl(128) 권한이 있으면 제외)
        if (!(menu.permission & 1) && !(menu.permission & 128)) {
            div.classList.add('menu-no-access');
        }
        
        div.setAttribute('data-parent', menu.parent_id || '');
        
        const children = allMenus.filter(m => m.parent_id === menu.id);

        // type / root icon
        const isRoot = !menu.parent_id;
        let iconHtml = '';
        if (isRoot) {
            iconHtml = `<i class="bi bi-house-door text-primary"></i>`;
        } else if (menu.type === 'text') {
            iconHtml = `<i class="bi bi-dot text-muted"></i>`;
        } else if (menu.type === 'link') {
            iconHtml = `<i class="bi bi-link-45deg text-info"></i>`;
        } else if (menu.type === 'page') {
            iconHtml = `<i class="bi bi-file-earmark text-secondary"></i>`;
        } else {
            iconHtml = `<i class="bi bi-arrow-right-short text-muted"></i>`;
        }

        // thumbnail if exists (우선순위: thumbnail > seo > header)
        let thumbnailHtml = '';
        if (menu.images) {
            let thumbUrl = null;
            let imageData = null;
            
            // 우선순위에 따라 이미지 선택
            if (menu.images.thumbnail) {
                imageData = menu.images.thumbnail;
            } else if (menu.images.seo) {
                imageData = menu.images.seo;
            } else if (menu.images.header) {
                imageData = menu.images.header;
            }
            
            if (imageData) {
                // 객체인 경우 url 속성에서 추출 (백엔드에서 이미 처리된 URL)
                if (typeof imageData === 'object' && imageData.url) {
                    thumbUrl = imageData.url;
                } else if (typeof imageData === 'string') {
                    thumbUrl = imageData;
                }
                
                // 문자열로 변환하고 빈 값 체크
                thumbUrl = String(thumbUrl || '').trim();
                
                // 백엔드에서 이미 완전한 URL이 처리되어 왔으므로 그대로 사용
                if (thumbUrl) {
                    thumbnailHtml = `<img src="${escapeHtml(thumbUrl)}" alt="${escapeHtml(menu.title)}" class="menu-thumbnail">`;
                }
            }
        }

        // target (show only when type !== 'text')
        let targetHtml = '';
        if (menu.type !== 'text' && menu.target) {
            let warningIcon = '';
            // Check if this menu has an invalid route
            if (menu.type === 'route' && this.invalidRouteMenuIds.has(menu.id)) {
                warningIcon = ' <i class="bi bi-exclamation-triangle text-warning" title="Invalid route: This route no longer exists"></i>';
            }
            targetHtml = `<div class="target">${escapeHtml(menu.target)}${warningIcon}</div>`;
        }
        
        div.innerHTML = `
            <div class="menu-content">
                <div class="menu-title-wrapper">
                    ${iconHtml}
                    ${thumbnailHtml}
                    <span class="menu-title" onclick="editMenu(${menu.id})">
                        ${escapeHtml(menu.title)}
                    </span>
                </div>
                ${targetHtml}
            </div>
        `;
        
        // 하위 메뉴가 있으면 추가
        if (children.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'menu-children';
            childrenContainer.setAttribute('data-parent-id', menu.id);
            children.forEach(child => {
                const childElement = this.createMenuElement(child, allMenus);
                childrenContainer.appendChild(childElement);
            });
            div.appendChild(childrenContainer);
        }
        
        return div;
    }
    
    initializeSortable() {
        // 메인 컨테이너 존재 확인
        const mainContainer = document.getElementById('menu-tree');
        if (!mainContainer) {
            console.error('❌ Main container #menu-tree not found!');
            return;
        }
        
        // 전역 인스턴스 배열 초기화
        if (!window.sortableInstances) {
            window.sortableInstances = [];
        }
        
        try {
            // 메인 컨테이너
            const mainSortable = new Sortable(mainContainer, {
                group: {
                    name: 'nested',
                    pull: true,
                    put: true
                },
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onStart: (evt) => {
                    this.isDragging = true;
                    document.body.classList.add('dragging');
                    
                    if (this.isCommandKeyPressed) {
                        document.body.classList.add('command-mode');
                    }
                },
                onEnd: (evt) => {
                    this.isDragging = false;
                    document.body.classList.remove('dragging');
                    document.body.classList.remove('command-mode');
                    this.enableAllSortables();
                    this.handleMove(evt);
                }
            });
            window.sortableInstances.push(mainSortable);
            
            // 하위 메뉴 컨테이너들
            const childContainers = document.querySelectorAll('.menu-children');
            
            childContainers.forEach((container) => {
                const childSortable = new Sortable(container, {
                    group: {
                        name: 'nested',
                        pull: true,
                        put: true
                    },
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    fallbackOnBody: true,
                    swapThreshold: 0.65,
                    onStart: (evt) => {
                        this.isDragging = true;
                        document.body.classList.add('dragging');
                        
                        if (this.isCommandKeyPressed) {
                            document.body.classList.add('command-mode');
                        }
                    },
                    onEnd: (evt) => {
                        this.isDragging = false;
                        document.body.classList.remove('dragging');
                        document.body.classList.remove('command-mode');
                        this.enableAllSortables();
                        this.handleMove(evt);
                    }
                });
                window.sortableInstances.push(childSortable);
            });
        } catch (error) {
            console.error('❌ Error initializing Sortable:', error);
        }
    }
    
    handleMove(evt) {
        const menuId = parseInt(evt.item.getAttribute('data-id'));
        
        // Command 모드: 다른 메뉴 위에 드롭된 경우 하위 메뉴로 만들기
        if (this.isCommandKeyPressed) {
            const targetMenuItem = this.findTargetMenuItem(evt);
            if (targetMenuItem) {
                const targetMenuId = parseInt(targetMenuItem.getAttribute('data-id'));
                
                // 하위 메뉴 컨테이너가 없으면 생성
                let childrenContainer = targetMenuItem.querySelector('.menu-children');
                if (!childrenContainer) {
                    childrenContainer = document.createElement('div');
                    childrenContainer.className = 'menu-children';
                    childrenContainer.setAttribute('data-parent-id', targetMenuId);
                    targetMenuItem.appendChild(childrenContainer);
                    
                    // 새 컨테이너에 Sortable 초기화
                    const newSortable = new Sortable(childrenContainer, {
                        group: {
                            name: 'nested',
                            pull: true,
                            put: true
                        },
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        chosenClass: 'sortable-chosen',
                        dragClass: 'sortable-drag',
                        fallbackOnBody: true,
                        swapThreshold: 0.65,
                        onStart: (evt) => {
                            this.isDragging = true;
                            document.body.classList.add('dragging');
                            
                            if (this.isCommandKeyPressed) {
                                document.body.classList.add('command-mode');
                            }
                        },
                        onEnd: (evt) => {
                            this.isDragging = false;
                            document.body.classList.remove('dragging');
                            document.body.classList.remove('command-mode');
                            this.enableAllSortables();
                            this.handleMove(evt);
                        }
                    });
                    window.sortableInstances.push(newSortable);
                }
                
                // 드래그된 아이템을 새 컨테이너로 이동
                childrenContainer.appendChild(evt.item);
                
                // 백엔드로 이동 요청 (첫 번째 자식으로)
                this.moveMenu(menuId, targetMenuId, 1);
                return;
            }
        }
        
        // 일반 모드: 기존 로직 그대로
        
        // 부모 ID 결정 로직 개선
        let parentId = null;
        if (evt.to.classList.contains('menu-children')) {
            // .menu-children 컨테이너에 드롭된 경우
            const parentDataId = evt.to.getAttribute('data-parent-id');
            if (parentDataId && parentDataId !== 'null') {
                parentId = parseInt(parentDataId);
            }
        } else if (evt.to.id === 'menu-tree') {
            // 메인 컨테이너에 드롭된 경우 (루트 레벨)
            parentId = null;
        }
        
        const position = evt.newIndex + 1; // SortableJS는 0-based, 백엔드는 1-based
        
        // 루트 레벨 이동 시에만 target_section 계산
        let targetSection = null;
        if (!parentId) { // 루트 레벨 이동일 때만
            const rootMenus = Array.from(document.querySelectorAll('.menu-item[data-parent-id="null"]'));
            const targetSectionMapping = rootMenus.map((item, index) => {
                const itemId = parseInt(item.getAttribute('data-id'));
                const section = parseInt(item.getAttribute('data-section') || (index + 1));
                return { id: itemId, section: section, position: index + 1 };
            });
            
            // position에 해당하는 섹션 번호 계산
            if (position <= targetSectionMapping.length) {
                targetSection = position; // position을 새로운 section 번호로 사용
            } else {
                targetSection = targetSectionMapping.length + 1;
            }
        }
        
        // 백엔드로 이동 요청
        this.moveMenu(menuId, parentId, position, targetSection);
    }
    
    moveMenu(menuId, parentId, position, targetSection = null) {
        const requestData = {
            id: menuId,
            parent_id: parentId,
            position: position,
            is_root_level: !parentId,
            original_section: null, // 백엔드에서 계산
        };
        
        // targetSection이 null이 아닐 때만 포함 (루트 레벨 이동시에만)
        if (targetSection !== null) {
            requestData.target_section = targetSection;
        }
        
        axios.post(this.moveUrl, requestData, {
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).then(response => {
            if (response.data && response.data.success) {
                // 성공 알림
                this.showToast('성공!', response.data.message, 'success');
                
                // 최신 메뉴 데이터를 서버에서 가져와서 UI 업데이트
                this.refreshMenuTree();
            } else {
                this.showToast('오류!', response.data.message || '이동에 실패했습니다.', 'error');
                window.location.reload(true);
            }
        }).catch(error => {
            console.error('❌ Full error object:', error);
            
            if (error.response) {
                console.error('📡 Response error status:', error.response.status);
                console.error('📡 Response error data:', error.response.data);
                console.error('📡 Response error headers:', error.response.headers);
            } else if (error.request) {
                console.error('📡 Request error (no response):', error.request);
            } else {
                console.error('📡 Setup error:', error.message);
            }
            
            console.error('📡 Error config:', error.config);
            
            this.showToast('오류!', '네트워크 오류가 발생했습니다.', 'error');
            location.reload();
        });
    }
    
    findTargetMenuItem(evt) {
        // 마우스 위치 기반으로 타겟 메뉴 찾기
        const mouseX = evt.originalEvent?.clientX || evt.clientX;
        const mouseY = evt.originalEvent?.clientY || evt.clientY;
        
        if (!mouseX || !mouseY) {
            return null;
        }
        
        // 마우스 위치에 있는 모든 요소들 가져오기
        const elementsAtPoint = document.elementsFromPoint(mouseX, mouseY);
        
        // 메뉴 아이템 찾기
        for (let element of elementsAtPoint) {
            if (element.classList.contains('menu-item')) {
                const targetId = element.getAttribute('data-id');
                const draggedId = evt.item.getAttribute('data-id');
                
                // 자기 자신이나 자신의 하위 메뉴는 제외
                if (targetId !== draggedId && !this.isDescendantOf(element, evt.item)) {
                    return element;
                }
            }
        }
        
        return null;
    }
    
    isDescendantOf(ancestor, descendant) {
        const ancestorId = ancestor.getAttribute('data-id');
        const childrenContainer = descendant.querySelector('.menu-children');
        
        if (!childrenContainer) return false;
        
        const descendants = childrenContainer.querySelectorAll('[data-id]');
        for (let desc of descendants) {
            if (desc.getAttribute('data-id') === ancestorId) {
                return true;
            }
        }
        return false;
    }
    
    showToast(title, message, type) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title,
                text: message,
                icon: type,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        } else {
            // SweetAlert2가 없으면 기본 alert 사용
            alert(`${title}: ${message}`);
        }
    }
    
    refreshMenuTree() {
        // 트리 데이터만 가져오는 API 엔드포인트 호출
        axios.get(this.treeUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response => {
            // 응답이 JSON 형태인지 확인
            if (response.data && Array.isArray(response.data)) {
                // menuData 업데이트
                this.menuData.length = 0; // 배열 비우기
                this.menuData.push(...response.data); // 새 데이터 추가
                
                this.buildMenuTree(); // 트리 재구성
            } else {
                window.location.reload(true);
            }
        }).catch(error => {
            console.error('❌ Failed to refresh menu tree:', error);
            window.location.reload(true);
        });
    }
}

// Export for use
window.MenuTreeManager = MenuTreeManager;
