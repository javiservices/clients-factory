// Client Forms JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const cardCodeInput = document.querySelector('#cardCodeField');
    const scannerToast = document.getElementById('scannerToast');

    if (cardCodeInput && scannerToast) {
        const toast = new bootstrap.Toast(scannerToast);
        cardCodeInput.addEventListener('focus', function() {
            toast.show();
        });

        cardCodeInput.addEventListener('blur', function() {
            toast.hide();
        });
    }

    const subscriptionSelect = document.querySelector('[data-subscription-type="select"]');
    const regularFields = document.getElementById('regularSubscriptionFields');
    const dailyPassFields = document.getElementById('dailyPassFields');

    function updateFieldsVisibility() {
        if (!subscriptionSelect || !regularFields || !dailyPassFields) return;

        const selectedOption = subscriptionSelect.options[subscriptionSelect.selectedIndex];
        const subscriptionType = selectedOption.text.toLowerCase();

        if (subscriptionType.includes('pase') || subscriptionType.includes('bono')) {
            regularFields.style.display = 'none';
            dailyPassFields.style.display = 'block';
        } else {
            regularFields.style.display = 'block';
            dailyPassFields.style.display = 'none';
        }
    }

    if (subscriptionSelect) {
        subscriptionSelect.addEventListener('change', updateFieldsVisibility);
        updateFieldsVisibility();
    }
});
