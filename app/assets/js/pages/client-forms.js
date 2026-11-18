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
    const amountInput = document.getElementById('paymentAmountField');
    const presetButtons = document.querySelectorAll('.js-amount-preset');
    const notice = document.getElementById('halfMonthNotice');
    const taxBadge = document.getElementById('taxBreakdownBadge');

    function updateFieldsVisibility() {
        if (!subscriptionSelect) return;

        const selectedOption = subscriptionSelect.options[subscriptionSelect.selectedIndex];
        if (!selectedOption) return;
        const subscriptionType = selectedOption.text.toLowerCase();

        if (regularFields && dailyPassFields) {
            if (subscriptionType.includes('pase') || subscriptionType.includes('bono')) {
                regularFields.style.display = 'none';
                dailyPassFields.style.display = 'block';
            } else {
                regularFields.style.display = 'block';
                dailyPassFields.style.display = 'none';
            }
        }
    }

    function updateTaxBreakdown() {
        if (!amountInput || !taxBadge) return;
        const val = parseFloat(amountInput.value);
        if (!isNaN(val) && val > 0) {
            const base = (val / 1.21).toFixed(2);
            const iva = (val - base).toFixed(2);
            taxBadge.textContent = 'Base: ' + base + '€ + IVA: ' + iva + '€';
            if (Math.abs(val - 30.0) < 0.01) {
                if (notice) notice.classList.remove('d-none');
            } else {
                if (notice) notice.classList.add('d-none');
            }
        } else {
            taxBadge.textContent = 'IVA 21% incluido';
            if (notice) notice.classList.add('d-none');
        }
    }

    if (subscriptionSelect) {
        subscriptionSelect.addEventListener('change', function() {
            updateFieldsVisibility();
            const selectedOpt = subscriptionSelect.options[subscriptionSelect.selectedIndex];
            if (selectedOpt && selectedOpt.dataset && selectedOpt.dataset.price) {
                if (amountInput && (!amountInput.value || amountInput.value === '50.00' || amountInput.value === '70.00')) {
                    amountInput.value = selectedOpt.dataset.price;
                    updateTaxBreakdown();
                }
            }
        });
        updateFieldsVisibility();
    }

    presetButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.dataset.amount;
            if (amountInput) {
                amountInput.value = amt;
                presetButtons.forEach(b => {
                    b.classList.remove('btn-primary', 'btn-warning', 'text-white');
                    if (b.dataset.amount === '50.00') b.classList.add('btn-outline-primary');
                    if (b.dataset.amount === '30.00') b.classList.add('btn-outline-warning');
                });
                if (amt === '50.00') {
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary', 'text-white');
                } else if (amt === '30.00') {
                    this.classList.remove('btn-outline-warning');
                    this.classList.add('btn-warning');
                }
                updateTaxBreakdown();
            }
        });
    });

    if (amountInput) {
        amountInput.addEventListener('input', updateTaxBreakdown);
        updateTaxBreakdown();
    }
});
