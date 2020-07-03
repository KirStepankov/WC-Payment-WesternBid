<?php
/**
 * Plugin Name: Westernbid
 * Description: Платёжный шлюз для WooCommerce.
 * Author: Степанков Кирилл
 * Author URI: https://www.fl.ru/users/dev-stepankoff/
 * Version: 1.0.0
 * Text Domain: wc-gateway-offline
 * Domain Path: /i18n/languages/
 *
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Offline
 * @author    Степанков Кирилл
 * @category  Admin
 *
 */
 
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'west_add_gateway_class' );
function west_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_West_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'west_init_gateway_class' );
function west_init_gateway_class() {
 
	class WC_West_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
		public function __construct() {
		 
			$this->id = 'west'; // payment gateway plugin ID
			
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'WesternBid';
			$this->method_description = 'Обеспечим прием Пей-Пал оплат от иностранных покупателей на любых западных торговых площадках, включая EBAY и ETSY.'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);
		 
			$dir = plugin_dir_path( __FILE__ );
			$dir = plugins_url('assets/img/westernbid.jpg', __FILE__);
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->enable_icon = 'yes' === $this->get_option( 'enable_icon' );
			
			$this->icon = $this->enable_icon ? $dir : ''; // URL of the icon that will be displayed on checkout page near your gateway name
			
			$this->private_key = $this->get_option( 'private_key' );
			$this->merchant_account = $this->get_option( 'merchant_account' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
			// You can also register a webhook here
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
		 }
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable westternBid Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'enable_icon' => array
				(
					'title' 	  => 'Show gateway icon?',
					'type' 		  => 'checkbox',
					'label' 	  => 'Show',
					'default' 	  => 'yes'
				),	
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Payment WesternBid',
					'desc_tip'    => true
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your credit card via our super-cool payment gateway.',
					'desc_tip'    => true
				),							
				'merchant_account' => array(
					'title'       => 'Merchant account',
					'type'        => 'text',
					'placeholder' => 'XXXXXXXXXXXX'
				),
				'private_key' => array(
					'title'       => 'Secret key',
					'type'        => 'password',
					'placeholder' => 'XXXXXXX'
				)
			);
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
			if ($this->description)
			{
				echo wpautop(wptexturize($this->description));
			}		
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
 
		
 
	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
			if( empty( $_POST[ 'billing_first_name' ]) ) {
				wc_add_notice(  'First name is required!', 'error' );
				return false;
			}
			return true;
 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
 
			global $woocommerce;
			$order = new WC_Order($order_id);		
			
			// we need it to get any order detailes
			$order_object = wc_get_order( $order_id );	
			
			$data = $order_object->get_data();
			
			$wb_login =  $this->merchant_account;
			$secret_key = $this->private_key;
			$amount = $data['total'];
			$invoice = $wb_login.'-'.$order->get_order_key($context);
			
			$wb_hash = md5($wb_login.$secret_key.$amount.$invoice);
			
			$args = array(
				'charset' => 'utf-8',
				'wb_login' => $wb_login, 
				'wb_hash' => $wb_hash, 
				'invoice' => $invoice, 
				'amount' => $amount,
				'email' => $_POST[ 'billing_email' ], 
				'phone' => $_POST[ 'billing_phone' ], 
				'first_name' => $_POST[ 'billing_first_name' ], 
				'last_name' => $_POST[ 'billing_last_name' ], 
				'address1' => $_POST[ 'billing_address_1' ],
				'address2' => $_POST[ 'billing_address_2' ],
				'country' => $_POST[ 'billing_country' ],
				'city' => $_POST[ 'billing_city' ],
				'zip' => $_POST[ 'billing_postcode' ],
				'shipping' => '0',
				'currency_code' => 'USD',

				'return' => $order->get_checkout_order_received_url(),
				'cancel_return' => $order->get_cancel_order_url(), 
				'notify_url' => WC()->api_request_url( 'WC_Gateway_Paypal' )
			);
			
			
			$item_arr = [];
			$order_items = $order_object->get_items( array('line_item', 'fee', 'shipping') );
			$i = 1;
			if ( !is_wp_error( $order_items ) ) {
				foreach( $order_items as $item_id => $order_item ) {					
					
				if($order_item == end($order_items)) {
						// делаем что-либо с последним элементом...
				}
				else {
					$item_data = $order_item->get_data();					
					$WC_Product = new WC_Product($item_data['product_id']);		
					$desc = substr($WC_Product->get_short_description(),0,500);
					
					
					$args['item_name_'.$i] = $item_data['name'];
					$args['description_'.$i] = strip_tags($desc);
					
					$args['item_number_'.$i] = $WC_Product->get_sku();
					$args['url_'.$i] = $WC_Product->get_permalink();
					$args['amount_'.$i] = $item_data['total'];
					$args['quantity_'.$i] = $item_data['quantity'];
					
					$i++;						
				}			

				}
			}  


			var_dump($args);
			
			
 			$query = build_query( $args );
			
     		return array
			(
				'result' => 'success',
				'redirect'	=> '/wp-content/plugins/westernbid/redirect.php/?'.$query
			);    
 
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
 
			$order = wc_get_order( $_GET['id'] );
			$order->payment_complete();
			$order->reduce_order_stock();
		 
			update_option('webhook_debug', $_GET);
 
	 	}
 	}
}