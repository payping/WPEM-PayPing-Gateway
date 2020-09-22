<?php

/**
 *
 * @link              mailto:payping.ir
 * @since             1.0.0
 * @package           PayPing_Wp_Events_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       درگاه پرداخت پی‌پینگ برای افزونه WP Events Manager
 * Plugin URI:        https://payping.ir
 * Description:       درگاه پرداخت پی‌پینگ برای افزونه مدیریت رویداد وردپرس.
 * Version:           1.1.0
 * Author:            Mahdi Sarani
 * Author URI:        https://mahdisarani.ir
 * License:           GPLv3
 * Text Domain:       payping-wp-events-manager
 */


defined( 'ABSPATH' ) || exit;

if( is_plugin_active( 'wp-events-manager/wp-events-manager.php' ) && file_exists( plugin_dir_path( __DIR__ ) . 'wp-events-manager\inc\abstracts\class-wpems-abstract-payment-gateway.php') ){
	require_once plugin_dir_path( __DIR__ ) . 'wp-events-manager\inc\abstracts\class-wpems-abstract-payment-gateway.php';

    if (!class_exists('WPEMS_Payment_Gateway_PayPing')) {
        include plugin_dir_path(__FILE__) . 'class-wpems-payment-gateway-payping.php';
    }

    // watch payment query
    add_action('init', 'ppwpems_get_request_payment', 99);
    function ppwpems_get_request_payment()
    {
        (new WPEMS_Payment_Gateway_PayPing())->payment_validation();
    }

    add_filter('wpems_currencies', function ($currencies) {
        $currencies['IRR'] = __('ایران (ریال)');
        $currencies['IRT'] = __('ایران (تومان)');
        return $currencies;
    });

    add_filter('tp_event_currency_symbol', function ($currency_symbol, $currency) {
        switch ($currency) {
            case 'IRR' :
                $currency_symbol = __('ریال');
                break;
            case 'IRT' :
                $currency_symbol = __('تومان');
                break;
        }
        return $currency_symbol;
    }, 11, 2);

    // add payping payment geteway to setting
    add_filter('wpems_payment_gateways', 'add_payping_checkout_section');
    function add_payping_checkout_section($gateways)
    {
        $gateways['payping'] = new WPEMS_Payment_Gateway_PayPing();
        return $gateways;
    }
}else{
    add_action('admin_notices', 'ppwpems_admin_notice_active_base_plugin');
    function ppwpems_admin_notice_active_base_plugin(){ ?>
        <div class="notice notice-warning">
            <p><b>توجه!!!</b>: برای استفاده از درگاه پی‌پینگ افزونه <a href="https://wordpress.org/plugins/wp-events-manager/" target="_blank">wp-events-manager</a> را نصب و فعال کنید.</p>
        </div>
<?php }
	}	