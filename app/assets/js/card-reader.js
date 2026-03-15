// assets/js/card-reader.js
const initCardReader = () => {
    
    // Verificar si existe el campo cardCodeField en la página
    const cardCodeField = document.getElementById('cardCodeField');
    if (!cardCodeField) {
        console.log("Campo cardCodeField no encontrado, no inicializar card reader");
        return;
    }
    
    const fetchCardId = async () => {
        try {
            const response = await fetch('/api/card-read', { method: 'GET' });
            const data = await response.json();

            if (data.success && data.card_id) {
                cardCodeField.value = data.card_id;
            }
        } catch (error) {
            console.error('Error al obtener el ID de la tarjeta:', error);
        }
    };

    // Iniciar el polling cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setInterval(fetchCardId, 2000);
        });
    } else {
        setInterval(fetchCardId, 2000);
    }
};

export { initCardReader };