<?php
/**
 * BST Plugin Data Import Handlers
 *
 * Bulk "Import Updates" tab import for bst_tour_booking (header-based rows).
 */

if (!defined('ABSPATH')) {
    exit;
}

// #region Import Updates Handler + logging

/**
 * Custom logging functions for import operations
 */

/**
 * Log import messages with multiple destinations
 *
 * @param string $message The message to log
 * @param string $level Log level (info, error, warning)
 */
function bst_log_import_message($message, $level = 'info') {
	$timestamp         = date('Y-m-d H:i:s');
	$formatted_message = "[{$timestamp}] BST Import {$level}: {$message}";

	error_log($formatted_message);

	$import_log_file = WP_CONTENT_DIR . '/bst-import.log';
	file_put_contents($import_log_file, $formatted_message . PHP_EOL, FILE_APPEND | LOCK_EX);

	if ($level === 'error' && function_exists('syslog')) {
		syslog(LOG_ERR, "BST Import Error: {$message}");
	}
}

/**
 * Log import errors specifically
 */
function bst_log_import_error($message) {
	bst_log_import_message($message, 'error');
}

/**
 * Log import warnings
 */
function bst_log_import_warning($message) {
	bst_log_import_message($message, 'warning');
}

/**
 * Admin handler to import updates from tab-delimited file.
 * Allows updating any fields in the bst_tour_booking table based on column headers.
 */
add_action('admin_post_bst_import_updates', 'bst_import_updates_handler');

function bst_import_updates_handler() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized', 'Error', array('response' => 403));
	}

	if (!wp_verify_nonce($_POST['import_nonce'], 'bst_import_updates')) {
		wp_die('Security check failed', 'Error', array('response' => 403));
	}

	if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
		wp_redirect(add_query_arg(array('page' => 'bst-tour-bookings', 'import_error' => 'file_upload'), admin_url('admin.php')));
		exit;
	}

	$file_path = $_FILES['import_file']['tmp_name'];

	if (!file_exists($file_path)) {
		wp_redirect(add_query_arg(array('page' => 'bst-tour-bookings', 'import_error' => 'file_not_found'), admin_url('admin.php')));
		exit;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'bst_tour_booking';

	$file_content = file_get_contents($file_path);
	$lines        = explode("\n", $file_content);

	if (empty($lines)) {
		wp_redirect(add_query_arg(array('page' => 'bst-tour-bookings', 'import_error' => 'empty_file'), admin_url('admin.php')));
		exit;
	}

	$headers = str_getcsv(trim($lines[0]), "\t");

	$headers = array_map(
		function ($header) {
			$header = str_replace("\xEF\xBB\xBF", '', $header);
			return trim($header);
		},
		$headers
	);

	error_log('Data import parsed headers: ' . implode(' | ', $headers));

	if (empty($headers)) {
		bst_log_import_error('No headers found in import file');
		wp_redirect(add_query_arg(array('page' => 'bst-tour-bookings', 'import_error' => 'no_headers'), admin_url('admin.php')));
		exit;
	}

	$table_columns  = $wpdb->get_col("DESCRIBE $table_name", 0);
	$valid_headers  = array();
	$invalid_headers = array();

	foreach ($headers as $header) {
		$header = trim($header);
		if ('export_reason' === $header) {
			continue;
		}
		if (in_array($header, $table_columns, true)) {
			$valid_headers[] = $header;
		} else {
			$invalid_headers[] = $header;
		}
	}

	if (!empty($invalid_headers)) {
		$error_message = 'Invalid field names found: ' . implode(', ', $invalid_headers);
		wp_redirect(
			add_query_arg(
				array(
					'page'         => 'bst-tour-bookings',
					'import_error' => 'invalid_field_names',
					'message'      => rawurlencode($error_message),
				),
				admin_url('admin.php')
			)
		);
		exit;
	}

	if (empty($valid_headers)) {
		bst_log_import_error('No valid headers found. Headers: ' . implode(', ', $headers) . '. Valid columns: ' . implode(', ', $table_columns));
		wp_redirect(add_query_arg(array('page' => 'bst-tour-bookings', 'import_error' => 'no_valid_headers'), admin_url('admin.php')));
		exit;
	}

	bst_log_import_message('Data import starting with valid headers: ' . implode(', ', $valid_headers) . '. Invalid headers: ' . implode(', ', $invalid_headers));

	$updated_count = 0;
	$created_count   = 0;
	$error_count     = 0;

	for ($i = 1; $i < count($lines); $i++) {
		$line = trim($lines[ $i ]);
		if ('' === $line) {
			continue;
		}

		$data = str_getcsv($line, "\t");
		while (count($data) < count($headers)) {
			$data[] = '';
		}

		if (count($data) > count($headers)) {
			bst_log_import_error('Row ' . ($i + 1) . ' has more columns than headers. Expected: ' . count($headers) . ', Got: ' . count($data));
			++$error_count;
			continue;
		}

		$update_data = array();
		$record_id   = null;

		for ($j = 0; $j < count($headers); $j++) {
			$header = trim($headers[ $j ]);
			if ('export_reason' === $header) {
				continue;
			}
			if (in_array($header, $valid_headers, true)) {
				$value = trim($data[ $j ]);
				if ('id' === $header) {
					$record_id = !empty($value) ? intval($value) : null;
				} else {
					$update_data[ $header ] = $value;
				}
			}
		}

		if (empty($update_data)) {
			bst_log_import_error('No valid update data for row ' . ($i + 1) . '. Line: ' . $line);
			++$error_count;
			continue;
		}

		$update_data['updated_by']   = wp_get_current_user()->user_login;
		$update_data['updated_date'] = current_time('mysql');

		try {
			if ($record_id && $record_id > 0) {
				$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE id = %d", $record_id));
				if ($existing) {
					$result = bst_update_tour_booking($record_id, $update_data, 'data_import_update');
					if ($result['success']) {
						++$updated_count;
					} else {
						bst_log_import_error('Data import update failed for ID ' . $record_id . ': ' . $result['error']);
						++$error_count;
					}
				} else {
					$update_data['id'] = $record_id;
					$result            = bst_create_tour_booking($update_data, 'data_import_insert');
					if ($result['success']) {
						++$created_count;
					} else {
						bst_log_import_error('Data import insert failed for ID ' . $record_id . ': ' . $result['error']);
						++$error_count;
					}
				}
			} else {
				$result = bst_create_tour_booking($update_data, 'data_import_new');
				if ($result['success']) {
					++$created_count;
				} else {
					bst_log_import_error('Data import create failed: ' . $result['error']);
					++$error_count;
				}
			}
		} catch (Exception $e) {
			bst_log_import_error('Data import exception for row ' . ($i + 1) . ': ' . $e->getMessage());
			++$error_count;
		}
	}

	bst_log_import_message("Import completed: {$updated_count} updated, {$created_count} created, {$error_count} errors");

	wp_redirect(
		add_query_arg(
			array(
				'page'           => 'bst-tour-bookings',
				'import_success' => '1',
				'updated'        => $updated_count,
				'created'        => $created_count,
				'errors'         => $error_count,
			),
			admin_url('admin.php')
		)
	);
	exit;
}

// #endregion
