/**
 * BST Admin Date Format Consistency
 * Replaces native date inputs with custom text inputs that enforce Y-m-d format
 */
jQuery(document).ready(function($) {
    
    function convertDateInputsToText() {
        $('input[type="date"]').each(function() {
            var $dateInput = $(this);
            
            // Skip if already converted
            if ($dateInput.hasClass('bst-date-text') || $dateInput.attr('type') !== 'date') {
                return;
            }
            
            // Skip numeric fields that might have been mis-targeted
            var inputName = $dateInput.attr('name');
            if (inputName && (inputName.includes('slots') || inputName.includes('number'))) {
                return;
            }
            
            var currentValue = $dateInput.val();
            var inputId = $dateInput.attr('id');
            var inputClass = $dateInput.attr('class') || '';
            var inputStyle = $dateInput.attr('style') || '';
            
            // Create new text input with Y-m-d format enforcement
            var $textInput = $('<input type="text" pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD" />');
            $textInput.attr('name', inputName);
            if (inputId) {
                $textInput.attr('id', inputId);
            }
            $textInput.attr('class', (inputClass + ' bst-date-text').trim());
            $textInput.attr('style', inputStyle); // Preserve original styling
            $textInput.val(currentValue);
            
            // Add validation and formatting
            $textInput.on('input', function() {
                var value = $(this).val();
                // Remove any non-numeric characters except hyphens
                value = value.replace(/[^\d-]/g, '');
                
                // Auto-format as user types
                if (value.length >= 4 && value.charAt(4) !== '-') {
                    value = value.substring(0, 4) + '-' + value.substring(4);
                }
                if (value.length >= 7 && value.charAt(7) !== '-') {
                    value = value.substring(0, 7) + '-' + value.substring(7);
                }
                
                // Limit to 10 characters (YYYY-MM-DD)
                if (value.length > 10) {
                    value = value.substring(0, 10);
                }
                
                $(this).val(value);
                
                // Validate the date
                if (value.length === 10) {
                    var dateRegex = /^\d{4}-\d{2}-\d{2}$/;
                    if (dateRegex.test(value)) {
                        var date = new Date(value);
                        if (date.toISOString().substr(0, 10) === value) {
                            $(this).addClass('valid').removeClass('invalid');
                            $(this).css('border-color', '');
                            $(this).next('.date-error').remove();
                        } else {
                            $(this).addClass('invalid').removeClass('valid');
                            $(this).css('border-color', '');
                            if (!$(this).next('.date-error').length) {
                                $(this).after('<span class="date-error">Invalid date</span>');
                            }
                        }
                    } else {
                        $(this).addClass('invalid').removeClass('valid');
                        $(this).css('border-color', '');
                    }
                } else {
                    $(this).removeClass('valid invalid');
                    $(this).css('border-color', '');
                    $(this).next('.date-error').remove();
                }
            });
            
            // Add focus handler to show format guidance
            $textInput.on('focus', function() {
                if (!$(this).next('.date-format-hint').length) {
                    $(this).after('<span class="date-format-hint">YYYY-MM-DD</span>');
                }
            });
            
            // Remove hint on blur if field is valid
            $textInput.on('blur', function() {
                var value = $(this).val();
                if (value.length === 10 && $(this).hasClass('valid')) {
                    $(this).next('.date-format-hint').remove();
                }
            });
            
            // Replace the original input
            $dateInput.replaceWith($textInput);
        });
    }
    
    // Convert existing date inputs
    convertDateInputsToText();
    
    // Handle dynamically added tour date rows
    $(document).on('click', '#add-tour-date', function() {
        setTimeout(convertDateInputsToText, 300);
    });
    
    // ACF compatibility
    if (typeof acf !== 'undefined') {
        acf.addAction('ready', function() {
            setTimeout(convertDateInputsToText, 100);
        });
        
        acf.addAction('append', function() {
            setTimeout(convertDateInputsToText, 100);
        });
    }
    
    console.log('BST Date Format Consistency loaded - Date inputs converted to Y-m-d text format');
});
