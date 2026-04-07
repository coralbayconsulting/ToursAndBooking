jQuery(document).ready(function ($) {
  var tourdatedropdown = $("#tourdatedropdown");
  var tourpackagedropdown = $("#tourpackagedropdown");
  var vehicleDropdown1Container = $("#vehicleDropdown1Container");
  var vehicleDropdown2Container = $("#vehicleDropdown2Container");
  var vehicleDropdown1 = $("#vehicledropdown1");
  var vehicleDropdown2 = $("#vehicledropdown2");
  var tourPriceField = $("#tourprice");
  var tourPriceConvertedField = $("#tourpriceconverted");
  var bookButton = $("#bookButton");
  var currencyDropdown = $("#tourpricecurrency");
  var currencyFlag = $("#currencyFlag");
  var extensionCheckboxContainer = $("#extension-checkbox-container");
  var extensionCheckbox = $("#extensionCheckbox");
  var extensionLabel = $("#extensionLabel");

  // Initial state
  tourpackagedropdown.prop("disabled", true);
  vehicleDropdown1Container.hide();
  vehicleDropdown2Container.hide();
  extensionCheckboxContainer.hide();
  bookButton.prop("disabled", true);
  
  // Auto-refresh to keep availability data current - using configurable interval
  var autoRefreshMinutes = (typeof ajax_object !== 'undefined' && ajax_object.auto_refresh_interval) 
    ? parseInt(ajax_object.auto_refresh_interval) 
    : 15; // Default to 15 minutes if setting not available
  var AUTO_REFRESH_TIME = autoRefreshMinutes * 60 * 1000; // Convert minutes to milliseconds
  
  setTimeout(function() {
    location.reload();
  }, AUTO_REFRESH_TIME);

  // Function to show/hide fields based on selections
  function showHideFields() {
    if (tourdatedropdown.val()) { // Allow any date selection, including sold out
      tourpackagedropdown.prop("disabled", false);
      if (!tourpackagedropdown.val()) {
        vehicleDropdown1Container.hide();
        vehicleDropdown2Container.hide();
        bookButton.prop("disabled", true);
        $("#waitingListButton").prop("disabled", true);
      } 
    } else {
      tourpackagedropdown.prop("disabled", true).val(tourpackagedropdown.find("option:first").val());
      vehicleDropdown1Container.hide();
      vehicleDropdown2Container.hide();
      bookButton.prop("disabled", true);
      $("#waitingListButton").prop("disabled", true);
      calculateTotalPrice();
    }
    
    // Check if we should show waiting list button instead of book button
    checkButtonDisplayState();
  }

  // Function to calculate and display the total price
  function calculateTotalPrice() {
    var packagePrice = parseFloat(tourpackagedropdown.find("option:selected").val()) || 0;
    var vehiclePrice1 = parseFloat(vehicleDropdown1.find("option:selected").val()) || 0;
    var vehiclePrice2 = parseFloat(vehicleDropdown2.find("option:selected").val()) || 0;
    var extensionPrice = 0;
    
    // Add extension price if checkbox is checked
    if (extensionCheckbox.is(':checked')) {
      extensionPrice = parseFloat(extensionCheckbox.data('price')) || 0;
    }
    
    var totalPrice = packagePrice + vehiclePrice1 + vehiclePrice2 + extensionPrice;

    if(totalPrice > 0) {
      // Get the user's locale
      var userLocale = navigator.language || 'en-US';
      
      // Get tour's assigned currency (from PHP localization)
      var tourCurrencyCode = (typeof tourCurrency !== 'undefined' && tourCurrency.currency) ? tourCurrency.currency : 'EUR';
      
      // Format the total price in the tour's currency
      var formattedPrice = new Intl.NumberFormat(userLocale, { style: 'currency', currency: tourCurrencyCode }).format(totalPrice);

      // Update the primary price field
      tourPriceField.val(formattedPrice);
      
      // Handle currency conversion display if user has different currency selected
      var selectedCurrencyCode = currencyDropdown.find("option:selected").data("currency");

      if(selectedCurrencyCode !== tourCurrencyCode) {
        var convertedPrice = totalPrice * currencyDropdown.val();
        var formattedConvertedPrice = new Intl.NumberFormat(userLocale, { style: 'currency', currency: selectedCurrencyCode }).format(convertedPrice);
        tourPriceConvertedField.val('(' + formattedConvertedPrice + ')').show();
      } else {
        tourPriceConvertedField.hide();
      }
    } else {
      tourPriceField.val('TBD');  
    }
  }

  // Populate the tour-package dropdown based on the tour ID
  function populatePackageDropdown(tourId, tourDateId) {
    if (!tourId) {
      console.error("No tour ID provided");
      return;
    }

    // Clear existing options except the first one
    tourpackagedropdown.find('option:not(:first)').remove();

    // Get tour's assigned currency (from PHP localization)
    var tourCurrencyCode = (typeof tourCurrency !== 'undefined' && tourCurrency.currency) ? tourCurrency.currency : 'EUR';

    var ajaxData = {
      action: "get_package_pricing",
      tour_id: tourId,
      currency: tourCurrencyCode,
    };

    // Include tour date ID if provided
    if (tourDateId) {
      ajaxData.tour_date_id = tourDateId;
    }

    $.ajax({
      url: ajax_object.ajaxurl,
      type: "POST",
      data: ajaxData,
      success: function (response) {
        // Handle new response structure that includes currency info
        var packages = response.data.packages || response.data; // Backward compatibility
        var currency = response.data.currency || 'EUR';
        
        // Update currency if we have the function available
        if (typeof window.bstUpdateCurrency === 'function') {
          window.bstUpdateCurrency(currency);
        }
        
        // Populate the tour-package dropdown with the new options
        $.each(packages, function (index, item) {
          var option = $("<option>", {
            value: item.value,
            text: item.text,
            "data-id": item["data.id"], // Set the custom data attribute for id
          });
          
          // Mark option as unavailable if it shows "(Not Available)" but don't disable it
          if (item.text.includes("(Not Available)")) {
            option.attr('data-unavailable', 'true');
          }
          
          tourpackagedropdown.append(option);
        });

        showHideFields();
        checkButtonDisplayState(); // Check if we should show waiting list button
      },
      error: function (xhr, status, error) {
        console.log("Tour Package AJAX Error:", error);
      },
    });
  }

  // Handle change event for the tour-date dropdown
  tourdatedropdown.on("change", function () {
    // Hide waiting list form if open
    if ($("#waitingListForm").is(":visible")) {
      hideWaitingListForm();
    }
    
    // Reset vehicle dropdowns to the first item
    vehicleDropdown1.prop('selectedIndex', 0);
    vehicleDropdown2.prop('selectedIndex', 0);
    
    // Hide extension checkbox
    extensionCheckboxContainer.hide();
    extensionCheckbox.prop('checked', false);

    // Re-populate package dropdown with the selected tour date to check slot availability
    var tourId = $('#tourBookingForm').data("tour-id");
    var selectedTourDateId = $(this).val();
    
    if (tourId && selectedTourDateId) {
      populatePackageDropdown(tourId, selectedTourDateId);
    } else {
      showHideFields();
    }
    
    // Check button display state after date selection
    checkButtonDisplayState();
  });

  // Handle change event for the package dropdown
  tourpackagedropdown.on("change", function () {
    // Hide waiting list form if open
    if ($("#waitingListForm").is(":visible")) {
      hideWaitingListForm();
    }
    
    var tourId = $('#tourBookingForm').data("tour-id"); // Use the tour ID from the data attribute
    var packageId = $(this).find("option:selected").data("id"); // Access the custom data attribute for package id

    // Disable the book button initially
    bookButton.prop("disabled", true);
    
    // Hide extension checkbox
    extensionCheckboxContainer.hide();
    extensionCheckbox.prop('checked', false);

    // Reset vehicle dropdowns to the first item
    vehicleDropdown1.prop('selectedIndex', 0);
    vehicleDropdown2.prop('selectedIndex', 0);
    
    // Clear vehicle dropdown options except the first one
    vehicleDropdown1.find('option:not(:first)').remove();
    vehicleDropdown2.find('option:not(:first)').remove();

    if (!tourId || !packageId) {
      // Clear and hide vehicle dropdowns if tourId, packageId, or packagePrice is not set
      vehicleDropdown1.closest(".vehicle-dropdown-container").hide();
      vehicleDropdown2.closest(".vehicle-dropdown-container").hide();
      bookButton.prop("disabled", true);
      return;
    } 

    // Get tour's assigned currency (from PHP localization)
    var tourCurrencyCode = (typeof tourCurrency !== 'undefined' && tourCurrency.currency) ? tourCurrency.currency : 'EUR';

    // Trigger AJAX request to fetch vehicle data
    $.ajax({
      url: ajax_object.ajaxurl,
      type: "POST",
      data: {
        action: "get_vehicle_data",
        tour_id: tourId,
        package_id: packageId,
        currency: tourCurrencyCode,
      },
      success: function (response) {
        // Logic to show/hide vehicle dropdowns based on the number of vehicles and packageId
        var numVehicles = packageSettings[packageId]
          ? packageSettings[packageId].vehicles
          : 1;

        if (response.data.length < 2) {
          vehicleDropdown1Container.hide();
          vehicleDropdown2Container.hide();
          
          // Update extension checkbox visibility when no vehicles available
          updateExtensionCheckbox();
        } else {
          // Populate the dropdowns with the fetched data
          $.each(response.data, function (index, item) {
            vehicleDropdown1.append(
              $("<option>", {
                value: item.value, // This contains the price for calculations
                text: item.text, // This is the display text
                "data-id": item["data-id"], // Vehicle class ID
                "data-vehicle-id": item["vehicle_id"] || "",
              })
            );
            if (numVehicles == 2) {
              vehicleDropdown2.append(
                $("<option>", {
                  value: item.value, // This contains the price for calculations  
                  text: item.text, // This is the display text
                  "data-id": item["data-id"], // Vehicle class ID
                  "data-vehicle-id": item["vehicle_id"] || "",
                })
              );
            }
          });

          vehicleDropdown1Container.show();
          vehicleDropdown1.prop("disabled", false); // Enable vehicleDropdown1
          if (numVehicles == 2) {
            vehicleDropdown2Container.show();
            vehicleDropdown2.prop("disabled", false); // Enable vehicleDropdown2
          } else {
            vehicleDropdown2Container.hide();
            vehicleDropdown2.prop("disabled", true); // Disable vehicleDropdown2
          }
        }

        // Trigger change event to update calculations
        vehicleDropdown1.trigger("change");
        vehicleDropdown2.trigger("change");

        // Re-enable the book button if conditions are met
        checkBookButtonState();
        
        // Calculate total price after vehicle dropdowns are populated
        calculateTotalPrice();
      },
      error: function (xhr, status, error) {
        console.log("Vehicle Data AJAX Error:", error);
      },
    });

    checkButtonDisplayState(); // Add this to check for sold out/unavailable state
  });

  // Handle change event for the vehicle dropdowns
  vehicleDropdown1.on("change", function() {
    updateExtensionCheckbox();
    calculateTotalPrice();
    checkBookButtonState();
  });
  vehicleDropdown2.on("change", function() {
    updateExtensionCheckbox();
    calculateTotalPrice();
    checkBookButtonState();
  });
  
  // Handle change event for the extension checkbox
  extensionCheckbox.on("change", function() {
    calculateTotalPrice();
  });
  
  // Function to update extension checkbox visibility and label
  function updateExtensionCheckbox() {
    // Check if extension is offered at tour level and date level
    var tourExtensionOffered = (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.offered);
    var selectedTourDateId = tourdatedropdown.val();
    var dateExtensionOffered = false;
    
    // Find the selected tour date and check if extension is offered
    if (selectedTourDateId && typeof tourDatesData !== 'undefined') {
      var selectedDate = tourDatesData.find(function(date) {
        return date.id == selectedTourDateId;
      });
      if (selectedDate) {
        dateExtensionOffered = selectedDate.date_extension_offered == '1' || selectedDate.date_extension_offered === true;
      }
    }
    
    // Check if vehicles are available/shown
    var vehiclesAvailable = vehicleDropdown1Container.is(':visible');
    
    // Check if a vehicle has been selected (only relevant if vehicles are available)
    var vehicleSelected = vehicleDropdown1.val() && vehicleDropdown1.val() !== '';
    
    // Show extension checkbox if:
    // 1. Extension is offered at both tour and date level AND
    // 2. Either no vehicles are available OR a vehicle has been selected
    var shouldShowExtension = tourExtensionOffered && dateExtensionOffered && (!vehiclesAvailable || vehicleSelected);
    
    if (shouldShowExtension) {
      // Get the selected package ID
      var packageId = tourpackagedropdown.find("option:selected").data("id");
      
      // Get extension price for this package
      var extensionPrice = 0;
      if (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.pricing) {
        var packageKey = 'package_' + packageId;
        extensionPrice = parseFloat(tourExtensionSettings.pricing[packageKey]) || 0;
      }
      
      // Calculate vehicle upcharge for extension if vehicles are selected
      // Get extension days from tour settings
      var extensionDays = (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.extensionDays) 
        ? parseInt(tourExtensionSettings.extensionDays) 
        : 0;
      
      if (selectedDate && extensionDays > 0) {
        // Get admin vehicle driving days
        var adminDrivingDays = (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.adminVehicleDrivingDays) 
          ? parseFloat(tourExtensionSettings.adminVehicleDrivingDays) 
          : 0;
        
        if (adminDrivingDays > 0 && extensionDays > 0) {
          // Calculate vehicle 1 upcharge
          var vehicle1Selected = vehicleDropdown1.val();
          
          if (vehicle1Selected && vehicle1Selected !== '' && vehicle1Selected !== '0') {
            var vehicle1Upcharge = parseFloat(vehicle1Selected) || 0;
            var addedAmount1 = Math.round(vehicle1Upcharge / adminDrivingDays * extensionDays);
            
            extensionPrice += addedAmount1;
          }
          
          // Calculate vehicle 2 upcharge if applicable
          var vehicle2Selected = vehicleDropdown2.val();
          
          if (vehicle2Selected && vehicle2Selected !== '' && vehicle2Selected !== '0') {
            var vehicle2Upcharge = parseFloat(vehicle2Selected) || 0;
            var addedAmount2 = Math.round(vehicle2Upcharge / adminDrivingDays * extensionDays);
            
            extensionPrice += addedAmount2;
          }
        }
      }
      
      // Round extension price to nearest integer
      extensionPrice = Math.round(extensionPrice);
      
      // Get tour currency
      var tourCurrencyCode = (typeof tourCurrency !== 'undefined' && tourCurrency.currency) ? tourCurrency.currency : 'EUR';
      var symbol = (tourCurrencyCode === 'USD') ? '$' : '€';
      
      // Format the price
      var formattedPrice = symbol + extensionPrice.toLocaleString();
      
      // Format extension dates if available
      var dateText = '';
      var extensionDays = (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.extensionDays) 
        ? parseInt(tourExtensionSettings.extensionDays) 
        : 0;
      
      if (selectedDate && extensionDays > 0) {
        // Extension starts on the last day of the tour (tour end date)
        // Parse tour end date from YYYYMMDD format
        var tourEndDateStr = String(selectedDate.end_date);
        var tourEndYear = parseInt(tourEndDateStr.substring(0, 4));
        var tourEndMonth = parseInt(tourEndDateStr.substring(4, 6));
        var tourEndDay = parseInt(tourEndDateStr.substring(6, 8));
        
        // Extension starts on tour end date
        var extStartDate = new Date(tourEndYear, tourEndMonth - 1, tourEndDay);
        
        // Extension ends extension_days after start
        var extEndDate = new Date(extStartDate);
        extEndDate.setDate(extEndDate.getDate() + extensionDays);
        
        // Format dates similar to tour date format (without year)
        var startMonth = extStartDate.toLocaleDateString('en-US', { month: 'short' });
        var endMonth = extEndDate.toLocaleDateString('en-US', { month: 'short' });
        var startDay = extStartDate.getDate();
        var endDay = extEndDate.getDate();
        
        if (startMonth === endMonth) {
          dateText = startDay + '-' + endDay + ' ' + endMonth;
        } else {
          dateText = startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth;
        }
      }
      
      // Update label with title, dates (without year), and price in same parentheses
      var labelText = tourExtensionSettings.title;
      var extensionDatesWithYear = '';
      if (dateText) {
        // Store dates with year for database/gravity forms
        var extYear = extEndDate.getFullYear();
        extensionDatesWithYear = dateText + ' ' + extYear;
        // Display dates without year in label
        labelText += ' (' + dateText + ' - ' + formattedPrice + ')';
      } else {
        labelText += ' (' + formattedPrice + ')';
      }
      extensionLabel.text(labelText);
      
      // Store price and dates (with year) in checkbox data attributes
      extensionCheckbox.data('price', extensionPrice);
      extensionCheckbox.data('extension-dates', extensionDatesWithYear);
      
      // Show the container
      extensionCheckboxContainer.show();
    } else {
      // Hide and uncheck the extension checkbox
      extensionCheckboxContainer.hide();
      extensionCheckbox.prop('checked', false);
    }
  }

  // Handle click event for the book button
  bookButton.on("click", function(e) {
    e.preventDefault();
    console.log('=== BOOK BUTTON CLICKED ===');

    // Get selected values
    var tourId = $('#tourBookingForm').data("tour-id"); // Get tour ID from data attribute
    var tourTitle = $('#tourBookingForm').data('tour-title');
    var tourType = $('#tourBookingForm').data('tour-type'); // Get tour type from data attribute
    var tourDate = tourdatedropdown.val();
    var tourDateText = tourdatedropdown.find("option:selected").text(); // Keep the full date range text
    var tourPackage = tourpackagedropdown.find("option:selected").data("id"); // Get package ID from data attribute

    console.log('About to check availability for tourDate:', tourDate, 'tourPackage:', tourPackage);
    
    // Real-time availability check before proceeding
    checkAvailabilityBeforeBooking(tourDate, tourPackage, function(available, debugData) {
      console.log('Availability check callback - available:', available, 'debugData:', debugData);
      if (!available) {
        // Alert and refresh are handled in the checkAvailabilityBeforeBooking function
        location.reload();
        return;
      }
      
      // Proceed with booking if still available
      proceedWithBooking();
    });

    function proceedWithBooking() {
    console.log('=== BOOK BUTTON CLICKED - STARTING proceedWithBooking ===');

    // Get package name from global settings
    var packageName = tourpackagedropdown.find("option:selected").text().split(' ')[0]; // Use the first word in the text from the package dropdown

    // Get package people and rooms from global settings
    var packagePeople = packageSettings[tourPackage].people;
    var packageRooms = packageSettings[tourPackage].rooms;
    var packageVehicles = packageSettings[tourPackage].vehicles;
    
    // Only get vehicle data if vehicle dropdowns are visible and have selections
    var vehicle1 = '';
    var vehicle1Text = '';
    var vehicle2 = '';
    var vehicle2Text = '';
    var hasVehicleChoices = false;
    
    if(vehicleDropdown1Container.is(":visible") && vehicleDropdown1.val() && vehicleDropdown1.prop('selectedIndex') !== 0) {
      vehicle1 = vehicleDropdown1.val();
      vehicle1Text = vehicleDropdown1.find("option:selected").text();
      hasVehicleChoices = true;
    }
    
    if(vehicleDropdown2Container.is(":visible") && vehicleDropdown2.val() && vehicleDropdown2.prop('selectedIndex') !== 0) {
      vehicle2 = vehicleDropdown2.val();
      vehicle2Text = vehicleDropdown2.find("option:selected").text();
    }
    
    var product_name = tourTitle +' Tour, '+tourDateText+': '+packageName+' Package';
    if(hasVehicleChoices && vehicle1Text) {
      product_name += ' ('+vehicle1Text.split(' (')[0];
      product_name += vehicle2Text ? '/'+vehicle2Text.split(' (')[0] : '';
      product_name += ')';
    }

    // Calculate tourPrice using the same logic as calculateTotalPrice
    var packagePrice = parseFloat(tourpackagedropdown.find("option:selected").val()) || 0;
    var vehiclePrice1 = (hasVehicleChoices && vehicle1) ? parseFloat(vehicle1) || 0 : 0;
    var vehiclePrice2 = (hasVehicleChoices && vehicle2) ? parseFloat(vehicle2) || 0 : 0;
    var extensionPrice = extensionCheckbox.is(':checked') ? parseFloat(extensionCheckbox.data('price')) || 0 : 0;
    var tourPrice = packagePrice + vehiclePrice1 + vehiclePrice2 + extensionPrice;

    // Get referrer and source from localized session data
    var referrer = sessionData.referrer;
    var source = sessionData.source;

    // Create the form element
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/tour-booking'; 
    form.target = '_self'; // Open in the same tab (changed from _blank to _self)

    // Calculate deposit and balance based on tour settings
    var depositAmount = 0;
    var balanceAmount = tourPrice;
    
    if (window.tourDepositSettings && window.tourDepositSettings.type) {
      if (window.tourDepositSettings.type === 'Percentage' && window.tourDepositSettings.percent) {
        depositAmount = tourPrice * (window.tourDepositSettings.percent / 100);
      } else if (window.tourDepositSettings.type === 'Fixed Amount') {
        if (packagePeople == 2 && window.tourDepositSettings.fixedDouble) {
          depositAmount = parseFloat(window.tourDepositSettings.fixedDouble);
        } else if (window.tourDepositSettings.fixedSingle) {
          depositAmount = parseFloat(window.tourDepositSettings.fixedSingle);
        }
      }
    }
    
    balanceAmount = tourPrice - depositAmount;

    // Get extension information if selected
    console.log('Extension checkbox:', extensionCheckbox);
    console.log('Extension checkbox length:', extensionCheckbox.length);
    var extensionAdded = extensionCheckbox.is(':checked');
    console.log('Extension checkbox is checked:', extensionAdded);
    var extensionText = '';
    var extensionDatesText = '';
    
    if (extensionAdded) {
      // Get extension title from tourExtensionSettings
      var extensionTitle = (typeof tourExtensionSettings !== 'undefined' && tourExtensionSettings.title) ? tourExtensionSettings.title : '';
      
      // Get extension price from checkbox data
      var extensionPrice = parseFloat(extensionCheckbox.data('price')) || 0;
      
      // Get tour currency for formatting
      var extensionCurrencyCode = (typeof tourCurrency !== 'undefined' && tourCurrency.currency) ? tourCurrency.currency : 'EUR';
      var extensionSymbol = (extensionCurrencyCode === 'USD') ? '$' : '€';
      var formattedPrice = extensionSymbol + Math.round(extensionPrice);
      
      // Set extensionText as title with price: "Title (+€price)"
      extensionText = extensionTitle + ' (+' + formattedPrice + ')';
      
      // Get extension dates with year directly from data attribute
      extensionDatesText = extensionCheckbox.data('extension-dates') || '';
      console.log('Extension dates from data attribute:', extensionDatesText);
    }

    // Add hidden input fields for each Gravity Forms field
    const vehicle1Id = hasVehicleChoices ? (vehicleDropdown1.find("option:selected").data("vehicle-id") || "") : "";
    const vehicle2Id = (vehicleDropdown2Container.is(":visible") && vehicleDropdown2.val() && vehicleDropdown2.prop('selectedIndex') !== 0)
      ? (vehicleDropdown2.find("option:selected").data("vehicle-id") || "")
      : "";

    const formData = {
      'tour_id': tourId,
      'tourtext': tourTitle,
      'tour_type': tourType,
      'tourdate_id': tourDate,
      'tourdatestext': tourDateText, 
      'package_id': tourPackage,
      'packagetext': packageName,
      'package_people': packagePeople,
      'package_rooms': packageRooms,
      'package_vehicles': packageVehicles,
      'vehicle1text': hasVehicleChoices ? vehicle1Text : '',
      'vehicle2text': hasVehicleChoices ? vehicle2Text : '', 
      'vehicle1id': vehicle1Id ? String(vehicle1Id) : '',
      'vehicle2id': vehicle2Id ? String(vehicle2Id) : '',
      'vehicle_choices': hasVehicleChoices ? (vehicle2Text ? '2' : '1') : '0',
      'tourprice': tourPrice.toFixed(2), 
      'deposit': depositAmount.toFixed(2),
      'balance': balanceAmount.toFixed(2),
      'product_name': product_name,
      'tour_currency': tourCurrency.currency,
      'deposit_type': window.tourDepositSettings ? window.tourDepositSettings.type : '',
      'deposit_percent': window.tourDepositSettings ? window.tourDepositSettings.percent : '',
      'deposit_fixed_single': window.tourDepositSettings ? window.tourDepositSettings.fixedSingle : '',
      'deposit_fixed_double': window.tourDepositSettings ? window.tourDepositSettings.fixedDouble : '',
      'bank_wire_discount_perc': window.bstBankWireDiscount || '2.5',
      'extensionadded': extensionAdded ? '1' : '0',
      'extensiontext': extensionText,
      'extensiondatestext': extensionDatesText
    };

    // Add source and referrer to formData if they exist in session data
    if (source) {
      formData['source'] = source;
    }
    if (referrer) {
      formData['referrer'] = referrer;
    }

    for (const fieldId in formData) {
      if (formData.hasOwnProperty(fieldId)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = fieldId;
        input.value = formData[fieldId];
        form.appendChild(input);
      }
    }

    // Add the Gravity Forms form ID
    const formIdInput = document.createElement('input');
    formIdInput.type = 'hidden';
    formIdInput.name = 'gform_submit';
    formIdInput.value = '9'; // Gravity Form ID

    form.appendChild(formIdInput);
    // Append the form to the body and submit it
    document.body.appendChild(form);
    form.submit();
    } // End proceedWithBooking function
  });

  // Function to check real-time availability before booking
  function checkAvailabilityBeforeBooking(tourDateId, packageId, callback) {
    $.ajax({
      url: ajax_object.ajaxurl,
      type: 'POST',
      data: {
        action: 'check_tour_availability',
        tour_date_id: tourDateId,
        package_id: packageId
      },
      success: function(response) {
        if (response.success && response.data) {
          console.log('Availability Check Response:', response.data);
          
          // Show user-friendly alert if not available
          if (!response.data.can_book) {
            var availableSlots = response.data.available_slots;
            var requiredSlots = response.data.package_vehicles;
            var message;
            
            if (availableSlots === 0) {
              message = 'Sorry, this tour is now fully booked. There are no remaining spots available.';
            } else {
              message = 'Sorry, there isn\'t enough space for your selected package. There ' + (availableSlots === 1 ? 'is' : 'are') + ' ' + availableSlots + ' remaining spot' + (availableSlots === 1 ? '' : 's') + ', but the selected package requires ' + requiredSlots + '.';
            }
            
            alert(message + '\n\nThe page will refresh to show current availability and other package options.');
          }
          
          callback(response.data.can_book === true);
        } else {
          // If there's an error, err on the side of caution and assume not available
          console.log('Availability Check Error:', response);
          callback(false);
        }
      },
      error: function() {
        // On AJAX error, err on the side of caution
        callback(false);
      }
    });
  }

  // Initial call to populate the package dropdown and set the initial state
  var tourId = $('#tourBookingForm').data("tour-id"); // Use the tour ID from the data attribute
  var tourTitle = $('#tourBookingForm').data('tour-title'); // Use the tour title from the data attribute
  
  // Load packages on initial page load (no date context yet)
  populatePackageDropdown(tourId);
  
  showHideFields();

  // Handle change event for the currency dropdown
  currencyDropdown.on("change", function () {
    const selectedOption = this.options[this.selectedIndex];
    const flag = selectedOption.getAttribute('data-flag');
    if (flag) {
      currencyFlag.text(flag).show();
    } else {
      currencyFlag.hide();
    }
    calculateTotalPrice(); // because it may need to show the converted price
  });

  // Trigger change event to display the initial flag
  currencyDropdown.trigger('change');

  function checkBookButtonState() {
    const vehicle1Selected = vehicleDropdown1.val() && vehicleDropdown1.prop('selectedIndex') !== 0;
    const vehicle2Selected = vehicleDropdown2.val() && vehicleDropdown2.prop('selectedIndex') !== 0;
  
    if (tourpackagedropdown.val() 
      && (
        (!vehicleDropdown1Container.is(":visible") && !vehicleDropdown2Container.is(":visible")) // No vehicle choices visible
        || (vehicleDropdown1Container.is(":visible") && vehicle1Selected && (!vehicleDropdown2Container.is(":visible") || vehicle2Selected)) // Vehicle 1 visible and has a value, and if Vehicle 2 is visible, it also has a value
      )
    ) {
      // Check if this should be booking or waiting list
      checkButtonDisplayState();
    } else {
      bookButton.prop("disabled", true);
      $("#waitingListButton").prop("disabled", true);
    }
  }

  // Function to check if waiting list button should be shown instead of book button
  function checkButtonDisplayState() {
    // Check if we have all required selections
    if (!tourdatedropdown.val() || !tourpackagedropdown.val()) {
      // Show book button but keep it disabled for consistent sizing
      bookButton.show().prop("disabled", true);
      $("#waitingListButton").hide();
      $("#bookButtonText").show();
      $("#waitingListButtonText").hide();
      return;
    }
    
    var isDateSoldOut = tourdatedropdown.find("option:selected").attr("data-sold-out") === "true";
    var isPackageUnavailable = tourpackagedropdown.find("option:selected").attr("data-unavailable") === "true";
    
    if (isDateSoldOut || isPackageUnavailable) {
      // Show waiting list button, hide book button
      bookButton.hide();
      $("#bookButtonText").hide();
      $("#waitingListButton").show().prop("disabled", false);
      $("#waitingListButtonText").show();
    } else {
      // Show book button, hide waiting list button
      $("#waitingListButton").hide();
      $("#waitingListButtonText").hide();
      bookButton.show().prop("disabled", false);
      $("#bookButtonText").show();
    }
  }

  // Handle waiting list button click
  $("#waitingListButton").on("click", function(e) {
    e.preventDefault();
    showWaitingListForm();
  });

  // Function to show waiting list confirmation form
  function showWaitingListForm() {
    // Clear any existing errors
    hideWaitingListError();
    
    // Show the waiting list form
    $("#waitingListForm").show();
    
    // Scroll to the form
    $('html, body').animate({
      scrollTop: $("#waitingListForm").offset().top - 50
    }, 500);
    
    // Bind events if not already bound
    bindWaitingListEvents();
  }

  // Initial binding of waiting list events
  bindWaitingListEvents();

  // Function to validate waiting list form
  function validateWaitingListForm() {
    var firstName = $("#waitingList_firstName").val().trim();
    var lastName = $("#waitingList_lastName").val().trim();
    var email = $("#waitingList_email").val().trim();
    var phone = $("#waitingList_phone").val().trim();
    
    if (firstName && lastName && email && phone) {
      $("#submitWaitingList").prop("disabled", false).css("background", "#28a745");
    } else {
      $("#submitWaitingList").prop("disabled", true).css("background", "#6c757d");
    }
  }

  // Function to hide and reset waiting list form
  function hideWaitingListForm() {
    $("#waitingListForm").hide();
    // Reset the form content to original structure
    $("#waitingListForm").html(
      '<h3 style="color: #8a6d3b; margin-top: 0;">Join Waiting List</h3>' +
      '<div class="customer-info-form">' +
      '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">' +
      '<div><label for="waitingList_firstName" style="display: block; font-weight: bold; margin-bottom: 5px;">First Name</label>' +
      '<input type="text" id="waitingList_firstName" name="first_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>' +
      '<div><label for="waitingList_lastName" style="display: block; font-weight: bold; margin-bottom: 5px;">Last Name</label>' +
      '<input type="text" id="waitingList_lastName" name="last_name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>' +
      '</div>' +
      '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">' +
      '<div><label for="waitingList_email" style="display: block; font-weight: bold; margin-bottom: 5px;">Email</label>' +
      '<input type="email" id="waitingList_email" name="email" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>' +
      '<div><label for="waitingList_phone" style="display: block; font-weight: bold; margin-bottom: 5px;">Phone</label>' +
      '<input type="tel" id="waitingList_phone" name="phone" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></div>' +
      '</div>' +
      '<div style="margin-bottom: 20px;"><label for="waitingList_notes" style="display: block; font-weight: bold; margin-bottom: 5px;">Notes</label>' +
      '<textarea id="waitingList_notes" name="notes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Any additional notes or special requests..."></textarea></div>' +
      '<div style="text-align: center;">' +
      '<button type="button" id="cancelWaitingList" class="button" style="margin-right: 10px; background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Cancel</button>' +
      '<button type="button" id="submitWaitingList" class="button" disabled style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Add to Waiting List</button>' +
      '</div>' +
      '</div>'
    );
    
    // Re-bind event handlers
    bindWaitingListEvents();
  }
  
  // Function to bind waiting list form events
  function bindWaitingListEvents() {
    // Handle cancel waiting list
    $("#cancelWaitingList").on("click", function() {
      hideWaitingListForm();
    });

    // Handle waiting list form validation
    $("#waitingList_firstName, #waitingList_lastName, #waitingList_email, #waitingList_phone").on("input", function() {
      hideWaitingListError(); // Clear any existing errors when user starts typing
      validateWaitingListForm();
    });
    
    // Handle waiting list submission
    $("#submitWaitingList").on("click", function(e) {
      e.preventDefault();
      submitWaitingList();
    });
  }

  // Handle waiting list submission
  function submitWaitingList() {
    var $button = $("#submitWaitingList");
    var originalText = $button.text();
    
    // Clear any existing errors
    hideWaitingListError();
    
    // Show loading state
    $button.prop("disabled", true).text("Adding to Waiting List...");
    
    // Collect form data
    var vehicleChoices = getVehicleChoices();
    var formData = {
      action: 'bst_create_waiting_list_booking',
      tour_id: $('#tourBookingForm').data("tour-id"),
      'tour_type': $('#tourBookingForm').data("tour-type"),
      tour_date_id: tourdatedropdown.val(),
      tour_package_id: tourpackagedropdown.find("option:selected").data("id"),
      tour_package_text: cleanPackageText(tourpackagedropdown.find("option:selected").text()),
      first_name: $("#waitingList_firstName").val().trim(),
      last_name: $("#waitingList_lastName").val().trim(),
      email: $("#waitingList_email").val().trim(),
      phone: $("#waitingList_phone").val().trim(),
      notes: $("#waitingList_notes").val().trim(),
      tour_price: parseFloat($("#tourprice").val().replace(/[^0-9.-]+/g,"")) || 0,
      vehicle_choices: vehicleChoices,
      vehicle1: vehicleDropdown1.find("option:selected").text() || '',
      tour_extension_added: extensionCheckbox.is(":checked") ? '1' : '',
      tour_extension_text: extensionCheckbox.is(":checked") ? (typeof tourExtensionSettings !== 'undefined' ? tourExtensionSettings.title + ' (+' + (($("#tourprice").data("currency") || "EUR") === "USD" ? "$" : "€") + (extensionCheckbox.data('price') || 0).toFixed(0) + ')' : '') : '',
      tour_extension_date_text: extensionCheckbox.is(":checked") ? (extensionCheckbox.data("extension-dates") || '') : '',
      source: typeof sessionData !== 'undefined' ? sessionData.source : '',
      referrer: typeof sessionData !== 'undefined' ? sessionData.referrer : ''
    };
    
    // Only add vehicle2 if vehicle_choices is greater than 1
    if (vehicleChoices > 1) {
      formData.vehicle2 = vehicleDropdown2.find("option:selected").text() || '';
    }
    
    // Submit via AJAX
    $.ajax({
      url: ajax_object.ajaxurl,
      type: "POST",
      data: formData,
      success: function(response) {
        if (response.success) {
          // Create queue position message
          var queueMessage = '';
          var queuePosition = null;
          
          // Check for queue_position in response.data
          if (response.data && typeof response.data.queue_position !== 'undefined') {
            queuePosition = parseInt(response.data.queue_position);
          }
          // Also check directly in response (in case structure is different)
          else if (typeof response.queue_position !== 'undefined') {
            queuePosition = parseInt(response.queue_position);
          }
          
          if (queuePosition !== null) {
            if (queuePosition === 0) {
              queueMessage = '<p style="font-size: 14px; margin-bottom: 10px; color: #28a745; font-weight: bold;">You are first in line for this tour date!</p>';
            } else {
              queueMessage = '<p style="font-size: 14px; margin-bottom: 10px; color: #f0ad4e; font-weight: bold;">You are #' + (queuePosition + 1) + ' in line for this tour date (' + queuePosition + ' guest' + (queuePosition === 1 ? '' : 's') + ' ahead of you in the queue).</p>';
            }
          }
          
          // Show success message
          $("#waitingListForm").html(
            '<div style="text-align: center; padding: 20px;">' +
            '<h4 style="color: #28a745; margin-bottom: 10px;">✓ Successfully Added to Waiting List!</h4>' +
            queueMessage +
            '<p style="font-size: 14px; margin-bottom: 15px;">Thank you! We have added you to the waiting list for this tour. We will contact you if a spot becomes available.</p>' +
            '<button type="button" id="waitingListOkButton" style="background: #007cba; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">OK</button>' +
            '</div>'
          );
          
          // Handle OK button click
          $("#waitingListOkButton").on("click", function() {
            hideWaitingListForm();
          });
        } else {
          showWaitingListError(response.data || "Unable to add to waiting list. Please try again.");
          $button.prop("disabled", false).text(originalText);
        }
      },
      error: function() {
        showWaitingListError("Unable to add to waiting list. Please try again.");
        $button.prop("disabled", false).text(originalText);
      }
    });
  }

  // Helper function to clean package text by removing pricing and availability messages
  function cleanPackageText(packageText) {
    if (!packageText) return '';
    
    // Remove pricing information (anything after " - ")
    var text = packageText.split(' - ')[0];
    
    // Remove availability messages
    text = text.replace(/\s*\(Not Available\)$/i, '');
    text = text.replace(/\s*\(Sold Out\)$/i, '');
    text = text.replace(/\s*\(Unavailable\)$/i, '');
    
    return text.trim();
  }

  // Helper function to get vehicle choices
  function getVehicleChoices() {
    var choices = 0;
    if (vehicleDropdown1.is(":visible") && vehicleDropdown1.val() && vehicleDropdown1.prop('selectedIndex') !== 0) {
      choices++;
    }
    if (vehicleDropdown2.is(":visible") && vehicleDropdown2.val() && vehicleDropdown2.prop('selectedIndex') !== 0) {
      choices++;
    }
    return choices;
  }

  // Helper function to show error message in waiting list form
  function showWaitingListError(message) {
    $("#waitingListErrorText").text(message);
    $("#waitingListError").show();
    // Scroll to the error message
    $("#waitingListError")[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Helper function to hide error message in waiting list form
  function hideWaitingListError() {
    $("#waitingListError").hide();
    $("#waitingListErrorText").text("");
  }

  // ========================================
  // Schedule Date Display Feature
  // ========================================
  
  var scheduleContent = $("#scheduleContent");
  var scheduleDateMessage = $("#scheduleDateMessage");
  var originalScheduleHTML = scheduleContent.html(); // Store original HTML
  
  // Update schedule display based on tour date selection
  function updateScheduleDisplay() {
    if (tourdatedropdown.val()) {
      // Show schedule with dates and update message
      scheduleDateMessage.text("Schedule dates based on tour date selection above.");
      showScheduleWithDates();
    } else {
      // Restore original schedule and update message
      scheduleDateMessage.text("Select a date above to see schedule based on tour dates.");
      scheduleContent.html(originalScheduleHTML);
    }
  }
  
  // Update schedule when tour date changes
  tourdatedropdown.on("change", function() {
    updateScheduleDisplay();
  });
  
  // Auto-select date if only one option available (besides "Select a Tour Date")
  // DISABLED: Causing timing issues with package loading
  // var dateOptions = tourdatedropdown.find("option[value!='']");
  // if (dateOptions.length === 1) {
  //   tourdatedropdown.val(dateOptions.first().val()).trigger("change");
  // }
  
  // Function to replace Day X with actual dates
  function showScheduleWithDates() {
    var selectedTourDateId = tourdatedropdown.val();
    if (!selectedTourDateId || typeof tourDatesData === 'undefined') {
      return;
    }
    
    // Find the selected tour date
    var selectedDate = tourDatesData.find(function(date) {
      return date.id == selectedTourDateId;
    });
    
    if (!selectedDate) {
      return;
    }
    
    // Parse start date (YYYYMMDD format)
    var startDateStr = String(selectedDate.start_date);
    var startYear = parseInt(startDateStr.substring(0, 4));
    var startMonth = parseInt(startDateStr.substring(4, 6)) - 1; // JS months are 0-indexed
    var startDay = parseInt(startDateStr.substring(6, 8));
    var tourStartDate = new Date(startYear, startMonth, startDay);
    
    // Get the schedule HTML
    var scheduleHTML = originalScheduleHTML;
    
    // Find the first day marker in the content.
    // If the schedule starts at Day 1, that should map to the tour start date.
    // If it starts at Day 0, Day 0 maps to the start date.
    var firstDayMatch = scheduleHTML.match(/<strong>Day\s+(\d+)[^<]*<\/strong>/i);
    var baseDayNumber = firstDayMatch ? parseInt(firstDayMatch[1], 10) : 0;
    if (isNaN(baseDayNumber)) {
      baseDayNumber = 0;
    }

    // Replace all Day X labels with actual calendar dates.
    // Pattern: <strong>Day 0</strong>, <strong>Day 1-ext</strong>, etc.
    scheduleHTML = scheduleHTML.replace(/<strong>Day\s+(\d+)[^<]*<\/strong>/gi, function(match, dayNum) {
      // Calculate the date for this day
      var currentDate = new Date(tourStartDate);
      var dayNumber = parseInt(dayNum, 10);
      if (isNaN(dayNumber)) {
        dayNumber = baseDayNumber;
      }
      currentDate.setDate(currentDate.getDate() + (dayNumber - baseDayNumber));
      
      // Format as "30 Aug" (day + abbreviated month)
      var day = currentDate.getDate();
      var month = currentDate.toLocaleDateString('en-US', { month: 'short' });
      
      // Return just the date, removing any suffix that was there
      return '<strong>' + day + ' ' + month + '</strong>';
    });
    
    // Update the schedule content
    scheduleContent.html(scheduleHTML);
  }
  
  // Initial check - update schedule display on page load
  updateScheduleDisplay();
});