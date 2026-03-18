# Gravity Forms Currency Bug Fix Summary
## Date: February 9, 2026

## THE PROBLEM
Production site showed errors:
- "Trying to access array offset on null" 
- "Undefined array key 'Freda Pratt'" in Gravity Forms currency.php

Customer name "Freda Pratt" was appearing where a currency code should be.

## ROOT CAUSE
When manually rebuilding forms on production, field IDs remained the same but their usage led to conflicts:

**Form 9 (Booking):**
- Field 223 = Tour Currency (inputName="tour_currency") ✓

**Form 10 (Finalization):**
- Field 223 = Emergency Contact Name (NOT currency!) 
- Field 266 = Tour Currency (inputName="tour_currency") ✓

### The Bug Chain:
1. Code tried to read currency from entries using hardcoded field 223
2. For Form 9 entries: Field 223 was currency ✓
3. **For Form 10 entries: Field 223 was "Freda Pratt" (emergency contact) ✗**
4. Code treated "Freda Pratt" as currency code
5. Gravity Forms tried to lookup "Freda Pratt" in currency config → **CRASH**

## FIXES APPLIED

### 1. Removed Redundant Field 223 Write (gravity-forms.php:842-844)
**Before:**
```php
} elseif ($field->id == 223 && $tour_currency) {
    // Always populate field 223 (hidden currency field) with detected currency
    $field->defaultValue = $tour_currency;
}
```
**After:** REMOVED (redundant - line 840 already handles this via inputName)

### 2. Created Helper Function (gravity-forms.php:79-102)
```php
function bst_get_currency_from_entry($entry) {
    // Safely gets currency based on form ID:
    // - Form 9: reads field 223
    // - Form 10: reads field 266
    // - Validates and defaults to EUR
}
```

### 3. Fixed 3 Unsafe Currency Reads (booking-display.php)
**Line 393:** Changed `rgar($entry, '223')` → `bst_get_currency_from_entry($entry)`
**Line 1259:** Changed `rgar($booking_entry, '223')` → `bst_get_currency_from_entry($booking_entry)`  
**Line 5072:** Already had form check ✓ (updated to use helper anyway for consistency)

### 4. Enhanced Logging (gravity-forms.php:260-270)
Added detailed logging to show both currency field AND emergency contact field for Form 10 entries to help diagnose future issues.

## FIELD ID MISMATCHES FOUND (DEV vs PROD)
Total: 29 field mismatches across Forms 9 & 10
Most critical: Currency handling (now fixed)

**Note:** Other mismatches exist but don't cause crashes. Monitor for issues with:
- Travel details (fields 240-254, 264-265)
- Guest information (fields 135, 165, 180, 223-225)
- Deposits/discounts (field 282)

## TESTING RECOMMENDATIONS
1. Browse Form 9 and Form 10 entries in wp-admin
2. Verify no "Freda Pratt" or invalid currency errors
3. Check debug.log for any remaining "BST Currency Validation" warnings
4. Test form submissions for both forms with different currencies

## PREVENTION
Always use `inputName` instead of hardcoded field IDs when possible, or check `form_id` before using field IDs that may differ between forms.
