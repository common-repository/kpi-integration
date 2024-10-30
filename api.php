<?php
/**
 * Created by PhpStorm.
 * User: Leon
 * Date: 4/10/2018
 * Time: 2:17 PM
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'is_plugin_active' ) ) //fix for older versions
	require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

class Kpi_Api_Route extends WP_REST_Controller {

	private $wpdb;
	private $apiKey = null;

	function __construct(){
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->apiKey = unserialize($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->prefix}options WHERE option_name=%s", 'kpi_opt_name')))['api_secret_key'];
		$this->registerRoute();
	}

	/**
	 * @example http://www.wp.dev/wp-json/kpi/?apiKey=test2&method=test
	 */
	public function registerRoute() {
		add_action( 'rest_api_init', function ( $server ) {
			$server->register_route( 'kpi', '/kpi', array(
//				'methods'   => WP_REST_Server::READABLE, // $_POST
//				'methods'   => WP_REST_Server::CREATABLE, // $_POST
				'methods'   => WP_REST_Server::ALLMETHODS, // all
				'callback'  => function() {
					return $this->api_check();
				},
			) );
		} );
	}

	/**
	 * check for api key & method
	 * @return json
	 */
	private function api_check(){
		$err = null;
		if(empty($_POST))								    $err = 'POST data is empty';
		else if(!isset($_POST['method']))					$err = 'API method is undefined';
		else if(!method_exists($this, $_POST['method']))	    $err = 'API method is invalid';
		else if(!isset($_POST['apiKey']))					$err = 'API key is undefined';
		else if($_POST['apiKey'] != $this->apiKey)	$err = 'API key is invalid';
		if($err) exit(json_encode(['apiErr' => $err]));
		else {
			header('Content-Type: text/html; charset=utf-8');
			if(phpversion() >= 7){
				return $this->{$_POST['method']}(); //fix for php 7
			} else {
				return $this->$_POST['method']();
			}
		}
	}

	/**
	 * simple test function for developers..
	 */
	private function test(){
		$this->dump('everything running good');
		// dev testing method
		exit();
	}

	/**
	 * @param $from_date
	 *	current issues: issue get submission by date & maybe need to improve a little more
	 * @return array
	 */
	private function Feedbackup_nf3($from_date){
		//todo: check if ninja forms is installed
		global $wpdb;
		$forms = $wpdb->get_col("SELECT id FROM wp_nf3_forms");
		$submissions = [];
		foreach ($forms as $form_id){
			//get forms fields
			$submissions[$form_id]['fields'] = $wpdb->get_results($wpdb->prepare("SELECT `id`, `key` FROM {$wpdb->prefix}nf3_fields WHERE parent_id=%s",$form_id), ARRAY_A);
			foreach ($submissions[$form_id]['fields'] as $i => $field){
				if($field['type'] === 'submit' || $field['key'] === 'submit') unset($submissions[$form_id]['fields'][$i]); //unset submit field
			}
		}

		/**
		 * hash map:
		 * 	[form_id] => [
		 * 		[submission_id] => [
		 * 			[field_index] => [ value, label ]
		 * //post_id - מספר פנייה
		 * //meta_key - שם שדה
		 */

		$nf_submission = $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value, post_id FROM {$wpdb->prefix}postmeta WHERE meta_key LIKE '_field_%'"), ARRAY_A);
		foreach ($nf_submission as $meta_value){
			foreach ($submissions as $form_id => $z){
				$key = $this->recursive_array_search( str_replace('_field_','',$meta_value['meta_key']), $submissions[$form_id]['fields'] );
				if(!empty($key)){
					$submissions[$form_id]['submissions'][$meta_value['post_id']][] = $meta_value;
				}
			}
		}
		unset($nf_submission);

		foreach ($submissions as $form_id => $b){
			$subs_by_form = $submissions[$form_id]['submissions'];
			foreach ($subs_by_form as $i => $f){
				foreach ($f as $ii => $ff){
					unset($ff['post_id']);
					$ff['meta_key'] = $submissions[$form_id]['fields'][$this->recursive_array_search(str_replace('_field_','',$ff['meta_key']), $submissions[$form_id]['fields'])[0]]['key'];
					//$submissions[$form_id]['submissions'][$i][$ii] = $ff;
					$submissions[$form_id]['submissions'][$i]['form_fields'][$ff['meta_key']] = $ff['meta_value'];
					unset($submissions[$form_id]['submissions'][$i][$ii]);
				}
				$submissions[$form_id]['submissions'][$i]['date'] = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->prefix}posts WHERE post_type=%s",'nf_sub'));
				$submissions[$form_id]['submissions'][$i]['lang'] = 'he';
				$submissions[$form_id]['submissions'][$i]['feedbackID'] = $i;
				$submissions[$form_id]['submissions'][$i]['status'] = '0';
				$submissions[$form_id]['submissions'][$i]['source'] = 'nf3';

				//extract page_url
				if(isset($submissions[$form_id]['submissions'][$i]['form_fields']['page_url'])){
					$submissions[$form_id]['submissions'][$i]['page_url'] = $submissions[$form_id]['submissions'][$i]['form_fields']['page_url'];
					unset($submissions[$form_id]['submissions'][$i]['form_fields']['page_url']);
				}

				//extract email
				if(isset($submissions[$form_id]['submissions'][$i]['form_fields']['email'])){
					$submissions[$form_id]['submissions'][$i]['email'] = $submissions[$form_id]['submissions'][$i]['form_fields']['email'];
					unset($submissions[$form_id]['submissions'][$i]['form_fields']['email']);
				}

				//extract name
				if(isset($submissions[$form_id]['submissions'][$i]['form_fields']['name'])){
					$submissions[$form_id]['submissions'][$i]['name'] = $submissions[$form_id]['submissions'][$i]['form_fields']['name'];
					unset($submissions[$form_id]['submissions'][$i]['form_fields']['name']);
				}

			}
			unset($submissions[$form_id]['fields']);
			$submissions[$form_id] = $submissions[$form_id]['submissions'];
		}

		$submissionsRes = [];
		foreach ($submissions as $form_id => $data){
			foreach ($data as $i => $d){
				$submissionsRes[$i] = $d;
			}
		}

		ob_start();
		return $submissionsRes;
		exit();
		ob_flush();
	}

	/**
	 * @param $from_date
	 * get submissions from contact form 7 plugin
	 * @return array
	 */
	private function Feedbackup_cf7($from_date){
		ob_start();
		global $wpdb;
		$contacts = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type=%s AND post_date > DATE_FORMAT(%s, '%%Y-%%m-%%d 00:00:00')",'flamingo_inbound',$from_date));
		$kpiData = [];
		foreach ($contacts as $form_id){
			$item = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id=%s AND meta_key LIKE '_field_%'",$form_id));
			$contactEmail = $contactName = '';
			foreach ($item as $field){
				if($field->meta_key == '_fields') continue;
				if(str_replace('_field_','',$field->meta_key) == 'your-email') $contactEmail = $field->meta_value;
				if(str_replace('_field_','',$field->meta_key) == 'your-name') $contactName = $field->meta_value;
				$kpiData[$form_id]['form_fields'][str_replace('_field_','',$field->meta_key)] = $field->meta_value;
			}
			$meta = unserialize($wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key=%s AND post_id=%s",'_meta',$form_id)));
			//$kpiData[$form_id]['meta'] = $meta;
			$kpiData[$form_id]['lang'] = 'he';
			$kpiData[$form_id]['feedbackID'] = $form_id;
			$kpiData[$form_id]['page_url'] = $meta['post_url'] ? $meta['post_url'] : $meta['url'];
			$kpiData[$form_id]['user_agent'] = $meta['user_agent'];
			$kpiData[$form_id]['date'] = $wpdb->get_var($wpdb->prepare("SELECT post_date FROM {$wpdb->prefix}posts WHERE post_type=%s AND ID=%s",'flamingo_inbound',$form_id));
			$kpiData[$form_id]['source'] = $meta['post_title'];
			$kpiData[$form_id]['status'] = '0';
			$kpiData[$form_id]['email'] = $contactEmail;
			$kpiData[$form_id]['name'] = $contactName;
		}
		return $kpiData;
	}

	/**
	 * get orders from woocommerce by date
	 * @url https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query
	 * @version woocommerce 2.6.0
	 */
	private function OrdersDuplicate() {

		//check woocommerce compability
		$this->woocommerceVersionCheck();

		$from_date = $_POST['fromDate'];
		if(!$from_date){
			$from_date = '2018-01-01 00:00:00';
		}

		//include woocommerce order funcs
		if ( !function_exists( 'wc_get_orders' ) ) {
			require_once ( plugin_dir_path( WooCommerce::plugin_path() ) . '/includes/wc-order-functions.php');
		}

		$args = [
			'date_after' => $from_date,
			'return' => 'ids',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
//			'created_via' => 'checkout', //type
		];
		$orders_id_list = wc_get_orders($args);

		$orders = [];

		foreach ($orders_id_list as $order_id){
			$order = wc_get_order( $order_id );
            $orderData = $order->get_data();

            /**
			 * product Array
			 */
			$products = [];
			//todo: get params, maybe by color for now, and do some tests to define structure
			foreach ($orderData['line_items'] as $item_id => $item_obj){

				$obj_id = $item_obj->get_id();
				$productObject = new WC_Product($item_obj->get_product_id());
				$productExist = $productObject->exists($item_obj->get_product_id());
                $n = isset($products[$obj_id]) ? count($products[$obj_id])+1 : 1;
                $the_data = $item_obj->get_data();
                if($productExist){
                    $the_data['price']['final'] = $productObject->get_price();
                    $the_data['price']['basic'] = $productObject->get_regular_price();
                    $the_data['serial'] = $productObject->get_sku(); //aka sku
                } else { //deleted product
                    $the_data = $item_obj->get_data();
                    $the_data['price']['final'] = $the_data['total'];
                    $the_data['price']['basic'] = $the_data['total'] / $the_data['quantity'];
                    $the_data['serial'] = ''; //aka sku
                }

                $the_data['params_inherit'] = '0'; //by default
                $the_data['params'] = $the_data['meta_data']; //by default

				unset($the_data['meta_data']);
				unset($the_data['variation_id']);
				unset($the_data['total']);
				unset($the_data['tax_class']);
				unset($the_data['subtotal']);
				unset($the_data['subtotal_tax']);
				unset($the_data['total_tax']);
				unset($the_data['taxes']);
				unset($the_data['order_id']);
				unset($the_data['product_id']);
				unset($the_data['id']);

                $products[$obj_id][$n] = $the_data;
			}

			$form_data_fields = [];
			foreach ($orderData['billing'] as $label => $value){
				$form_data_fields['billing'][] = [
					'name' => $label,
					'content' => $value,
				];
			}
			if(isset($orderData['shipping'])){
				foreach ($orderData['shipping'] as $label => $value){
					$form_data_fields['shipping'][] = [
						'name' => $label,
						'content' => $value,
					];
				}
			}

			//order date creation
			$dateArr = (array) $orderData['date_created'];
			$date = $dateArr['date'];
			$date = new DateTime( $date );
			$date = $date->format( 'Y-m-d H:i:s');

			//get salesman data
			$sales_man = get_user_by('id',$order->get_customer_id());
			$sales_man = json_encode([ 'user_id' => $sales_man->ID, 'user_name' => $sales_man->user_nicename ],true);

			$orders[] = [
                'id' => $order->get_id(),
                'sales_man' => $sales_man,
                'total_amount' => $orderData['shipping_total'] + $orderData['total'],
                'full_name' => isset($orderData['shipping']) ? $orderData['shipping']['first_name'] . $orderData['shipping']['last_name'] : $orderData['billing']['first_name'] . $orderData['billing']['last_name'],
                'payer_name' => $orderData['billing']['first_name'] . $orderData['billing']['last_name'],
                'date' => $date,
                'status' => $orderData['status'],
                'is_new' => '1', //be default
                'lang_code' => 'he', //by default
                'payment_type' => $orderData['payment_method_title'], //or 'payment_method'
                'payment_confirmation' => '', //todo: on paypal/tranzila.. need to check with selected
                'uniqID' => $orderData['transaction_id'], //todo: on paypal/tranzila.. need to check with selected
				'form_data' => [
					'form_name' => 'טופס הזמנה', //by default
					'source' => [
						'title' => 'מקור הפניה',
						'form_name' => 'טופס הזמנה',
					], //by default
					'fields' => $form_data_fields,
				],
				'order_data' => [
					'info' => [
						'lang_code' => 'he', //by default
						'lang_name' => 'עברית', //by default
						'currencyCode' => 'ILS', //by default
						'currencySymbol' => '&#8362;', //₪ code (shekel)
						'currencyName' => 'שקל', //by default
						'products_amount' => $orderData['total'],
						'discount_total' => $orderData['discount_total'],
						'total_amount' => $orderData['shipping_total'] + $orderData['total'],
						'payment_type' => $orderData['payment_method_title'], //or 'payment_method'
						'payment_status' => $orderData['status'], //or 'payment_method'
					],
					'products' => $products,
					'shipping' => [
						'id' => key($order->get_items( 'shipping' )),
						'type' => $order->get_shipping_method(), //or 'payment_method',
						'price' => $orderData['shipping_total'],
					],
					'client_data' => [
						'name' => $orderData['billing']['first_name'] . ' ' . $orderData['billing']['last_name'],
						'email' => $orderData['billing']['email'],
					],
				],
			];
		}
//		$this->dump($orders);
        $api['orders'] = $orders;
		print json_encode($api,true);
		exit();
	}

	/**
	 * todo: throw message if there any issue with version / missing plugin
	 */
	private function FeedbacksDuplicate() {

		/**
		 * tiny version control..
		 */

		//todo: define versions on script start, maybe move all checks before active this method..
		$compatibility = [
			'nf3' => '3.0.0',
			'cf7' => '5.0.0',
			'flamingo' => '1.8',
		];

		/**
		 * Ninja forms 3 checking
		 */
		$nf3_compatibility = false;
		if ( is_plugin_active( 'ninja-forms/ninja-forms.php') ) {
			if(version_compare(Ninja_Forms::VERSION, $compatibility['nf3'],'>=')){
				$nf3_compatibility = true;
			} else {
				$errors['ninja_forms_3'][] = 'version below ' . $compatibility['nf3'] . ', please upgrade';
			}
		}

		/**
		 * Contact Form 7 checking
		 */
		$cf7_compatibility = false;
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php') ) {
			if(version_compare(WPCF7_VERSION, $compatibility['cf7'],'>=')){
				$cf7_compatibility = true;
			} else {
				$errors['contact_form_7'][] = 'version below ' . $compatibility['cf7'] . ', please upgrade';
			}
		} else {
			$errors['contact_form_7'][] = 'plugin not active';
		}

		$flamingo_compatibility = false;
		if($cf7_compatibility){
			if ( is_plugin_active( 'flamingo/flamingo.php') ) {
				if(version_compare(FLAMINGO_VERSION, $compatibility['flamingo'],'>=')){
					$flamingo_compatibility = true;
				} else {
					$errors['flamingo'][] = 'flamingo version is below 1.8, please upgrade';
				}
			} else {
				$errors['flamingo'][] = 'plugin not active';
			}
		} else {
			$errors['flamingo'][] = 'cf7 is not compatible please fix it\'s errors before continue';
		}

		if(!$flamingo_compatibility || !$cf7_compatibility){
			$cf7_compatibility = false;
		}

		/**
		 * Checking for errors
		 */
		if(!empty($errors)){
			$this->dump($errors);
		} else {
//			$this->dump('everything is ready to go.');
		}

		$from_date = $_POST['fromDate'];
		if(!$from_date){
			$from_date = '2018-01-01 00:00:00';
		}

		$nf3 = [];
		$cf7 = [];

		if($nf3_compatibility){
			$nf3 = $this->Feedbackup_nf3($from_date);
		}

		if($cf7_compatibility){
			$cf7 = $this->Feedbackup_cf7($from_date);
		}

		//todo: gravity forms compatibility & woocommerce orders
		$res['wp_feedbacks'] = array_merge($nf3,$cf7);

		$json_data = json_encode($res,true);

		print $json_data;
		exit();
		ob_flush();
	}

	/*****************************
	 *          Helpers         *
	 ***************************/

	/*
	 * Searches for $needle in the multidimensional array $haystack.
	 *
	 * @param mixed $needle The item to search for
	 * @param array $haystack The array to search
	 * @return array|bool The indices of $needle in $haystack across the
	 *  various dimensions. FALSE if $needle was not found.
	 */
	private function recursive_array_search($needle,$haystack) {
		foreach($haystack as $key=>$value) {
			if($needle===$value) {
				return array($key);
			} else if (is_array($value) && $subkey = $this->recursive_array_search($needle,$value)) {
				array_unshift($subkey, $key);
				return $subkey;
			}
		}
	}

	/**
	 * dump function for wordpress
	 * @param $d
	 * @param bool $stop
	 */
	private function dump($d, $stop = true){
		echo '<pre>';
		print_r($d);
		echo '</pre>';
		if($stop){
			die();
		}
	}

	/**
	 * check if woocommerce version is alright for this plugin
	 * todo: check if version 2.6 is alright or we need 3+...
	 * @return bool
	 */
	private function woocommerceVersionCheck(){
		if ( is_plugin_active( 'woocommerce/woocommerce.php') ) {
			global $woocommerce;
			if(!version_compare($woocommerce->version, '2.6','>=')){
				$errors['woocommerce'][] = 'version below 2.6, please upgrade';
			}
		} else {
            $errors['woocommerce'][] = 'WooCommerce not found';
        }
		if(!empty($errors)){
			$this->dump($errors);
			return false;
		}
	}

}