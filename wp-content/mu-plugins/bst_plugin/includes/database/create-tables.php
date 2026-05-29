<?php

/**
 * Drop one-time migration snapshot columns from bst_tour_booking once (ids + live CPT data only).
 *
 * Runs on plugins_loaded; safe to omit if manual SQL already dropped them.
 *
 * @return void
 */
function bst_drop_deprecated_booking_snapshot_columns_maybe() {
	global $wpdb;

	if ( (int) get_option( 'bst_booking_denormalized_snapshots_removed', 0 ) === 1 ) {
		return;
	}

	$table = $wpdb->prefix . 'bst_tour_booking';
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( ! $found ) {
		return;
	}

	$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
	if ( ! is_array( $cols ) ) {
		return;
	}

	$want_drop = array(
		'tour_text',
		'tour_date_text',
		'tour_package_text',
		'vehicle1',
		'vehicle2',
		'tour_extension_text',
		'tour_extension_date_text',
	);
	$to_drop = array_values( array_intersect( $want_drop, $cols ) );
	if ( empty( $to_drop ) ) {
		update_option( 'bst_booking_denormalized_snapshots_removed', 1, false );
		return;
	}

	foreach ( $to_drop as $col ) {
		$ok = $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col}`" );
		if ( false === $ok ) {
			error_log( 'BST Plugin: bst_drop_deprecated_booking_snapshot_columns_maybe failed dropping `' . $col . '`: ' . $wpdb->last_error );
			return;
		}
	}

	update_option( 'bst_booking_denormalized_snapshots_removed', 1, false );
}

/**
 * Add vehicle1_id / vehicle2_id if missing (after tour_package_id when present).
 *
 * @return bool True if table exists and both columns are present afterward.
 */
function bst_ensure_tour_booking_vehicle_id_columns() {
	global $wpdb;

	if ( (int) get_option( 'bst_booking_vehicle_id_columns_ok', 0 ) === 1 ) {
		return true;
	}

	$table = $wpdb->prefix . 'bst_tour_booking';
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( ! $found ) {
		return false;
	}

	$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
	if ( ! is_array( $cols ) ) {
		return false;
	}

	$has1 = in_array( 'vehicle1_id', $cols, true );
	$has2 = in_array( 'vehicle2_id', $cols, true );
	if ( $has1 && $has2 ) {
		update_option( 'bst_booking_vehicle_id_columns_ok', 1, false );
		return true;
	}

	$anchor = null;
	if ( in_array( 'tour_package_id', $cols, true ) ) {
		$anchor = 'tour_package_id';
	}

	$sql = null;
	if ( ! $has1 && ! $has2 ) {
		if ( $anchor ) {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle1_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `{$anchor}`, ADD COLUMN `vehicle2_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `vehicle1_id`";
		} else {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle1_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL, ADD COLUMN `vehicle2_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL";
		}
	} elseif ( ! $has1 ) {
		if ( $anchor ) {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle1_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `{$anchor}`";
		} else {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle1_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL";
		}
	} elseif ( ! $has2 ) {
		if ( $has1 ) {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle2_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `vehicle1_id`";
		} elseif ( $anchor ) {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle2_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `{$anchor}`";
		} else {
			$sql = "ALTER TABLE `{$table}` ADD COLUMN `vehicle2_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL";
		}
	}

	if ( ! $sql ) {
		update_option( 'bst_booking_vehicle_id_columns_ok', 1, false );
		return true;
	}

	$result = $wpdb->query( $sql );
	if ( false === $result ) {
		error_log( 'BST Plugin: bst_ensure_tour_booking_vehicle_id_columns failed: ' . $wpdb->last_error );
		return false;
	}

	update_option( 'bst_booking_vehicle_id_columns_ok', 1, false );
	return true;
}

function create_tour_booking_tables() {
    global $wpdb;

    // Match WordPress DB charset/collation (dbDelta expects this helper).
    $charset_collate = $wpdb->get_charset_collate();

    // Create customers table first (since booking table references it)
    $customers_table = $wpdb->prefix . 'bst_customers';
    $sql = "CREATE TABLE $customers_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        partner_first VARCHAR(100) DEFAULT NULL,
        partner_last VARCHAR(100) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        credit VARCHAR(20) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        data_source VARCHAR(40) DEFAULT NULL,
        created_by VARCHAR(50),
        created_date DATETIME,
        updated_by VARCHAR(50),
        updated_date DATETIME,
        PRIMARY KEY  (id),
        KEY email_idx (email)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Tour booking table: vehicle CPT ids live in vehicle1_id / vehicle2_id only (snapshot text columns removed).
    $tour_booking_table = $wpdb->prefix . "bst_tour_booking";

    $sql = "
        CREATE TABLE $tour_booking_table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_entry_id BIGINT(20) UNSIGNED, 
            finalization_entry_id BIGINT(20) UNSIGNED,
            additional_payment_entry_id BIGINT(20) UNSIGNED,
            booking_invoice_number VARCHAR(20) UNIQUE,
            booking_invoice_date DATETIME,
            booking_eu_percent DECIMAL(5, 2),
            booking_vat_rate DECIMAL(5, 2),
            booking_tour_package_amount DECIMAL(10, 2),
            booking_vehicle_1_use_amount DECIMAL(10,2),
            booking_vehicle_2_use_amount DECIMAL(10,2),
            customer_id BIGINT(20) UNSIGNED,
            guest1_first_name VARCHAR(100),
            guest1_last_name VARCHAR(100),
            guest1_nickname VARCHAR(100), 
            guest1_phone VARCHAR(20),
            guest1_email VARCHAR(100),
            guest1_address_line1 VARCHAR(100), 
            guest1_address_line2 VARCHAR(100),
            guest1_city VARCHAR(50), 
            guest1_state_province VARCHAR(50), 
            guest1_postal_code VARCHAR(20), 
            guest1_country VARCHAR(50), 
            guest1_shirt_size VARCHAR(20),
            guest1_driving_status VARCHAR(20), 
            guest1_travel_details VARCHAR(512), 
            guest1_dietary_restrictions VARCHAR(200), 
            guest1_medical_insurance VARCHAR(100),
            guest1_emergency_contact_name VARCHAR(100),
            guest1_emergency_contact_phone VARCHAR(20),
            guest1_emergency_contact_email VARCHAR(100),
            guest2_first_name VARCHAR(100),
            guest2_last_name VARCHAR(100),
            guest2_nickname VARCHAR(100), 
            guest2_phone VARCHAR(20),
            guest2_email VARCHAR(100),
            guest2_address_line1 VARCHAR(100), 
            guest2_address_line2 VARCHAR(100), 
            guest2_city VARCHAR(50), 
            guest2_state_province VARCHAR(50), 
            guest2_postal_code VARCHAR(20), 
            guest2_country VARCHAR(50), 
            guest2_shirt_size VARCHAR(20),
            guest2_driving_status VARCHAR(20), 
            guest2_travel_details VARCHAR(512), 
            guest2_dietary_restrictions VARCHAR(200), 
            guest2_medical_insurance VARCHAR(100), 
            guest2_emergency_contact_name VARCHAR(100),
            guest2_emergency_contact_phone VARCHAR(20),
            guest2_emergency_contact_email VARCHAR(100),
            tour_id BIGINT(20) UNSIGNED,
            tour_date_id BIGINT(20) UNSIGNED,
            tour_package_id BIGINT(20) UNSIGNED,
            vehicle1_id BIGINT(20) UNSIGNED,
            vehicle2_id BIGINT(20) UNSIGNED,
            tour_extension_added BOOLEAN DEFAULT FALSE,
            participant_sex VARCHAR(10),
            sharing_preference VARCHAR(20),
            bed_preference VARCHAR(20),
            hotel_nights_before INT(11),
            hotel_nights_after INT(11),
            package_people INT(11),
            package_rooms DECIMAL(4,1),
            package_vehicles INT(11),
            vehicle_choices INT(11),
            tour_price DECIMAL(10, 2),
            tour_currency VARCHAR(10),
            net_tour_price DECIMAL(10, 2),
            coupon_code VARCHAR(50),
            coupon_amount DECIMAL(10, 2),
            payment_discount_amount DECIMAL(10, 2),
            total_paid DECIMAL(10, 2),
            additional_charge DECIMAL(10, 2),
            balance_due DECIMAL(10, 2),
            deposit_payment_method VARCHAR(20),
            deposit_payment_amount DECIMAL(10, 2),
            deposit_payment_date DATETIME,
            deposit_payment_discount DECIMAL(10, 2),
            deposit_payment_status VARCHAR(20),
            balance_payment_method VARCHAR(20),
            balance_payment_amount DECIMAL(10, 2),
            balance_payment_date DATETIME,
            balance_payment_discount DECIMAL(10, 2),
            balance_payment_status VARCHAR(20),
            additional_payment_method VARCHAR(20),
            additional_payment_amount DECIMAL(10, 2),
            additional_payment_date DATETIME,
            additional_payment_discount DECIMAL(10, 2),
            additional_payment_status VARCHAR(20),
            refund_payment_method VARCHAR(20),
            refund_payment_amount DECIMAL(10, 2),
            refund_payment_date DATETIME,
            refund_payment_status VARCHAR(20),
            how_heard VARCHAR(100),
            how_heard_other VARCHAR(100),
            motor_club VARCHAR(100),
            source VARCHAR(100),
            referrer VARCHAR(100),
            booking_status VARCHAR(20),
            booking_method VARCHAR(20) DEFAULT 'Web',
            booking_commission_percent DECIMAL(10, 2) DEFAULT 0.02,
            booking_commission_reason VARCHAR(100),
            deposit_commission_invoice VARCHAR(20), 
            balance_commission_invoice VARCHAR(20),
            additional_payment_commission_invoice VARCHAR(20),
            refund_commission_invoice VARCHAR(20),
            notes TEXT DEFAULT NULL,
            data_source VARCHAR(40),
            created_by VARCHAR(50),
            created_date DATETIME,
            updated_by VARCHAR(50),
            updated_date DATETIME,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id)
        ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    bst_ensure_tour_booking_vehicle_id_columns();

    // Add foreign key constraint separately (dbDelta doesn't handle constraints well)
    // Check if the constraint already exists before trying to create it
    $constraint_check = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = DATABASE() 
        AND TABLE_NAME = %s 
        AND CONSTRAINT_NAME = 'fk_customer_id'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ", $tour_booking_table));
    
    if ($constraint_check == 0) {
        // Constraint doesn't exist, create it
        $result = $wpdb->query("
            ALTER TABLE $tour_booking_table 
            ADD CONSTRAINT fk_customer_id 
            FOREIGN KEY (customer_id) REFERENCES {$wpdb->prefix}bst_customers(id) 
            ON DELETE SET NULL ON UPDATE CASCADE
        ");
        
        if ($result === false) {
            error_log('BST Plugin: Failed to create foreign key constraint fk_customer_id: ' . $wpdb->last_error);
        } else {
            error_log('BST Plugin: Successfully created foreign key constraint fk_customer_id');
        }
    }
    // Constraint already exists - no need to log this every time

    // Create email log table
    $email_log_table = $wpdb->prefix . 'bst_email_log';
    $sql = "CREATE TABLE $email_log_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_id BIGINT(20) UNSIGNED NOT NULL,
        template_id BIGINT(20) UNSIGNED DEFAULT NULL,
        email_type VARCHAR(50) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        content LONGTEXT NOT NULL,
        sent_date DATETIME NOT NULL,
        sent_by VARCHAR(100) NOT NULL,
        sent_successfully TINYINT(1) DEFAULT 0,
        gmail_message_id VARCHAR(255) DEFAULT NULL,
        gmail_thread_id VARCHAR(255) DEFAULT NULL,
        direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
        message_id VARCHAR(255) DEFAULT NULL,
        batch_id BIGINT(20) UNSIGNED DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY booking_id_idx (booking_id),
        KEY email_type_idx (email_type),
        KEY sent_date_idx (sent_date),
        KEY batch_id_idx (batch_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    if ($wpdb->last_error) {
        error_log('BST Plugin: Error creating email log table: ' . $wpdb->last_error);
    }
    // Table created or already exists - only log errors

    // Create email batch table
    $email_batch_table = $wpdb->prefix . 'bst_email_batch';
    $sql = "CREATE TABLE $email_batch_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        batch_timestamp DATETIME NOT NULL,
        sent_by_user_id BIGINT(20) UNSIGNED NOT NULL,
        email_type VARCHAR(50) NOT NULL,
        template_id BIGINT(20) UNSIGNED DEFAULT NULL,
        email_subject VARCHAR(255) NOT NULL,
        cc_emails TEXT DEFAULT NULL,
        tour_date_id BIGINT(20) UNSIGNED DEFAULT NULL,
        total_emails INT(11) NOT NULL DEFAULT 0,
        successful_emails INT(11) NOT NULL DEFAULT 0,
        failed_emails INT(11) NOT NULL DEFAULT 0,
        is_test TINYINT(1) DEFAULT 0,
        notes TEXT DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY batch_timestamp_idx (batch_timestamp),
        KEY sent_by_user_id_idx (sent_by_user_id),
        KEY email_type_idx (email_type),
        KEY tour_date_id_idx (tour_date_id)
    ) $charset_collate;";
    dbDelta($sql);
    
    if ($wpdb->last_error) {
        error_log('BST Plugin: Error creating email batch table: ' . $wpdb->last_error);
    }
    // Table created or already exists - only log errors

    if ( function_exists( 'bst_create_share_log_table' ) ) {
        bst_create_share_log_table();
    }

    // Set a transient to indicate tables have been created successfully
    set_transient('bst_tour_booking_tables_created', time(), 24 * HOUR_IN_SECONDS);
}

// Check if tables need to be created (with better race condition protection)
// Only run if WordPress has a valid database connection
if (isset($wpdb) && $wpdb->dbh !== null) {
    $tables_created = get_transient('bst_tour_booking_tables_created');
    $lock_key = 'bst_table_creation_lock';

    if (!$tables_created) {
        // Use WordPress transient as a simple lock mechanism with better atomic check
        if (false === get_transient($lock_key)) {
            // Set lock before starting - this is more atomic
            if (set_transient($lock_key, time(), 60)) { // 60 second lock, store timestamp
                try {
                    create_tour_booking_tables();
                } finally {
                    // Always clean up the lock, even if creation fails
                    delete_transient($lock_key);
                }
            }
        }
    }
}

add_action(
	'plugins_loaded',
	static function () {
		if ( function_exists( 'bst_ensure_tour_booking_vehicle_id_columns' ) ) {
			bst_ensure_tour_booking_vehicle_id_columns();
		}
		if ( function_exists( 'bst_drop_deprecated_booking_snapshot_columns_maybe' ) ) {
			bst_drop_deprecated_booking_snapshot_columns_maybe();
		}
	},
	25
);

