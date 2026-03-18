<?php
/**
 * Diagnostic: Find currency field IDs on Forms 9 and 10
 * 
 * Add this to your wp-config.php temporarily:
 * define('BST_DIAGNOSTIC_FIELDS', true);
 * 
 * Then visit any tour page or the forms in admin to see the output
 */

if (!defined('BST_DIAGNOSTIC_FIELDS') || !BST_DIAGNOSTIC_FIELDS) {
    return;
}

add_filter('gform_pre_render', 'bst_diagnostic_log_field_ids', 5, 2);
function bst_diagnostic_log_field_ids($form, $ajax) {
    $form_id = $form['id'];
    
    // Only check Forms 9 and 10
    if ($form_id != 9 && $form_id != 10) {
        return $form;
    }
    
    error_log("=== BST DIAGNOSTIC: Form $form_id Field Report ===");
    
    foreach ($form['fields'] as $field) {
        $field_info = array(
            'id' => $field->id,
            'type' => $field->type,
            'label' => $field->label,
            'inputName' => isset($field->inputName) ? $field->inputName : 'N/A',
            'visibility' => $field->visibility,
            'cssClass' => $field->cssClass
        );
        
        // Highlight fields that might be currency-related
        $is_currency_candidate = false;
        if (!empty($field->inputName) && stripos($field->inputName, 'currency') !== false) {
            $is_currency_candidate = true;
            $field_info['CURRENCY_CANDIDATE'] = '*** YES ***';
        }
        
        // Also check for hidden fields
        if ($field->visibility === 'hidden' || $field->type === 'hidden') {
            $field_info['IS_HIDDEN'] = 'yes';
        }
        
        error_log("Field " . $field->id . ": " . json_encode($field_info));
    }
    
    error_log("=== END Form $form_id Field Report ===");
    
    return $form;
}

// Also log when viewing entries
add_filter('gform_get_input_value', 'bst_diagnostic_log_entry_fields', 10, 4);
function bst_diagnostic_log_entry_fields($value, $entry, $field, $input_id) {
    static $logged_entries = array();
    
    $form_id = $entry['form_id'];
    $entry_id = $entry['id'];
    
    // Only check Forms 9 and 10, and only log each entry once
    if (($form_id != 9 && $form_id != 10) || isset($logged_entries[$entry_id])) {
        return $value;
    }
    
    $logged_entries[$entry_id] = true;
    
    error_log("=== BST DIAGNOSTIC: Form $form_id Entry $entry_id Data ===");
    
    // Look for fields 223 and 266 specifically
    if ($form_id == 9) {
        if (isset($entry['223'])) {
            error_log("Entry $entry_id - Field 223 value: " . var_export($entry['223'], true));
        }
        // Look for any field with 'currency' in the value
        foreach ($entry as $key => $val) {
            if (is_numeric($key) && in_array(strtoupper($val), array('USD', 'CAD', 'AUD', 'EUR', 'GBP'))) {
                error_log("Entry $entry_id - CURRENCY FOUND in Field $key: " . var_export($val, true));
            }
        }
    } elseif ($form_id == 10) {
        if (isset($entry['266'])) {
            error_log("Entry $entry_id - Field 266 value: " . var_export($entry['266'], true));
        }
        // Look for any field with 'currency' in the value
        foreach ($entry as $key => $val) {
            if (is_numeric($key) && in_array(strtoupper($val), array('USD', 'CAD', 'AUD', 'EUR', 'GBP'))) {
                error_log("Entry $entry_id - CURRENCY FOUND in Field $key: " . var_export($val, true));
            }
        }
    }
    
    error_log("=== END Entry $entry_id Data ===");
    
    return $value;
}
