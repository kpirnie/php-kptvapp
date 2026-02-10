/**
 * DataTables Plain/Tailwind Theme Helper
 * 
 * Provides modal and notification functionality for plain and Tailwind themes
 * that don't have a framework providing these features.
 * 
 * @since   1.1.0
 * @author  Kevin Pirnie <me@kpirnie.com>
 * @package KPT/DataTables
 */

const KPDataTablesPlain = {
    /**
     * Show a modal
     * @param {string} modalId - The modal element ID
     */
    showModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('kp-dt-open');
            modal.classList.add('kp-dt-open-tailwind');
            document.body.style.overflow = 'hidden';
        }
    },

    /**
     * Hide a modal
     * @param {string} modalId - The modal element ID
     */
    hideModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('kp-dt-open');
            modal.classList.remove('kp-dt-open-tailwind');
            document.body.style.overflow = '';
        }
    },

    /**
     * Show a notification
     * @param {string} message - The message to display
     * @param {string} status - The status type (success, danger, warning)
     */
    notification: function(message, status = 'success') {
        const container = document.querySelector('.kp-dt-notification-container') || this.createNotificationContainer();
        
        const notification = document.createElement('div');
        notification.className = `kp-dt-notification kp-dt-notification-${status} kp-dt-notification-tailwind kp-dt-notification-${status}-tailwind`;
        notification.textContent = message;
        
        container.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    },

    /**
     * Create notification container if not exists
     * @returns {HTMLElement} The notification container
     */
    createNotificationContainer: function() {
        const container = document.createElement('div');
        container.className = 'kp-dt-notification-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1040; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
        return container;
    },

    /**
     * Show a confirmation dialog
     * @param {string} message - The confirmation message
     * @returns {Promise} Resolves if confirmed, rejects if cancelled
     */
    confirm: function(message) {
        return new Promise((resolve, reject) => {
            // Create overlay
            const overlay = document.createElement('div');
            overlay.className = 'kp-dt-modal kp-dt-modal-tailwind kp-dt-open kp-dt-open-tailwind';
            overlay.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1050;';
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = 'kp-dt-modal-dialog kp-dt-modal-dialog-tailwind';
            dialog.style.cssText = 'background: white; padding: 30px; border-radius: 4px; max-width: 400px; text-align: center;';
            
            dialog.innerHTML = `
                <p style="margin-bottom: 20px;">${message}</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button class="kp-dt-button kp-dt-button-tailwind kp-dt-confirm-cancel" style="padding: 8px 24px;">Cancel</button>
                    <button class="kp-dt-button kp-dt-button-primary kp-dt-button-tailwind kp-dt-button-primary-tailwind kp-dt-confirm-ok" style="padding: 8px 24px;">Confirm</button>
                </div>
            `;
            
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            
            // Handle clicks
            dialog.querySelector('.kp-dt-confirm-ok').addEventListener('click', () => {
                overlay.remove();
                resolve();
            });
            
            dialog.querySelector('.kp-dt-confirm-cancel').addEventListener('click', () => {
                overlay.remove();
                reject();
            });
            
            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    reject();
                }
            });
        });
    }
};

// Also add Bootstrap helper
const KPDataTablesBootstrap = {
    /**
     * Show a notification using Bootstrap toast
     * @param {string} message - The message to display
     * @param {string} status - The status type (success, danger, warning)
     */
    notification: function(message, status = 'success') {
        let container = document.querySelector('.kp-dt-toast-container-bootstrap');
        if (!container) {
            container = document.createElement('div');
            container.className = 'kp-dt-toast-container-bootstrap toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
        }
        
        const bgClass = status === 'success' ? 'bg-success' : (status === 'danger' ? 'bg-danger' : 'bg-warning');
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white ${bgClass} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        container.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    },

    /**
     * Show a confirmation dialog using Bootstrap modal
     * @param {string} message - The confirmation message
     * @returns {Promise} Resolves if confirmed, rejects if cancelled
     */
    confirm: function(message) {
        return new Promise((resolve, reject) => {
            const modalId = 'kp-dt-confirm-modal-' + Date.now();
            
            const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body text-center py-4">
                                <p class="mb-4">${message}</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary kp-dt-confirm-ok">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = document.getElementById(modalId);
            const bsModal = new bootstrap.Modal(modal);
            
            modal.querySelector('.kp-dt-confirm-ok').addEventListener('click', () => {
                bsModal.hide();
                resolve();
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
                reject();
            });
            
            bsModal.show();
        });
    }
};

// Make available globally
window.KPDataTablesPlain = KPDataTablesPlain;
window.KPDataTablesBootstrap = KPDataTablesBootstrap;
