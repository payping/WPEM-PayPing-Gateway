<?php
defined( 'ABSPATH' ) || exit;

class WPEMS_Payment_Gateway_PayPing extends WPEMS_Abstract_Payment_Gateway{

    /**
     * id of payment
     * @var null
     */
    public $id = 'payping';
    // title
    public $title = null;
    // email
    protected $payping_token = null;
    // url
    protected $payping_url = null;
    // rest url
    protected $rest_url = 'https://api.payping.ir';
    // enable
    protected static $enable = false;
    //Server IO
    protected $payping_io;

    public function __construct(){
        $this->title = __('پی‌پینگ', 'wp-events-manager');
        $this->icon = plugin_dir_url(__FILE__) . 'payping.png';
        parent::__construct();

        // production environment
        $this->payping_token = wpems_get_option('payping_token') ? wpems_get_option('payping_token') : '';
        $this->payping_io = wpems_get_option('payping_io') ? wpems_get_option('payping_io') : false;
        if( $this->payping_io == 'no' ){
            $this->payping_url = 'https://api.payping.ir';
        }else{
            $this->payping_url = 'https://api.payping.io';
        }
    }


    /*
     * Check gateway available
     */
    public function is_available(){
        return true;
    }

    /*
     * Check gateway enable
     */
    public function is_enable(){
        self::$enable = !empty($this->payping_token) && wpems_get_option('payping_enable') === 'yes';
        return apply_filters('tp_event_enable_payping_payment', self::$enable);
    }


    // callback
    public function payment_validation(){
        // check validate query
        if (!isset($_GET['event-book']) || !$_GET['event-book']) {
            return;
        }

        $booking_id = absint($_GET['event-book']);

        if( !isset($_GET['tp-event-payping-nonce']) || !wp_verify_nonce($_GET['tp-event-payping-nonce'], 'tp-event-payping-nonce' . $booking_id) ){
            return;
        }

        $book = new WPEMS_Booking($booking_id);
        if (is_null($book)) {
            return;
        }

        // check validate payment
		$refid = isset($_POST['refid']) ? $_POST['refid'] : '';
		$amount = absint($book->price);
		if($book->currency == 'IRR'){
            $amount /= 10;
        }
		$data = array(
            'refId'   => $refid,
            'amount'  => $amount
        );
		 $args = array(
            'timeout'      => 45,
            'redirection'  => '5',
            'httpsversion' => '1.0',
            'blocking'     => true,
            'headers'      => array(
                                  'Authorization' => 'Bearer ' . $this->payping_token,
                                  'Content-Type'  => 'application/json',
                                  'Accept'        => 'application/json'
                              ),
			'body'         => json_encode( $data, true ),
            'cookies'      => array()
        );
		$VerifyResponse = wp_remote_post( $this->payping_url.'/v2/pay/verify', $args );
		$ResponseXpId = wp_remote_retrieve_headers( $VerifyResponse )['x-paypingrequest-id'];
		
		if( is_wp_error($response) ){
			$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$ResponseXpId;
			wpems_add_notice('error', sprintf(__('تراکنش ناموفق! کد خطا: ' . $Message )));
		}else{
			$code = wp_remote_retrieve_response_code( $response );
			if( $code === 200 ){
				if(isset( $refid ) and $refid != ''){
					$status = 'ea-completed';
					$book->update_status($status);
					wpems_add_notice('success', sprintf(__('تراکنش با شماره ' . $refid . ' پرداخت شد.')));
				}else{
					$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$ResponseXpId;
					wpems_add_notice('error', sprintf(__('تراکنش ناموفق! کد خطا: ' . $Message )));
				}
			}elseif( $code == 400){
				$rbody = json_decode( $body, true );
				if( array_key_exists('15', $rbody) ){
					$status = 'ea-completed';
					$book->update_status($status);
					wpems_add_notice('success', sprintf(__('تراکنش با شماره ' . $refid . ' پرداخت شد.')));
				}else{
					$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$ResponseXpId;
					wpems_add_notice('error', sprintf(__('تراکنش ناموفق! کد خطا: ' . $Message )));
				}
			}else{
				$Message = wp_remote_retrieve_body( $response ).'<br /> شماره خطا: '.$ResponseXpId;
				wpems_add_notice('error', sprintf(__('تراکنش ناموفق! کد خطا: ' . $Message )));
			}
		}
    }

    /**
     * fields settings
     * @return array
     */
    public function admin_fields(){
        $prefix = 'thimpress_events_';
        return apply_filters('tp_event_payping_admin_fields', array(
            array(
                'type' => 'section_start',
                'id' => 'payping_settings',
                'title' => __('تنظیمات پی‌پینگ', 'wp-events-manager'),
                'desc' => esc_html__('ساخت درگاه پی‌پینگ', 'wp-events-manager')
            ),
            array(
                'type' => 'yes_no',
                'title' => __('فعال کردن', 'wp-events-manager'),
                'id' => $prefix . 'payping_enable',
                'default' => 'no',
                'desc' => apply_filters('tp_event_filter_enable_payping_gateway', '')
            ),
            array(
                'type' => 'text',
                'title' => __('توکن', 'wp-events-manager'),
                'id' => $prefix . 'payping_token',
                'default' => '',
            ),
            array(
                'type' => 'yes_no',
                'title' => __('استفاده از سرور خارج', 'wp-events-manager'),
                'id' => $prefix . 'payping_io',
                'default' => 'no',
                'desc' => apply_filters('tp_event_filter_enable_payping_gateway', '')
            ),
            array(
                'type' => 'section_end',
                'id' => 'payping_settings'
            )
        ));
    }

    /**
     * get_item_name
     * @return string
     */
    public function get_item_name($booking_id = null){
        if (!$booking_id)
            return;
        // book
        $book = WPEMS_Booking::instance($booking_id);
        $description = sprintf('%s(%s)', $book->post->post_title, wpems_format_price($book->price, $book->currency));
        return $description;
    }
	
    public function process($booking_id = false){
        if(!$this->is_available()){
            return array(
                'status' => false,
                'message' => __('توکن کد را بررسی کنید.', 'wp-events-manager')
            );
        }
		if(!$booking_id){
            wp_send_json(array(
                'status' => false,
                'message' => __('Booking ID is not exists!', 'wp-events-manager')
            ));
            wp_die();
        }
        // book
        $book = wpems_get_booking($booking_id);
        // process amount
        $amount = absint($book->price);
        if($book->currency != 'IRR' && $book->currency != 'IRT'){
            wp_send_json(array(
                'status' => false,
                'message' => __('برای استفاده از درگاه مبلغ باید به ریال یا تومان باشد.')
            ));
            die();
        }
        if($book->currency == 'IRR'){
            $amount /= 10;
        }
		
        // create nonce
        $nonce = wp_create_nonce('tp-event-payping-nonce' . $booking_id);

        $user = get_userdata($book->user_id);
        $email = $user->user_email;
        $callback_url = add_query_arg(array('tp-event-payping-nonce' => $nonce, 'event-book' => $booking_id), wpems_account_url());
        $description = 'شماره رزرو: ' . $booking_id . ' | خریدار: ' . $user->display_name;

		$data = array(
            'payerName'     => $user->display_name,
            'amount'        => $amount,
            'returnUrl'     => $callback_url,
            'description'   => $description,
            'payerIdentity' => $email,
            'clientRefId'   => $booking_id,
        );
		 $args = array(
            'timeout'      => 45,
            'redirection'  => '5',
            'httpsversion' => '1.0',
            'blocking'     => true,
            'headers'      => array(
                                  'Authorization' => 'Bearer ' . $this->payping_token,
                                  'Content-Type'  => 'application/json',
                                  'Accept'        => 'application/json'
                              ),
			'body'         => json_encode( $data, true ),
            'cookies'      => array()
        );
		$PayResponse = wp_remote_post( $this->payping_url.'/v2/pay', $args );
		$ResponseXpId = wp_remote_retrieve_headers( $PayResponse )['x-paypingrequest-id'];
        if( is_wp_error( $PayResponse ) ){
            $Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا ' . $PayResponse->get_error_message() . '<br/> شماره خطای پی‌پینگ: ' . $ResponseXpId;
            return array(
                    'status' => false,
                    'message' => $Message,
                );
        }else{
            $code = wp_remote_retrieve_response_code( $PayResponse );
            if( $code === 200 ){
                if ( isset( $PayResponse["body"] ) && $PayResponse["body"] != '' ) {
                    $CodePay = wp_remote_retrieve_body( $PayResponse );
                    $CodePay =  json_decode( $CodePay, true );
					return array(
                    	'status' => true,
                    	'url' => sprintf( $this->payping_url .'/v2/pay/gotoipg/%s', $CodePay['code'] ),
                	);
                }else{
                    $Message = ' اتصال به بانک ناموفق بود- کد خطا : ' . $ResponseXpId;
                    return array(
						'status' => false,
						'message' => $Message,
                	);
                }
            }else{
                $Message = wp_remote_retrieve_body( $PayResponse ) . '<br /> کد خطا: ' . $ResponseXpId;
                return array(
                    'status' => false,
                    'message' => $Message,
                );
            }
        }
    }

}