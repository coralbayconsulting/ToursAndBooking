jQuery(document).ready(function($) {
    // Debug logging to console - can be removed after debugging
    console.log('tour-dates-admin.js loaded');
    console.log('bstAdmin object:', typeof bstAdmin !== 'undefined' ? bstAdmin : 'undefined');

    // Tour edit screen — Related Tour Dates meta box: hide past and/or cancelled rows (display only).
    if (typeof bstTourDatesEmbedded !== 'undefined' && $('#bst-hide-past-embedded-tour-dates').length && $('#tour-dates-container').length) {
        function bstEmbeddedStartYmd($container) {
            var $inp = $container.find('input[name="start_date"]').first();
            var v = ($inp.val() || '').trim();
            return v ? v : (($container.attr('data-start-ymd') || '').trim());
        }

        function bstEmbeddedRowStatus($container) {
            var $sel = $container.find('select[name="status"]').first();
            return ($sel.val() || '').trim();
        }

        function bstApplyEmbeddedPastFilter() {
            var hide = $('#bst-hide-past-embedded-tour-dates').prop('checked');
            var today = (bstTourDatesEmbedded.todayYmd || '').trim();

            $('#tour-dates-container tbody tr.tour-date-item').each(function () {
                var $row = $(this);
                var ymd = bstEmbeddedStartYmd($row);
                var past = !!(ymd && today && ymd < today);
                var cancelled = bstEmbeddedRowStatus($row) === 'cancelled';
                var hideRow = hide && (past || cancelled);
                $row.toggle(!hideRow);
            });
            $('#tour-dates-container .mobile-cards .tour-date-card').each(function () {
                var $card = $(this);
                var ymd = bstEmbeddedStartYmd($card);
                var past = !!(ymd && today && ymd < today);
                var cancelled = bstEmbeddedRowStatus($card) === 'cancelled';
                var hideCard = hide && (past || cancelled);
                $card.toggle(!hideCard);
            });
        }

        $('#bst-hide-past-embedded-tour-dates').on('change', bstApplyEmbeddedPastFilter);
        $(document).on('change input', '#tour-dates-container input[name="start_date"]', function () {
            var $el = $(this).closest('.tour-date-item, .tour-date-card');
            $el.attr('data-start-ymd', ($(this).val() || '').trim());
            bstApplyEmbeddedPastFilter();
        });
        $(document).on('change', '#tour-dates-container select[name="status"]', bstApplyEmbeddedPastFilter);
        bstApplyEmbeddedPastFilter();
    }
    
    function showMessage(message, type) {
        var messageArea = $('#message-area');
        messageArea.removeClass();
        messageArea.addClass(type);
        messageArea.text(message);
        messageArea.show();
        setTimeout(function() {
            messageArea.fadeOut();
        }, 5000);
    }

    // Function to calculate and update availability for a row
    function updateAvailability($row) {
        console.log('updateAvailability called for row:', $row);
        
        // Calculate availability from current form values
        var maxSlots = parseInt($row.find('input[name="max_slots"]').val()) || 0;
        var soldSlots = parseInt($row.find('input[name="sold_slots"]').val()) || 0;
        var offlineSoldSlots = parseInt($row.find('input[name="offline_sold_slots"]').val()) || 0;
        var reservedSlots = parseInt($row.find('input[name="reserved_slots"]').val()) || 0;
        
        var calculatedAvailability = maxSlots - soldSlots - offlineSoldSlots - reservedSlots;
        calculatedAvailability = Math.max(0, calculatedAvailability); // Ensure never negative
        
        console.log('Calculated availability:', {
            maxSlots: maxSlots,
            soldSlots: soldSlots, 
            offlineSoldSlots: offlineSoldSlots,
            reservedSlots: reservedSlots,
            calculatedAvailability: calculatedAvailability
        });
        
        // Update availability ONLY when max_slots or offline_sold_slots changed
        var $maxInput = $row.find('input[name="max_slots"]');
        var $offlineInput = $row.find('input[name="offline_sold_slots"]');
        var $availInput = $row.find('input[name="available_slots"]');

        var lastMax = parseInt($maxInput.data('last-value'), 10);
        var lastOffline = parseInt($offlineInput.data('last-value'), 10);

        var maxChanged = isNaN(lastMax) || lastMax !== maxSlots;
        var offlineChanged = isNaN(lastOffline) || lastOffline !== offlineSoldSlots;

        if (maxChanged || offlineChanged) {
            $availInput.val(calculatedAvailability);
        }
    }

    // Function to update delete button state based on current values
    function updateDeleteButtonState($container) {
        var soldSlots = parseInt($container.find('input[name="sold_slots"]').val()) || 0;
        var offlineSoldSlots = parseInt($container.find('input[name="offline_sold_slots"]').val()) || 0;
        var reservedSlots = parseInt($container.find('input[name="reserved_slots"]').val()) || 0;
        
        var hasActivity = (soldSlots > 0 || offlineSoldSlots > 0 || reservedSlots > 0);
        var $deleteButton = $container.find('.delete-tour-date');
        
        if (hasActivity) {
            $deleteButton.prop('disabled', true);
            $deleteButton.attr('title', 'Cannot delete tour date with sold, offline, or reserved slots. Change status to Cancelled instead.');
        } else {
            $deleteButton.prop('disabled', false);
            $deleteButton.attr('title', 'Delete this tour date');
        }
    }

    // Cache initial max/offline values on load so we only treat real edits as changes
    $('.tour-date-item, .tour-date-card').each(function() {
        var $row = $(this);
        var $maxInput = $row.find('input[name="max_slots"]');
        var $offlineInput = $row.find('input[name="offline_sold_slots"]');

        $maxInput.data('last-value', parseInt($maxInput.val(), 10) || 0);
        $offlineInput.data('last-value', parseInt($offlineInput.val(), 10) || 0);
    });

    // Add real-time availability calculation on field changes
    $(document).on('input change', '.calculate-availability', function() {
        console.log('Availability calculation triggered');
        var $row = $(this).closest('.tour-date-item');
        var $card = $(this).closest('.tour-date-card');
        
        if ($row.length > 0) {
            updateAvailability($row);
            // Only disable delete button for safety - don't enable until server confirms save
            var soldSlots = parseInt($row.find('input[name="sold_slots"]').val()) || 0;
            var offlineSoldSlots = parseInt($row.find('input[name="offline_sold_slots"]').val()) || 0;
            var reservedSlots = parseInt($row.find('input[name="reserved_slots"]').val()) || 0;
            var hasActivity = (soldSlots > 0 || offlineSoldSlots > 0 || reservedSlots > 0);
            
            if (hasActivity) {
                var $deleteButton = $row.find('.delete-tour-date');
                $deleteButton.prop('disabled', true);
                $deleteButton.attr('title', 'Cannot delete tour date with sold, offline, or reserved slots. Save changes first.');
            }
        } else if ($card.length > 0) {
            updateCardAvailability($card);
            // Only disable delete button for safety - don't enable until server confirms save
            var soldSlots = parseInt($card.find('input[name="sold_slots"]').val()) || 0;
            var offlineSoldSlots = parseInt($card.find('input[name="offline_sold_slots"]').val()) || 0;
            var reservedSlots = parseInt($card.find('input[name="reserved_slots"]').val()) || 0;
            var hasActivity = (soldSlots > 0 || offlineSoldSlots > 0 || reservedSlots > 0);
            
            if (hasActivity) {
                var $deleteButton = $card.find('.delete-tour-date');
                $deleteButton.prop('disabled', true);
                $deleteButton.attr('title', 'Cannot delete tour date with sold, offline, or reserved slots. Save changes first.');
            }
        } else {
            console.error('Could not find .tour-date-item parent row or .tour-date-card parent');
            return;
        }
    });

    // Save tour date
    $(document).on('click', '.save-tour-date', function(e) {
        e.preventDefault();
        console.log('Save tour date button clicked');

        // Check if bstAdmin is available
        if (typeof bstAdmin === 'undefined') {
            console.error('bstAdmin object is not defined');
            showMessage('JavaScript configuration error. Please refresh the page.', 'error');
            return;
        }

        var $container = $(this).closest('.tour-date-item, .tour-date-card');
        var tourDateId = $container.find('input[name="tour_date_id"]').val();
        var startDate = $container.find('input[name="start_date"]').val();
        var endDate = $container.find('input[name="end_date"]').val();
        var maxSlots = $container.find('input[name="max_slots"]').val();
        var soldSlots = $container.find('input[name="sold_slots"]').val();
        var status = $container.find('select[name="status"]').val();
        var offlineSoldSlots = $container.find('input[name="offline_sold_slots"]').val();
        var reservedSlots = $container.find('input[name="reserved_slots"]').val();
        var availability = $container.find('input[name="available_slots"]').val();
        var extensionOffered = $container.find('input[name="extension_offered"]').is(':checked') ? '1' : '0';

        console.log('AJAX data being sent:', {
            action: 'save_tour_date',
            nonce: bstAdmin.nonce,
            tour_id: $('#post_ID').val(),
            tour_date_id: tourDateId,
            start_date: startDate,
            end_date: endDate,
            max_slots: maxSlots,
            sold_slots: soldSlots,
            offline_sold_slots: offlineSoldSlots,
            reserved_slots: reservedSlots,
            availability: availability,
            extension_offered: extensionOffered,
            status: status
        });

        $.ajax({
            url: bstAdmin.ajax_url,
            method: 'POST',
            data: {
                action: 'save_tour_date',
                nonce: bstAdmin.nonce,
                tour_id: $('#post_ID').val(),
                tour_date_id: tourDateId,
                start_date: startDate,
                end_date: endDate,
                max_slots: maxSlots,
                sold_slots: soldSlots,
                offline_sold_slots: offlineSoldSlots,
                reserved_slots: reservedSlots,
                availability: availability,
                extension_offered: extensionOffered,
                status: status
            },
            success: function(response) {
                console.log('AJAX response received:', response);
                if (response.success) {
                    showMessage('Tour date saved successfully.', 'success');
                    // Update the container with the new tour date ID if it was a new entry
                    if (!tourDateId) {
                        $container.find('input[name="tour_date_id"]').val(response.data.tour_date_id);
                    }
                    // Update availability with server-calculated value
                    if (response.data.available_slots !== undefined) {
                        $container.find('input[name="available_slots"]').val(response.data.available_slots);
                    }
                    // Update delete button state based on current slot values
                    updateDeleteButtonState($container);
                } else {
                    console.error('AJAX request failed:', response);
                    showMessage('Failed to save tour date: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                showMessage('AJAX request failed: ' + error, 'error');
            }
        });
    });

    // Delete tour date
    $(document).on('click', '.delete-tour-date', function(e) {
        e.preventDefault();

        var $container = $(this).closest('.tour-date-item, .tour-date-card');
        var tourDateId = $container.find('input[name="tour_date_id"]').val();

        if (tourDateId) {
            if (!confirm('Are you sure you want to delete this tour date?')) {
                return;
            }

            $.ajax({
                url: bstAdmin.ajax_url,
                method: 'POST',
                data: {
                    action: 'delete_tour_date',
                    nonce: bstAdmin.nonce,
                    tour_date_id: tourDateId
                },
                success: function(response) {
                    if (response.success) {
                        $container.remove();
                        showMessage('Tour date deleted successfully.', 'success');
                    } else {
                        showMessage('Failed to delete tour date.', 'error');
                    }
                }
            });
        } else {
            // If the tour date ID is not set, simply remove the container
            $container.remove();
            showMessage('Tour date deleted successfully.', 'success');
        }
    });

    // Sync tour date - update sold slots from bookings
    $(document).on('click', '.sync-tour-date', function(e) {
        e.preventDefault();

        var $container = $(this).closest('.tour-date-item, .tour-date-card');
        var tourDateId = $container.find('input[name="tour_date_id"]').val();
        var $button = $(this);

        if (!tourDateId) {
            showMessage('Cannot sync unsaved tour date.', 'error');
            return;
        }

        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.find('i').removeClass('fa-sync-alt').addClass('fa-spinner fa-spin');

        $.ajax({
            url: bstAdmin.ajax_url,
            method: 'POST',
            data: {
                action: 'sync_tour_date',
                nonce: bstAdmin.nonce,
                tour_date_id: tourDateId
            },
            success: function(response) {
                console.log('Sync response for tour date ' + tourDateId + ':', response);
                
                if (response.success && response.data) {
                    var data = response.data;
                    
                    // Update the UI fields
                    $container.find('input[name="sold_slots"]').val(data.sold_slots);
                    if (data.reserved_slots !== undefined) {
                        $container.find('input[name="reserved_slots"]').val(data.reserved_slots);
                    }
                    // Update availability field
                    if (data.available_slots !== undefined) {
                        $container.find('input[name="available_slots"]').val(data.available_slots);
                    }
                    
                    // Build detailed success message
                    var messages = [];
                    if (data.updates_made) {
                        var updateParts = [];
                        if (data.sold_updated) {
                            updateParts.push('sold slots updated');
                        }
                        if (data.reserved_updated) {
                            updateParts.push('reserved slots updated');
                        }
                        if (data.availability_updated) {
                            updateParts.push('availability updated');
                        }
                        messages.push('✓ Sync complete: ' + updateParts.join(', '));
                    } else {
                        messages.push('✓ Sync complete: no changes needed');
                    }
                    
                    if (data.tour_name && data.tour_name !== 'Unknown Tour') {
                        messages.push('Tour: ' + data.tour_name);
                    }
                    
                    messages.push('Available slots: ' + data.available_slots);
                    
                    if (data.debug && data.debug.sold_slots_change) {
                        messages.push('Sold: ' + data.debug.sold_slots_change);
                    }
                    if (data.debug && data.debug.reserved_slots_change) {
                        messages.push('Reserved: ' + data.debug.reserved_slots_change);
                    }
                    
                    showMessage(messages.join(' | '), 'success');
                    
                    // Log debug info
                    if (data.debug) {
                        console.log('Debug info for tour date ' + tourDateId + ':', data.debug);
                    }
                    
                    // Show any warnings
                    if (data.errors && data.errors.length > 0) {
                        console.warn('Warnings during sync:', data.errors);
                        showMessage('Sync completed with warnings: ' + data.errors.join(', '), 'warning');
                    }
                    
                } else {
                    var errorMsg = 'Failed to sync tour date';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    showMessage(errorMsg, 'error');
                    console.error('Sync failed for tour date ' + tourDateId + ':', response);
                    
                    // Log debug info for failed sync
                    if (response.data && response.data.debug) {
                        console.log('Debug info for failed sync:', response.data.debug);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error for tour date ' + tourDateId + ':', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                showMessage('Network error during sync: ' + error, 'error');
            },
            complete: function() {
                // Re-enable button and restore icon
                $button.prop('disabled', false);
                $button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-sync-alt');
            }
        });
    });

    // Add new tour date
    $('#add-tour-date').on('click', function(e) {
        e.preventDefault();

        var newRow = `
            <tr class="tour-date-item" data-start-ymd="">
                <td class="id-column"><input type="text" name="tour_date_id" value="" readonly class="small-input"></td>
                <td class="date-column"><input type="date" name="start_date" value="" min="${new Date().toISOString().split('T')[0]}"></td>
                <td class="date-column"><input type="date" name="end_date" value=""></td>
                <td class="max-slots-column"><input type="number" name="max_slots" value="0" class="small-input calculate-availability"></td>
                <td class="sold-slots-column"><input type="number" name="sold_slots" value="0" class="small-input" readonly title="Calculated from booking records with Pending, Booked, Finalized, and Completed status"></td>
                <td class="offline-sold-slots-column"><input type="number" name="offline_sold_slots" value="0" class="small-input calculate-availability"></td>
                <td class="reserved-slots-column"><input type="number" name="reserved_slots" value="0" class="small-input" readonly title="Calculated from booking records with Reserved status"></td>
                <td class="available-slots-column">
                    <input type="number" name="available_slots" value="0" class="small-input" readonly title="Calculated as: Max Slots - Sold Slots - Offline Sold - Reserved Slots">
                </td>
                <td class="status-column">
                    <select name="status">
                        <option value="draft">Draft</option>
                        <option value="publish">Publish</option>
                        <option value="pending">Pending</option>
                        <option value="private">Private</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </td>
                <td class="ext-column">
                    <input type="checkbox" name="extension_offered" value="1" style="margin: 0; cursor: pointer;">
                </td>
                <td class="actions-column">
                    <button type="button" class="save-tour-date button"><i class="fas fa-save"></i></button>
                    <button type="button" class="delete-tour-date button"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;

        $('#tour-dates-container tbody').append(newRow);

        // Add event listener to update end_date min attribute based on start_date
        $('#tour-dates-container tbody tr:last-child input[name="start_date"]').on('change', function() {
            var startDate = new Date($(this).val());
            var minEndDate = new Date(startDate);
            minEndDate.setDate(minEndDate.getDate() + 1);
            var minEndDateString = minEndDate.toISOString().split('T')[0];
            $(this).closest('tr').find('input[name="end_date"]').attr('min', minEndDateString);
        });
    });

    // Add event listener to update end_date min attribute based on start_date for existing rows
    $('input[name="start_date"]').on('change', function() {
        var startDate = new Date($(this).val());
        var minEndDate = new Date(startDate);
        minEndDate.setDate(minEndDate.getDate() + 1);
        var minEndDateString = minEndDate.toISOString().split('T')[0];
        $(this).closest('tr').find('input[name="end_date"]').attr('min', minEndDateString);
    });

    // ===== INDIVIDUAL TOUR DATE ADMIN SCREEN FUNCTIONALITY =====
    
    // Check if we're on a tour-date edit screen
    if ($('body').hasClass('post-type-tour-date')) {
        // Sync button functionality for individual tour date
        $(document).on('click', '#sync-sold-slots', function(e) {
            e.preventDefault();
            
            var tourDateId = $(this).data('tour-date-id');
            var $button = $(this);
            var originalText = $button.html();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
            
            $.ajax({
                url: bstAdmin.ajax_url,
                method: 'POST',
                data: {
                    action: 'sync_tour_date',
                    nonce: bstAdmin.nonce,
                    tour_date_id: tourDateId
                },
                success: function(response) {
                    console.log('Individual sync response for tour date ' + tourDateId + ':', response);
                    
                    if (response.success && response.data) {
                        var data = response.data;
                        
                        // Update sold slots field (try multiple selectors for ACF field variations)
                        var $soldSlotsField = $('input[name="acf[field_sold_slots]"]')
                                            .add('input[name*="sold_slots"]')
                                            .add('#acf-field_sold_slots');
                        
                        if ($soldSlotsField.length && data.sold_slots !== undefined) {
                            $soldSlotsField.val(data.sold_slots);
                        }
                        
                        // Update reserved slots field if it exists
                        var $reservedSlotsField = $('input[name="acf[field_reserved_slots]"]')
                                                .add('input[name*="reserved_slots"]')
                                                .add('#acf-field_reserved_slots');
                        
                        if ($reservedSlotsField.length && data.reserved_slots !== undefined) {
                            $reservedSlotsField.val(data.reserved_slots);
                        }
                        
                        // Update availability field if it exists
                        var $availabilityField = $('input[name="acf[field_available_slots]"]')
                                               .add('input[name*="available_slots"]')
                                               .add('#acf-field_available_slots');
                        
                        if ($availabilityField.length && data.available_slots !== undefined) {
                            $availabilityField.val(data.available_slots);
                        }
                        
                        // Build detailed success message
                        var messages = [];
                        if (data.updates_made) {
                            var updateParts = [];
                            if (data.sold_updated) {
                                updateParts.push('sold slots updated to ' + data.sold_slots);
                            }
                            if (data.reserved_updated) {
                                updateParts.push('reserved slots updated to ' + data.reserved_slots);
                            }
                            if (data.availability_updated) {
                                updateParts.push('availability updated to ' + data.available_slots);
                            }
                            messages.push('✓ Sync complete: ' + updateParts.join(', '));
                        } else {
                            messages.push('✓ Sync complete: no changes needed');
                            messages.push('Sold slots: ' + data.sold_slots);
                            messages.push('Reserved slots: ' + data.reserved_slots);
                        }
                        
                        if (data.tour_name && data.tour_name !== 'Unknown Tour') {
                            messages.push('Tour: ' + data.tour_name);
                        }
                        
                        messages.push('Available slots: ' + data.available_slots);
                        
                        var successMessage = messages.join(' | ');
                        
                        // Show success message
                        if (typeof showMessage === 'function') {
                            showMessage(successMessage, 'updated');
                        } else {
                            alert(successMessage);
                        }
                        
                        // Log debug info
                        if (data.debug) {
                            console.log('Debug info for individual sync:', data.debug);
                        }
                        
                        // Show any warnings
                        if (data.errors && data.errors.length > 0) {
                            console.warn('Warnings during individual sync:', data.errors);
                            var warningMsg = 'Sync completed with warnings: ' + data.errors.join(', ');
                            if (typeof showMessage === 'function') {
                                showMessage(warningMsg, 'notice-warning');
                            } else {
                                alert(warningMsg);
                            }
                        }
                        
                    } else {
                        var errorMsg = 'Error syncing availability';
                        if (response.data && response.data.message) {
                            errorMsg = response.data.message;
                        }
                        
                        console.error('Individual sync failed for tour date ' + tourDateId + ':', response);
                        
                        // Log debug info for failed sync
                        if (response.data && response.data.debug) {
                            console.log('Debug info for failed individual sync:', response.data.debug);
                        }
                        
                        if (typeof showMessage === 'function') {
                            showMessage(errorMsg, 'error');
                        } else {
                            alert(errorMsg);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error for individual sync of tour date ' + tourDateId + ':', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    var errorMsg = 'Network error during sync: ' + error;
                    if (typeof showMessage === 'function') {
                        showMessage(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                },
                complete: function() {
                    // Restore button state
                    $button.prop('disabled', false);
                    $button.html(originalText);
                }
            });
        });
    }

    // Sync all tour dates button
    $(document).on('click', '#sync-all-tour-dates', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.html();
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.html('<i class="fas fa-spinner fa-spin"></i> Syncing...');

        // Get all tour date IDs from the table
        var tourDateIds = [];
        $('.tour-date-item').each(function() {
            var tourDateId = $(this).find('input[name="tour_date_id"]').val();
            if (tourDateId && tourDateId !== '') {
                tourDateIds.push(tourDateId);
            }
        });

        if (tourDateIds.length === 0) {
            showMessage('No saved tour dates found to sync.', 'warning');
            $button.prop('disabled', false);
            $button.html(originalText);
            return;
        }

        showMessage('Starting sync for ' + tourDateIds.length + ' tour dates...', 'info');

        // Sync each tour date sequentially
        var syncCount = 0;
        var totalCount = tourDateIds.length;
        var errors = [];

        function syncNext() {
            if (syncCount >= totalCount) {
                // All done
                var successCount = totalCount - errors.length;
                var message = 'Sync completed: ' + successCount + ' successful';
                if (errors.length > 0) {
                    message += ', ' + errors.length + ' failed';
                }
                showMessage(message, errors.length > 0 ? 'warning' : 'success');
                
                $button.prop('disabled', false);
                $button.html(originalText);
                return;
            }

            var tourDateId = tourDateIds[syncCount];
            var $row = $('.tour-date-item').find('input[name="tour_date_id"][value="' + tourDateId + '"]').closest('.tour-date-item');
            
            $.ajax({
                url: bstAdmin.ajax_url,
                method: 'POST',
                data: {
                    action: 'sync_tour_date',
                    nonce: bstAdmin.nonce,
                    tour_date_id: tourDateId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;
                        
                        // Update the UI fields
                        $row.find('input[name="sold_slots"]').val(data.sold_slots);
                        if (data.reserved_slots !== undefined) {
                            $row.find('input[name="reserved_slots"]').val(data.reserved_slots);
                        }
                        if (data.available_slots !== undefined) {
                            $row.find('input[name="available_slots"]').val(data.available_slots);
                        }
                    } else {
                        errors.push('Tour date ID ' + tourDateId + ': ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function() {
                    errors.push('Tour date ID ' + tourDateId + ': Network error');
                },
                complete: function() {
                    syncCount++;
                    // Update progress
                    $button.html('<i class="fas fa-spinner fa-spin"></i> Syncing ' + syncCount + '/' + totalCount + '...');
                    // Continue with next sync
                    setTimeout(syncNext, 100); // Small delay to prevent overwhelming the server
                }
            });
        }

        // Start the sync process
        syncNext();
    });

    // Regenerate all titles button
    $(document).on('click', '#regenerate-all-titles', function(e) {
        e.preventDefault();

        var $button = $(this);
        var originalText = $button.html();
        
        if (!confirm('This will regenerate titles for all tour dates based on their tour name and date range. Continue?')) {
            return;
        }
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $button.html('<i class="fas fa-spinner fa-spin"></i> Regenerating...');

        $.ajax({
            url: bstAdmin.ajax_url,
            method: 'POST',
            data: {
                action: 'bst_regenerate_tour_date_titles',
                nonce: bstAdmin.regenerate_nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');
                    // Reload the page to show updated titles
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showMessage('Failed to regenerate titles: ' + (response.data ? response.data : 'Unknown error'), 'error');
                }
            },
            error: function() {
                showMessage('Network error while regenerating titles.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.html(originalText);
            }
        });
    });
    
    // Mobile card event handlers
    function updateCardAvailability($card) {
        console.log('updateCardAvailability called for card:', $card);
        
        // Calculate availability from current form values
        var maxSlots = parseInt($card.find('input[name="max_slots"]').val()) || 0;
        var soldSlots = parseInt($card.find('input[name="sold_slots"]').val()) || 0;
        var offlineSoldSlots = parseInt($card.find('input[name="offline_sold_slots"]').val()) || 0;
        var reservedSlots = parseInt($card.find('input[name="reserved_slots"]').val()) || 0;
        
        var calculatedAvailability = maxSlots - soldSlots - offlineSoldSlots - reservedSlots;
        calculatedAvailability = Math.max(0, calculatedAvailability); // Ensure never negative
        
        console.log('Card calculated availability:', {
            maxSlots: maxSlots,
            soldSlots: soldSlots, 
            offlineSoldSlots: offlineSoldSlots,
            reservedSlots: reservedSlots,
            calculatedAvailability: calculatedAvailability
        });
        
        // Update the availability field value
        $card.find('input[name="available_slots"]').val(calculatedAvailability);
    }

    // === ACF FIELD AVAILABILITY CALCULATION ===
    // Function to calculate availability for ACF fields on individual tour-date edit pages
    function updateACFAvailability() {
        // Find ACF fields by data-name attribute (most reliable method)
        var $maxSlotsField = $('[data-name="max_slots"] input[type="number"]').first();
        var $soldSlotsField = $('[data-name="sold_slots"] input[type="number"]').first();
        var $offlineSoldSlotsField = $('[data-name="offline_sold_slots"] input[type="number"]').first();
        var $reservedSlotsField = $('[data-name="reserved_slots"] input[type="number"]').first();
        var $availableSlotsField = $('[data-name="available_slots"] input[type="number"]').first();
        
        // Check if we found the ACF fields (we need at least max_slots and available_slots)
        if ($maxSlotsField.length === 0 || $availableSlotsField.length === 0) {
            return; // Not on tour-date edit page or fields not loaded yet
        }
        
        // Get current values
        var maxSlots = parseInt($maxSlotsField.val()) || 0;
        var soldSlots = parseInt($soldSlotsField.val()) || 0;
        var offlineSoldSlots = parseInt($offlineSoldSlotsField.val()) || 0;
        var reservedSlots = parseInt($reservedSlotsField.val()) || 0;
        
        // Calculate availability
        var calculatedAvailability = maxSlots - soldSlots - offlineSoldSlots - reservedSlots;
        calculatedAvailability = Math.max(0, calculatedAvailability); // Ensure never negative
        
        // Update the availability field value (only when user changes max_slots or offline_sold_slots)
        $availableSlotsField.val(calculatedAvailability);
        $availableSlotsField.trigger('change');
    }

    // Run availability calculation only when user changes max_slots or offline_sold_slots (not on load)
    $(document).on('input change keyup', '[data-name="max_slots"] input, [data-name="offline_sold_slots"] input', function() {
        updateACFAvailability();
    });
});