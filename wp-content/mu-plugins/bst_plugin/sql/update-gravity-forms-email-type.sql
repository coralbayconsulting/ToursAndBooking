-- ===============================================
-- Update Gravity Forms Email Type
-- ===============================================
-- Changes email_type from 'notification' to 'Gravity' 
-- for emails sent by Gravity Forms to differentiate
-- them from system-sent emails.
--
-- Date: 2026-02-11
-- ===============================================

-- Show count of records that will be updated
SELECT 
    COUNT(*) as records_to_update,
    email_type,
    sent_by
FROM wp_bst_email_log
WHERE sent_by = 'Gravity Forms' 
  AND email_type = 'notification'
GROUP BY email_type, sent_by;

-- Update email_type from 'notification' to 'Gravity'
UPDATE wp_bst_email_log
SET email_type = 'Gravity'
WHERE sent_by = 'Gravity Forms' 
  AND email_type = 'notification';

-- Verify the update
SELECT 
    COUNT(*) as updated_records,
    email_type,
    sent_by
FROM wp_bst_email_log
WHERE sent_by = 'Gravity Forms' 
  AND email_type = 'Gravity'
GROUP BY email_type, sent_by;

-- ===============================================
-- ROLLBACK (if needed)
-- ===============================================
-- To revert this change, run:
-- UPDATE wp_bst_email_log
-- SET email_type = 'notification'
-- WHERE sent_by = 'Gravity Forms' 
--   AND email_type = 'Gravity';
