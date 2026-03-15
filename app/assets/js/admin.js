// assets/js/admin.js
document.addEventListener('DOMContentLoaded', function () {
    // Initialize Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Generic delete confirmation modal hook (optional)
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    if (deleteConfirmModal) {
        document.querySelectorAll('[data-delete-trigger="true"]').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                const deleteModal = new bootstrap.Modal(deleteConfirmModal);
                deleteModal.show();
            });
        });
    }
});