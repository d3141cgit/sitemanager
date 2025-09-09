/**
 * SiteManager 통합 알림 및 모달 시스템
 * SweetAlert2를 기본으로 하고, fallback으로 기본 alert/confirm 사용
 */

class SiteManagerNotifications {
    constructor() {
        this.hasSweetAlert = typeof Swal !== 'undefined';
        this.hasBootstrap = typeof bootstrap !== 'undefined';
    }

    /**
     * 성공 메시지 표시
     * @param {string} message - 메시지 내용
     * @param {string} title - 제목 (선택사항)
     */
    success(message, title = 'Success') {
        if (this.hasSweetAlert) {
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            alert(`${title}: ${message}`);
        }
    }

    /**
     * 에러 메시지 표시
     * @param {string} message - 메시지 내용
     * @param {string} title - 제목 (선택사항)
     */
    error(message, title = 'Error') {
        if (this.hasSweetAlert) {
            Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            alert(`${title}: ${message}`);
        }
    }

    /**
     * 경고 메시지 표시
     * @param {string} message - 메시지 내용
     * @param {string} title - 제목 (선택사항)
     */
    warning(message, title = 'Warning') {
        if (this.hasSweetAlert) {
            Swal.fire({
                icon: 'warning',
                title: title,
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            alert(`${title}: ${message}`);
        }
    }

    /**
     * 정보 메시지 표시
     * @param {string} message - 메시지 내용
     * @param {string} title - 제목 (선택사항)
     */
    info(message, title = 'Information') {
        if (this.hasSweetAlert) {
            Swal.fire({
                icon: 'info',
                title: title,
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            alert(`${title}: ${message}`);
        }
    }

    /**
     * 확인 대화상자 표시
     * @param {string} message - 확인 메시지
     * @param {string} title - 제목 (선택사항)
     * @param {Object} options - 추가 옵션
     * @returns {Promise<boolean>} - 사용자 선택 결과
     */
    confirm(message, title = 'Confirm', options = {}) {
        const defaultOptions = {
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            confirmButtonColor: '#d33',
            ...options
        };

        if (this.hasSweetAlert) {
            return Swal.fire({
                icon: 'question',
                title: title,
                text: message,
                showCancelButton: true,
                confirmButtonText: defaultOptions.confirmButtonText,
                cancelButtonText: defaultOptions.cancelButtonText,
                confirmButtonColor: defaultOptions.confirmButtonColor,
                reverseButtons: true
            }).then((result) => result.isConfirmed);
        } else {
            return Promise.resolve(confirm(`${title}: ${message}`));
        }
    }

    /**
     * 삭제 확인 대화상자 (특화)
     * @param {string} item - 삭제할 항목명 (선택사항)
     * @returns {Promise<boolean>} - 사용자 선택 결과
     */
    confirmDelete(item = 'this item') {
        return this.confirm(
            `Are you sure you want to delete ${item}? This action cannot be undone.`,
            'Delete Confirmation',
            {
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#d33'
            }
        );
    }

    /**
     * 로딩 표시
     * @param {string} message - 로딩 메시지
     */
    showLoading(message = 'Loading...') {
        if (this.hasSweetAlert) {
            Swal.fire({
                title: message,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    }

    /**
     * 로딩 숨기기
     */
    hideLoading() {
        if (this.hasSweetAlert) {
            Swal.close();
        }
    }

    /**
     * 토스트 알림 표시 (우상단)
     * @param {string} message - 메시지 내용
     * @param {string} type - 알림 타입 (success, error, warning, info)
     * @param {number} duration - 표시 시간 (ms)
     */
    toast(message, type = 'info', duration = 5000) {
        if (this.hasSweetAlert && Swal.mixin) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: type,
                title: message
            });
        } else {
            // Fallback: Bootstrap alert
            this.showBootstrapAlert(message, type, duration);
        }
    }

    /**
     * Bootstrap Alert fallback
     * @param {string} message - 메시지 내용
     * @param {string} type - 알림 타입
     * @param {number} duration - 표시 시간 (ms)
     */
    showBootstrapAlert(message, type = 'info', duration = 5000) {
        // 기존 알림 제거
        document.querySelectorAll('.sitemanager-alert').forEach(alert => alert.remove());
        
        const alertType = type === 'error' ? 'danger' : type;
        const alert = document.createElement('div');
        alert.className = `alert alert-${alertType} alert-dismissible fade show position-fixed sitemanager-alert`;
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // 자동 제거
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, duration);
    }
}

class SiteManagerModals {
    constructor() {
        this.hasBootstrap = typeof bootstrap !== 'undefined';
    }

    /**
     * 이미지 미리보기 모달
     * @param {string} imageUrl - 이미지 URL
     * @param {string} imageName - 이미지 이름
     * @param {string} downloadUrl - 다운로드 URL (선택사항)
     */
    showImagePreview(imageUrl, imageName = 'Image', downloadUrl = null) {
        // 기존 모달 제거
        const existingModal = document.getElementById('sitemanager-image-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const finalDownloadUrl = downloadUrl || imageUrl;
        const modalId = 'sitemanager-image-modal';
        
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imageModalLabel">${imageName}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${imageUrl}" alt="${imageName}" class="img-fluid" style="max-width: 100%; max-height: 70vh;">
                        </div>
                        <div class="modal-footer">
                            <a href="${finalDownloadUrl}" class="btn btn-primary" download>
                                <i class="bi bi-download"></i> Download
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        if (this.hasBootstrap) {
            const modal = new bootstrap.Modal(document.getElementById(modalId));
            modal.show();
            
            // 모달이 닫힐 때 DOM에서 제거
            document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
    }

    /**
     * 커스텀 모달 생성
     * @param {Object} options - 모달 옵션
     */
    showCustomModal(options) {
        const defaults = {
            id: 'sitemanager-custom-modal',
            title: 'Modal',
            body: '',
            size: '', // '', 'lg', 'xl', 'sm'
            footer: true,
            closeButton: true,
            backdrop: true
        };

        const config = { ...defaults, ...options };
        
        // 기존 모달 제거
        const existingModal = document.getElementById(config.id);
        if (existingModal) {
            existingModal.remove();
        }

        const sizeClass = config.size ? `modal-${config.size}` : '';
        const backdrop = config.backdrop ? '' : 'data-bs-backdrop="static"';
        
        const modalHtml = `
            <div class="modal fade" id="${config.id}" tabindex="-1" ${backdrop}>
                <div class="modal-dialog ${sizeClass}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${config.title}</h5>
                            ${config.closeButton ? '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' : ''}
                        </div>
                        <div class="modal-body">
                            ${config.body}
                        </div>
                        ${config.footer ? `
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        if (this.hasBootstrap) {
            const modal = new bootstrap.Modal(document.getElementById(config.id));
            modal.show();
            
            // 모달이 닫힐 때 DOM에서 제거
            document.getElementById(config.id).addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
            
            return modal;
        }
    }
}

// 전역 인스턴스 생성
window.SiteManager = window.SiteManager || {};
window.SiteManager.notifications = new SiteManagerNotifications();
window.SiteManager.modals = new SiteManagerModals();

// 편의 함수들 (하위 호환성)
window.showAlert = function(message, type = 'info') {
    if (type === 'success') {
        window.SiteManager.notifications.success(message);
    } else if (type === 'error' || type === 'danger') {
        window.SiteManager.notifications.error(message);
    } else if (type === 'warning') {
        window.SiteManager.notifications.warning(message);
    } else {
        window.SiteManager.notifications.info(message);
    }
};

window.showImageModal = function(imageUrl, imageName, downloadUrl = null) {
    window.SiteManager.modals.showImagePreview(imageUrl, imageName, downloadUrl);
};

window.confirmDelete = function(item = 'this item') {
    return window.SiteManager.notifications.confirmDelete(item);
};

// Toast 편의 함수
window.showToast = function(message, type = 'info', duration = 5000) {
    window.SiteManager.notifications.toast(message, type, duration);
};
