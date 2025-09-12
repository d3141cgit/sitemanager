/**
 * Sortable Table Functionality
 * 범용 테이블 정렬 스크립트
 */
document.addEventListener('DOMContentLoaded', function() {
    const sortableTables = document.querySelectorAll('.sortable-table');
    
    sortableTables.forEach(function(table) {
        initSortableTable(table);
    });
});

function initSortableTable(table) {
    const currentSort = table.dataset.currentSort || 'created_at';
    const currentOrder = table.dataset.currentOrder || 'desc';
    
    // 현재 정렬 상태를 시각적으로 표시
    updateSortIcons(table, currentSort, currentOrder);
    
    // 정렬 가능한 헤더에 클릭 이벤트 추가
    const sortableHeaders = table.querySelectorAll('th.sortable');
    
    sortableHeaders.forEach(function(header) {
        header.style.cursor = 'pointer';
        
        header.addEventListener('click', function() {
            const sortField = this.dataset.sort;
            const newOrder = calculateNewOrder(currentSort, currentOrder, sortField);
            
            // URL 파라미터 업데이트 및 페이지 리로드
            const url = new URL(window.location);
            url.searchParams.set('sort', sortField);
            url.searchParams.set('order', newOrder);
            url.searchParams.set('page', '1'); // 정렬 시 첫 페이지로
            
            window.location.href = url.toString();
        });
    });
}

function calculateNewOrder(currentSort, currentOrder, newSort) {
    if (currentSort === newSort) {
        // 같은 필드 클릭 시 순서 토글
        return currentOrder === 'asc' ? 'desc' : 'asc';
    } else {
        // 다른 필드 클릭 시 기본값 (desc)
        return 'desc';
    }
}

function updateSortIcons(table, currentSort, currentOrder) {
    const headers = table.querySelectorAll('th.sortable');
    
    headers.forEach(function(header) {
        const icon = header.querySelector('.sort-icon');
        const sortField = header.dataset.sort;
        
        if (sortField === currentSort) {
            // 현재 정렬 필드
            if (currentOrder === 'asc') {
                icon.className = 'sort-icon bi bi-chevron-up';
            } else {
                icon.className = 'sort-icon bi bi-chevron-down';
            }
        } else {
            // 정렬되지 않은 필드
            icon.className = 'sort-icon bi bi-chevron-expand';
        }
    });
}