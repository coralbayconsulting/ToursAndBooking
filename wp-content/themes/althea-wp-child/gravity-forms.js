jQuery(document).ready(function ($) {

  // Get the locale from the localized script data
  var userLocale = localeData.locale.replace('_', '-') || 'de-DE'; // Default to 'de-DE' if localeData.locale is not set

  // Validate the locale
  try {
    new Intl.NumberFormat(userLocale);
  } catch (e) {
    console.error("Invalid locale provided:", userLocale);
    userLocale = 'de-DE'; // Fallback to 'de-DE' if the locale is invalid
  }

  
  // Function to format currency values according to the user's locale and specified currency
  function formatCurrency(value, currency = 'EUR') {
    return new Intl.NumberFormat(userLocale, { style: 'currency', currency: currency }).format(value);
  }

  // Function to parse formatted currency string according to the user's locale
  function parseCurrency(value) {
    var numberFormat = new Intl.NumberFormat(userLocale, { style: 'currency', currency: 'EUR' });
    var parts = numberFormat.formatToParts(12345.67);
    var groupSeparator = parts.find(part => part.type === 'group').value;
    var decimalSeparator = parts.find(part => part.type === 'decimal').value;

    // Remove currency symbol and spaces
    value = value.replace(/[^\d.,-]/g, '');

    // Replace group separator with empty string and decimal separator with dot
    value = value.split(groupSeparator).join('').split(decimalSeparator).join('.');

    return parseFloat(value);
  }

  // Function to parse a product value string (ID|Price)
  function parseProductValue(valueString) {
      /**
       * Parses a product value string (ID|Price) and returns an object containing the ID and price.
       *
       * @param {string} valueString - The product value string to parse.
       * @returns {object|null} - An object with 'id' and 'price' properties if parsing is successful, null otherwise.
       */

      if (typeof valueString !== 'string') {
          return null; // Invalid input
      }

      const parts = valueString.split('|');

      if (parts.length === 2) {
          const id = parseInt(parts[0], 10);
          const price = parseFloat(parts[1]);

          if (!isNaN(id) && !isNaN(price)) {
              return { id, price }; // Return the object
          }
      }

      return null; // Invalid value string format or parsing failed
  }

  function buildProductValue(id, price) {
      /**
       * Builds a product value string (ID|Price) from the provided ID and price.
       *
       * @param {number} id - The product ID (integer).
       * @param {number} price - The product price (float).
       * @returns {string|null} - The formatted product value string if successful, null otherwise.
       */

      if (typeof id !== 'number' || typeof price !== 'number') {
          return null; // Invalid input types
      }

      return `${id}|${price.toFixed(2)}`;
  }

  // Check if the form with ID 9 is present
  if ($('#gform_wrapper_9').length) { /// Code for form ID 9 (Deposit Calculation Form)
     // Define variables for the fields
     var tourPriceField = $('.gf-tour-price input[name="input_158.2"]');
     var depositField = $('.gf-deposit input');
     var balanceField = $('.gf-balance input');
     var couponField = $('#gf_coupon_codes_9');
     var totalField = $('.gf-total input');
 
     // Function to calculate the deposit and balance
     function calculateDepositAndBalance() {
       var totalValue = parseCurrency(totalField.val()) || 0;
       
       // Get form values using specific Gravity Forms field IDs
       var tourId = $('input[name="input_149"]').val(); // Tour ID
       var packagePeople = parseInt($('input[name="input_151"]').val()) || 1; // Package people
       var currentCurrency = $("input[name='input_223']").val() || 'EUR'; // Currency
       var netTourPrice = parseCurrency($('input[name="input_160"]').val()) || totalValue; // Net tour price

       // Fetch deposit settings and calculate if we have a valid tour ID
       if (tourId && /^\d+$/.test(tourId)) {
         $.ajax({
           url: '/wp-admin/admin-ajax.php',
           type: 'POST',
           data: {
             action: 'get_tour_deposit_settings',
             tour_id: tourId
           },
           success: function(response) {
             if (response.success) {
               var depositSettings = response.data;
               var depositValue = 0;
               
               // Only recalculate deposit for percentage-based deposits
               // Fixed amount deposits should not change when coupons are applied
               if (depositSettings.type === 'Percentage' && depositSettings.percent) {
                 depositValue = netTourPrice * (parseFloat(depositSettings.percent) / 100);
                 
                 // Calculate balance and update fields
                 var balanceValue = netTourPrice - depositValue;
                 var depositFormatted = formatCurrency(depositValue, currentCurrency);
                 var balanceFormatted = formatCurrency(balanceValue, currentCurrency);
                 
                 depositField.val(depositFormatted);
                 balanceField.val(balanceFormatted);
               }
               // For fixed amounts, do nothing - let the original values remain unchanged
             }
           }
         });
       }
     }
 
     // Function to trigger the calculation with a delay
     function triggerCalculationWithDelay() {
       setTimeout(calculateDepositAndBalance, 100);
     }
 
     // Use MutationObserver to detect changes in the hidden coupon field
     var couponObserver = new MutationObserver(function (mutations) {
       mutations.forEach(function (mutation) {
         if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
           triggerCalculationWithDelay();
         }
       });
     });
 
     // Observe changes to the hidden input field that stores the applied coupon value
     if (couponField.length) {
       couponObserver.observe(couponField[0], {
         attributes: true,
         attributeFilter: ['value']
       });
     }
   }

 });