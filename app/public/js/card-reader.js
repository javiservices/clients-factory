// public/js/card-reader.js
(function() {
    const cardCodeField = document.getElementById('cardCodeField') || document.getElementById('client_cardCode');
    if (!cardCodeField) return;

    let isFetching = false;
    let pollInterval = null;

    const fetchCardId = async () => {
        if (document.hidden || isFetching) return;
        isFetching = true;

        try {
            const response = await fetch('/api/card-read', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) return;

            const data = await response.json();

            if (data && data.success && data.card_id) {
                if (cardCodeField.value !== data.card_id) {
                    cardCodeField.value = data.card_id;
                    cardCodeField.classList.add('card-code-highlight');
                    cardCodeField.dispatchEvent(new Event('input', { bubbles: true }));
                    cardCodeField.dispatchEvent(new Event('change', { bubbles: true }));

                    setTimeout(() => {
                        cardCodeField.classList.remove('card-code-highlight');
                    }, 2000);
                }

                try {
                    await fetch('/api/card-read/clear', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                } catch (_) {}
            }
        } catch (_) {
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
})();
