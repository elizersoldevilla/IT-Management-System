function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: '<i class="fas fa-check-circle text-success me-2"></i>',
        error: '<i class="fas fa-exclamation-circle text-danger me-2"></i>',
        warning: '<i class="fas fa-exclamation-triangle text-warning me-2"></i>',
        info: '<i class="fas fa-info-circle text-info me-2"></i>'
    };

    const bgClass = {
        success: 'bg-success bg-opacity-10 border-success border-opacity-25',
        error: 'bg-danger bg-opacity-10 border-danger border-opacity-25',
        warning: 'bg-warning bg-opacity-10 border-warning border-opacity-25',
        info: 'bg-info bg-opacity-10 border-info border-opacity-25'
    };

    toast.innerHTML = `
        <div class="toast-header ${bgClass[type] || bgClass.info} border-0 py-3 px-4">
            <div class="d-flex align-items-center">
                ${icons[type] || icons.info}
                <span class="text-white small fw-medium">${escapeHtml(message)}</span>
            </div>
            <button type="button" class="btn-close btn-close-white ms-2" onclick="removeToast(this.closest('.toast'))"></button>
        </div>
    `;

    container.appendChild(toast);

    if (duration > 0) {
        setTimeout(() => {
            removeToast(toast);
        }, duration);
    }

    return toast;
}

function removeToast(toast) {
    if (!toast || toast.classList.contains('toast-removing')) return;
    toast.classList.add('toast-removing');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 250);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('[data-flash]');
    flashMessages.forEach(el => {
        const type = el.getAttribute('data-flash-type') || 'info';
        showToast(el.textContent, type);
    });
});
