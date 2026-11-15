// assets/js/card-reader.js
const initCardReader = () => {
    // Buscar el campo cardCodeField o client_cardCode
    const cardCodeField = document.getElementById('cardCodeField') || document.getElementById('client_cardCode');
    if (!cardCodeField) {
        return;
    }

    let isFetching = false;

    const fetchCardId = async () => {
        if (isFetching) return;
        isFetching = true;
        try {
            const response = await fetch('/api/card-read', { method: 'GET' });
            if (!response.ok) {
                isFetching = false;
                return;
            }
            const data = await response.json();

            if (data.success && data.card_id) {
                cardCodeField.value = data.card_id;
                cardCodeField.classList.add('card-code-highlight');
                cardCodeField.dispatchEvent(new Event('input', { bubbles: true }));
                cardCodeField.dispatchEvent(new Event('change', { bubbles: true }));

                setTimeout(() => {
                    cardCodeField.classList.remove('card-code-highlight');
                }, 1800);

                // Limpiar de caché para evitar que siga asignándolo
                await fetch('/api/card-read/clear', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
            }
        } catch (error) {
            console.error('Error al obtener el ID de la tarjeta:', error);
        } finally {
            isFetching = false;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setInterval(fetchCardId, 1500);
        });
    } else {
        setInterval(fetchCardId, 1500);
    }
};

export { initCardReader };
