document.addEventListener('DOMContentLoaded', function() {
    console.log("Esperando lectura de tarjeta...");

    const fetchCardId = async () => {
        try {
            const response = await fetch('/api/card-read', { method: 'GET' });
            const data = await response.json();

            if (data.success && data.card_id) {
                const cardCodeField = document.getElementById('client_cardCode');
                if (cardCodeField) {
                    cardCodeField.value = data.card_id; // Actualiza el campo con el ID de la tarjeta
                    console.log("ID de tarjeta actualizado: " + data.card_id);
                    
                    // Después de usar el ID, lo limpiamos del caché
                    await fetch('/api/card-read/clear', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error al obtener el ID de la tarjeta:', error);
        }
    };

    // Iniciar el polling para verificar nuevas tarjetas cada 2 segundos
    setInterval(fetchCardId, 2000);
});