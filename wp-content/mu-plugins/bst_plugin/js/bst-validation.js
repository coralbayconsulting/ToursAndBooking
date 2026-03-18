document.addEventListener('DOMContentLoaded', function() {
    console.log("Validation script loaded");
    
    var exchangeRateInput = document.getElementById('bst_exchange_rate');

    exchangeRateInput.addEventListener('input', function() {
        var value = parseFloat(exchangeRateInput.value);
        if (isNaN(value) || value <= 0) {
            exchangeRateInput.setCustomValidity('Please enter a valid positive decimal number.');
        } else {
            exchangeRateInput.setCustomValidity('');
        }
    });
});