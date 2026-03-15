// Base JavaScript (layout v2)
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        const openSidebar = () => {
            sidebar.classList.add('open');
            overlay.classList.add('show');
        };

        const closeSidebar = () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        };

        document.querySelectorAll('.btn-sidebar-toggle').forEach((btn) => {
            btn.addEventListener('click', openSidebar);
        });

        overlay.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('.sidebar-link').forEach((link) => {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
        });
    }

    // Initialize Bootstrap tooltips if any
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const userMenu = document.querySelector('[data-user-menu]');
    const userMenuTrigger = document.querySelector('[data-user-menu-trigger]');
    const userMenuPanel = document.querySelector('[data-user-menu-panel]');

    if (userMenu && userMenuTrigger && userMenuPanel) {
        const closeUserMenu = () => {
            userMenuPanel.classList.remove('show');
            userMenuTrigger.setAttribute('aria-expanded', 'false');
        };

        userMenuTrigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const isOpen = userMenuPanel.classList.contains('show');
            if (isOpen) {
                closeUserMenu();
                return;
            }

            userMenuPanel.classList.add('show');
            userMenuTrigger.setAttribute('aria-expanded', 'true');
        });

        document.addEventListener('click', function (event) {
            if (!userMenu.contains(event.target)) {
                closeUserMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeUserMenu();
            }
        });
    }

});

// Función para crear backup
function createBackup() {
    if (confirm('¿Estás seguro de que quieres crear un backup y eliminar todos los clientes no facturables? Esta acción no se puede deshacer.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/client/backup/non-billable';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        // Obtener el token CSRF desde un meta tag o variable global
        token.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    }
}
