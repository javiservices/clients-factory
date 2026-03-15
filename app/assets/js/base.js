// Base JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const closeSidebar = document.getElementById('closeSidebar');

    // Abrir sidebar
    if (sidebarCollapse && sidebar && overlay) {
        sidebarCollapse.addEventListener('click', function() {
            sidebar.classList.add('active');
            overlay.style.display = 'block';
        });
    }

    // Cerrar sidebar con el botón
    if (closeSidebar && sidebar && overlay) {
        closeSidebar.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
        });
    }

    // Cerrar sidebar cuando se hace clic en el overlay
    if (overlay && sidebar) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
        });
    }

    // Cerrar sidebar en resize si está en modo desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768 && sidebar && overlay) {
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
        }
    });

    // Cerrar sidebar al hacer clic en un enlace (opcional)
    if (sidebar) {
        const sidebarLinks = sidebar.querySelectorAll('.nav-link');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768 && sidebar && overlay) {
                    sidebar.classList.remove('active');
                    overlay.style.display = 'none';
                }
            });
        });
    }

    // Atajo de teclado para cambiar modo del sistema (Ctrl+Shift+H)
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'H') {
            e.preventDefault();
            // Obtener el modo del sistema desde un atributo data o variable global
            const currentMode = document.body.dataset.systemMode || 'normal';
            const newMode = currentMode === 'hidden' ? 'normal' : 'hidden';
            window.location.href = '/client/system/mode/' + newMode;
        }
    });

    // Inicializar tooltip del botón flotante
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Funcionalidad del botón flotante
    const systemModeToggle = document.getElementById('system-mode-toggle');
    if (systemModeToggle) {
        systemModeToggle.addEventListener('click', function() {
            const currentMode = document.body.dataset.systemMode || 'normal';
            const newMode = currentMode === 'hidden' ? 'normal' : 'hidden';
            window.location.href = '/client/system/mode/' + newMode;
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
