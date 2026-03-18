-- SQL script to add new fields to wp_bst_tour_booking table
-- Run this script to add the fields marked as "new" in create-tables.php


-- Add booking invoice date field after booking_invoice_number
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_invoice_date DATETIME AFTER booking_invoice_number;

-- Add booking EU percent field after booking_invoice_date
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_eu_percent DECIMAL(5, 2) AFTER booking_invoice_date;

-- Add booking VAT rate field after booking_eu_percent
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_vat_rate DECIMAL(5, 2) AFTER booking_eu_percent;

-- Add booking tour package amount field after booking_vat_rate
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_tour_package_amount DECIMAL(10, 2) AFTER booking_vat_rate;

-- Add booking vehicle 1 use amount field after booking_tour_package_amount
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_vehicle_1_use_amount DECIMAL(10, 2) AFTER booking_tour_package_amount;

-- Add booking vehicle 2 use amount field after booking_vehicle_1_use_amount
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN booking_vehicle_2_use_amount DECIMAL(10, 2) AFTER booking_vehicle_1_use_amount;

-- Add payment discount amount field after coupon_amount (cumulative total of all payment discounts)
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN payment_discount_amount DECIMAL(10, 2) AFTER coupon_amount;

-- Add deposit payment discount field after deposit_payment_date
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN deposit_payment_discount DECIMAL(10, 2) AFTER deposit_payment_date;

-- Add balance payment discount field after balance_payment_date
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN balance_payment_discount DECIMAL(10, 2) AFTER balance_payment_date;

-- Add additional payment discount field after additional_payment_date
ALTER TABLE wp_bst_tour_booking 
ADD COLUMN additional_payment_discount DECIMAL(10, 2) AFTER additional_payment_date;
