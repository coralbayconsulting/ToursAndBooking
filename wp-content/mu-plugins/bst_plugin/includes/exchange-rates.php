<?php

// Fetch exchange rates from Airwallex API
function bst_fetch_exchange_rates() {

    // Get Airwallex credentials from settings
    $client_id = get_option('bst_airwallex_client_id');
    $api_key = get_option('bst_airwallex_api_key');

    if (empty($client_id) || empty($api_key)) {
        error_log('Airwallex credentials not configured in settings.');
        return false;
    }

    // Always use production for live exchange rates
    $base_url = 'https://api.airwallex.com/api/v1';

    // Step 1: Authenticate and get token
    $auth_response = wp_remote_post($base_url . '/authentication/login', array(
        'headers' => array(
            'x-client-id' => $client_id,
            'x-api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => '{}',
        'timeout' => 30
    ));

    if (is_wp_error($auth_response)) {
        error_log('Airwallex authentication error: ' . $auth_response->get_error_message());
        return false;
    }

    $auth_body = wp_remote_retrieve_body($auth_response);
    $auth_data = json_decode($auth_body, true);

    if (!isset($auth_data['token'])) {
        error_log('Airwallex authentication failed: ' . print_r($auth_data, true));
        return false;
    }

    $token = $auth_data['token'];

    // Step 2: Fetch EUR-based rates
    $eur_currencies = array('USD', 'CAD', 'AUD', 'GBP', 'NZD', 'JPY', 'ZAR');
    foreach ($eur_currencies as $currency) {
        $rate = bst_get_airwallex_rate($base_url, $token, 'EUR', $currency);
        if ($rate !== false) {
            update_option('bst_exchange_rate_eur_' . strtolower($currency), $rate);
        } else {
            error_log("Failed to fetch EUR to $currency rate from Airwallex.");
        }
    }

    // Step 3: Fetch USD-based rates
    // Airwallex returns rates based on standard currency pairs
    // For pairs where USD is the quote currency (EURUSD, GBPUSD, AUDUSD, NZDUSD), we need to invert
    // For pairs where USD is the base currency (USDCAD, USDJPY, USDZAR), use directly
    
    $invert_currencies = array('EUR', 'GBP', 'AUD', 'NZD'); // USD is quote currency in standard pair
    $direct_currencies = array('CAD', 'JPY', 'ZAR'); // USD is base currency in standard pair
    
    // Fetch and invert rates for EUR, GBP, AUD, NZD
    foreach ($invert_currencies as $currency) {
        $rate = bst_get_airwallex_rate($base_url, $token, 'USD', $currency);
        if ($rate !== false && $rate > 0) {
            // Invert because we get EURUSD but need USD→EUR
            $usd_rate = round(1 / $rate, 6);
            update_option('bst_exchange_rate_usd_' . strtolower($currency), $usd_rate);
        } else {
            error_log("Failed to fetch USD to $currency rate from Airwallex.");
        }
    }
    
    // Fetch direct rates for CAD, JPY, ZAR
    foreach ($direct_currencies as $currency) {
        $rate = bst_get_airwallex_rate($base_url, $token, 'USD', $currency);
        if ($rate !== false) {
            // Use directly because we get USDCAD which is already USD→CAD
            update_option('bst_exchange_rate_usd_' . strtolower($currency), $rate);
        } else {
            error_log("Failed to fetch USD to $currency rate from Airwallex.");
        }
    }

    // Step 4: Fetch reverse rates for payment currencies (CAD, AUD, GBP) → EUR and USD
    // These are needed when customers pay in these currencies for EUR/USD tours
    $payment_currencies = array('CAD', 'AUD', 'GBP');
    $tour_currencies = array('EUR', 'USD');
    
    foreach ($payment_currencies as $payment_currency) {
        foreach ($tour_currencies as $tour_currency) {
            $rate_data = bst_get_airwallex_rate_with_spread($base_url, $token, $payment_currency, $tour_currency);
            if ($rate_data !== false && $rate_data['rate'] > 0) {
                $client_rate = $rate_data['rate'];
                $awx_rate = $rate_data['awx_rate'];
                $spread_pct = $rate_data['spread_pct'];
                
                // Determine if we need to invert based on spread direction
                // Positive spread = rate is in the format we need (payment→tour)
                // Negative spread = rate is inverted (tour→payment), we must invert it correctly
                
                if ($spread_pct >= 0) {
                    // Spread is positive: CLIENT rate is already in payment→tour format
                    // Examples: AUD→USD (AUDUSD), GBP→USD (GBPUSD) - use directly
                    $final_rate = round($client_rate, 6);
                } else {
                    // Spread is negative: rate is in inverted format
                    // The CLIENT rate's spread is bidirectional - when inverted, it automatically maintains
                    // the unfavorable direction to the customer. No need to re-apply spread.
                    // Examples: CAD→EUR (returns EURCAD), AUD→EUR (returns EURAUD), GBP→EUR (returns EURGBP)
                    
                    // Simply invert the CLIENT rate - the spread is preserved
                    $final_rate = round(1 / $client_rate, 6);
                }
                
                update_option('bst_exchange_rate_' . strtolower($payment_currency) . '_' . strtolower($tour_currency), $final_rate);
            } else {
                error_log("Failed to fetch $payment_currency to $tour_currency rate from Airwallex.");
            }
        }
    }

    $current_time = current_time('mysql');
    // Rates are good until next day at 00:00:01 GMT (when Airwallex updates)
    $expires_time = gmdate('Y-m-d', strtotime('+1 day')) . ' 00:00:01';
    
    update_option('bst_exchange_rate_last_updated', $current_time);
    update_option('bst_exchange_rate_expires', $expires_time);

    error_log('Exchange rates updated successfully from Airwallex.');

    return true;
}

// Helper function to get a single rate from Airwallex
function bst_get_airwallex_rate($base_url, $token, $buy_currency, $sell_currency) {
    $result = bst_get_airwallex_rate_with_spread($base_url, $token, $buy_currency, $sell_currency);
    return $result !== false ? $result['rate'] : false;
}

// Helper function to get rate with spread information
function bst_get_airwallex_rate_with_spread($base_url, $token, $buy_currency, $sell_currency) {
    $url = $base_url . '/fx/rates/current?buy_currency=' . $buy_currency . '&sell_currency=' . $sell_currency;
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    ));

    if (is_wp_error($response)) {
        error_log("Airwallex rate fetch error ($buy_currency to $sell_currency): " . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $awx_rate = null;
    $client_rate = null;

    // Extract both AWX and CLIENT rates for comparison
    if (isset($data['rate_details'])) {
        foreach ($data['rate_details'] as $detail) {
            if ($detail['level'] === 'AWX') {
                $awx_rate = floatval($detail['rate']);
            }
            if ($detail['level'] === 'CLIENT') {
                $client_rate = floatval($detail['rate']);
            }
        }
    }

    // Fallback to main rate
    if ($client_rate === null && isset($data['rate'])) {
        $client_rate = floatval($data['rate']);
    }

    // Calculate spread for internal use
    $spread_pct = ($awx_rate && $client_rate) ? round((($client_rate - $awx_rate) / $awx_rate) * 100, 3) : 0;

    return ($client_rate !== false && $awx_rate !== false) ? array('rate' => $client_rate, 'awx_rate' => $awx_rate, 'spread_pct' => $spread_pct) : false;
}

// Hook the function to the cron event
add_action('bst_fetch_exchange_rates_event', 'bst_fetch_exchange_rates');

// Schedule the cron event to run daily after midnight
function bst_schedule_exchange_rates_cron() {
    // If the event is scheduled, nothing to do
    if (wp_next_scheduled('bst_fetch_exchange_rates_event')) {
        return;
    }

    // wp_next_scheduled is unreliable during cron execution and on multi-instance hosting.
    // Check whether rates were updated in the last 13 hours as a reliable proxy.
    $last_updated = get_option('bst_exchange_rate_last_updated');
    if ($last_updated && (time() - strtotime($last_updated)) < 13 * HOUR_IN_SECONDS) {
        return;
    }

    // Rate-limit scheduling attempts to once per day to avoid log noise
    if (get_transient('bst_cron_schedule_attempted')) {
        return;
    }
    set_transient('bst_cron_schedule_attempted', 1, DAY_IN_SECONDS);

    $timestamp = strtotime('tomorrow 00:30:00 UTC');
    $scheduled = wp_schedule_event($timestamp, 'twicedaily', 'bst_fetch_exchange_rates_event');

    if ($scheduled === true || $scheduled === null) {
        error_log('BST: Exchange rates cron job scheduled for daily execution at 00:30 UTC.');
    } elseif (is_wp_error($scheduled)) {
        error_log('BST: Failed to schedule exchange rates cron job. Error: ' . $scheduled->get_error_message());
    } else {
        error_log('BST: Failed to schedule exchange rates cron job. wp_schedule_event returned: ' . var_export($scheduled, true));
    }
}
// Check only once per day if the cron job exists
add_action('wp_loaded', 'bst_schedule_exchange_rates_cron');

// Callback functions to access exchange rates
function bst_get_exchange_rate($from_currency, $to_currency) {
    $from_currency = strtolower($from_currency);
    $to_currency = strtolower($to_currency);
    
    // Same currency
    if ($from_currency === $to_currency) {
        return 1.0;
    }
    
    // Direct rate lookup
    $rate = get_option('bst_exchange_rate_' . $from_currency . '_' . $to_currency);
    if ($rate !== false) {
        return floatval($rate);
    }
    
    // For backward compatibility - old function signature
    if (func_num_args() === 1) {
        return get_option('bst_exchange_rate_eur_' . $from_currency);
    }
    
    return false;
}

// Legacy function for backward compatibility
function bst_get_exchange_rate_legacy($currency) {
    return get_option('bst_exchange_rate_eur_' . strtolower($currency));
}

function bst_get_last_updated() {
    return get_option('bst_exchange_rate_last_updated');
}

// Function to return an array of exchange rates with currency code, rate, and symbols
// $base_currency: The currency that prices are stored in (EUR or USD)
function bst_get_exchange_rates_array($base_currency = 'EUR') {
    $base_currency = strtoupper($base_currency);
    
    $currencies = array(
        'EUR' => array('symbol' => '€', 'flag' => '🇪🇺'),
        'USD' => array('symbol' => '$', 'flag' => '🇺🇸'),
        'CAD' => array('symbol' => 'C$', 'flag' => '🇨🇦'),
        'AUD' => array('symbol' => 'A$', 'flag' => '🇦🇺'),
        'GBP' => array('symbol' => '£', 'flag' => '🇬🇧'),
        'NZD' => array('symbol' => 'NZ$', 'flag' => '🇳🇿'),
        'JPY' => array('symbol' => '¥', 'flag' => '🇯🇵'),
        'ZAR' => array('symbol' => 'R', 'flag' => '🇿🇦')
    );
    
    $exchange_rates = array();
    
    foreach ($currencies as $currency => $info) {
        if ($currency === $base_currency) {
            $rate = 1.0; // Base currency has rate of 1
        } else {
            $rate = bst_get_exchange_rate($base_currency, $currency);
            if ($rate === false) {
                $rate = bst_get_exchange_rate_legacy($currency); // Fallback for EUR-based rates
            }
        }
        
        $exchange_rates[] = array(
            'currency' => $currency,
            'rate' => $rate,
            'symbol' => $info['symbol'],
            'flag' => $info['flag']
        );
    }

    return $exchange_rates;
}

function enqueue_bst_exchange_rates_script($hook) {
    if (strpos($hook, 'bst-exchange-rates') === false) {
        return;
    }
    $script_url = content_url('mu-plugins/bst_plugin/js/bst-exchange-rates.js');
    $script_path = WP_CONTENT_DIR . '/mu-plugins/bst_plugin/js/bst-exchange-rates.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : time();
    wp_enqueue_script('bst-exchange-rates', $script_url, array('jquery'), $script_version, true);
    wp_localize_script('bst-exchange-rates', 'bstExchangeRates', array(
        'nonce' => wp_create_nonce('bst_exchange_rates_nonce')
    ));
}
add_action('admin_enqueue_scripts', 'enqueue_bst_exchange_rates_script');

// Handle AJAX request to update exchange rates
function bst_update_exchange_rates_ajax() {
    check_ajax_referer('bst_exchange_rates_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }
    $success = bst_fetch_exchange_rates();

    if ($success) {
        $response = array(
            // EUR-based rates
            'eur_usd' => bst_get_exchange_rate_legacy('usd'),
            'eur_cad' => bst_get_exchange_rate_legacy('cad'),
            'eur_aud' => bst_get_exchange_rate_legacy('aud'),
            'eur_gbp' => bst_get_exchange_rate_legacy('gbp'),
            'eur_nzd' => bst_get_exchange_rate_legacy('nzd'),
            'eur_jpy' => bst_get_exchange_rate_legacy('jpy'),
            'eur_zar' => bst_get_exchange_rate_legacy('zar'),
            'eur_eur' => 1.0,
            // USD-based rates
            'usd_eur' => bst_get_exchange_rate('USD', 'EUR'),
            'usd_cad' => bst_get_exchange_rate('USD', 'CAD'),
            'usd_aud' => bst_get_exchange_rate('USD', 'AUD'),
            'usd_gbp' => bst_get_exchange_rate('USD', 'GBP'),
            'usd_nzd' => bst_get_exchange_rate('USD', 'NZD'),
            'usd_jpy' => bst_get_exchange_rate('USD', 'JPY'),
            'usd_zar' => bst_get_exchange_rate('USD', 'ZAR'),
            'usd_usd' => 1.0,
            'last_updated' => bst_get_last_updated()
        );

        wp_send_json_success($response);
    } else {
        error_log('Failed to fetch exchange rates.');
        wp_send_json_error('Failed to fetch exchange rates.');
    }
}
add_action('wp_ajax_bst_update_exchange_rates', 'bst_update_exchange_rates_ajax');
