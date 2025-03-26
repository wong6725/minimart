<?php

if ( !defined( 'ABSPATH' ) ) 
	exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Function" ) )
{

class WCWH_Function
{
	protected $refs;

	public function __construct( $refs )
	{
		$this->refs = $refs;
		
		//----------------04/10/2022
		//add_filter('login_message',array($this, 'custom_login_page_message'));
		add_filter( 'wp_login_errors', array($this, 'custom_login_error_message') );
		add_action('init',array($this, 'login_validation'));
		//----------------04/10/2022

		add_filter( 'wcwh_get_template_path', array( $this, 'get_template_path' ), 10, 1 );
		add_action( 'wcwh_get_template', array( $this, 'get_template' ), 10, 2 );
		add_action( 'wcwh_templating', array( $this, 'templating' ), 10, 3 );
		add_filter( 'wcwh_get_template_content', array( $this, 'get_template_content' ), 10, 2 );

		add_filter( 'wcwh_data_sanitizing', array( $this, 'data_sanitizing' ), 10, 1 );

		add_filter( 'wcwh_get_setting', array( $this, 'get_setting' ), 10, 4 );

		add_filter( 'wcwh_get_i18n', array( $this, 'get_i18n' ), 10, 1 );

		add_filter( 'wcwh_get_status', array( $this, 'get_status' ), 10, 2 );

		add_filter( 'wcwh_generate_token', array( $this, 'generate_token' ), 10, 1 );
		add_filter( 'wcwh_verify_token', array( $this, 'verify_token' ), 10, 2 );

		add_filter( 'wcwh_get_user_ip', array( $this, 'get_user_ip' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );

		add_filter( 'wcwh_get_formatted_address', array( $this, 'get_formatted_address' ), 10, 3 );
		
		//-------- 7/9/22 jeff DashboardWid -----//
		add_filter('screen_layout_columns', array( $this, 'dashboard_columns' ));
		add_filter('get_user_option_screen_layout_dashboard', array( $this, 'layout_dashboard' ));
		add_action( 'wp_dashboard_setup', array( $this, 'custom_dahsboard_widget' ) );
		//-------- 7/9/22 jeff DashboardWid -----//
		
		$this->modules_functions();
	}

	public function __destruct()
	{
		
	}
	
	//----------------04/10/2022
	public function login_validation() 
	{
		$user = wp_get_current_user();
		$start_date = get_user_meta( $user->ID, 'start_date', true);
		$end_date = get_user_meta( $user->ID, 'end_date', true);
		$current_date = current_time('Y-m-d');
		if( ($start_date && $start_date > $current_date) || ($end_date && $current_date > $end_date) )
		{
			wp_logout();
			if ( ! empty( $_REQUEST['redirect_to'] ) ) {
				$redirect_to           = $_REQUEST['redirect_to'];
				$requested_redirect_to = $redirect_to;
			} else {
				$condition = '';
				$timestamp = '';
				
				if($start_date && $start_date > $current_date) 
				{
					$timestamp = strtotime($start_date);
					$condition = 'st';
				}
				else if($end_date && $current_date > $end_date) $condition = 'et';

				$redirect_to = add_query_arg(
					array(
						'loggedout' => 'true',
						'forced_logout' => $condition,
						'ts' => $timestamp,
						'wp_lang'   => get_user_locale( $user ),
					),
					wp_login_url()
				);

				$requested_redirect_to = '';
			}
			$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );			
			wp_safe_redirect( $redirect_to );
			exit();
		}
	}
	
	public function custom_login_error_message($errors) 
	{
    	if ( isset($_GET['forced_logout']) && $_GET['forced_logout'] )
    	{
    		$action = $_GET['forced_logout'];
    		switch ($action) 
    		{
    			case 'et':
    				$errors->errors['loggedout'][0] = 'You are no longer have permission to access this site.';
    				$error_msg = true;
    				break;
    			case 'st':
    				if(isset($_GET['ts']) && $_GET['ts'])
    					$date = date('d-m-Y', (int)$_GET['ts']);
    				if($date)
    					$errors->errors['loggedout'][0] = 'Please try to login after '.$date.'.';
    				else
    					$errors->errors['loggedout'][0] = 'Error ';
    					$error_msg = true;
    				break;
    		}
    		if($error_msg)
    		{
    			$errors->error_data = [];
    		}
    	} 

    	return $errors;
	}
	
	/*
	function custom_login_page_message() 
	{
    	if ( isset($_GET['forced_logout']) && $_GET['forced_logout'] ) 
        	$message = "<p id='login_error' >You are no longer have permission to access this site.</p>";

    	return $message;
	}
	*/ 
	//----------------04/10/2022
	
	//-------- 7/9/22 jeff DashboardWid -----//
	public function dashboard_columns($columns) 
	{
  		$columns['dashboard'] = 1;
  		return $columns;
	}

	public function layout_dashboard () 
	{ 
  		return 1; 
	}

	
	public function custom_dahsboard_widget() 
	{
		if( current_user_cans( [ 'wh_dc_supervisor', 'wh_dc_executive', 'wh_store_supervisor', 'wh_store_executive', 'wh_store_officer' ] ) )
		{
			wp_add_dashboard_widget(
	        	'lastest_price_variation_dashboard',
	        	esc_html__( 'Lastest Price Variation', 'wporg' ),
	        	array($this, 'lastest_price_variation_widget')
	        );

	        wp_add_dashboard_widget(
	        	'new_product_dashboard',
	        	esc_html__( 'New Inbound Product', 'wporg' ),
	        	array($this, 'new_product_widget')
	        );
			
			//wp_add_dashboard_widget(
	        //	'task_schedule_dashboard',
	        //	esc_html__( 'Task Detail', 'wporg' ),
	        //	array($this, 'task_schedule_widget')
	        //);
		}
	}
	
	public function lastest_price_variation_widget() 
	{
		$from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
		$from_date = date('Y-m-d', $from_date);

		$filters = ['from_date'=>$from_date];
		$orders = [];
		$args = ['usage'=>1, 'uom'=>1];
		$pricing = apply_filters( 'wcwh_get_price_doc', $filters, $orders, false, $args );
	
		if($pricing)
		{
			$ids = [];
			foreach ($pricing as $value) 
			{
				$ids[] = $value['id'];
			}

			if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );
			$Inst = new WCWH_Pricing();
			$item_list = $Inst->get_dist_item( ['pricing_id'=>$ids] );

			if($item_list)
			{
				$pdt_ids = [];
				foreach ($item_list as $value) 
				{
					$pdt_ids[] = $value['product_id'];
				}
				
				$sellers = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
				$filters = ['id' => $pdt_ids, 'seller'=>$sellers['code']];
				$orders = ['pr.since'=>'DESC'];
				$items = apply_filters( 'wcwh_get_latest_price', $filters, $orders, false, [ 'usage'=>1, 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
				
				//---------21/9/22 
				//Compare latest product' uprice with previous uprice
				//get previous uprice 
				$since = strtotime($from_date);
				$since = strtotime('-1 day', $since);
				$since = date('Y-m-d', $since);
				$filters = ['id' => $pdt_ids, 'seller'=>$sellers['code'], 'on_date'=>$since];
				$prv_item = apply_filters( 'wcwh_get_latest_price', $filters, [], false, [ 'usage'=>1, 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
				$prv_uprice = [];
				foreach( $prv_item as $key => $pi )
				{
					$prv_uprice[$pi['id']] = $pi['uprice'];
				}				
				//---------21/9/22
				
				if($items)
				{
					$num_count = 1;
					foreach($items as $key => &$i)
					{
						$i['name'] = $i['code']." - ".$i['name'];
						//---------21/9/22
						if( $prv_uprice[$i['id']] )
						{
							if( $prv_uprice[$i['id']] == $i['uprice'])
							{
								unset($items[$key]);
								continue;
							}
							else
							{
								$i['uprice'] = '<span style="text-decoration: line-through; text-decoration-thickness:15%;">'.$prv_uprice[$i['id']].'</span><br>'.$i['uprice'];

							}
						}
						//---------21/9/22
						if($i['_thumbnail_id'])
						{
							$attch = wp_get_attachment_image_src( $i['_thumbnail_id'], 'full' );
							if($attch)
							{
								$src = $attch[0];
								$src = is_ssl() ? str_replace( "http", "https", $src ) : $src ;
								$i['image'] = '<div style="display:flex; justify-content:center; overflow:hidden;"><img src="'.$src.'" style="max-width:124px; max-height:124px;" /></div>';
							}				
						}
						$i['num'] = $num_count;
						$num_count++;					
					}
				
					$Listing = new WCWH_Listing();

					$cols = [
						'num' => '',
						'image' => 'Image',
						'name' => 'Item',
						'_uom_code' => 'UOM',
						'_sku' => 'SKU',
						'uprice' => 'Selling Price',
						'created_at' => 'Created',
					];

					echo $Listing->get_listing( $cols, $items, [], [], ['pagination'=>false, 'list_only'=>true]);
				}
			}		
		}
	}
	
	function new_product_widget() 
	{
		$from_date = strtotime('-2 weeks', current_time( 'timestamp' ));
		$from_date = date('Y-m-d H:i:s', $from_date);

		$filters = ['from_date'=>$from_date];
		$orders = [ 'a.created_at' => 'DESC' ];
		$args = ['usage'=>1, 'parent'=>1, 'group'=>1, 'category'=>1];
		$products = apply_filters('wcwh_get_item', $filters, $orders, false, $args);

		if($products)
		{
			foreach ($products as $key => &$pdt) 
			{
				if($pdt['_thumbnail_id'])
				{
					$attch = wp_get_attachment_image_src( $pdt['_thumbnail_id'], 'full' );
					if($attch)
					{
						$src = $attch[0];
						$src = is_ssl() ? str_replace( "http", "https", $src ) : $src ;
						$pdt['image'] = '<div style="display:flex; justify-content:center; overflow:hidden;"><img src="'.$src.'" style="max-width:124px; max-height:124px;" /></div>';
					}				
				}

				$pdt['num'] = $key + 1;
				$pdt['name'] = $pdt['code']." - ".$pdt['name'];
				$pdt['cat'] = $pdt['cat_slug'].'-'.$pdt['cat_name'];
				$pdt['grp'] = $pdt['grp_code'].'-'.$pdt['grp_name'];
			}
		}
	
		$Inst = new WCWH_Listing();

		$cols = [
			'num' => '',
			'image' => 'Image',
			'name' => 'Item',
			'_uom_code' => 'UOM',
			'_sku' => 'SKU',
			//'code' => 'Code',
			//'serial' => 'Barcode',
			//'grp' => 'Group',
			//'cat' => 'Category',
			//'prt_name' => 'Base Item',
			'created_at' => 'Created',
		];

		echo $Inst->get_listing( $cols, $products, [], [], ['pagination'=>false, 'list_only'=>true]);
	}
	//-------- 7/9/22 jeff DashboardWid -----//
	
	/*public function task_schedule_widget(){
		$now_date = date('Y-m-d', time());
		$status = '6';
		$args = [];
		// $checklist = 'non-check';
		// $metas = ['doc_time', 'run_date', 'next_date'];
		$filters = [ 'from_date' => $now_date,'status' => $status];
		$orders = [ 'a.doc_date' => 'ASC' ];
		// $args = ['usage'=>1, 'parent'=>1, 'group'=>1, 'category'=>1];
		$tasks = apply_filters('wcwh_get_task_schedule', $filters, $orders, false, $args);
		// var_dump($tasks);
		if($tasks){
			foreach($tasks as $key => $sch)
			{
				$task[$key]['num'] = $key + 1;
				$task[$key]['warehouse_id'] = $sch['warehouse_id'];
				$task[$key]['docno'] = $sch['docno'];
				$task[$key]['_serial2'] = $sch['_serial2'];
				$task[$key]['doc_date'] = $sch['doc_date'];
				$task[$key]['remark'] = $sch['remark'];
			}
		}
	
		$Inst = new WCWH_Listing();

		$cols = [
			'num' => '',
			'warehouse_id' => 'Warehouse',
			'docno' => 'DocNo.',
			'_serial2' => 'Task',
			'doc_date' => 'Action Date',
			'remark' => 'Remark',
		];

		echo $Inst->get_listing( $cols, $task, [], [], ['pagination'=>false, 'list_only'=>true]);
	}*/
	
	public function get_template_path( $tpl_file )
	{
		$template_name  = $tpl_file;
		$default_path   = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/view/';

		// Look within passed path within the theme - this is priority
		$template = locate_template(
			array(
				$default_path . $template_name,
				'woocommerce-warehouse/'.$template_name
			)
		);

		// Get default template
		if ( ! $template )
			$template = $default_path . $template_name;
		
		return $template;
	}

	public function get_template( $tpl_file, $args = array() )
	{
		include( WCWH_Function::get_template_path( $tpl_file ) );
	}

	public function templating( $tpl_file, $id = "", $args = array() )
	{
		echo '<script type="text/template" id="'.$id.'TPL" class="hidden_tpl">';
		include( WCWH_Function::get_template_path( $tpl_file ) );
		echo '</script>';
	}

	public function get_template_content( $tpl_file, $args = array() )
	{
		ob_start();
		include( WCWH_Function::get_template_path( $tpl_file ) );
		return ob_get_clean();
	}
	
	public function data_sanitizing( $datas )
	{
		if( is_array( $datas ) )
		{
			$datas = array_filter( $datas, function( $val ){ return ( $val !== null ); } );
			foreach( $datas as $key => $data )
			{
				$datas[$key] = self::data_sanitizing( $data );
			}
			return $datas;
		}
		else
		{
			return _sanitize_text_fields( htmlspecialchars( stripslashes( $datas ) ), true );
		}
	}

	public function get_setting( $category = "", $field = "", $seller = 0, $key = 'wcwh_option' ) 
	{
		$_option = get_option( $key, array() );

		if( $seller )
		{
			global $wpdb;
			$dbname = get_warehouse_meta( $seller, 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";

			$cond = $wpdb->prepare( "AND option_name = %s ", $key );
			$sql = "SELECT option_value FROM {$dbname}{$wpdb->prefix}options WHERE 1 {$cond} ";

			if( ! empty( $dbname ) )
			{
				$_option = $wpdb->get_var( $sql );
				if( $_option ) $_option = maybe_unserialize( $_option );
			}
		}

		if( !empty( $category ) )
		{
			$_option = $_option[$category];

			if( !empty( $field ) )
				$_option = $_option[$field];
		}

		return $_option;
	}

	public function get_i18n( $name = "doc_types" )
	{
		return include( WCWH_DIR."/i18n/{$name}.php" );
	}

	public function get_status( $val, $by = "action" )	//by: action, key
	{
		$refs = $this->refs;

		$statuses = $refs['action_statuses'];

		foreach( $statuses as $stat => $row )
		{
			switch( $by )
			{
				case 'key':
					if( $row['key'] == $val ) return $stat;
				break;
				case 'action':
				default:
					if( $row['action'] == $val ) return $stat;
				break;
			}
		}
	}

	public function generate_token( $key = "" )	//key = user_id+form_id
	{	
		global $wcwh;
		$token = wp_create_nonce( $wcwh->appid.$key );

		return $token;
	}

	public function verify_token( $token = "", $key = "" )
	{
		if( ! $token || ! $key ) return false;
		global $wcwh;

		return wp_verify_nonce( $token, $wcwh->appid.$key );
	}
	
	public function get_user_ip()
	{
		$ip = "";

		if ( !empty( $_SERVER["HTTP_CLIENT_IP"] ) )
		{
			//check for ip from share internet
			$ip = $_SERVER["HTTP_CLIENT_IP"];
		}
		elseif ( !empty( $_SERVER["HTTP_X_FORWARDED_FOR"] ) )
		{
			// Check for the Proxy User
			$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
		}
		else
		{
			$ip = $_SERVER["REMOTE_ADDR"];
		}

		return $ip;
	}

	public function get_user_role()
	{
		if( is_user_logged_in() ) 
		{
		 	$user = wp_get_current_user();
		 	$roles = ( array ) $user->roles;

		 	return $roles;
		}

		return array();
	}
	
	public function get_countries()
	{
		if ( class_exists( 'WooCommerce' ) )
		{
			$inst = new WC_Countries();
			return $inst->__get('countries');
		}
		else
			return array();
	}
	
	public function get_country( $country )
	{
		if ( class_exists( 'WooCommerce' ) )
		{
			$inst = new WC_Countries();
			$countries = $inst->__get('countries');
			return $countries[$country];
		}
		else
			return false;
	}

	public function get_countries_states()
	{
		if ( class_exists( 'WooCommerce' ) )
			return include WC()->plugin_path() . '/i18n/states.php';
		else
			return array();
	}
	
	public function get_states( $country = "MY" )
	{
		if ( class_exists( 'WooCommerce' ) )
			return WC()->countries->get_states( $country );
		else
			return array();
	}
	
	public function get_state( $state, $country = "MY" )
	{
		if ( class_exists( 'WooCommerce' ) )
		{
			$states = WC()->countries->get_states( $country );
			return $states[$state];
		}
		else
			return false;
	}

	public function get_address_formats() 
	{
		$address_formats = apply_filters(
			'wcwh_localisation_address_formats',
			array(
				'default' => "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}\n{postcode}\n{country}",
				'AU'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city} {state} {postcode}\n{country}",
				'AT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'BE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'CA'      => "{company}\n{name}\n{address_1}\n{address_2}\n{city} {state_code} {postcode}\n{country}",
				'CH'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'CL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{state}\n{postcode} {city}\n{country}",
				'CN'      => "{country} {postcode}\n{state}, {city}, {address_2}, {address_1}\n{company}\n{name}",
				'CZ'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'DE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'EE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'FI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'DK'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'FR'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city_upper}\n{country}",
				'HK'      => "{company}\n{first_name} {last_name_upper}\n{address_1}\n{address_2}\n{city_upper}\n{state_upper}\n{country}",
				'HU'      => "{last_name} {first_name}\n{company}\n{city}\n{address_1}\n{address_2}\n{postcode}\n{country}",
				'IN'      => "{company}\n{name}\n{address_1}\n{address_2}\n{city} {postcode}\n{state}, {country}",
				'IS'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'IT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode}\n{city}\n{state_upper}\n{country}",
				'JP'      => "{postcode}\n{state} {city} {address_1}\n{address_2}\n{company}\n{last_name} {first_name}\n{country}",
				'TW'      => "{company}\n{last_name} {first_name}\n{address_1}\n{address_2}\n{state}, {city} {postcode}\n{country}",
				'LI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'NL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'NZ'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city} {postcode}\n{country}",
				'NO'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'PL'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'PR'      => "{company}\n{name}\n{address_1} {address_2}\n{state} \n{country} {postcode}",
				'PT'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'SK'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'RS'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'SI'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'ES'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city}\n{state}\n{country}",
				'SE'      => "{company}\n{name}\n{address_1}\n{address_2}\n{postcode} {city}\n{country}",
				'TR'      => "{name}\n{company}\n{address_1}\n{address_2}\n{postcode} {city} {state}\n{country}",
				'UG'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city}\n{state}, {country}",
				'US'      => "{name}\n{company}\n{address_1}\n{address_2}\n{city}, {state_code} {postcode}\n{country}",
				'VN'      => "{name}\n{company}\n{address_1}\n{city}\n{country}",
				'MY' 	  => "{company}\n{address_1}\n{address_2}\n{postcode} {city} {state}\n{country_upper}\n\n{name} {phone}",
			)
		);

		return $address_formats;
	}

	public function get_formatted_address( $args = array(), $separator = '', $custom_format = '' ) 
	{
		$default_args = array(
			'first_name' => '',
			'last_name'  => '',
			'company'    => '',
			'address_1'  => '',
			'address_2'  => '',
			'city'       => '',
			'state'      => '',
			'postcode'   => '',
			'country'    => '',
			'phone'		 => '',
		);

		$countries = $this->get_countries();
		$default = wc_get_base_location();
		$base_country = $default['country'];

		$args    = array_map( 'trim', wp_parse_args( $args, $default_args ) );
		$state   = $args['state'];
		$country = $args['country'];

		$states = $this->get_countries_states();
		
		// Get all formats.
		$formats = $this->get_address_formats();

		// Get format for the address' country.
		$format = ( $country && isset( $formats[ $country ] ) ) ? $formats[ $country ] : $formats['default'];
		$format = ( $custom_format )? $custom_format : $format;

		// Handle full country name.
		$full_country = ( isset( $countries[ $country ] ) ) ? $countries[ $country ] : $country;

		// Country is not needed if the same as base.
		if ( $country === $base_country ) {
			$format = str_replace( '{country}', '', $format );
		}

		// Handle full state name.
		$full_state = ( $country && $state && isset( $states[ $country ][ $state ] ) ) ? $states[ $country ][ $state ] : $state;

		// Substitute address parts into the string.
		$replace = array_map(
			'esc_html',
			apply_filters(
				'wcwh_formatted_address_replacements',
				array(
					'{first_name}'       => $args['first_name'],
					'{last_name}'        => $args['last_name'],
					'{name}'             => $args['first_name'] . ' ' . $args['last_name'],
					'{company}'          => $args['company'],
					'{address_1}'        => $args['address_1'],
					'{address_2}'        => $args['address_2'],
					'{city}'             => $args['city'],
					'{state}'            => $full_state,
					'{postcode}'         => $args['postcode'],
					'{country}'          => $full_country,
					'{first_name_upper}' => wc_strtoupper( $args['first_name'] ),
					'{last_name_upper}'  => wc_strtoupper( $args['last_name'] ),
					'{name_upper}'       => wc_strtoupper( $args['first_name'] . ' ' . $args['last_name'] ),
					'{company_upper}'    => wc_strtoupper( $args['company'] ),
					'{address_1_upper}'  => wc_strtoupper( $args['address_1'] ),
					'{address_2_upper}'  => wc_strtoupper( $args['address_2'] ),
					'{city_upper}'       => wc_strtoupper( $args['city'] ),
					'{state_upper}'      => wc_strtoupper( $full_state ),
					'{state_code}'       => wc_strtoupper( $state ),
					'{postcode_upper}'   => wc_strtoupper( $args['postcode'] ),
					'{country_upper}'    => wc_strtoupper( $full_country ),
					'{phone}'			 => $args['phone'],
				),
				$args
			)
		);

		$formatted_address = str_replace( array_keys( $replace ), $replace, $format );

		// Clean up white space.
		$formatted_address = preg_replace( '/  +/', ' ', trim( $formatted_address ) );
		$formatted_address = preg_replace( '/\n\n+/', "\n", $formatted_address );

		// Break newlines apart and remove empty lines/trim commas and white space.
		$formatted_address = array_filter( array_map( array( $this, 'trim_formatted_address_line' ), explode( "\n", $formatted_address ) ) );

		// Add html breaks.
		$separator = ( $separator )? $separator : '<br/>';
		$formatted_address = implode( $separator, $formatted_address );
		
		return $formatted_address;
	}

	private function trim_formatted_address_line( $line ) {
		return trim( $line, ', ' );
	}

	public function cron_schedules()
	{
		return array(
			'minutely' => array(
	        	'interval' => 60,
	            'display'  => __( 'Minutely' ),
	        ),
	        'fifthly' => array(
	        	'interval' => 300,
	            'display'  => __( 'Five Minutely' ),
	        ),
	        'tenthly' => array(
	        	'interval' => 600,
	            'display'  => __( 'Ten Minutely' ),
	        ),
	        'quadhourly' => array(
	        	'interval' => 900,
	            'display'  => __( 'Quad Hourly' ),
	        ),
	        'twicehourly' => array(
	            'interval' => 1800,
	            'display'  => __( 'Twice Hourly' ),
	        ),
	        'daily' => array(
	        	'interval' => 86400,
	            'display'  => __( 'Daily' ),
	        ),
	    );
	}

	/**
	 *	General functions
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function modules_functions()
	{
		add_filter( 'wcwh_get_company', array( $this, 'get_company' ), 10, 6 );

		add_filter( 'wcwh_get_warehouse', array( $this, 'get_warehouse' ), 10, 6 );

		add_filter( 'wcwh_get_brand', array( $this, 'get_brand' ), 10, 6 );

		add_filter( 'wcwh_get_supplier', array( $this, 'get_supplier' ), 10, 6 );

		add_filter( 'wcwh_get_client', array( $this, 'get_client' ), 10, 6 );

		add_filter( 'wcwh_get_asset', array( $this, 'get_asset' ), 10, 6 );

		add_filter( 'wcwh_get_vending_machine', array( $this, 'get_vending_machine' ), 10, 6 );

		add_filter( 'wcwh_get_item_by_pos', array( $this, 'get_item_by_pos' ), 10, 1 );
		add_filter( 'wcwh_get_product_by_item', array( $this, 'get_product_by_item' ), 10, 3 );
		add_filter( 'wcwh_get_item', array( $this, 'get_item' ), 10, 6 );
		add_filter( 'wcwh_get_item_tree', array( $this, 'get_item_tree' ), 10, 2 );
		add_filter( 'wcwh_item_uom_conversion', array( $this, 'item_uom_conversion' ), 10, 5 );
		add_filter( 'wcwh_get_item_group', array( $this, 'get_item_group' ), 10, 6 );
		add_filter( 'wcwh_get_store_type', array( $this, 'get_store_type' ), 10, 6 );
		add_filter( 'wcwh_get_item_category', array( $this, 'get_item_category' ), 10, 6 );
		add_filter( 'wcwh_get_uom', array( $this, 'get_uom' ), 10, 6 );
		add_filter( 'wcwh_get_reprocess_item', array( $this, 'get_reprocess_item' ), 10, 6 );
		add_filter( 'wcwh_get_order_type', array( $this, 'get_order_type' ), 10, 6 );

		add_filter( 'wcwh_get_itemize', array( $this, 'get_itemize' ), 10, 6 );
		add_filter( 'wcwh_count_available_itemize', array( $this, 'count_available_itemize' ), 10, 2 );
		add_filter( 'wcwh_itemize_handler', array( $this, 'itemize_handler' ), 10, 4 );

		add_filter( 'wcwh_get_price_doc', array( $this, 'get_price_doc' ), 10, 6 );
		add_filter( 'wcwh_get_price_ref', array( $this, 'get_price_ref' ), 10, 6 );
		add_filter( 'wcwh_get_price', array( $this, 'get_price' ), 10, 4 );
		add_filter( 'wcwh_get_latest_price', array( $this, 'get_latest_price' ), 10, 6 );

		add_filter( 'wcwh_get_promo_header', array( $this, 'get_promo_header' ), 10, 6 );

		add_filter( 'wcwh_get_latest_purchase_price', array( $this, 'get_latest_purchase_price' ), 10, 6 );

		add_filter( 'wcwh_get_margining', array( $this, 'get_margining' ), 10, 6 );

		add_filter( 'wcwh_get_inventory', array( $this, 'get_inventory' ), 10, 6 );
		add_filter( 'wcwh_get_stocks', array( $this, 'get_stocks' ), 10, 6 );

		add_filter( 'wcwh_get_customer_all', array( $this, 'get_customer_all' ), 10, 6 );
		add_filter( 'wcwh_get_customer', array( $this, 'get_customer' ), 10, 6 );
		add_filter( 'wcwh_get_customer_group', array( $this, 'get_customer_group' ), 10, 6 );
		add_filter( 'wcwh_get_customer_job', array( $this, 'get_customer_job' ), 10, 6 );
		add_filter( 'wcwh_get_origin_group', array( $this, 'get_origin_group' ), 10, 6 );
		add_filter( 'wcwh_get_account_type', array( $this, 'get_account_type' ), 10, 6 );

		add_filter( 'wcwh_update_customer_count', array( $this, 'update_customer_count' ), 10, 5 );

		add_filter( 'wcwh_get_credit_term', array( $this, 'get_credit_term' ), 10, 6 );
		add_filter( 'wcwh_get_payment_term', array( $this, 'get_payment_term' ), 10, 6 );
		add_filter( 'wcwh_get_payment_method', array( $this, 'get_payment_method' ), 10, 6 );

		add_filter( 'wcwh_get_membership', array( $this, 'get_membership' ), 10, 6 );

		//Bank In Service
		add_filter( 'wcwh_get_exchange_rate', array( $this, 'get_exchange_rate' ), 10, 6 );
		add_filter( 'wcwh_get_latest_exchange_rate', array( $this, 'get_latest_exchange_rate' ), 10, 6 );
		add_filter( 'wcwh_get_service_charge', array( $this, 'get_service_charge' ), 10, 6 );
		add_filter( 'wcwh_get_bankin_info', array( $this, 'get_bankin_info' ), 10, 6 );
		add_filter( 'wcwh_get_bankin_service', array( $this, 'get_bankin_service' ), 10, 6 );

		add_filter( 'wcwh_get_storage', array( $this, 'get_storage' ), 10, 6 );

		add_filter( 'wcwh_doc_stage', array( $this, 'doc_stage' ), 10, 2 );
		add_filter( 'wcwh_get_doc_stage', array( $this, 'get_doc_stage' ), 10, 6 );
		add_filter( 'wcwh_get_doc_stage_detail', array( $this, 'get_doc_stage_detail' ), 10, 6 );

		add_filter( 'wcwh_todo_arrangement', array( $this, 'todo_arrangement' ), 10, 3 );
		add_filter( 'wcwh_todo_external_action', array( $this, 'todo_external_action' ), 10, 4 );

		add_filter( 'wcwh_get_stockout', array( $this, 'get_stockout' ), 10, 6 );
		add_filter( 'wcwh_get_section', array( $this, 'get_section' ), 10, 6 );

		add_filter( 'wcwh_get_arrangement', array( $this, 'get_arrangement' ), 10, 6 );
		add_filter( 'wcwh_get_todo_action', array( $this, 'get_todo_action' ), 10, 6 );
		add_filter( 'wcwh_get_todo', array( $this, 'get_todo' ), 10, 6 );

		add_filter( 'wcwh_sync_arrangement', array( $this, 'sync_arrangement' ), 10, 6 );
		add_filter( 'wcwh_einv_arrangement', array( $this, 'einv_arrangement' ), 10, 8 );
		add_filter( 'wcwh_api_request', array( $this, 'api_request' ), 10, 6 );
		add_filter( 'wcwh_get_sync', array( $this, 'get_sync' ), 10, 6 );
		add_filter( 'wcwh_sync_handler', array( $this, 'sync_handler' ), 10, 4 );
		
		add_filter( 'wcwh_get_running_no', array( $this, 'get_running_no' ), 10, 6 );
		add_filter( 'warehouse_generate_docno', array( $this, 'generate_docno' ), 10, 4 );
		add_filter( 'warehouse_renew_docno', array( $this, 'renew_docno' ), 10, 3 );

		add_filter( 'wcwh_get_system_storage', array( $this, 'get_system_storage' ), 10, 3 );

		add_filter( 'wcwh_get_suitable_template', array( $this, 'get_suitable_template' ), 10, 2 );

		add_filter( 'wcwh_get_doc_header', array( $this, 'get_doc_header' ), 10, 6 );

		add_filter( 'wcwh_get_doc_detail', array( $this, 'get_doc_detail' ), 10, 6 );

		add_filter( 'wcwh_pos_transaction', array( $this, 'pos_transaction' ), 10, 3 );

		add_filter( 'wcwh_log_activity', array( $this, 'log_activity' ), 10, 2 );
		add_filter( 'wcwh_log_mail', array( $this, 'log_mail' ), 10, 2 );

		add_filter( 'wcwh_get_pos_register', array( $this, 'get_pos_register' ), 10, 2 );

		add_filter( 'wcwh_tool_request_completion', array( $this, 'tool_request_completion' ), 10, 2 );
		add_filter( 'wcwh_parts_request_completion', array( $this, 'parts_request_completion' ), 10, 2 );

		//Task Schedule
		add_filter( 'wcwh_get_task_schedule', array( $this, 'get_task_schedule' ), 10, 6 );
	}

	public function get_company( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Company_Class" ) ) require_once( WCWH_DIR . "/includes/classes/company.php" );

		$Inst = new WCWH_Company_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_warehouse( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Warehouse_Class" ) ) require_once( WCWH_DIR . "/includes/classes/warehouses.php" );

		$Inst = new WCWH_Warehouse_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_brand( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Brand_Class" ) ) require_once( WCWH_DIR . "/includes/classes/brand.php" );

		$Inst = new WCWH_Brand_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_supplier( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Supplier_Class" ) ) require_once( WCWH_DIR . "/includes/classes/supplier.php" );

		$Inst = new WCWH_Supplier_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_client( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Client_Class" ) ) require_once( WCWH_DIR . "/includes/classes/client.php" );

		$Inst = new WCWH_Client_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_asset( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Asset_Class" ) ) require_once( WCWH_DIR . "/includes/classes/asset.php" );

		$Inst = new WCWH_Asset_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_vending_machine( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_VendingMachine_Class" ) ) require_once( WCWH_DIR . "/includes/classes/vending-machine.php" );

		$Inst = new WCWH_VendingMachine_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_item_by_pos( $id = 0 )
	{
		if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );

		$Inst = new WCWH_Item_Class();

		return $Inst->get_item_by_pos( $id );
	}

	public function get_product_by_item( $item_id = 0, $single = true, $args = array() )
	{
		if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );

		$Inst = new WCWH_Item_Class();

		return $Inst->get_product( $item_id, $single, $args );
	}

	public function get_item( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );

		$Inst = new WCWH_Item_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_item_tree( $id = 0, $exclude_self = false )
	{
		if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );

		$Inst = new WCWH_Item_Class();

		return $Inst->get_all_parent_child( $id, $exclude_self );
	}

	public function item_uom_conversion( $id = 0, $amt = 0, $to_id = 0, $type= 'qty', $args = array() )
	{
		if ( !class_exists( "WCWH_Item_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item.php" );

		$Inst = new WCWH_Item_Class();

		return $Inst->uom_conversion( $id, $amt, $to_id, $type, $args );
	}

	public function get_item_group( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ItemGroup_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item-group.php" );

		$Inst = new WCWH_ItemGroup_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_store_type( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_StoreType_Class" ) ) require_once( WCWH_DIR . "/includes/classes/store-type.php" );

		$Inst = new WCWH_StoreType_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_item_category( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ItemCategory_Class" ) ) require_once( WCWH_DIR . "/includes/classes/item-category.php" );

		$Inst = new WCWH_ItemCategory_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_uom( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_UOM_Class" ) ) require_once( WCWH_DIR . "/includes/classes/uom.php" );

		$Inst = new WCWH_UOM_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_reprocess_item( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ReprocessItem_Class" ) ) require_once( WCWH_DIR . "/includes/classes/reprocess-item.php" );

		$Inst = new WCWH_ReprocessItem_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_order_type( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_OrderType_Class" ) ) require_once( WCWH_DIR . "/includes/classes/order-type.php" );

		$Inst = new WCWH_OrderType_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_itemize( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Itemize_Class" ) ) require_once( WCWH_DIR . "/includes/classes/itemize.php" );

		$Inst = new WCWH_Itemize_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function count_available_itemize( $id = 0, $args = [] )
	{
		if ( !class_exists( "WCWH_Itemize_Class" ) ) require_once( WCWH_DIR . "/includes/classes/itemize.php" );

		$Inst = new WCWH_Itemize_Class();

		return $Inst->count_available( $id, $args );
	}

	public function itemize_handler( $action, $datas = array(), $obj = array(), $transact = false )
	{
		if ( !class_exists( "WCWH_Itemize_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/itemizeCtrl.php" );

		$Inst = new WCWH_Itemize_Controller();

		return $Inst->action_handler( $action, $datas, $obj, $transact );
	}

	public function get_price_doc( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );

		$Inst = new WCWH_Pricing();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_price_ref( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_PriceRef" ) ) require_once( WCWH_DIR . "/includes/classes/price-ref.php" );

		$Inst = new WCWH_PriceRef();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_price( $prdt_id = 0, $seller = '', $schemes = array(), $datetime = '' )
	{
		if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );

		$Inst = new WCWH_Pricing();

		return $Inst->get_price( $prdt_id, $seller, $schemes, $datetime );
	}

	public function get_latest_price( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Pricing" ) ) require_once( WCWH_DIR . "/includes/classes/pricing.php" );

		$Inst = new WCWH_Pricing();

		return $Inst->get_latest_price( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_latest_purchase_price( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_PurchasePricing" ) ) require_once( WCWH_DIR . "/includes/classes/purchase-pricing.php" );

		$Inst = new WCWH_PurchasePricing();

		return $Inst->get_latest_price( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_promo_header( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_PromoHeader" ) ) require_once( WCWH_DIR . "/includes/classes/promo-header.php" );

		$Inst = new WCWH_PromoHeader();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_margining( $wh_id = '', $margining_id = '', $client = [], $date = '', $type = 'def', $sap_po = false )
	{
		if ( !class_exists( "WCWH_Margining" ) ) require_once( WCWH_DIR . "/includes/classes/margining.php" );

		$Inst = new WCWH_Margining();

		return $Inst->get_margining( $wh_id, $margining_id, $client, $date, $type, $sap_po );
	}

	public function get_inventory( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Inventory_Class" ) ) require_once( WCWH_DIR . "/includes/classes/inventory.php" );

		$Inst = new WCWH_Inventory_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_stocks( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Inventory_Class" ) ) require_once( WCWH_DIR . "/includes/classes/inventory.php" );

		$Inst = new WCWH_Inventory_Class();

		return $Inst->get_inventory( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_customer_all( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Customer_Class" ) ) require_once( WCWH_DIR . "/includes/classes/customer.php" );

		$Inst = new WCWH_Customer_Class();

		return $Inst->get_customer_all( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_customer( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Customer_Class" ) ) require_once( WCWH_DIR . "/includes/classes/customer.php" );

		$Inst = new WCWH_Customer_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_customer_group( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_CustomerGroup_Class" ) ) require_once( WCWH_DIR . "/includes/classes/customer-group.php" );

		$Inst = new WCWH_CustomerGroup_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_customer_job( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_CustomerJob_Class" ) ) require_once( WCWH_DIR . "/includes/classes/customer-job.php" );

		$Inst = new WCWH_CustomerJob_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_origin_group( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_OriginGroup_Class" ) ) require_once( WCWH_DIR . "/includes/classes/origin-group.php" );

		$Inst = new WCWH_OriginGroup_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_account_type( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_AccountType_Class" ) ) require_once( WCWH_DIR . "/includes/classes/account-type.php" );

		$Inst = new WCWH_AccountType_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function update_customer_count( $user_id = 0, $serial = '', $total = 0, $credit = 0, $plus = '+' )
	{
		if ( !class_exists( "WCWH_Customer_Class" ) ) require_once( WCWH_DIR . "/includes/classes/customer.php" );

		$Inst = new WCWH_Customer_Class();

		return $Inst->update_customer_count( $user_id, $serial, $total, $credit, $plus );
	}

	public function get_credit_term( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_CreditTerm_Class" ) ) require_once( WCWH_DIR . "/includes/classes/credit-term.php" );

		$Inst = new WCWH_CreditTerm_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_payment_term( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_PaymentTerm_Class" ) ) require_once( WCWH_DIR . "/includes/classes/payment-term.php" );

		$Inst = new WCWH_PaymentTerm_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_payment_method( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_PaymentMethod_Class" ) ) require_once( WCWH_DIR . "/includes/classes/payment-method.php" );

		$Inst = new WCWH_PaymentMethod_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_membership( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Membership_Class" ) ) require_once( WCWH_DIR . "/includes/classes/membership.php" );

		$Inst = new WCWH_Membership_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	//Bank In Service
	public function get_service_charge( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ServiceCharge_Class" ) ) require_once( WCWH_DIR . "/includes/classes/servicecharge.php" );

		$Inst = new WCWH_ServiceCharge_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}
	public function get_exchange_rate( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ExchangeRate_Class" ) ) require_once( WCWH_DIR . "/includes/classes/exchange-rate.php" );

		$Inst = new WCWH_ExchangeRate_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}
	public function get_latest_exchange_rate( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_ExchangeRate_Class" ) ) require_once( WCWH_DIR . "/includes/classes/exchange-rate.php" );

		$Inst = new WCWH_ExchangeRate_Class();

		return $Inst->get_latest_exchange_rate( $filters, $order, $single, $args, $group, $limit );
	}
	public function get_bankin_info( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_BankInInfo_Class" ) ) require_once( WCWH_DIR . "/includes/classes/bankininfo.php" );

		$Inst = new WCWH_BankInInfo_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_bankin_service( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_BankInService_Class" ) ) require_once( WCWH_DIR . "/includes/classes/bankinservice.php" );

		$Inst = new WCWH_BankInService_Class();

		return $Inst->get_header( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_storage( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Storage_Class" ) ) require_once( WCWH_DIR . "/includes/classes/storage.php" );

		$Inst = new WCWH_Storage_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function doc_stage( $action = 'save', $datas = array() )
	{
		if( ! $datas ) return false;

		if ( !class_exists( "WCWH_Stage_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/stageCtrl.php" );

		$Inst = new WCWH_Stage_Controller();

		$result = $Inst->action_handler( $action, $datas, $datas, false );
		if( $result['succ'] )
			return $result['id'];

		return false;
	}

	public function get_doc_stage( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Stage" ) ) require_once( WCWH_DIR . "/includes/classes/stage.php" );

		$Inst = new WCWH_Stage();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_doc_stage_detail( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_StageDetail" ) ) require_once( WCWH_DIR . "/includes/classes/stage-detail.php" );

		$Inst = new WCWH_StageDetail();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function todo_arrangement( $ref_id = 0, $section_id = "", $action = 'save' )
	{
		if( ! $ref_id || ! $section_id ) return false;

		if ( !class_exists( "WCWH_TODO_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/todoCtrl.php" );

		$Inst = new WCWH_TODO_Controller();

		return $Inst->todo_arrangement( $ref_id, $section_id, $action );
	}

	public function todo_external_action( $ref_id = 0, $section_id = "", $action = "", $remark = "" )
	{
		if( ! $ref_id || ! $section_id ) return false;

		if ( !class_exists( "WCWH_TODO_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/todoCtrl.php" );

		$Inst = new WCWH_TODO_Controller();

		return $Inst->todo_external_action( $ref_id, $section_id, $action, $remark );
	}

	public function get_stockout( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Stockout_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stockout.php" );

		$Inst = new WCWH_Stockout_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	//Task Schedule
	public function get_task_schedule( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_TaskSchedule_Class" ) ) require_once( WCWH_DIR . "/includes/classes/task-schedule.php" );

		$Inst = new WCWH_TaskSchedule_Class();

		return $Inst->get_schedule(  $filters, $order, $single, $args, $group, $limit );
	}

	public function get_section( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Section_Class" ) ) require_once( WCWH_DIR . "/includes/classes/section.php" );

		$Inst = new WCWH_Section_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_arrangement( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_TodoArrangement_Class" ) ) require_once( WCWH_DIR . "/includes/classes/todo-arrangement.php" );

		$Inst = new WCWH_TodoArrangement_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_todo_action( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_TodoAction_Class" ) ) require_once( WCWH_DIR . "/includes/classes/todo-action.php" );

		$Inst = new WCWH_TodoAction_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_todo( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_TODO_Class" ) ) require_once( WCWH_DIR . "/includes/classes/todo.php" );

		$Inst = new WCWH_TODO_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function sync_arrangement( $ref_id = 0, $section_id = "", $action = '', $ref = '', $wh = '', $data = [] )
	{
		if( ! $ref_id || ! $section_id ) return false;

		if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );

		$Inst = new WCWH_SYNC_Controller();

		return $Inst->sync_arrangement( $ref_id, $section_id, $action, $ref, $wh, $data );
	}

	public function einv_arrangement( $ref_id = 0, $section_id = "", $action = '', $ref = '', $wh = '', $remote_url = '', $data = [], $direct = false )
	{
		if( ! $ref_id || ! $section_id ) return false;

		if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );

		$Inst = new WCWH_SYNC_Controller();

		return $Inst->einv_arrangement( $ref_id, $section_id, $action, $ref, $wh, $remote_url, $data, $direct );
	}

	public function api_request( $action = '', $ref_id = 0, $target = '', $section = '', $ref_data = [], $remote_url = '' )
	{
		if( ! $action || ! $ref_id ) return false;

		if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );

		$Inst = new WCWH_SYNC_Controller();

		return $Inst->api_request( $action, $ref_id, $target, $section, $ref_data, $remote_url );
	}

	public function get_sync( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_SYNC_Class" ) ) require_once( WCWH_DIR . "/includes/classes/sync.php" );

		$Inst = new WCWH_SYNC_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function sync_handler( $action = '', $datas = array(), $metas = array(), $obj = array() )
	{
		if ( !class_exists( "WCWH_SYNC_Class" ) ) require_once( WCWH_DIR . "/includes/classes/sync.php" );

		$Inst = new WCWH_SYNC_Class();

		return $Inst->action_handler( $action, $datas, $metas, $obj );
	}

	public function get_running_no( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_RunningNo_Class" ) ) require_once( WCWH_DIR . "/includes/classes/running-no.php" );

		$Inst = new WCWH_RunningNo_Class();

		return $Inst->get_infos( $filters, $order, $single, $args, $group, $limit );
	}

	public function generate_docno( $sdocno = '', $doc_type = '', $def_prefix = '', $args = array() )
	{	
		if ( !class_exists( "WCWH_RunningNo_Class" ) ) require_once( WCWH_DIR . "/includes/classes/running-no.php" );

		$Inst = new WCWH_RunningNo_Class();
		$sdocno = $Inst->generate_docno( $sdocno, $doc_type, $def_prefix, $args );

		return apply_filters( 'warehouse_after_generate_docno', $sdocno );
	}

	public function renew_docno( $sdocno = '', $doc_type = '', $args = array() )
	{	
		if ( !class_exists( "WCWH_RunningNo_Class" ) ) require_once( WCWH_DIR . "/includes/classes/running-no.php" );

		$Inst = new WCWH_RunningNo_Class();
		$sdocno = $Inst->renew_docno( $sdocno, $doc_type, $args );

		return apply_filters( 'warehouse_after_renew_docno', $sdocno );
	}

	public function get_system_storage( $strg_id = 0, $header = array(), $detail = array() )
	{
		if ( !class_exists( "WCWH_Storage_Class" ) ) require_once( WCWH_DIR . "/includes/classes/storage.php" );

		$Inst = new WCWH_Storage_Class();
		$strg_id = $Inst->get_system_storage( $strg_id, $header, $detail );

		return $strg_id;
	}

	public function get_suitable_template( $tpl_code = "", $args = array() )
	{
		if ( !class_exists( "WCWH_Template_Class" ) ) require_once( WCWH_DIR . "/includes/classes/template.php" );

		$Inst = new WCWH_Template_Class();
		$tpl = $Inst->get_suitable_template( $tpl_code, $args );

		return $tpl;
	}

	public function get_doc_header( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Doc_Class" ) ) require_once( WCWH_DIR . "/includes/classes/doc.php" );

		$Inst = new WCWH_Doc_Class();

		return $Inst->get_header( $filters, $order, $single, $args, $group, $limit );
	}

	public function get_doc_detail( $filters = array(), $order = array(), $single = false, $args = [], $group = [], $limit = [] )
	{
		if ( !class_exists( "WCWH_Doc_Class" ) ) require_once( WCWH_DIR . "/includes/classes/doc.php" );

		$Inst = new WCWH_Doc_Class();

		return $Inst->get_detail( $filters, $order, $single, $args, $group, $limit );
	}

	public function pos_transaction( $action = "save", $datas = array(), $obj = array() )
	{
		if ( !class_exists( "WCWH_PosTransact_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/posTransactCtrl.php" );

		$Inst = new WCWH_PosTransact_Controller();
		$outcome = $Inst->action_handler( $action, $datas, $obj );

		return $outcome;
	}

	public function log_activity( $action = 'save', $datas = array() )
	{	
		if( ! $datas ) return false;

		if ( !class_exists( "WCWH_ActivityLog_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/activityLogCtrl.php" );

		$Inst = new WCWH_ActivityLog_Controller();

		$result = $Inst->action_handler( $action, $datas, $datas );
		if( $result['succ'] )
			return $result['id'];

		return false;
	}

	public function log_mail( $action = 'save', $datas = array() )
	{	
		if( ! $datas ) return false;

		if ( !class_exists( "WCWH_MailLog_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/mailLogCtrl.php" );

		$Inst = new WCWH_MailLog_Controller();

		$result = $Inst->action_handler( $action, $datas, $datas );
		if( $result['succ'] )
			return $result['id'];

		return false;
	}

	public function get_pos_register( $id = 0, $seller = 0 )
	{
		if ( !class_exists( "WCWH_PosOrder_Class" ) ) include_once( WCWH_DIR . "/includes/classes/pos-order.php" );

		$Inst = new WCWH_PosOrder_Class();

		$result = $Inst->wc_pos_get_register( $id, $seller );

		return $result;
	}

	public function tool_request_completion( $succ, $datas = [] )
	{
		if ( !class_exists( "WCWH_ToolRequest_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/toolRequestCtrl.php" );

		$Inst = new WCWH_ToolRequest_Controller();

		$result = $Inst->tool_request_completion( $succ, $datas );

		return $result;
	}

	public function parts_request_completion( $succ, $datas = [] )
	{
		if ( !class_exists( "WCWH_PartsRequest_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/partsRequestCtrl.php" );

		$Inst = new WCWH_PartsRequest_Controller();

		$result = $Inst->parts_request_completion( $succ, $datas );

		return $result;
	}
}

new WCWH_Function( $refs );

}


#-----------------------------------------------------------------#
#	>	Current User Cans
#-----------------------------------------------------------------#
if ( ! function_exists( 'current_user_cans' ) ) 
{
	add_filter( 'current_user_cans', 'current_user_cans' );
	function current_user_cans( $caps = array() )
	{
		if( ! $caps || ! is_user_logged_in() ) return false;

		$caps = ( is_array( $caps ) )? $caps : [ $caps ];

		global $current_user, $wcwh;
		if( ! $current_user ) return false;
		
		if( $wcwh->caps && ( count( $current_user->allcaps ) != count( $wcwh->caps ) ) ) $current_user->allcaps = $wcwh->caps;
		
		//$user_id = $current_user->ID;
		$current_user_caps = array_keys( $current_user->allcaps );

		$matches = array_intersect( $caps, $current_user_caps );
		if( ! $matches || empty( $matches ) || count( $matches ) <= 0 ) 
		{
			return false;
		}

		return true;
	}
}


#-----------------------------------------------------------------#
#	>	Section
#-----------------------------------------------------------------#
if ( ! function_exists( 'get_section' ) ) 
{
	function get_section( $section = '', $db_wpdb = array() )
	{
		if( ! $section ) return false;

		global $wpdb, $wcwh;
		$refs = $wcwh->get_plugin_ref();
		if( $refs['sections'] )
		{
			return $refs['sections'][ $section ];
		}

		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		$cond = "";
		$cond.= $wdb->prepare( "AND section_id = %s ", $section );

		$sql = "SELECT * FROM {$wdb->section} WHERE 1 {$cond} ";

		return $wdb->get_row( $sql, ARRAY_A );
	}
}
if ( ! function_exists( 'get_sections' ) ) 
{
	function get_sections( $db_wpdb = array() )
	{
		global $wpdb;
		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		$sql = "SELECT * FROM {$wdb->section} WHERE 1;";

		return $wdb->get_results( $sql, ARRAY_A );
	}
}


#-----------------------------------------------------------------#
#	>	Scheme
#-----------------------------------------------------------------#
if ( ! function_exists( 'get_scheme' ) ) 
{
	function get_scheme( $section = '', $scheme = '', $db_wpdb = array() )
	{
		if( ! $section || ! $scheme ) return false;

		global $wpdb;
		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		$cond = "";
		$cond.= $wdb->prepare( "AND section = %s ", $section );
		$cond.= $wdb->prepare( "AND scheme = %s ", $scheme );

		$sql = "SELECT * FROM {$wdb->scheme} WHERE 1 {$cond} ";

		return $wdb->get_row( $sql, ARRAY_A );
	}
}
if ( ! function_exists( 'get_schemes' ) ) 
{
	function get_schemes( $section = '', $db_wpdb = array() )
	{
		if( ! $section ) return false;

		global $wpdb;
		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		$cond.= $wdb->prepare( "AND section = %s", $section );
		
		$sql  = "SELECT * FROM {$wdb->scheme} WHERE 1 {$cond} ";
		
		return $wdb->get_results( $sql , ARRAY_A );
	}
}


#-----------------------------------------------------------------#
#	>	Document metadata handler
#-----------------------------------------------------------------#
/**
 *	Doc Meta - Add meta.
 */
if ( ! function_exists('add_document_meta') ) 
{
	function add_document_meta( $doc_id = 0, $meta_key = '', $meta_value = '', $item_id = 0 )
	{
		if( !$doc_id || empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		
		$succ = $wpdb->insert( $wpdb->doc_meta, 
			array( 
				'doc_id'	=> $doc_id, 
				'item_id'	=> $item_id,
				'meta_key'	=> $meta_key,
				'meta_value'=> $meta_value
			)
		);
		
		if( $succ )
			return $wpdb->insert_id;
		else
			return false;
	}
}
/**
 *	Doc Meta - Update meta.
 */
if ( ! function_exists('update_document_meta') ) 
{
	function update_document_meta( $doc_id = 0, $meta_key = '', $meta_value = '', $item_id = 0 )
	{
		if( !$doc_id || empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		
		$item_id = ( ! $item_id )? 0 : $item_id;
		$exists = get_document_meta( $doc_id, $meta_key, $item_id, true );
		
		if( $exists )
		{
			$cond = array( 'doc_id' => $doc_id, 'meta_key' => $meta_key, 'item_id' => $item_id );
			$update = $wpdb->update( $wpdb->doc_meta, array( 'meta_value' => $meta_value ), $cond );
		}
		else
		{
			$update = add_document_meta( $doc_id, $meta_key, $meta_value, $item_id );
		}

		if ( false === $update ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Doc Meta - Delete meta.
 */
if ( ! function_exists('delete_document_meta') ) 
{
	function delete_document_meta( $doc_id = 0, $meta_key = '', $meta_value = '', $item_id = 0 )
	{
		if( !$doc_id )
			return false;
		
		global $wpdb;

		$item_id = ( ! $item_id )? 0 : $item_id;
		$cond = array( 'doc_id' => $doc_id, 'item_id' => $item_id );
		if( !empty( $meta_key ) ) $cond['meta_key'] = $meta_key;
		if( !empty( $meta_value ) ) $cond['meta_value'] = $meta_value;
		
		$update = $wpdb->delete( $wpdb->doc_meta, $cond );

		if ( false === $update ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Doc Meta - Get meta.
 */
if ( ! function_exists('get_document_meta') ) 
{
	function get_document_meta( $doc_id = 0, $meta_key = '', $item_id = 0, $single = false )
	{
		if( ! $doc_id ) return false;
		
		global $wpdb;
		
		$cond = $wpdb->prepare( " AND doc_id = %d", $doc_id );
		if( !empty( $meta_key ) ) $cond.= $wpdb->prepare( " AND meta_key = %s", $meta_key );
		
		if( is_numeric( $item_id ) )
			$cond.= $wpdb->prepare( " AND item_id = %d", $item_id );
		else
			$cond.= " AND item_id {$item_id} ";
		
		$sql  = "SELECT * FROM {$wpdb->doc_meta} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$metas = array();
		if( $results )
		{
			foreach( $results as $row )
			{
				if( !empty( $meta_key ) )
				{
					$metas[] = $row['meta_value'];
				}
				else
				{
					$metas[$row['meta_key']][] = $row['meta_value'];
				}
			}
		}
		
		if( !empty( $meta_key ) && $single )
		{
			return $metas[0];
		}

		return $metas;
	}
}


#-----------------------------------------------------------------#
#	>	Items metadata handler
#-----------------------------------------------------------------#
/**
 *	Items Meta - Add meta.
 *
if ( ! function_exists( 'add_item_rel_meta' ) ) 
{
	function add_item_rel_meta( $item_id = 0, $meta_key = '', $meta_value = '', $scheme = 'default', $ref_id = 0 )
	{
		if( !$item_id || empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		$section = 'items_meta_rel';

		$scheme = get_scheme( $section, $scheme );
		$lvl = ( $scheme )? $scheme['scheme_lvl'] : 0;
		
		$succ = $wpdb->insert( $wpdb->items_meta_rel, 
			array( 
				'scheme'		=> ( $scheme )? $scheme : 'default',
				'scheme_lvl'	=> $lvl,
				'ref_id'		=> ( $ref_id )? $ref_id : 0,
				'items_id'		=> $item_id, 
				'meta_key'		=> $meta_key,
				'meta_value'	=> $meta_value
			)
		);
		
		if( $succ )
			return $wpdb->insert_id;
		else
			return false;
	}
}
/**
 *	Items Meta - Update meta.
 *
if ( ! function_exists( 'update_item_rel_meta' ) ) 
{
	function update_item_rel_meta( $item_id = 0, $meta_key = '', $meta_value = '', $scheme = 'default', $ref_id = 0 )
	{
		if( !$item_id || empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		$section = 'items_meta_rel';
		
		$exists = get_item_rel_meta( $item_id, $meta_key, true, $scheme, $ref_id );

		$scheme = ( $scheme )? $scheme : 'default';
		$ref_id = ( $ref_id )? $ref_id : 0;
		
		if( $exists )
		{
			$cond = array( 'items_id' => $item_id, 'meta_key' => $meta_key, 'scheme' => $scheme, 'ref_id' => $ref_id );
			$update = $wpdb->update( $wpdb->items_meta_rel, array( 'meta_value' => $meta_value ), $cond );
		}
		else
		{
			$update = add_document_meta( $item_id, $meta_key, $meta_value, $scheme, $ref_id );
		}

		if ( false === $update ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Items Meta - Delete meta.
 *
if ( ! function_exists( 'delete_item_rel_meta' ) ) 
{
	function delete_item_rel_meta( $item_id = 0, $meta_key = '', $meta_value = '', $scheme = '', $ref_id = -1 )
	{
		if( ! $item_id ) return false;
		
		global $wpdb;
		$section = 'items_meta_rel';

		$cond = array( 'items_id' => $item_id );

		if( !empty( $meta_key ) ) $cond['meta_key'] = $meta_key;

		if( !empty( $meta_value ) ) $cond['meta_value'] = $meta_value;

		if( !empty( $scheme ) ) $cond['scheme'] = $scheme;

		if( $ref_id >= 0 ) $cond['ref_id'] = $ref_id;
		
		$delete = $wpdb->delete( $wpdb->items_meta_rel, $cond );

		if ( false === $delete ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Items Meta - Get meta.
 *
if ( ! function_exists( 'get_item_rel_meta' ) ) 
{
	function get_item_rel_meta( $item_id = 0, $meta_key = '', $single = false, $scheme = '', $ref_id = -1 )
	{
		if( ! $item_id ) return false;
		
		global $wpdb;
		$section = 'items_meta_rel';
		
		$cond = $wpdb->prepare( " AND items_id = %d", $item_id );

		if( !empty( $meta_key ) ) $cond.= $wpdb->prepare( " AND meta_key = %s", $meta_key );

		if( !empty( $scheme ) ) $cond.= $wpdb->prepare( " AND scheme = %s", $scheme );

		if( $ref_id >= 0 ) $cond.= $wpdb->prepare( " AND ref_id = %d", $ref_id );
		
		$sql  = "SELECT * FROM {$wpdb->items_meta_rel} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$metas = array();
		if( $results )
		{
			foreach( $results as $row )
			{
				if( !empty( $meta_key ) )
				{
					$metas[] = $row;
				}
				else
				{
					$metas[$row['meta_key']][] = $row;
				}
			}
		}
		
		if( !empty( $meta_key ) && $single )
		{
			return $metas[0];
		}

		return $metas;
	}
}
*/

#-----------------------------------------------------------------#
#	>	Transaction metadata handler
#-----------------------------------------------------------------#
/**
 *	Transaction Meta - Add meta.
 */
if ( ! function_exists('add_transaction_meta') ) 
{
	function add_transaction_meta( $hid = 0, $meta_key = '', $meta_value = '', $did = 0, $ddid = 0 )
	{
		if( empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		
		$succ = $wpdb->insert( $wpdb->transaction_meta, 
			array( 
				'hid'		=> $hid, 
				'did'		=> $did,
				'ddid'		=> $ddid,
				'meta_key'	=> $meta_key,
				'meta_value'=> $meta_value
			)
		);
		
		if( $succ )
			return $wpdb->insert_id;
		else
			return false;
	}
}
/**
 *	Transaction Meta - Update meta.
 */
if ( ! function_exists('update_transaction_meta') ) 
{
	function update_transaction_meta( $hid = 0, $meta_key = '', $meta_value = '', $did = 0, $ddid = 0 )
	{
		if( empty( $meta_key ) || empty( $meta_value ) )
			return false;
		
		global $wpdb;
		
		$hid = ( ! $hid )? 0 : $hid;
		$did = ( ! $did )? 0 : $did;
		$ddid = ( ! $ddid )? 0 : $ddid;

		$exists = get_transaction_meta( $hid, $meta_key, $did, $ddid, true );
		
		if( $exists )
		{
			$cond = array( 'hid' => $hid, 'meta_key' => $meta_key, 'did' => $did, 'ddid' => $ddid );
			$update = $wpdb->update( $wpdb->transaction_meta, array( 'meta_value' => $meta_value ), $cond );
		}
		else
		{
			$update = add_transaction_meta( $hid, $meta_key, $meta_value, $did, $ddid );
		}

		if ( false === $update ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Transaction Meta - Delete meta.
 */
if ( ! function_exists('delete_transaction_meta') ) 
{
	function delete_transaction_meta( $hid = 0, $meta_key = '', $meta_value = '', $did = 0, $ddid = 0 )
	{
		if( !$doc_id )
			return false;
		
		global $wpdb;

		$hid = ( ! $hid )? 0 : $hid;
		$did = ( ! $did )? 0 : $did;
		$ddid = ( ! $ddid )? 0 : $ddid;

		$cond = array( 'hid' => $hid, 'did' => $did, 'ddid' => $ddid );
		if( !empty( $meta_key ) ) $cond['meta_key'] = $meta_key;
		if( !empty( $meta_value ) ) $cond['meta_value'] = $meta_value;
		
		$update = $wpdb->delete( $wpdb->transaction_meta, $cond );

		if ( false === $update ) 
		{
			return false;
		}
		
		return true;
	}
}
/**
 *	Transaction Meta - Get meta.
 */
if ( ! function_exists('get_transaction_meta') ) 
{
	function get_transaction_meta( $hid = 0, $meta_key = '', $did = 0, $ddid = 0, $single = false )
	{
		global $wpdb;
		
		$cond = $wpdb->prepare( " AND hid = %d", $hid );
		$cond.= $wpdb->prepare( " AND did = %d", $did );
		$cond.= $wpdb->prepare( " AND ddid = %d", $ddid );
		if( !empty( $meta_key ) ) $cond.= $wpdb->prepare( " AND meta_key = %s", $meta_key );
		
		$sql  = "SELECT * FROM {$wpdb->transaction_meta} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$metas = array();
		if( $results )
		{
			foreach( $results as $row )
			{
				if( !empty( $meta_key ) )
				{
					$metas[] = $row['meta_value'];
				}
				else
				{
					$metas[$row['meta_key']][] = $row['meta_value'];
				}
			}
		}
		
		if( !empty( $meta_key ) && $single )
		{
			return $metas[0];
		}

		return $metas;
	}
}


#-----------------------------------------------------------------#
#	>	metadata handler
#-----------------------------------------------------------------#
/**
 *	Meta API - Add meta.
 */
if ( ! function_exists('add_company_meta') ) 
{
	function add_company_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'company', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_warehouse_meta') ) 
{
	function add_warehouse_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'warehouse', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_supplier_meta') ) 
{
	function add_supplier_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'supplier', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_client_meta') ) 
{
	function add_client_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'client', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_vending_machine_meta') ) 
{
	function add_vending_machine_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'vending_machine', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_brand_meta') ) 
{
	function add_brand_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'brand', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_storage_meta') ) 
{
	function add_storage_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'storage', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_asset_meta') ) 
{
	function add_asset_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'asset', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_items_meta') ) 
{
	function add_items_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'items', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_itemize_meta') ) 
{
	function add_itemize_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'itemize', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_customer_meta') ) 
{
	function add_customer_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'customer', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_pricing_meta') ) 
{
	function add_pricing_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'pricing', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_promo_header_meta') ) 
{
	function add_promo_header_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'promo_header', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_member_meta') ) 
{
	function add_member_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'member', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
if ( ! function_exists('add_member_transact_meta') ) 
{
	function add_member_transact_meta( $item_id, $meta_key, $meta_value, $unique = false ) 
	{
		if ( $meta_id = add_metadata( 'member_transact', $item_id, $meta_key, $meta_value, $unique ) ) 
		{
			return $meta_id;
		}
		return 0;
	}
}
/**
 *	Meta API - Update meta.
 */
if ( ! function_exists('update_company_meta') ) 
{
	function update_company_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'company', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_warehouse_meta') ) 
{
	function update_warehouse_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'warehouse', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_supplier_meta') ) 
{
	function update_supplier_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'supplier', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_client_meta') ) 
{
	function update_client_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'client', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_vending_machine_meta') ) 
{
	function update_vending_machine_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'vending_machine', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_brand_meta') ) 
{
	function update_brand_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'brand', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_storage_meta') ) 
{
	function update_storage_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'storage', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_asset_meta') ) 
{
	function update_asset_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'asset', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_items_meta') ) 
{
	function update_items_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'items', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_itemize_meta') ) 
{
	function update_itemize_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'itemize', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_customer_meta') ) 
{
	function update_customer_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'customer', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_pricing_meta') ) 
{
	function update_pricing_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'pricing', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_promo_header_meta') ) 
{
	function update_promo_header_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'promo_header', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_member_meta') ) 
{
	function update_member_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'member', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('update_member_transact_meta') ) 
{
	function update_member_transact_meta( $item_id, $meta_key, $meta_value, $prev_value = '' ) 
	{
		if ( update_metadata( 'member_transact', $item_id, $meta_key, $meta_value, $prev_value ) ) 
		{
			return true;
		}
		return false;
	}
}
/**
 *	Meta API - Delete meta.
 */
if ( ! function_exists('delete_company_meta') ) 
{
	function delete_company_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'company', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_warehouse_meta') ) 
{
	function delete_warehouse_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'warehouse', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_supplier_meta') ) 
{
	function delete_supplier_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'supplier', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_client_meta') ) 
{
	function delete_client_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'client', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_vending_machine_meta') ) 
{
	function delete_vending_machine_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'vending_machine', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_brand_meta') ) 
{
	function delete_brand_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'brand', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_storage_meta') ) 
{
	function delete_storage_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'storage', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_asset_meta') ) 
{
	function delete_asset_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'asset', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_items_meta') ) 
{
	function delete_items_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'items', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_itemize_meta') ) 
{
	function delete_itemize_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'itemize', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_customer_meta') ) 
{
	function delete_customer_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'customer', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_pricing_meta') ) 
{
	function delete_pricing_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'pricing', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_promo_header_meta') ) 
{
	function delete_promo_header_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'promo_header', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_member_meta') ) 
{
	function delete_member_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'member', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
if ( ! function_exists('delete_member_transact_meta') ) 
{
	function delete_member_transact_meta( $item_id, $meta_key = '', $meta_value = '', $delete_all = false ) 
	{
		if ( delete_metadata( 'member_transact', $item_id, $meta_key, $meta_value, $delete_all ) ) 
		{
			return true;
		}
		return false;
	}
}
/**
 *	Meta API - Get meta.
 */
if ( ! function_exists('get_company_meta') ) 
{
	function get_company_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'company', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_warehouse_meta') ) 
{
	function get_warehouse_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'warehouse', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_supplier_meta') ) 
{
	function get_supplier_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'supplier', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_client_meta') ) 
{
	function get_client_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'client', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_vending_machine_meta') ) 
{
	function get_vending_machine_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'vending_machine', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_brand_meta') ) 
{
	function get_brand_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'brand', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_storage_meta') ) 
{
	function get_storage_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'storage', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_asset_meta') ) 
{
	function get_asset_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'asset', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_items_meta') ) 
{
	function get_items_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'items', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_itemize_meta') ) 
{
	function get_itemize_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'itemize', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_customer_meta') ) 
{
	function get_customer_meta( $item_id, $meta_key = '', $single = true, $db = '', $prefix = '' ) 
	{
		if( !empty( $db ) )
		{
			global $wpdb; $ori = $wpdb;
			$wpdb = new wpdb( DB_USER, DB_PASSWORD, $db, DB_HOST );
			$wpdb->customermeta = !empty( $prefix )? $prefix.'customermeta' : $ori->customermeta;
		}

		$result = get_metadata( 'customer', $item_id, $meta_key, $single );

		if( $ori ){ $wpdb = $ori; unset( $ori ); }

		return $result;
	}
}
if ( ! function_exists('get_pricing_meta') ) 
{
	function get_pricing_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'pricing', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_promo_header_meta') ) 
{
	function get_promo_header_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'promo_header', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_member_meta') ) 
{
	function get_member_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'member', $item_id, $meta_key, $single );
	}
}
if ( ! function_exists('get_member_transact_meta') ) 
{
	function get_member_transact_meta( $item_id, $meta_key = '', $single = true ) 
	{
		return get_metadata( 'member_transact', $item_id, $meta_key, $single );
	}
}


#-----------------------------------------------------------------#
#	>	Misc Query
#-----------------------------------------------------------------#
/**
 *	Simple users
 */
if ( ! function_exists('get_simple_users') ) 
{
	function get_simple_users( $user_id = 0, $cap = true, $dbname = '' )
	{
		global $wpdb;

		$fld = "a.*, b.meta_value AS name ";

		$tbl = "{$dbname}{$wpdb->users} a ";
		$tbl.= "LEFT JOIN {$dbname}{$wpdb->usermeta} b ON b.user_id = a.ID AND b.meta_key = 'first_name' ";
		
		$cond = "";

		if( $cap )
		{
			$tbl.= "LEFT JOIN {$dbname}{$wpdb->usermeta} c ON c.user_id = a.ID AND c.meta_key = '{$wpdb->prefix}capabilities' ";

			$cond.= $wpdb->prepare( "AND c.meta_value NOT LIKE %s ", "%customer%" );
			$cond.= $wpdb->prepare( "AND c.meta_value NOT LIKE %s ", "%subscriber%" );
			$cond.= $wpdb->prepare( "AND c.meta_value NOT LIKE %s ", "%client%" );
		}

		if( $user_id )
		{
			if( is_array( $user_id ) )
			{
				$user_id = array_unique( $user_id );
				$cond.= "AND a.ID IN ('" .implode( "','", $user_id ). "') ";
			}
			else
				$cond.= $wpdb->prepare( "AND a.ID = %d ", $user_id );
		}
		
		$sql  = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		$stid = 0;
		$users = [];
		if( $results )
		{
			foreach( $results as $row )
			{	
				$stid = ( ! $stid )? $row['ID'] : $stid;
				$users[ $row['ID'] ] = $row;
			}
		}
		
		if( $user_id && ! is_array( $user_id ) )
		{
			return $users[ $stid ];
		}

		return $users;
	}
}


#-----------------------------------------------------------------#
#	>	Debug Used
#-----------------------------------------------------------------#
/*
 * display sql result set in table format
 */
if ( ! function_exists( 'print_table' ) ) 
{
	function print_table( $datas )
	{
		if( ! $datas )
			return false;

		if( count( $datas ) > 0 )
		{
			echo "<br /><table border='1' width='100%'>";

			$header = array_keys( $datas[0] );
			echo "<tr>";
			foreach ( $header as $hd)
			{
				echo "<th scope='col'><b>". ( $hd )."</b></td>";
			}
			echo "</tr>";
			foreach ( $datas as $columns)
			{
				echo "<tr>";
				foreach ( $columns as $field)
				{
					echo "<td scope='col'>". ( $field )."</td>";
				}
				echo "</tr>";
			}
			echo "</table>";
		}
	}
}
#-----------------------------------------------------------------#
#	>	General functions
#-----------------------------------------------------------------#
if ( ! function_exists('print_data') ) 
{
	function print_data ( $data, $force = false, $die = false )
	{
		if( !$force && ( !defined( 'WCWH_DEBUG' ) || ! WCWH_DEBUG ) ) return;
		print('<pre>');
		print_r($data);
		print('</pre>');
		if( $die ) die();
	}
}
if ( ! function_exists('get_print_data') ) 
{
	function get_print_data ( $data, $force = false )
	{
		ob_start();

		print_data( $data, true, false );

		return ob_get_clean();
	}
}
if ( ! function_exists('pd') ) 
{
	function pd( $data, $die = false, $user_id = 0 )
	{
		if( ! $user_id && ! current_user_cans( 'wh_can_debug' ) ) return;
		if( $user_id > 0 && $user_id != get_current_user_id() ) return;
		print('<pre>');
		print_r($data);
		print('</pre>');
		if( $die ) die();
	}
}
if ( ! function_exists('rt') ) 
{
	function rt( $datas )
	{	
		if( ! $datas ) return false;
		if( is_user_logged_in() && ! current_user_cans( 'wh_can_debug' ) ) return false;

		if( count( $datas ) > 0 )
		{
			echo "<table border='1' width='100%'>";

			$header = array_keys( $datas[0] );
			echo "<tr>";
			foreach ( $header as $hd)
			{
				echo "<th scope='col'><b>". ( $hd )."</b></td>";
			}
			echo "</tr>";
			foreach ( $datas as $columns)
			{
				echo "<tr>";
				foreach ( $columns as $field)
				{
					echo "<td scope='col'>". ( $field )."</td>";
				}
				echo "</tr>";
			}
			echo "</table><br>";
		}
	}
}
/*
 * START TRANS
 */
if ( ! function_exists('wpdb_start_transaction') ) 
{
	function wpdb_start_transaction( $db_wpdb = array() )
	{
		global $wpdb;
		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		$wdb->query('START TRANSACTION');
	}
}
/*
 * COMMIT/ROLLBACK
 */
if ( ! function_exists('wpdb_end_transaction') ) 
{
	function wpdb_end_transaction ( $succ, $db_wpdb = array() )
	{
		global $wpdb;
		$wdb = ( $db_wpdb )? $db_wpdb : $wpdb;

		if( $succ ) 
		{
		    $wdb->query('COMMIT'); // if you come here then well done
		}
		else 
		{
		    $wdb->query('ROLLBACK'); // // something went wrong, Rollback
		}

		$wdb->flush();
	}
}
/*
 * Array Map Default Key Only
 */
if ( ! function_exists('array_map_key') ) 
{
	function array_map_key ( $data , $default )
	{
		$result = array();
		$array_key = array_keys( $default );
		foreach( $default as $key => $value )
		{
			$result[$key] = isset( $data[$key] ) ? $data[$key] : $value ;
		}
		return $result;
	}
}
/*
 * get current time
 */
if ( ! function_exists('get_current_time') ) 
{
	function get_current_time ( $date_format = 'Y-m-d H:i:s' )
	{
		return date( $date_format , current_time( 'timestamp', 0 ) );
	}
}
/*
 * get date time fragment
 */
if ( ! function_exists('get_datime_fragment') ) 
{
	function get_datime_fragment( $date_string, $date_format = 'Y-m-d H:i:s' )
	{
		$date = date_create( $date_string );
		return date_format( $date, $date_format );
	}
}
/*
 * array item in array
 */
if ( ! function_exists('item_in_array') ) 
{
	function item_in_array( $item, $array = array(), $cond = 'or' )
	{
		if( ! $item || ! $array ) return false;

		if( is_array( $item ) )
		{
			switch( strtolower( $cond ) )
			{
				case 'or':
					$succ = false;
					foreach( $item as $row )
					{
						if( in_array( $row, $array ) ) $succ = true;
					}
					return $succ;
				break;
				case 'and':
					$succ = true;
					foreach( $item as $row )
					{
						if( !in_array( $row, $array ) ) $succ = false;
					}
					return $succ;
				break;
			}
		}
		else
		{
			return in_array( $item, $array );
		}
	}
}
/**
 * convert date format.
 */
if ( ! function_exists('date_format_conversion') ) 
{
	function date_format_conversion ( $date , $date_format_from , $date_format_to ){
		if( ! $date ) {
		   return false;
		}
		return date_format( date_create_from_format( $date_format_from, $date) , $date_format_to );
	}
}
if ( ! function_exists('date_formating') ) 
{
	function date_formating ( $date, $time = '', $date_format_to = "Y-m-d H:i:s" ){
		if( ! $date ) {
		   return false;
		}

		$dateOnly = date( 'Y-m-d', strtotime( $date ) );
		$timeOnly = ( $time )? $time : current_time( ' H:i:s' );
		$newDateTime = $dateOnly.$timeOnly;

		$date = date_create( $newDateTime );
		return date_format( $date, $date_format_to );
	}
}
/**
 * 	date diff
 */
if ( ! function_exists('time_diff') ) 
{
	function time_diff( $firstTime, $lastTime )
	{
		// convert to unix timestamps
		$firstTime = strtotime( $firstTime );
		$lastTime = strtotime( $lastTime );

		// perform subtraction to get the difference (in seconds) between times
		$timeDiff = $lastTime - $firstTime;

		// return the difference
		return $timeDiff;
	}
}
/**
 * Rounding Decimal
 */
if ( ! function_exists('round_to') ) 
{
	function round_to( $num , $decimals = 2, $string = false, $num_format = false )
	{
		if( is_infinite( $num ) ) return;
		$rounded = ( round( $num * pow( 10, $decimals ) , 0 ) ) /  pow( 10, $decimals );
		if( $string )
		{
			$text = explode( '.', (string)$rounded );
			$rounded = $text[0];
			$rounded = $rounded.'.'.str_pad( ( $text[1] )? $text[1] : 0, $decimals, '0' );
		}

		if( $num_format ) $rounded = number_format( $rounded, $decimals );
		
		return $rounded;
	}
}
/**
 * Generate Serial
 */
if ( ! function_exists('generateSerial') ) 
{
	function generateSerial( $limit = 12 )
	{	
		$code = '';
		for( $i = 0; $i < $limit; $i++ )
		{ 
			$code .= mt_rand( 0, 9 ); 
		}

		return $code;
	}
}
/**
 * Generate Serial in Range
 */
if ( ! function_exists('generateRangeSerial') ) 
{
	function generateRangeSerial( $min = 0, $max = 99999 )
	{	
		$code = (string)rand( $min, $max );

		return $code;
	}
}
/**
 * Generate HW Serial
 */
if ( ! function_exists( 'hw_code_gen' ) ) 
{
	function hw_code_gen( $min = 100000, $max = 999999, $codeset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ" )
	{
		$base = strlen( $codeset );
		$n = mt_rand( $min, $max );
		$m = $n;
		$converted = "";
		
		while( $n > 0 )
		{
			$converted = substr( $codeset, ( $n % $base ), 1 ) . $converted;
			$n = floor( $n / $base );
		}
		
		return $m."-".$converted;
	}
}
/**
 * HW code pattern check / decode
 */
if ( ! function_exists( 'hw_code_gen' ) ) 
{
	function hw_code_check( $key, $decode = "", $codeset = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ" )
	{
		$base = strlen( $codeset );
		$converted = $key;
		
		$c = 0;
		for ( $i = strlen( $converted); $i; $i-- ) 
		{
			$c+= strpos( $codeset, substr( $converted, ( -1 * ( $i - strlen( $converted ) ) ), 1 ) ) * pow( $base, $i - 1 );
		}
		
		if( isset( $decode ) )
				return ( $c == $decode )? true : false;
		else 
			return $c;
	}
}
/**
 * simple decrypt from jquery encryption
 */
if ( ! function_exists( 'wcwh_simple_decrypt' ) ) 
{
	function wcwh_simple_decrypt( $encoded ) 
	{
		$encoded = base64_decode($encoded);
		$decoded = "";
		for( $i = 0; $i < strlen($encoded); $i++ ) 
		{
			$b = ord($encoded[$i]);
			$a = $b ^ 10;  
			$decoded .= chr($a);
		}

		return base64_decode(base64_decode($decoded));
	}
}
/*
 * Submit button
 */
if( ! function_exists('wcwh_submit_button') )
{
	function wcwh_submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null, $btn = false ) 
	{
		echo get_wcwh_submit_button( $text, $type, $name, $wrap, $other_attributes, $btn );
	}
}
if( ! function_exists('get_wcwh_submit_button') )
{
	function get_wcwh_submit_button( $text = '', $type = 'primary btn-sm', $name = 'submit', $wrap = true, $other_attributes = '', $btn = false )
	{
		if ( ! is_array( $type ) ) 
		{
			$type = explode( ' ', $type );
		}

		$button_shorthand = array( 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark' );
		$classes          = array( 'btn' );
		foreach ( $type as $t ) 
		{
			if ( 'button-secondary' === $t ) 
			{
				continue;
			}
			$classes[] = in_array( $t, $button_shorthand ) ? 'btn-' . $t : $t;
		}
		// Remove empty items, remove duplicate items, and finally build a string.
		$class = implode( ' ', array_unique( array_filter( $classes ) ) );

		$text = $text ? $text : __( 'Save Changes' );

		// Default the id attribute to $name unless an id was specifically provided in $other_attributes.
		$id = $name;
		if ( is_array( $other_attributes ) && isset( $other_attributes['id'] ) ) 
		{
			$id = $other_attributes['id'];
			unset( $other_attributes['id'] );
		}
		
		$attributes = '';
		if ( is_array( $other_attributes ) ) 
		{
			foreach ( $other_attributes as $attribute => $value ) 
			{
				$attributes .= $attribute . '="' . esc_attr( $value ) . '" '; // Trailing space is important.
			}
		} 
		elseif ( ! empty( $other_attributes ) ) 
		{ // Attributes provided as a string.
			$attributes = $other_attributes;
		}

		// Don't output empty name and id attributes.
		$name_attr = $name ? ' name="' . esc_attr( $name ) . '"' : '';
		$id_attr   = $id ? ' id="' . esc_attr( $id ) . '"' : '';

		$button  = '<input type="submit"' . $name_attr . $id_attr . ' class="' . esc_attr( $class );
		$button .= '" value="' . esc_attr( $text ) . '" ' . $attributes . ' />';

		if( $btn )
		{
			$button = '<button type="submit" ' . $name_attr . $id_attr . ' class="' . esc_attr( $class ) . '" ' . $attributes . ' >';
			$button.= $text . '</button>';
		}

		if ( $wrap ) 
		{
			$button = '<p class="submit">' . $button . '</p>';
		}

		return $button;
	}
}
/**
 * 	Outputs a checkout/address form field.
 */
if ( ! function_exists( 'wcwh_form_field' ) ) 
{
	function wcwh_form_field( $key = '', $args = array(), $value = null, $isView = false ) 
	{
		$defaults = array(
			'type'              => 'text',
			'label'             => '',
			'description'       => '',
			'placeholder'       => '',
			'required'          => false,
			'id'                => $key,
			'class'             => array(),
			'label_class'		=> array(),
			'return'            => false,
			'options'           => array(),
			'titles'			=> array(),
			'attrs' 			=> array(),
			'default'           => '',
			'offClass'			=> false,
			'tip'				=> '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( $args['required'] ) 
		{
			$args['attrs'][] = "required";
			$required        = '&nbsp;<span class="required toolTip" title="required">*</span>';
		} else 
		{
			$required = '';
		}

		if ( is_string( $args['label_class'] ) ) 
		{
			$args['label_class'] = array( $args['label_class'] );
		}

		if ( is_null( $value ) ) 
		{
			$value = $args['default'];
		}

		$field           = '';
		$label_id        = $args['id'];

		$formCtrl = ( $args['offClass'] )? '' : 'form-control';

		switch ( $args['type'] ) {
			case 'label':
				if( is_json( $value ) )
				{
					$value = json_decode( $value, true );
				}
				if( is_array( $value ) )
				{
					$value = get_print_data( $value );
				}

				$field .= '<span id="' . esc_attr( $args['id'] ) . '" class=" ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' >'. $value .'</span>';
			break;
			case 'textarea':
				if( ! $isView )
				{
					$field .= '<textarea name="' . esc_attr( $key ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . implode( ' ', $args['attrs'] ) . '>' . esc_textarea( $value ) . '</textarea>';
				}
				else
				{
					$field .= '<span id="' . esc_attr( $args['id'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' >'. esc_textarea( $value ) .'</span>';
				}
			break;
			case 'checkbox':
				if( ! $isView )
				{
					$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( $value, 1, false ) . implode( ' ', $args['attrs'] ) . ' /> ';
				}
				else
				{
					if( $key )
					$field .= '<span id="' . esc_attr( $args['id'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' >'. ( ( $value )? 'Yes' : 'No' ) .'</span>';
				}
			break;
			case 'text':
			case 'password':
			case 'datetime':
			case 'date':
			case 'month':
			case 'time':
			case 'week':
			case 'number':
			case 'email':
			case 'url':
			case 'tel':
				if( ! $isView )
				{
					$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $args['attrs'] ) . ' />';
				}
				else
				{
					$field .= '<span id="' . esc_attr( $args['id'] ) . '" class="view '.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' >'. esc_attr( $value ) .'</span>';
				}
			break;
			case 'file':
				if( ! $isView )
				{
					$field .= '<input type="' . esc_attr( $args['type'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $args['attrs'] ) . ' 
					'.( $args['multiple']? 'multiple="multiple"' : '' ).' />';
				}
			break;
			case 'hidden':
				$field .= '<input type="hidden" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $value ) . '" ' . implode( ' ', $args['attrs'] ) . ' />';
			break;
			case 'select':
				$field   = '';
				$options = '';

				//if ( ! empty( $args['options'] ) ) 
				//{
					$values = $value; $text = array();
					if( $args['options'] )
					foreach ( $args['options'] as $option_key => $option_text ) 
					{
						if ( '' === $option_key ) 
						{
							// If we have a blank option, select2 needs a placeholder.
							if ( empty( $args['placeholder'] ) ) 
							{
								$args['placeholder'] = $option_text ? $option_text : __( 'Choose an option', 'woocommerce' );
							}
						}

						if( $args['multiple'] && $value === '' ) $value = '!@|';
						if( $args['multiple'] && is_array( $values ) )
						{
							$value = in_array( $option_key, $values )? $option_key : '!@|';
						}

						if( $option_key && selected( $value, $option_key, false ) )
							$text[] = esc_attr( $option_text );
						$title = ( $args['titles'][$option_key] )? $args['titles'][$option_key] : '';
						$options .= '<option title="'.$title.'" value="' . esc_attr( $option_key ) . '" ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_text ) . '</option>';
					}

					if( ! $isView )
					{
						$field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' data-placeholder="' . esc_attr( $args['placeholder'] ) . '" ' . ( $args['multiple']? 'multiple="multiple"' : '' ) . ' >
							' . $options . '
						</select>';
					}
					else
					{
						$field .= '<span id="' . esc_attr( $args['id'] ) . '" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" ' . implode( ' ', $args['attrs'] ) . ' >'. implode( ', ', $text ) .'</span>';
					}
				//}
			break;
			case 'radio':
				$label_id .= '_' . current( array_keys( $args['options'] ) );

				if ( ! empty( $args['options'] ) ) 
				{
					foreach ( $args['options'] as $option_key => $option_text ) 
					{
						$field .= '<input type="radio" class="'.$formCtrl.' ' . esc_attr( implode( ' ', $args['class'] ) ) . '" value="' . esc_attr( $option_key ) . '" name="' . esc_attr( $key ) . '" ' . implode( ' ', $args['attrs'] ) . ' id="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '"' . checked( $value, $option_key, false ) . ' />';
						$field .= '<label for="' . esc_attr( $args['id'] ) . '_' . esc_attr( $option_key ) . '" class="radio ' . implode( ' ', $args['label_class'] ) . '">' . $option_text . '</label>';
					}
				}

				break;
		}

		if ( ! empty( $field ) ) 
		{
			$field_html = '';

			$field_html .= $field;

			$tooltip = '';
			if ( $args['description'] && !in_array( $args['type'], [ 'hidden' ] ) ) 
			{
				$tooltip .= '&nbsp;<sup title="' . $args['description'] . '" data-toggle="tooltip" data-placement="right" >&nbsp;?&nbsp;</sup>';
			}

			if ( $args['label'] && !in_array( $args['type'], [ 'hidden' ] ) ) 
			{
				$field_html .= '<label for="' . esc_attr( $label_id ) . '" class=" control-label' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . $tooltip . '</label>';
			}

			$field           = $field_html;
		}

		if ( $args['return'] ) {
			return $field;
		} else {
			echo $field; // WPCS: XSS ok.
		}
	}
}
/**
 * 	table template
 */
if ( ! function_exists( 'wcwh_render_table' ) ) 
{
	function wcwh_render_table( $columns = [], $datas = [], $args = [] ) 
	{
		if( ! $columns || ! $datas ) return '';

		ob_start();

		echo "<table>";

		echo "<tr>";
		foreach( $columns as $key => $col )
		{
			echo "<th>{$col}</th>";
		}
		echo "</tr>";

		foreach( $datas as $i => $row )
		{
			echo "<tr>";
			foreach( $columns as $key => $col )
			{
				echo "<td>{$row[$key]}</td>";
			}
			echo "</tr>";
		}

		echo "</table>";

		return ob_get_clean();
	}
}
/**
 *	Option Data
 */
if( ! function_exists( 'wh_apply_discount' ) )
{
	function wh_apply_discount( $amount, $price_action ) 
	{	
		if( ! $amount || ! $price_action ) return $amount;

		$amount = round_to( $amount, 2 );

		if ( strstr( $price_action, '%' ) ) : 	//-1-% of the amount
			$amount = round_to( ( $amount / 100 ) * str_replace( '%', '', $price_action ), 2 );
		else :									//-10 of the amount
			$amount = round_to( $price_action, 2 );
		endif;
		
		return $amount;
	}
}
/**
 *	Option Data
 */
if( ! function_exists( 'options_data' ) )
{
	function options_data( $datas = array(), $value_key = '', $title_key = array( 'name' ), $empty = 'Select', $addon_opts = [] )
	{
		$option = array();

		if( $empty ) $option[''] = $empty;

		if( $addon_opts )
		{
			foreach( $addon_opts as $key => $val )
				$option[ $key ] = $val;
		}

		if( ! $datas ) return $option;

		if( $value_key )
		{
			foreach( $datas as $i => $row )
			{
				$row = (array) $row;
				$title = array();
				if( $title_key ){
					foreach( $title_key as $tkey )
					{
						$title[] = $row[ $tkey ];
					}
				}
				$title = array_filter( $title );
				$option[ $row[ $value_key ] ] = implode( ', ', $title );
			}
		}
		else
		{
			$option = array_merge( $option, $datas );

			if( !empty( $title_key ) )
			{
				foreach( $datas as $key => $value )
				{
					$option[ $key ] = $value;
				}
			}
		}

		return $option;
	}
}
/**
 *	Json Validate
 */
if( ! function_exists( 'is_json' ) )
{
	function is_json( $string )
	{
		$error = '';

		if( ! $string ) return false;

	    try
	    {
	    	// decode the JSON data
	    	$result = json_decode( $string );

	    	// switch and check possible JSON errors
		    switch ( json_last_error() )
		    {
		        case JSON_ERROR_NONE:
		            $error = ''; // JSON is valid // No error has occurred
		        break;
		        case JSON_ERROR_DEPTH:
		            $error = 'The maximum stack depth has been exceeded.';
		        break;
		        case JSON_ERROR_STATE_MISMATCH:
		            $error = 'Invalid or malformed JSON.';
		        break;
		        case JSON_ERROR_CTRL_CHAR:
		            $error = 'Control character error, possibly incorrectly encoded.';
		        break;
		        case JSON_ERROR_SYNTAX:
		            $error = 'Syntax error, malformed JSON.';
		        break;
		        // PHP >= 5.3.3
		        case JSON_ERROR_UTF8:
		            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
		        break;
		        // PHP >= 5.5.0
		        case JSON_ERROR_RECURSION:
		            $error = 'One or more recursive references in the value to be encoded.';
		        break;
		        // PHP >= 5.5.0
		        case JSON_ERROR_INF_OR_NAN:
		            $error = 'One or more NAN or INF values in the value to be encoded.';
		        break;
		        case JSON_ERROR_UNSUPPORTED_TYPE:
		            $error = 'A value of a type that cannot be encoded was given.';
		        break;
		        default:
		            $error = 'Unknown JSON error occured.';
		        break;
		    }
	    }
	    catch(\Exception $e) 
	    {
	    	if( $error !== '' ) 
	    	{
	        	// throw the Exception or exit // or whatever :)
	        	exit($error);
	        	return false;
	    	}
	    }

	    // everything is OK
	    if( $error === '' ) return true;
	    else return false;
	}
}
/**
 *	getAlphaFromNumber
 */
if( ! function_exists( 'getAlphaFromNumber' ) )
{
	function getAlphaFromNumber( $num ) 
	{
	    $numeric = $num % 26;
	    $letter = chr(65 + $numeric);
	    $num2 = intval($num / 26);
	    if ($num2 > 0) {
	        return getAlphaFromNumber($num2 - 1) . $letter;
	    } else {
	        return $letter;
	    }
	}
}
/**
 *	Href
 */
if( ! function_exists( 'wcwh_href' ) )
{
	function wcwh_href( $params = array() )
	{
		if( ! empty( $_REQUEST["page"] ) )
			$params = array_merge( array( 'page' => $_REQUEST["page"] ), $params );

		return admin_url( "admin.php".add_query_arg( $params, '' ) );
	}
}
/**
 *	Change Currency Synbol ( MYR / RM )
 */
if( ! function_exists( 'wcwh_currency_symbol' ) )
{
	function wcwh_currency_symbol( $currency_symbol, $currency ) {
		$currency_symbol = $currency;
	    
	    return $currency_symbol;
	}
}
//add_filter('woocommerce_currency_symbol', 'wcwh_currency_symbol', 30, 2 );
/**
 *	Convert Amount to Text Amount
 */
if( ! function_exists( 'amtToText' ) )
{
	function amtToText( $x )
	{
		$nwords = array( 'Zero', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 
			'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 
			'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 
			'Nineteen', 'Twenty', 30 => 'Thirty', 40 => 'Forty', 
			50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 
			90 => 'Ninety' 
		);

		if( !is_numeric( $x ) )
		{ 
			$w = '#'; 
		}
		else if( fmod( $x, 1 ) != 0 )
		{ 
			$w = '#'; 
		}
		else
		{ 
			if( $x < 0 )
			{ 
				$w = 'Minus '; 
				$x = -$x; 
			}
			else
			{ 
				$w = ''; 
			} 

			if( $x < 21 )
			{ 
				$w .= $nwords[$x]; 
			}
			else if( $x < 100 )
			{ 
				$w .= $nwords[10 * floor( $x/10 )]; 
				$r = fmod( $x, 10 ); 
				if( $r > 0 )
				{ 
					$w .= '-'. $nwords[$r]; 
				} 
			}
			else if( $x < 1000 ) 
			{ 
				$w .= $nwords[floor( $x/100 )] .' Hundred'; 
				$r = fmod( $x, 100 ); 
				if( $r > 0 ) 
				{ 
					$w .= ' And '. amtToText( $r ); 
				} 
			}
			else if( $x < 1000000 ) 
			{ 
				$w .= amtToText( floor( $x/1000 ) ) .' Thousand'; 
				$r = fmod( $x, 1000 ); 
				if( $r > 0 ) 
				{ 
					$w .= ' '; 
					if( $r < 100 ) 
					{ 
						$w .= 'And '; 
					} 
					$w .= amtToText($r); 
				} 
			} 
			else 
			{ 
				$w .= amtToText( floor( $x/1000000 ) ) .' Million'; 
				$r = fmod( $x, 1000000 ); 
				if( $r > 0 ) 
				{ 
					$w .= ' '; 
					if( $r < 100 ) 
					{ 
						$word .= 'And '; 
					} 
					$w .= amtToText($r); 
				} 
			} 
		} 
		return $w; 
	}
}
/**
 *	Convert Numeric Currency to Text Currency
 */
if( ! function_exists( 'convertCurrencyToWords' ) )
{
	function convertCurrencyToWords( $amount, $letterCase = 'upper', $currencyLabel = [ 'prefix'=>'Ringgit Malaysia', 'midfix'=>'Cents', 'suffix'=>'Only' ] )
	{
		if( ! is_numeric( $amount ) ) return false;
		
		$amount = number_format( $amount, 2 );
		$amount = str_replace( ",", "", $amount );
		$nums = explode( ".", $amount );
		$prefix = !empty( $currencyLabel['prefix'] )? $currencyLabel['prefix']." " : "";
		
		$text = $prefix.amtToText( (int)$nums[0] );
		if( isset( $nums[1] ) && $nums[1] > 0 ){
			$midfix = !empty( $currencyLabel['midfix'] )? $currencyLabel['midfix']." " : "";
			$text.= " And ".$midfix.amtToText( (int)$nums[1] );
		}
		
		$suffix = !empty( $currencyLabel['suffix'] )? " ".$currencyLabel['suffix'] : "";
		$text.= $suffix;
		
		switch( $letterCase )
		{
			case 'upper':
				$text = strtoupper( $text );
			break;
			case 'lower':
				$text = strtolower( $text );
			break;
			case 'ucwords':
				$text = ucwords( $text );
			break;
		}
		
		return $text;
	}
}
/**
 *	Get Currency Prefix
 */
if( ! function_exists( 'wcwh_currency_prefix' ) )
{
	function wcwh_currency_prefix( $code = 'MYR' )
	{
		$currencies = array( 
			'MYR' => 'Ringgit Malaysia', 
			'USD' => 'United States Dollar',
			'SGD' => 'SINGAPORE DOLLAR'
		);
		
		$currency = $currencies[$code];
		
		return $currency;
	}
}
add_filter( 'wcwh_currency_prefix', 'wcwh_currency_prefix', 10, 1 );
/**
 *	Count String Line for template use
 */
if( ! function_exists( 'countStringLine' ) )
{
	function countStringLine( $text = '', $row_max = 20 )
	{
		if( ! $text ) return 1;
		$line = 1;

		$text = trim( $text );
		$words = explode( " ", $text );

		if( $words )
		{
			$string = "";
			foreach( $words as $word )
			{
				$word = $word." ";
				$tlen = strlen( $string );
				$len = strlen( $word );
				
				if( $tlen + $len > $row_max )
				{
					$string = $word;
					$line++;
				}
				else
				{
					$string.= $word;
				}
			}
		}

		return $line;
	}
}
/**
 *	Count Row Per Page for template use
 */
if( ! function_exists( 'rowPerPage' ) )
{
	function rowPerPage( $detail = [], $row_max = 20, $row_per_pg = 32, $row_last_pg = 22 )
	{
		if( empty( $detail ) ) return $detail;
		
		$pages = []; $pg = 0;

		$r = 0; $s = 0; $p = 0; $num = 0;
		foreach( $detail as $i => $row )
		{
			$num++;
			$row['num'] = $num;

			$l = countStringLine( $row['item'], $row_max );
			$r+= $l; $s+= $l;
			$row['row'] = $l;

			$row['item'] = wordwrap( $row['item'], $row_max, "<br>\n" );

			if( $s > $row_last_pg )
			{	
				$pg++;
				$s = 0; $p++;
			}
			else if( $p > 0 && $s > $row_per_pg - $row_last_pg - 1 )
			{
				foreach( $pages[$pg] as $j => $in_row )
				{
					$pages[$pg-1][] = $in_row;
				}
				unset( $pages[$pg] );

				$s = 1; $p = 0;
			}

			$pages[$pg][] = $row;
		}

		return $pages;
	}
}
/**
 *	Replacing Wordpress download_url()
 */
if( ! function_exists( 'wcwh_download_url' ) )
{
	function wcwh_download_url( $url, $timeout = 300, $signature_verification = false ) {
	    // WARNING: The file is not automatically deleted, the script must unlink() the file.
	    if ( ! $url ) {
	        return new WP_Error( 'http_no_url', __( 'Invalid URL Provided.' ) );
	    }
	 
	    $url_path     = parse_url( $url, PHP_URL_PATH );
	    $url_filename = '';
	    if ( is_string( $url_path ) && '' !== $url_path ) {
	        $url_filename = basename( $url_path );
	    }
	 
	    $tmpfname = wp_tempnam( $url_filename );
	    if ( ! $tmpfname ) {
	        return new WP_Error( 'http_no_file', __( 'Could not create temporary file.' ) );
	    }
	 
	    $response = wp_remote_get(
	        $url,
	        array(
	            'timeout'  => $timeout,
	            'stream'   => true,
	            'filename' => $tmpfname,
	            'sslverify' => false,
	        )
	    );
	 
	    if ( is_wp_error( $response ) ) {
	        unlink( $tmpfname );
	        return $response;
	    }
	 
	    $response_code = wp_remote_retrieve_response_code( $response );
	 
	    if ( 200 !== $response_code ) {
	        $data = array(
	            'code' => $response_code,
	        );
	 
	        // Retrieve a sample of the response body for debugging purposes.
	        $tmpf = fopen( $tmpfname, 'rb' );
	 
	        if ( $tmpf ) {
	            $response_size = apply_filters( 'download_url_error_max_body_size', KB_IN_BYTES );
	 
	            $data['body'] = fread( $tmpf, $response_size );
	            fclose( $tmpf );
	        }
	 
	        unlink( $tmpfname );
	 
	        return new WP_Error( 'http_404', trim( wp_remote_retrieve_response_message( $response ) ), $data );
	    }
	 
	    $content_disposition = wp_remote_retrieve_header( $response, 'content-disposition' );
	 
	    if ( $content_disposition ) {
	        $content_disposition = strtolower( $content_disposition );
	 
	        if ( 0 === strpos( $content_disposition, 'attachment; filename=' ) ) {
	            $tmpfname_disposition = sanitize_file_name( substr( $content_disposition, 21 ) );
	        } else {
	            $tmpfname_disposition = '';
	        }
	 
	        // Potential file name must be valid string.
	        if ( $tmpfname_disposition && is_string( $tmpfname_disposition )
	            && ( 0 === validate_file( $tmpfname_disposition ) )
	        ) {
	            $tmpfname_disposition = dirname( $tmpfname ) . '/' . $tmpfname_disposition;
	 
	            if ( rename( $tmpfname, $tmpfname_disposition ) ) {
	                $tmpfname = $tmpfname_disposition;
	            }
	 
	            if ( ( $tmpfname !== $tmpfname_disposition ) && file_exists( $tmpfname_disposition ) ) {
	                unlink( $tmpfname_disposition );
	            }
	        }
	    }
	 
	    $content_md5 = wp_remote_retrieve_header( $response, 'content-md5' );
	 
	    if ( $content_md5 ) {
	        $md5_check = verify_file_md5( $tmpfname, $content_md5 );
	 
	        if ( is_wp_error( $md5_check ) ) {
	            unlink( $tmpfname );
	            return $md5_check;
	        }
	    }
	 
	    // If the caller expects signature verification to occur, check to see if this URL supports it.
	    if ( $signature_verification ) {
	        $signed_hostnames = apply_filters( 'wp_signature_hosts', array( 'wordpress.org', 'downloads.wordpress.org', 's.w.org' ) );
	 
	        $signature_verification = in_array( parse_url( $url, PHP_URL_HOST ), $signed_hostnames, true );
	    }
	 
	    // Perform signature valiation if supported.
	    if ( $signature_verification ) {
	        $signature = wp_remote_retrieve_header( $response, 'x-content-signature' );
	 
	        if ( ! $signature ) {
	            // Retrieve signatures from a file if the header wasn't included.
	            // WordPress.org stores signatures at $package_url.sig.
	 
	            $signature_url = false;
	 
	            if ( is_string( $url_path ) && ( '.zip' === substr( $url_path, -4 ) || '.tar.gz' === substr( $url_path, -7 ) ) ) {
	                $signature_url = str_replace( $url_path, $url_path . '.sig', $url );
	            }
	 
	            $signature_url = apply_filters( 'wp_signature_url', $signature_url, $url );
	 
	            if ( $signature_url ) {
	                $signature_request = wp_safe_remote_get(
	                    $signature_url,
	                    array(
	                        'limit_response_size' => 10 * KB_IN_BYTES, // 10KB should be large enough for quite a few signatures.
	                    )
	                );
	 
	                if ( ! is_wp_error( $signature_request ) && 200 === wp_remote_retrieve_response_code( $signature_request ) ) {
	                    $signature = explode( "\n", wp_remote_retrieve_body( $signature_request ) );
	                }
	            }
	        }
	 
	        // Perform the checks.
	        $signature_verification = verify_file_signature( $tmpfname, $signature, $url_filename );
	    }
	 
	    if ( is_wp_error( $signature_verification ) ) {
	        if (
	            apply_filters( 'wp_signature_softfail', true, $url )
	        ) {
	            $signature_verification->add_data( $tmpfname, 'softfail-filename' );
	        } else {
	            // Hard-fail.
	            unlink( $tmpfname );
	        }
	 
	        return $signature_verification;
	    }
	 
	    return $tmpfname;
	}
}
/**
 *	Cron Action
 */
if( ! function_exists( 'wcwh_scheduled_actions' ) )
{
	function wcwh_scheduled_actions()
	{
		if ( !class_exists( "WCWH_SYNC_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );
		
		//scheduling log
		//file_put_contents('wcwh_scheduling.log',"\r\n".current_time( 'mysql' ),FILE_APPEND);

		update_option( 'wcwh_2ndlast_scheduled', get_option( 'wcwh_last_scheduled' ) );
		update_option( 'wcwh_last_scheduled', current_time( 'mysql' ) );

		if( defined( 'WCWH_MANUAL_INTEGRATE' ) && WCWH_MANUAL_INTEGRATE ) return true;

		$Inst = new WCWH_SYNC_Controller();

		$Inst->sync_remote_api();
	}
}
add_action( 'wcwh_scheduled_actions', 'wcwh_scheduled_actions' );

if( ! function_exists( 'wcwh_scheduled_credit_topup' ) )
{
	function wcwh_scheduled_credit_topup()
	{
		if ( !class_exists( "WCWH_CreditTopup_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/creditTopupCtrl.php" );

		$Inst = new WCWH_CreditTopup_Controller();

		$Inst->auto_topup_handler();
	}
}
add_action( 'wcwh_scheduled_credit_topup', 'wcwh_scheduled_credit_topup' );

// cron job task schedule
if( ! function_exists( 'wcwh_task_actions' ) )
{
	function wcwh_task_actions()
	{
		if ( !class_exists( "WCWH_TaskSchedule_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/taskScheduleCtrl.php" );
		
		$Inst = new WCWH_TaskSchedule_Controller();

		$Inst->task_refresher();
	}
}
add_action( 'wcwh_task_actions', 'wcwh_task_actions' );

if( ! function_exists( 'wcwh_scheduled_daily_reminder' ) )
{
	function wcwh_scheduled_daily_reminder()
	{
		if ( !class_exists( "WCWH_MoneyCollector_Controller" ) ) require_once( WCWH_DIR . "/includes/controller/moneyCollectorCtrl.php" );

		$Inst = new WCWH_MoneyCollector_Controller();

		$Inst->collector_reminder();
	}
}
add_action( 'wcwh_scheduled_daily_reminder', 'wcwh_scheduled_daily_reminder' );

/**
 *	Cron log mail
 */
if( ! function_exists( 'wcwh_cron_log_mail' ) )
{
	function wcwh_cron_log_mail( $action = 'save', $datas = array() )
	{	
		if( ! $datas ) return false;

		if ( !class_exists( "WCWH_MailLog_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/mailLogCtrl.php" );

		$Inst = new WCWH_MailLog_Controller();

		$result = $Inst->action_handler( $action, $datas, $datas );
		if( $result['succ'] )
			return $result['id'];

		return false;
	}
}
/**
 *	Cron Monitor Shortcode
 */
if( ! function_exists( 'wcwh_cron_monitor_shortcode' ) )
{
	function wcwh_cron_monitor_shortcode()
	{
		$whs = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'meta'=>['dbname'] ] );
		if( $whs )
		{
			global $wpdb;
			
			$union = [];
			$sql = "SELECT 'SELF' AS wh, a.option_value AS time FROM {$wpdb->prefix}options a WHERE a.option_name = 'wcwh_last_scheduled' ";
			$union[] = $sql;
			foreach( $whs as $wh )
			{
				if( $wh['parent'] > 0 && ! empty( $wh['dbname'] ) )
				{
					$sql = "SELECT '{$wh['code']}' AS wh, a.option_value AS time FROM {$wh['dbname']}.{$wpdb->prefix}options a WHERE a.option_name = 'wcwh_last_scheduled' ";
					$union[] = $sql;
				}
			}
			
			if( $union )
			{
				$query = implode( "  UNION ALL  ", $union );
				
				$result = $wpdb->get_results( $query , ARRAY_A );
				echo "<h3>Scheduler Latest Run Time</h3>";
				if( $result )
				{
					foreach( $result as $i => $row )
					{
						if( $row['wh'] == '1027-TSP' ) unset( $result[$i] );
					}
				}
				rt($result);
			}

			//--------------------------------------------
			$union = [];
			foreach( $whs as $wh )
			{
				if( $wh['parent'] > 0 && ! empty( $wh['dbname'] ) )
				{
					$cond = "AND a.post_type = 'pos_temp_register_or' AND a.post_status = 'publish' ";
					$ord = "ORDER BY a.post_date DESC LIMIT 0,1 ";
					$sql = "SELECT '{$wh['code']}' AS wh, a.post_date FROM {$wh['dbname']}.{$wpdb->posts} a WHERE 1 {$cond} {$ord} ";
					$union[] = $sql;
				}
			}

			if( $union )
			{
				$query = implode( "  UNION ALL  ", $union );

				$union_sql = "( ".implode( " ) UNION ALL ( ", $union ).") ";
				$sql = "SELECT a.* FROM ( {$union_sql} ) a ";
				
				$result = $wpdb->get_results( $sql , ARRAY_A );
				echo "<h3>Canteen Latest Sales Time</h3>";
				rt($result);
			}
		}
	}
}
add_shortcode( 'wcwh_cron_monitor', 'wcwh_cron_monitor_shortcode' );