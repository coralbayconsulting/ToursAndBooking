-- BST Plugin: Add Email Batch Tracking
-- Run this SQL script to add batch tracking to the email system
-- Replace 'wp_' with your actual WordPress table prefix if different

-- Add batch_id column to email_log table (if not exists)
ALTER TABLE wp_bst_email_log 
ADD COLUMN IF NOT EXISTS batch_id BIGINT UNSIGNED NULL AFTER message_id,
ADD INDEX IF NOT EXISTS idx_batch_id (batch_id);

-- Add error_message column to email_log table (if not exists)
ALTER TABLE wp_bst_email_log 
ADD COLUMN IF NOT EXISTS error_message TEXT NULL AFTER batch_id;

-- Create email_batch table (if not exists)
CREATE TABLE IF NOT EXISTS wp_bst_email_batch (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_timestamp DATETIME NOT NULL,
  sent_by_user_id BIGINT UNSIGNED NOT NULL,
  email_type VARCHAR(50) NOT NULL DEFAULT 'Ad Hoc',
  template_id BIGINT UNSIGNED NULL,
  email_subject VARCHAR(255) NOT NULL,
  cc_emails VARCHAR(500) NULL,
  tour_date_id BIGINT UNSIGNED NULL,
  total_emails INT NOT NULL DEFAULT 0,
  successful_emails INT NOT NULL DEFAULT 0,
  failed_emails INT NOT NULL DEFAULT 0,
  is_test TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  PRIMARY KEY (id),
  INDEX idx_batch_timestamp (batch_timestamp),
  INDEX idx_sent_by_user_id (sent_by_user_id),
  INDEX idx_email_type (email_type),
  INDEX idx_tour_date_id (tour_date_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
