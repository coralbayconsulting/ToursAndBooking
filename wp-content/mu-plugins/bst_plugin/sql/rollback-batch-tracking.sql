-- BST Plugin: Rollback Email Batch Tracking
-- Use this script to revert the batch tracking changes
-- Replace 'wp_' with your actual WordPress table prefix if different

-- Remove batch_id column from email_log table
ALTER TABLE wp_bst_email_log 
DROP COLUMN IF EXISTS batch_id;

-- Remove error_message column from email_log table
ALTER TABLE wp_bst_email_log 
DROP COLUMN IF EXISTS error_message;

-- Drop email_batch table
DROP TABLE IF EXISTS wp_bst_email_batch;
