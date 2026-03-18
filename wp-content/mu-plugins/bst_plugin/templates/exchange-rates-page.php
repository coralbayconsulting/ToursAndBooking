<?php
if (!current_user_can('manage_options')) {
    return;
}

// EUR-based rates
$eur_usd = get_option('bst_exchange_rate_eur_usd');
$eur_cad = get_option('bst_exchange_rate_eur_cad');
$eur_aud = get_option('bst_exchange_rate_eur_aud');
$eur_gbp = get_option('bst_exchange_rate_eur_gbp');
$eur_nzd = get_option('bst_exchange_rate_eur_nzd');
$eur_jpy = get_option('bst_exchange_rate_eur_jpy');
$eur_zar = get_option('bst_exchange_rate_eur_zar');

// USD-based rates
$usd_eur = get_option('bst_exchange_rate_usd_eur');
$usd_cad = get_option('bst_exchange_rate_usd_cad');
$usd_aud = get_option('bst_exchange_rate_usd_aud');
$usd_gbp = get_option('bst_exchange_rate_usd_gbp');
$usd_nzd = get_option('bst_exchange_rate_usd_nzd');
$usd_jpy = get_option('bst_exchange_rate_usd_jpy');
$usd_zar = get_option('bst_exchange_rate_usd_zar');

$last_updated = get_option('bst_exchange_rate_last_updated');
$expires = get_option('bst_exchange_rate_expires');
$display_text = $last_updated ? 'Updated: ' . $last_updated : 'Not yet updated';
if ($expires && $expires !== '') {
    $display_text .= ' | Good until: ' . $expires;
}
?>
<div class="wrap">
    <h1>Exchange Rates</h1>
    <p>Exchange rates are automatically updated daily for both EUR and USD-based tours.</p>
    
    <form id="bst-exchange-rates-form" method="post" action="">
        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th scope="col" style="width: 200px; font-weight: bold;">Currency</th>
                    <th scope="col" style="text-align: center; font-weight: bold; background-color: #0073aa; color: white;">EUR Base Rate</th>
                    <th scope="col" style="text-align: center; font-weight: bold; background-color: #00a32a; color: white;">USD Base Rate</th>
                </tr>
                <tr style="background-color: #f9f9f9;">
                    <th scope="col" style="font-style: italic; color: #666;">Target Currency</th>
                    <th scope="col" style="text-align: center; font-style: italic; color: #666;">1 EUR equals...</th>
                    <th scope="col" style="text-align: center; font-style: italic; color: #666;">1 USD equals...</th>
                </tr>
            </thead>
            <tbody>
                <tr class="alternate">
                    <th scope="row" style="font-weight: bold;">🇺🇸 US Dollar (USD)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_usd" value="<?php echo esc_attr($eur_usd); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center; color: #666; font-style: italic;">1.0000</td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: bold;">🇪🇺 Euro (EUR)</th>
                    <td style="text-align: center; color: #666; font-style: italic;">1.0000</td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_eur" value="<?php echo esc_attr($usd_eur); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr class="alternate">
                    <th scope="row" style="font-weight: bold;">🇨🇦 Canadian Dollar (CAD)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_cad" value="<?php echo esc_attr($eur_cad); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_cad" value="<?php echo esc_attr($usd_cad); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: bold;">🇦🇺 Australian Dollar (AUD)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_aud" value="<?php echo esc_attr($eur_aud); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_aud" value="<?php echo esc_attr($usd_aud); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr class="alternate">
                    <th scope="row" style="font-weight: bold;">🇬🇧 British Pound (GBP)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_gbp" value="<?php echo esc_attr($eur_gbp); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_gbp" value="<?php echo esc_attr($usd_gbp); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: bold;">🇳🇿 New Zealand Dollar (NZD)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_nzd" value="<?php echo esc_attr($eur_nzd); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_nzd" value="<?php echo esc_attr($usd_nzd); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr class="alternate">
                    <th scope="row" style="font-weight: bold;">🇯🇵 Japanese Yen (JPY)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_jpy" value="<?php echo esc_attr($eur_jpy); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_jpy" value="<?php echo esc_attr($usd_jpy); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
                <tr>
                    <th scope="row" style="font-weight: bold;">🇿🇦 South African Rand (ZAR)</th>
                    <td style="text-align: center;"><input type="text" id="bst_eur_zar" value="<?php echo esc_attr($eur_zar); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                    <td style="text-align: center;"><input type="text" id="bst_usd_zar" value="<?php echo esc_attr($usd_zar); ?>" readonly style="text-align: center; border: none; background: transparent; font-weight: bold;"></td>
                </tr>
            </tbody>
        </table>
        
        <p style="margin-top: 30px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #0073aa; max-width: 800px;">
            <strong>Rate Status:</strong> <?php echo esc_html($display_text); ?>
        </p>
        
        <p class="submit">
            <input type="button" id="bst_update_exchange_rates" class="button button-primary" value="Update Exchange Rates">
            <span style="margin-left: 15px; color: #666; font-style: italic;">Updates both EUR and USD-based rates from live API</span>
        </p>
    </form>
</div>