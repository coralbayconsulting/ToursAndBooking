<?php
/**
 * Template for creating a new tou<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>

    <div id="booking-creation-form" style="background: white; padding: 20p        <!-- Action Buttons -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: right;">
            <button type="button" id="cancel-booking-btn" class="button" style="margin-right: 10px;">Cancel</button>
            
            <button type="button" id="create-booking-btn" class="button button-primary">`n-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">`ng.
 *
 * Available variables:
 *   - $tours: Array of tour posts
 *   - $selected_tour_id: Pre-selected tour ID from filters
 *   - $selected_tour_date_id: Pre-selected tour date ID from filters
 *   - $booking_type: Type of booking (paper, waiting_list, reservation)
 */

// Get current user info for defaults
$current_user = wp_get_current_user();
$default_commission_reason = '';
$default_commission_percent = 0;
$default_booking_method = '';
$default_status = '';

// Set defaults based on booking type
switch($booking_type) {
    case 'paper':
        $default_commission_reason = 'Bill';
        $default_commission_percent = 2; // Store as percentage for display
        $default_booking_method = 'Offline';
        $default_status = 'Booked';
        $page_title = 'Add Paper Booking';
        break;
    case 'waiting_list':
        $default_commission_reason = '';
        $default_commission_percent = 0;
        $default_booking_method = 'Web';
        $default_status = 'Waiting List';
        $page_title = 'Add to Waiting List';
        break;
    case 'reservation':
        $default_commission_reason = '';
        $default_commission_percent = 0;
        $default_booking_method = 'Web';
        $default_status = 'Reserved';
        $page_title = 'Add Reservation';
        break;
    default:
        $page_title = 'Add New Booking';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <?php if ($booking_type !== 'waiting_list' && $booking_type !== 'reservation'): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings' . 
        ($selected_tour_id ? '&filter_tour_id=' . $selected_tour_id : '') . 
        ($selected_tour_date_id ? '&filter_tour_date_id=' . $selected_tour_date_id : ''))); ?>" class="page-title-action">← Back to Bookings</a>
    <?php endif; ?>

    <div id="booking-creation-form" style="background: white; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        
        <!-- Progress Message -->
        <div id="progress-message" style="background: #f0f6fc; border: 1px solid #b6d7ff; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
            <p style="margin: 0; color: #0969da; font-weight: 600;">
                <i class="fas fa-info-circle"></i> 
                <span id="progress-text">Please select a tour, tour date, and package to continue with your booking.</span>
            </p>
        </div>
        
        <!-- Tour & Package Information Section (moved to top) -->
        <div class="creation-section" style="margin-bottom: 30px;">
            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">Tour & Package Information</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                <div>
                    <label for="tour_id" style="display: block; font-weight: 600; margin-bottom: 5px;">Tour *</label>
                    <select id="tour_id" name="tour_id" required style="width: 100%; padding: 8px;">
                        <option value="">Select a Tour</option>
                        <?php foreach ($tours as $tour): ?>
                            <option value="<?php echo esc_attr($tour->ID); ?>" <?php selected($selected_tour_id, $tour->ID); ?>>
                                <?php echo esc_html($tour->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="tour_date_id" style="display: block; font-weight: 600; margin-bottom: 5px;">Tour Date *</label>
                    <select id="tour_date_id" name="tour_date_id" required style="width: 100%; padding: 8px;">
                        <option value="">Select Tour First</option>
                    </select>
                </div>
                
                <div>
                    <label for="tour_package_id" style="display: block; font-weight: 600; margin-bottom: 5px;">Package *</label>
                    <select id="tour_package_id" name="tour_package_id" required style="width: 100%; padding: 8px;">
                        <option value="">Select Tour First</option>
                    </select>
                </div>
                
                <div>
                    <label for="tour_currency" style="display: block; font-weight: 600; margin-bottom: 5px;">Currency</label>
                    <select id="tour_currency" name="tour_currency" style="width: 100%; padding: 8px;">
                        <?php if ($booking_type === 'paper'): ?>
                            <option value="USD" selected>USD ($)</option>
                            <option value="EUR">EUR (€)</option>
                        <?php else: ?>
                            <option value="">Select Currency</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="USD">USD ($)</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <!-- Tour Price section at bottom of Tour & Package Info -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="display: flex; align-items: end; gap: 8px;">
                        <div style="flex: 1;">
                            <label for="tour_price" style="display: block; font-weight: 600; margin-bottom: 5px;">Tour Price</label>
                            <input type="number" id="tour_price" name="tour_price" step="0.01" style="width: 100%; padding: 8px;" readonly>
                            <small style="color: #666;">Automatically populated from package selection</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px; margin-bottom: 20px;">
                            <input type="checkbox" id="price_override_checkbox" style="margin: 0;">
                            <label for="price_override_checkbox" style="margin: 0; font-weight: 600; font-size: 12px;">Override</label>
                        </div>
                    </div>
                    <div></div> <!-- Empty cell for grid alignment -->
                </div>
            </div>
        </div>

        <!-- Essential Information Section -->
        <div class="creation-section" id="essential-section" style="margin-bottom: 30px; display: none;">
            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">Essential Information</h3>
            
            <!-- Email field moved to top with Populate from Customer button -->
            <div style="margin-top: 15px; margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end;">
                    <div>
                        <label for="guest1_email" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 1 Email *</label>
                        <input type="email" id="guest1_email" name="guest1_email" required style="width: 100%; padding: 8px;">
                    </div>
                    <div>
                        <button type="button" id="populate-from-customer" class="button button-secondary" style="height: 42px; white-space: nowrap;">
                            <i class="fas fa-user-plus"></i> Populate from Customer
                        </button>
                    </div>
                    <div></div> <!-- Empty cell for grid alignment -->
                </div>
                <small style="color: #666; display: block; margin-top: 5px;">Enter email and click "Populate from Customer" to auto-fill customer information</small>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label for="guest1_first_name" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 1 First Name *</label>
                    <input type="text" id="guest1_first_name" name="guest1_first_name" required style="width: 100%; padding: 8px;">
                </div>
                
                <div>
                    <label for="guest1_last_name" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 1 Last Name *</label>
                    <input type="text" id="guest1_last_name" name="guest1_last_name" required style="width: 100%; padding: 8px;">
                </div>
                
                <div>
                    <label for="guest1_phone" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 1 Phone</label>
                    <input type="tel" id="guest1_phone" name="guest1_phone" style="width: 100%; padding: 8px;">
                </div>
                
                <div></div> <!-- Empty cell for alignment -->
            </div>
            
            <!-- Guest 2 fields (shown when package_people = 2) -->
            <div id="guest2_fields" style="display: none; margin-top: 15px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label for="guest2_first_name" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 2 First Name</label>
                        <input type="text" id="guest2_first_name" name="guest2_first_name" style="width: 100%; padding: 8px;">
                    </div>
                    
                    <div>
                        <label for="guest2_last_name" style="display: block; font-weight: 600; margin-bottom: 5px;">Guest 2 Last Name</label>
                        <input type="text" id="guest2_last_name" name="guest2_last_name" style="width: 100%; padding: 8px;">
                    </div>
                </div>
            </div>
        </div>

        <?php if ($booking_type === 'paper'): ?>
        <!-- Deposit Payment Section (renamed and only for paper bookings) -->
        <div class="creation-section" id="deposit-section" style="margin-bottom: 30px; display: none;">
            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">Deposit Payment</h3>
            
            <div style="margin-top: 15px;">
                <div style="display: grid; grid-template-columns: 100px 130px 1fr; gap: 12px;">
                    <div>
                        <label for="deposit_payment_amount" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Amount</label>
                        <input type="number" id="deposit_payment_amount" name="deposit_payment_amount" step="0.01" style="width: 100%; padding: 6px; font-size: 13px;">
                        <small style="color: #666; font-size: 11px;">Optional</small>
                    </div>
                    
                    <div>
                        <label for="deposit_payment_method" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Method</label>
                        <select id="deposit_payment_method" name="deposit_payment_method" style="width: 100%; padding: 6px; font-size: 13px;">
                            <option value="">Select Method</option>
                            <option value="Bank Wire">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                        <small style="color: #666; font-size: 11px;">If amount entered</small>
                    </div>
                    
                    <div>
                        <label for="deposit_payment_date" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px;">Date</label>
                        <input type="date" id="deposit_payment_date" name="deposit_payment_date" style="width: 100%; padding: 6px; font-size: 13px; max-width: 150px;" value="<?php echo date('Y-m-d'); ?>">
                        <small style="color: #666; font-size: 11px;">Defaults to today</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes Section -->
        <div class="creation-section" id="notes-section" style="margin-bottom: 30px; display: none;">
            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ddd;">Notes</h3>
            
            <div style="margin-top: 15px;">
                <label for="notes" style="display: block; font-weight: 600; margin-bottom: 5px;">Booking Notes</label>
                <textarea id="notes" name="notes" rows="4" style="width: 100%; padding: 8px;" placeholder="Enter any additional notes about this booking..."></textarea>
            </div>
        </div>

        <!-- Hidden Administrative Fields -->
        <input type="hidden" name="booking_type" value="<?php echo esc_attr($booking_type); ?>">
        <input type="hidden" name="booking_method" value="<?php echo esc_attr($default_booking_method); ?>">
        <input type="hidden" name="booking_status" value="<?php echo esc_attr($default_status); ?>">
        <input type="hidden" name="booking_commission_percent" value="<?php echo esc_attr($default_commission_percent / 100); ?>"> <!-- Store as decimal -->
        <input type="hidden" name="booking_commission_reason" value="<?php echo esc_attr($default_commission_reason); ?>">
        <input type="hidden" name="data_source" value="<?php 
            if ($booking_type === 'waiting_list') {
                echo 'Waiting List';
            } elseif ($booking_type === 'reservation') {
                echo 'Reservation';
            } else {
                echo 'Bill Booking';
            }
        ?>">
        <input type="hidden" name="action" value="bst_create_booking">
        
        <!-- Package-related fields (populated when package is selected) -->
        <input type="hidden" id="package_people" name="package_people" value="">
        <input type="hidden" id="package_rooms" name="package_rooms" value="">
        <input type="hidden" id="package_vehicles" name="package_vehicles" value="">
        <input type="hidden" id="vehicle_choices" name="vehicle_choices" value="">
        
        <?php wp_nonce_field('bst_create_booking', 'create_booking_nonce'); ?>

        <!-- Action Buttons -->
        <div id="action-buttons" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: right; display: none;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=bst-tour-bookings' . 
                ($selected_tour_id ? '&filter_tour_id=' . $selected_tour_id : '') . 
                ($selected_tour_date_id ? '&filter_tour_date_id=' . $selected_tour_date_id : ''))); ?>" 
               class="button" style="margin-right: 10px;">Cancel</a>
            
            <button type="button" id="create-booking-btn" class="button button-primary">
                <?php if ($booking_type === 'paper'): ?>
                    Create Paper Booking
                <?php elseif ($booking_type === 'waiting_list'): ?>
                    Add to Waiting List
                <?php elseif ($booking_type === 'reservation'): ?>
                    Create Reservation
                <?php else: ?>
                    Create Booking
                <?php endif; ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize tour package dependencies
    initializeCreateBookingDependencies();
    
    // Create booking button handler
    $('#create-booking-btn').on('click', function(e) {
        e.preventDefault();
        createBooking();
    });
    
    // Cancel button handler - return to list with preserved filters
    $('#cancel-booking-btn').on('click', function(e) {
        e.preventDefault();
        
        // Build return URL with current filters preserved
        var params = new URLSearchParams(window.location.search);
        var returnParams = new URLSearchParams();
        
        // Add the base page parameter
        returnParams.set('page', 'bst-tour-bookings');
        
        // Preserve filter/search/sort parameters from the list view
        var filterParams = [
            'filter_tour_id',
            'filter_tour_date_id',
            'filter_package_id',
            'guest1_first_name',
            'guest1_last_name',
            'guest1_email',
            'tour_id',
            'tour_date_id',
            'tour_package_id',
            'booking_status',
            'tour_currency',
            'how_heard',
            'source',
            'referrer',
            'current_page',
            'per_page',
            'search',
            'sort_by',
            'sort_order'
        ];
        filterParams.forEach(function(param) {
            var value = params.get(param);
            if (value) {
                returnParams.set(param, value);
            }
        });
        
        // Build return URL
        var returnUrl = '<?php echo admin_url('admin.php'); ?>';
        
        if (returnParams.toString()) {
            returnUrl += '?' + returnParams.toString();
        }
        
        // Navigate back to list
        window.location.href = returnUrl;
    });
    
    function initializeCreateBookingDependencies() {
        // Initially hide dependent sections until tour, date, and package are selected
        hideDependentSections();
        
        // Tour selection handler
        $('#tour_id').on('change', function() {
            var tourId = $(this).val();
            if (tourId) {
                loadTourDates(tourId);
                loadPackages(tourId);
                updateTourCurrency(tourId);
            } else {
                clearTourDates();
                clearPackages();
                hideDependentSections();
            }
        });
        
        // Tour date selection handler
        $('#tour_date_id').on('change', function() {
            // Check if all required selections are made
            showDependentSections();
        });
        
        // Currency change handler
        $('#tour_currency').on('change', function() {
            updateTourPriceEditability();
            loadPackages($('#tour_id').val()); // Reload packages to show/hide prices
        });
        
        // Package selection handler
        $('#tour_package_id').on('change', function() {
            updateTourPrice();
            setPackageData();
            // Reset manual price checkbox when package changes
            $('#price_override_checkbox').prop('checked', false);
            updateTourPriceEditability();
        });
        
        // Manual price checkbox handler
        $('#price_override_checkbox').on('change', function() {
            updateTourPriceEditability();
        });
        
        // If we have a pre-selected tour, load its dependencies
        var preSelectedTour = $('#tour_id').val();
        if (preSelectedTour) {
            var preSelectedDate = '<?php echo esc_js($selected_tour_date_id); ?>';
            loadTourDates(preSelectedTour, preSelectedDate);
            loadPackages(preSelectedTour);
            updateTourCurrency(preSelectedTour);
        }
        
        // Initialize tour price editability based on current currency
        updateTourPriceEditability();
    }
    
    function loadTourDates(tourId, preSelectedDate) {
        var $dateSelect = $('#tour_date_id');
        $dateSelect.html('<option value="">Loading dates...</option>');
        
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_get_tour_dates',
                tour_id: tourId,
                nonce: '<?php echo wp_create_nonce('bst_tour_bookings_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">Select a Tour Date</option>';
                    response.data.forEach(function(date) {
                        options += '<option value="' + date.id + '">' + date.text + '</option>';
                    });
                    $dateSelect.html(options);
                    
                    // Apply pre-selected date if provided
                    if (preSelectedDate && preSelectedDate !== '') {
                        $dateSelect.val(preSelectedDate);
                        // Trigger change event to load packages if needed
                        $dateSelect.trigger('change');
                    }
                } else {
                    $dateSelect.html('<option value="">No dates available</option>');
                }
            },
            error: function() {
                $dateSelect.html('<option value="">Error loading dates</option>');
            }
        });
    }
    
    function updateTourCurrency(tourId) {
        // Get tour currency and update the currency dropdown
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_tour_currency',
                tour_id: tourId
            },
            success: function(response) {
                if (response.success && response.data && response.data.currency) {
                    $('#tour_currency').val(response.data.currency);
                    // Trigger change event to update packages and price fields
                    $('#tour_currency').trigger('change');
                }
            },
            error: function(xhr, status, error) {
                console.log('Error getting tour currency:', error);
            }
        });
    }
    
    function loadPackages(tourId) {
        var $packageSelect = $('#tour_package_id');
        $packageSelect.html('<option value="">Loading packages...</option>');
        
        var currency = $('#tour_currency').val();
        var showPrices = currency !== 'USD'; // Hide prices for USD
        
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_package_pricing',
                tour_id: tourId
            },
            success: function(response) {
                if (response.success && response.data && response.data.packages) {
                    var options = '<option value="">Select a Package *</option>';
                    response.data.packages.forEach(function(pkg) {
                        // Store price as data attribute for tour price calculation
                        var price = pkg.value || 0;
                        var displayText = pkg.text;
                        
                        // If USD currency, remove the price portion from display text
                        if (!showPrices && displayText.includes(' - ')) {
                            displayText = displayText.split(' - ')[0]; // Keep only the package name
                        }
                        
                        options += '<option value="' + pkg['data.id'] + '" data-price="' + price + '" data-package-id="' + pkg['data.id'] + '">' + displayText + '</option>';
                    });
                    $packageSelect.html(options);
                    updateTourPriceEditability(); // Update price field editability
                    updateTourPrice(); // Update price after loading packages
                } else {
                    $packageSelect.html('<option value="">No packages available</option>');
                }
            },
            error: function(xhr, status, error) {
                $packageSelect.html('<option value="">Error loading packages</option>');
            }
        });
    }
    
    function updateTourPrice() {
        var $packageSelect = $('#tour_package_id');
        var $priceField = $('#tour_price');
        var $selectedOption = $packageSelect.find('option:selected');
        var currency = $('#tour_currency').val();
        
        if ($selectedOption.length && $selectedOption.val() && $selectedOption.data('price')) {
            var price = parseFloat($selectedOption.data('price'));
            $priceField.val(price.toFixed(2));
        } else {
            $priceField.val('');
        }
        
        // Update editability based on currency
        updateTourPriceEditability();
    }
    
    function updateTourPriceEditability() {
        var $priceField = $('#tour_price');
        var $checkbox = $('#price_override_checkbox');
        
        if ($checkbox.is(':checked')) {
            // Make editable when checkbox is checked
            $priceField.prop('readonly', false);
            $priceField.css('background-color', '#fff');
            $priceField.css('border', '2px solid #007cba');
        } else {
            // Keep readonly when checkbox is unchecked
            $priceField.prop('readonly', true);
            $priceField.css('background-color', '#f5f5f5');
            $priceField.css('border', '1px solid #ddd');
        }
    }
    
    function setPackageData() {
        var $packageSelect = $('#tour_package_id');
        var $selectedOption = $packageSelect.find('option:selected');
        var packageId = $selectedOption.val();
        
        if (!packageId) {
            // Clear package data if no package selected
            $('#package_people').val('');
            $('#package_rooms').val('');
            $('#package_vehicles').val('');
            $('#vehicle_choices').val('');
            $('#guest2_fields').hide();
            
            // Hide sections that depend on package selection
            hideDependentSections();
            return;
        }
        
        // Get package configuration from server
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: {
                action: 'bst_get_package_config',
                package_id: packageId
            },
            success: function(response) {
                if (response.success && response.data) {
                    $('#package_people').val(response.data.people || '');
                    $('#package_rooms').val(response.data.rooms || '');
                    $('#package_vehicles').val(response.data.vehicles || '');
                    $('#vehicle_choices').val(''); // Reset vehicle choices
                    
                    // Show/hide Guest 2 fields based on package_people
                    var packagePeople = parseInt(response.data.people || 0);
                    if (packagePeople === 2) {
                        $('#guest2_fields').show();
                    } else {
                        $('#guest2_fields').hide();
                        // Clear Guest 2 fields when hidden
                        $('#guest2_first_name').val('');
                        $('#guest2_last_name').val('');
                    }
                    
                    // Show dependent sections now that package is selected
                    showDependentSections();
                } else {
                    // Failed to get package configuration
                }
            },
            error: function(xhr, status, error) {
                // Package config AJAX error
            }
        });
    }
    
    function clearTourDates() {
        $('#tour_date_id').html('<option value="">Select Tour First</option>');
        hideDependentSections();
    }
    
    function clearPackages() {
        $('#tour_package_id').html('<option value="">Select Tour First</option>');
        $('#tour_price').val('');
        hideDependentSections();
    }
    
    function hideDependentSections() {
        $('#essential-section').hide();
        $('#deposit-section').hide();
        $('#notes-section').hide();
        $('#action-buttons').hide();
        $('#progress-message').show().text('Please select a tour, tour date, and package to continue.');
    }
    
    function showDependentSections() {
        var selectedTour = $('#tour_id').val();
        var selectedDate = $('#tour_date_id').val();
        var selectedPackage = $('#tour_package_id').val();
        
        if (selectedTour && selectedDate && selectedPackage) {
            $('#essential-section').show();
            $('#deposit-section').show();
            $('#notes-section').show();
            $('#action-buttons').show();
            $('#progress-message').hide();
        } else {
            hideDependentSections();
        }
    }
    
    function createBooking() {
        // Validate required fields (only those that are visible)
        var isValid = true;
        var $requiredFields = $('#booking-creation-form input[required]:visible, #booking-creation-form select[required]:visible');
        
        $requiredFields.each(function() {
            var $field = $(this);
            var fieldValue = $field.val();
            
            if (!fieldValue || fieldValue.trim() === '') {
                $field.addClass('error');
                if (!$field.next('.error-message').length) {
                    $field.after('<span class="error-message">This field is required</span>');
                }
                isValid = false;
            } else {
                $field.removeClass('error');
                $field.next('.error-message').remove();
            }
        });
        
        // Special validation for deposit fields (only for paper bookings)
        var bookingType = $('input[name="booking_type"]').val();
        
        if (bookingType === 'paper') {
            var depositAmount = parseFloat($('#deposit_payment_amount').val() || 0);
            
            if (depositAmount > 0) {
                var $methodField = $('#deposit_payment_method');
                var $dateField = $('#deposit_payment_date');
                
                if (!$methodField.val()) {
                    $methodField.addClass('error');
                    if (!$methodField.next('.error-message').length) {
                        $methodField.after('<span class="error-message">Payment method required when deposit amount is entered</span>');
                    }
                    isValid = false;
                } else {
                    $methodField.removeClass('error');
                    $methodField.next('.error-message').remove();
                }
                
                if (!$dateField.val()) {
                    $dateField.addClass('error');
                    if (!$dateField.next('.error-message').length) {
                        $dateField.after('<span class="error-message">Payment date required when deposit amount is entered</span>');
                    }
                    isValid = false;
                } else {
                    $dateField.removeClass('error');
                    $dateField.next('.error-message').remove();
                }
            } else {
                // No deposit amount, skip deposit validation
            }
        }
        
        if (!isValid) {
            showMessage('Please fill in all required fields', 'error');
            return;
        }
        
        // Collect form data
        var formData = {
            action: 'bst_create_booking',
            create_booking_nonce: '<?php echo wp_create_nonce('bst_create_booking'); ?>'
        };
        
        // Collect all form fields
        $('#booking-creation-form input, #booking-creation-form select, #booking-creation-form textarea').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            if (name) {
                formData[name] = $field.val();
            }
        });
        
        // Calculate and add derived fields
        var bookingType = formData.booking_type;
        var tourPrice = parseFloat(formData.tour_price) || 0;
        var depositAmount = parseFloat(formData.deposit_payment_amount) || 0;
        
        // 1. vehicle_choices - based on actual vehicle pricing grid rows available
        // Need to check if there are any vehicle pricing options for this tour/package
        // If no vehicle pricing rows exist, vehicle_choices should be 0
        
        // Make a synchronous check for vehicle data to determine vehicle_choices
        var vehicleChoices = 0;
        
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            async: false, // Make synchronous to get result before proceeding
            data: {
                action: 'get_vehicle_data',
                tour_id: formData.tour_id,
                package_id: formData.tour_package_id,
                currency: formData.tour_currency,
                vehicle_labels_for: 'staff',
                show_archived: 0
            },
            success: function(response) {
                if (response.success && response.data && Array.isArray(response.data)) {
                    var vehicleCount = response.data.length;
                    
                    if (vehicleCount === 0) {
                        vehicleChoices = 0; // No vehicles available
                    } else {
                        // For paper bookings, we set vehicle_choices based on package configuration
                        // since there's no user selection interface
                        var packageVehicles = parseInt(formData.package_vehicles) || 0;
                        vehicleChoices = Math.min(packageVehicles, vehicleCount); // Don't exceed available vehicles
                    }
                } else {
                    vehicleChoices = 0; // No vehicle data or error
                }
            },
            error: function() {
                vehicleChoices = 0; // Error case - assume no vehicles
            }
        });
        
        formData.vehicle_choices = vehicleChoices;
        
        // 3. net_tour_price - same as tour_price for new bookings (no coupons initially)
        formData.net_tour_price = tourPrice;
        
        // 4. total_paid - based on booking type
        if (bookingType === 'paper') {
            // For paper bookings, total_paid = deposit amount
            formData.total_paid = depositAmount;
        } else {
            // For reservations and waiting list, total_paid = 0
            formData.total_paid = 0;
        }
        
        // 5. additional_charge - default to 0 for new bookings
        formData.additional_charge = 0;
        
        // 6. balance_due - net tour price minus total paid
        formData.balance_due = formData.net_tour_price - formData.total_paid;
        
        // Show loading state
        var $createBtn = $('#create-booking-btn');
        $createBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');
        
        // Submit via AJAX
        $.ajax({
            url: window.ajaxurl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    var bookingType = formData.booking_type;
                    var bookingId = response.data.booking_id;
                    
                    if (bookingType === 'paper') {
                        // For paper bookings, redirect to edit page with filters preserved
                        var urlParams = new URLSearchParams(window.location.search);
                        var editUrlParams = new URLSearchParams();
                        
                        // Add the base edit parameters
                        editUrlParams.set('page', 'bst-tour-bookings');
                        editUrlParams.set('action', 'edit');
                        editUrlParams.set('id', bookingId);
                        
                        // Preserve filter parameters for the back button on edit page
                        var filterParams = ['filter_tour_id', 'filter_tour_date_id', 'filter_package_id', 'filter_status', 'filter_search', 'current_page', 'per_page'];
                        filterParams.forEach(function(param) {
                            var value = urlParams.get(param);
                            if (value) {
                                editUrlParams.set(param, value);
                            }
                        });
                        
                        var editUrl = '<?php echo admin_url('admin.php'); ?>' + '?' + editUrlParams.toString();
                        window.location.href = editUrl;
                    } else {
                        // For waiting list and reservations, go back to list with filters preserved
                        var returnUrl = '<?php echo admin_url('admin.php'); ?>';
                        
                        // Preserve current filters from URL parameters
                        var urlParams = new URLSearchParams(window.location.search);
                        
                        // Start building the return URL properly
                        var returnUrlParams = new URLSearchParams();
                        
                        // Add the base page parameter
                        returnUrlParams.set('page', 'bst-tour-bookings');
                        
                        if (urlParams.get('filter_tour_id')) {
                            returnUrlParams.set('filter_tour_id', urlParams.get('filter_tour_id'));
                        }
                        if (urlParams.get('filter_tour_date_id')) {
                            returnUrlParams.set('filter_tour_date_id', urlParams.get('filter_tour_date_id'));
                        }
                        if (urlParams.get('filter_status')) {
                            returnUrlParams.set('filter_status', urlParams.get('filter_status'));
                        }
                        if (urlParams.get('filter_search')) {
                            returnUrlParams.set('filter_search', urlParams.get('filter_search'));
                        }
                        
                        // Add success message
                        returnUrlParams.set('booking_created', '1');
                        returnUrlParams.set('booking_type', bookingType);
                        
                        // Properly construct the final URL
                        returnUrl += '?' + returnUrlParams.toString();
                        
                        window.location.href = returnUrl;
                    }
                } else {
                    showMessage('Error creating booking: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error creating booking: ' + error, 'error');
            },
            complete: function() {
                $createBtn.prop('disabled', false).html($createBtn.data('original-text') || 'Create Booking');
            }
        });
    }
    
    function showMessage(message, type) {
        // Create or update message div
        var $messageDiv = $('#creation-message');
        if (!$messageDiv.length) {
            $messageDiv = $('<div id="creation-message" style="margin: 15px 0; padding: 10px; border-radius: 4px;"></div>');
            $('#booking-creation-form').prepend($messageDiv);
        }
        
        $messageDiv.removeClass('success error')
                   .addClass(type)
                   .html(message)
                   .css({
                       'background-color': type === 'error' ? '#ffeaea' : '#eaffea',
                       'border-color': type === 'error' ? '#dc3232' : '#46b450',
                       'color': type === 'error' ? '#dc3232' : '#155724',
                       'border': '1px solid'
                   });
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                $messageDiv.fadeOut();
            }, 5000);
        }
    }
    
    // Store original button text
    $('#create-booking-btn').data('original-text', $('#create-booking-btn').text());
    
    // Populate from Customer button handler
    $('#populate-from-customer').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var email = $('#guest1_email').val().trim();
        var packageId = $('#tour_package_id').val();
        
        if (!packageId) {
            showMessage('Please select a package first.', 'error');
            return;
        }
        
        if (!email) {
            showMessage('Please enter an email address first.', 'error');
            return;
        }
        
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Searching...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'populate_customer_from_email',
                email: email,
                nonce: '<?php echo wp_create_nonce('populate_customer_from_email'); ?>'
            },
            success: function(response) {
                if (response.success && response.data) {
                    var customer = response.data.customer;
                    var commission = response.data.commission;
                    
                    // Populate customer fields
                    if (customer.first_name) $('#guest1_first_name').val(customer.first_name);
                    if (customer.last_name) $('#guest1_last_name').val(customer.last_name);
                    if (customer.phone) $('#guest1_phone').val(customer.phone);
                    if (customer.partner_first) $('#guest2_first_name').val(customer.partner_first);
                    if (customer.partner_last) $('#guest2_last_name').val(customer.partner_last);
                    
                    // Store customer ID for later use
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'customer_id',
                        value: customer.id
                    }).appendTo('#booking-creation-form');
                    
                    // Store commission info for later use
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'customer_commission_percent',
                        value: commission.percent
                    }).appendTo('#booking-creation-form');
                    
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'customer_commission_reason',
                        value: commission.reason
                    }).appendTo('#booking-creation-form');
                    
                    showMessage('Customer information populated successfully! Commission: ' + commission.percent + '% (' + commission.reason + ')', 'success');
                    
                    // Change button text to indicate success
                    $btn.html('<i class="fas fa-check"></i> Customer Found');
                    setTimeout(function() {
                        $btn.html(originalText);
                    }, 3000);
                    
                } else {
                    showMessage(response.data || 'Customer not found with this email address.', 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Error searching for customer: ' + error, 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
                if ($btn.html().indexOf('fa-spinner') !== -1) {
                    $btn.html(originalText);
                }
            }
        });
    });
    
    // Global ajaxurl for AJAX calls
    window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
});
</script>
