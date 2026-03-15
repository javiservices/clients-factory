// Subscription Forms JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const isDailyPassCheckbox = document.querySelector('#subscription_type_isDailyPass');
    const monthsField = document.querySelector('#monthsField');
    const daysField = document.querySelector('#daysField');

    function updateDurationFields() {
        if (!isDailyPassCheckbox || !monthsField || !daysField) return;

        if (isDailyPassCheckbox.checked) {
            monthsField.style.display = 'none';
            daysField.style.display = 'block';
        } else {
            monthsField.style.display = 'block';
            daysField.style.display = 'block';
        }
    }

    if (isDailyPassCheckbox) {
        isDailyPassCheckbox.addEventListener('change', updateDurationFields);
        updateDurationFields();
    }
});
