// assets/js/card-reader.js
const initCardReader = () => {
    // Buscar el campo cardCodeField o client_cardCode
    const cardCodeField = document.getElementById('cardCodeField') || document.getElementById('client_cardCode');
    if (!cardCodeField) {
        return;
    }

    let isFetching = false;
    let pollInterval = null;

    const fetchCardId = async () => {
        // No consultar si la pestaña está en segundo plano o si ya hay una petición en vuelo
        if (document.hidden || isFetching) return;
        isFetching = true;

        try {
            const response = await fetch('/api/card-read', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (data && data.success && data.card_id) {
                // Solo asignar si el valor es diferente para no sobrescribir ediciones manuales
                if (cardCodeField.value !== data.card_id) {
                    cardCodeField.value = data.card_id;
                    cardCodeField.classList.add('card-code-highlight');
                    cardCodeField.dispatchEvent(new Event('input', { bubbles: true }));
                    cardCodeField.dispatchEvent(new Event('change', { bubbles: true }));

                    setTimeout(() => {
                        cardCodeField.classList.remove('card-code-highlight');
                    }, 2000);
                }

                // Limpiar de caché del backend para evitar asignaciones repetidas
                try {
                    await fetch('/api/card-read/clear', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                } catch (_) {}
            }
        } catch (_) {
            // Silencioso ante cortes de red momentáneos
        } finally {
            isFetching = false;
        }
    };

    const startPolling = () => {
        if (!pollInterval) {
            pollInterval = setInterval(fetchCardId, 2000);
        }
    };

    const stopPolling = () => {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    };

    // Pausar sondeo al cambiar de pestaña y reanudar al volver
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    window.addEventListener('beforeunload', stopPolling);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }
};

export { initCardReader };
