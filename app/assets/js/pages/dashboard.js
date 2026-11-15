// Dashboard JavaScript
function renderClients(clients) {
    const container = document.getElementById('turnstileClients');
    if (!container) return;

    container.innerHTML = '';
    if (clients.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="waiting-state">
                    <div class="waiting-icon">
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <div class="waiting-text">Esperando que pase un cliente por el torno...</div>
                </div>
            </div>
        `;
        return;
    }

    clients.forEach(client => {
        const lastAccessFormatted = client.lastAccess ? new Date(client.lastAccess).toLocaleString('es-ES', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone
        }) : '-';

        const statusClass = client.hasPaid ? 'status-active' : 'status-inactive';
        const statusText = client.hasPaid ? 'Activo' : 'Inactivo';

        let actions = `<a href="/client/${client.id}/edit" class="btn btn-dashboard btn-primary-dashboard">
            <i class="bi bi-pencil-square me-2"></i>Editar
        </a>`;

        // Botón de renovar con token CSRF válido
        actions += `<form method="post" action="/client/${client.id}/renew" style="display:inline;">
            <input type="hidden" name="_token" value="${client.csrfToken || ''}">
            <button type="submit" class="btn btn-dashboard btn-success-dashboard">
                <i class="bi bi-arrow-repeat me-2"></i>Renovar
            </button>
        </form>`;

        const avatarHtml = client.photoUrl
            ? `<div class="text-center mb-3">
                <img src="${client.photoUrl}" alt="${client.name}" class="rounded-circle object-fit-cover shadow-sm" style="width:76px;height:76px;border:3px solid var(--c-primary,#D72638);">
               </div>`
            : `<div class="client-avatar"><i class="bi bi-person-circle"></i></div>`;

        container.innerHTML += `
            <div class="col-12 col-lg-10 col-xl-8">
                <div class="client-card updated">
                    <div class="client-info">
                        ${avatarHtml}

                        <h2 class="client-name">${client.name} ${client.surname}</h2>

                        <div class="text-center mb-3">
                            <span class="status-indicator ${statusClass}">
                                <i class="bi bi-circle-fill me-2"></i>${statusText}
                            </span>
                        </div>

                        <div class="client-details">
                            <div class="detail-item">
                                <div class="detail-label">ID Cliente</div>
                                <div class="detail-value">#${client.id}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Código de Tarjeta</div>
                                <div class="detail-value">${client.cardCode}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Último Acceso</div>
                                <div class="detail-value">${lastAccessFormatted}</div>
                            </div>
                        </div>

                        <div class="action-buttons">
                            ${actions}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
}

function fetchClients() {
    fetch('/api/turnstile-clients')
        .then(res => res.json())
        .then(data => {
            renderClients(data);
            updateLastUpdate();
        })
        .catch(error => {
            console.error('Error fetching clients:', error);
            updateConnectionStatus(false);
        });
}

function updateLastUpdate() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('lastUpdate').textContent = `Última actualización: ${timeString}`;
    updateConnectionStatus(true);
}

function updateConnectionStatus(connected) {
    const statusElement = document.getElementById('connectionStatus');
    if (connected) {
        statusElement.className = 'bi bi-circle-fill text-success me-1';
    } else {
        statusElement.className = 'bi bi-circle-fill text-danger me-1';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    fetchClients();
    setInterval(fetchClients, 5000); // Actualiza cada 5 segundos
});
