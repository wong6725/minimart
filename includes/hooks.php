<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( !class_exists( "WCWH_Hooks" ) )
{

class WCWH_Hooks 
{
	protected $refs = array();
	
	/**
	 * Constructor 
	 */
	public function __construct( $refs ) 
	{
		$this->refs = $refs;
		
		$this->wc_hook();
		$this->perform_action_hook();
		$this->get_info_hook();
	}

	public function __destruct()
	{
		
	}
	
	/*	====================================================
	 *	Woocommerce or other plugin
	 */
	
	public function wc_hook()
	{
			//After refund
		add_action( 'woocommerce_refund_created', array( $this, 'order_refund_created' ), 10, 2 );
		
			//Pos Product API
		add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'wc_rest_prepare_product' ), 1000, 3 );
			//POS Customer API
		add_filter( 'woocommerce_rest_prepare_customer', array( $this, 'wc_rest_prepare_customer' ), 1000, 3 );
			//POS Order API
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'wc_rest_prepare_order' ), 1000, 3 );
			//POS Refund API
		add_filter( 'woocommerce_rest_prepare_shop_order_refund_object', array( $this, 'wc_rest_prepare_refund' ), 1000, 3 );
		
		add_filter( 'woocommerce_rest_customer_query', array( $this, 'rest_customer_query' ), 100, 2 );
		
			//checkout fields modified
		add_filter( 'woocommerce_checkout_fields' , array( $this, 'override_checkout_fields' ), 100, 1 );
		
			//extra handling of woocommerce "generated fields"
		add_action( 'woocommerce_admin_field_label', array( $this, 'woocommerce_admin_field_label' ) );
		add_action( 'woocommerce_admin_field_hidden', array( $this, 'woocommerce_admin_field_hidden' ) );

		add_filter( 'woocommerce_email_settings', array( $this, 'email_settings' ) );
	}
		
		//After refund
		public function order_refund_created( $id, $args ){
			if( ! $args['order_id'] ) return;
			
			$order_id = $args['order_id'];
			$order = wc_get_order( $order_id );
			$warehouse_id = get_post_meta( $order_id, 'wc_pos_warehouse_id', true );

			$item_comment = array();
			$refunded_line_items = $args['line_items'];
			foreach ( $order->get_items() as $item_id => $item ) 
			{
				if ( ! isset( $refunded_line_items[ $item_id ], $refunded_line_items[ $item_id ]['qty'] ) ) 
				{
					continue;
				}
				$product = $item->get_product();
				
				$uprice = $order->get_line_subtotal( $item, $order->prices_include_tax, true );
				$qty = $refunded_line_items[ $item_id ]['qty'];
				
				$item_comment[] = 'Refund Item #'.$product->get_id().":".$product->get_name().' qty:'.$qty;
			}

			if( $item_comment )
			{
				$this->add_order_note( 
					'Warehouse '.$warehouse_id.', '.implode( ', ',$item_comment ),
					$order
				);
			}
			
			do_action( 'wc_pos_after_order_refund', $id, $order, $args );
		}

		//Pos Refund API
		public function wc_rest_prepare_refund( $response, $the_order, $request )
		{
			$data = $response->get_data();
			if( $data['id'] && $data['id'] > 0 )
			{
				$refund = get_post( $data['id'] );
				$order_id = $refund->post_parent;
				
				if( $order_id )
				{
					$order = wc_get_order( $order_id );
					
					$user_id = $order->get_user_id();
					if( $user_id ) $customer_id = get_user_meta( $user_id, 'customer_id', true );
					$customer_id = ( $customer_id )? $customer_id : get_post_meta( $order_id, 'customer_id', true );
					if( ! $customer_id && $order_id )
					{
						$customer_code = get_post_meta( $order_id, 'customer_code', true );
						if( $customer_code ) $customer = apply_filters( 'wcwh_get_customer', [ 'code'=>$customer_code ], [], true, [] );
						if( $customer ) $customer_id = $customer['id'];
					}
					
					if( $customer_id )
					{
						$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'group'=>1, 'member'=>1, 'meta'=>['sapuid','sapuid_date'] ] );
						if( $customer )
						{
							$customer['active'] = true;
							//$customer['sapuid'] = ( $customer['uid'] )? $customer['uid'] : get_customer_meta( $customer_id, 'sapuid', true );
							//$customer['sapuid_date'] = get_customer_meta( $customer_id, 'sapuid_date', true );
						}
						else
						{
							$customer['active'] = false;
						}
						
						$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $customer_id, $customer );
						
						$data['active'] = $customer['active'];
						$data['first_name'] = $customer['name'];
						$data['member_id'] = ( $customer )? $customer['serial'] : '';
						$data['member_code'] = ( $customer )? $customer['code'] : '';
						$data['member_data'] = ( $customer )? $customer : array();
						$data['member_credits'] = ( $user_credits )? $user_credits : array();
						if( $customer['last_day'] ) $data['last_day'] = $customer['last_day'];

						//membership
						$data['is_membership'] = false;
						if( $customer['member_serial'] )
						{
							$user_debits = apply_filters( 'wc_credit_limit_get_client_debits', $customer_id, $customer );

							$data['is_membership'] = true;
							$data['membership_serial'] = ( $customer['member_serial'] )? $customer['member_serial'] : '';
							$data['member_credits'] = ( $user_debits )? $user_debits : array();
						}
					}
				}
			}

			$response->set_data($data);
			
			return $response;
		}

		//Pos Order API
		public function wc_rest_prepare_order( $response, $the_order, $request )
		{
			$order_data = $response->get_data();
			$order_id = $order_data['id'];
			
			//$request->get_params()

			//customer info
			$user_id = get_post_meta( $order_id, '_customer_user', true );
			if( $user_id ) $customer_id = get_user_meta( $user_id, 'customer_id', true );
			$customer_id = ( $customer_id )? $customer_id : get_post_meta( $order_id, 'customer_id', true );
			if( ! $customer_id && $order_id )
			{
				$customer_code = get_post_meta( $order_id, 'customer_code', true );
				if( $customer_code ) $customer = apply_filters( 'wcwh_get_customer', [ 'code'=>$customer_code ], [], true, [] );
				if( $customer ) $customer_id = $customer['id'];
			}
			if( $customer_id )
			{
				$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'group'=>1, 'member'=>1, 'meta'=>['sapuid','sapuid_date','last_day'] ] );
				if( $customer )
				{
					$customer['active'] = true;
					//$customer['sapuid'] = ( $customer['uid'] )? $customer['uid'] : get_customer_meta( $customer_id, 'sapuid', true );
					//$customer['sapuid_date'] = get_customer_meta( $customer_id, 'sapuid_date', true );
				}
				else
				{
					$customer['active'] = false;
				}
				
				$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $customer_id, $customer );
				
				$order_data['active'] = $customer['active'];
				$order_data['first_name'] = $customer['name'];
				$order_data['member_id'] = ( $customer )? $customer['serial'] : '';
				$order_data['member_code'] = ( $customer )? $customer['code'] : '';
				$order_data['member_data'] = ( $customer )? $customer : array();
				$order_data['member_credits'] = ( $user_credits )? $user_credits : array();
				if( $customer['last_day'] ) $order_data['last_day'] = $customer['last_day'];

				//membership
				$order_data['is_membership'] = false;
				if( $customer['member_serial'] )
				{
					$user_debits = apply_filters( 'wc_credit_limit_get_client_debits', $customer_id, $customer );

					$order_data['is_membership'] = true;
					$order_data['membership_serial'] = ( $customer['member_serial'] )? $customer['member_serial'] : '';
					$order_data['member_credits'] = ( $user_debits )? $user_debits : array();
				}
			}

			//order info
			$docno = $this->get_order_docno( $order_id );
			$order_data['docno'] = ( $docno )? $docno : $order_id;
			$order_data['ord_status'] = $the_order->get_status();
			$order_data['canCancel'] = ( in_array( $the_order->get_status(), [ 'processing', 'completed' ] ) )? true : false;
			$order_data['session'] = $the_order->pos_session_id;
			$order_data['timediff'] = abs( current_time('timestamp') - strtotime( $order_data['date_paid'] ) );
			$ctime = !empty( $this->refs['pos_cancel_time'] )? $this->refs['pos_cancel_time'] : 3660;
			if( !empty( $order_data['timediff'] ) && $order_data['timediff'] > $ctime ) $order_data['canCancel'] = false;
			$vcancel = isset( $this->refs['virtual_cancel'] )? $this->refs['virtual_cancel'] : 0;

			if( $user_id )
			{
				$order_data['customer'] = $customer['name'];
				$order_data['customer_code'] = get_post_meta( $order_id, '_customer_code', true );
				if( !$order_data['customer_code'] ) $order_data['customer_code'] = $customer['code'];
			}
			
			if( $order_data['line_items'] )
			{
				$item_ids = []; $matching = [];
				foreach( $order_data['line_items'] as $line_id => $line_item )
				{
					$item_id = 0;
					if( $line_item['meta_data'] )
					{
						foreach( $line_item['meta_data'] as $m => $item_meta )
						{
							if( $item_meta['key'] == '_items_id' )
							{
								$item_id = $item_meta['value'];
								break;
							}
						}
					}

					if( $item_id ) 
					{
						$item_ids[] = $item_id;
						$matching[ $item_id ] = $line_id;
					}
				}

				if( $item_ids )
				{
					$items = apply_filters( 'wcwh_get_item_by_pos', $item_ids );
					if( $items )
					{
						foreach( $items as $item )
						{
							if( $item['virtual'] )
							{
								$order_data['line_items'][ $matching[ $item['id'] ] ]['is_virtual'] = true;

								$vStock = apply_filters( 'wcwh_count_available_itemize', $item['id'] );
								$order_data['line_items'][ $matching[ $item['id'] ] ]['stocks_qty'] = $vStock;
								if( $vStock <= 0 ) $order_data['line_items'][ $matching[ $item['id'] ] ]['usable'] = false;

								if( ! $vcancel ) $order_data['canCancel'] = false;
							}
						}
					}
				}
			}

			$order_data = apply_filters( 'wcwh_after_prepare_order', $order_data );

			$response->set_data($order_data);
			
			return $response;
		}
		
		//Pos Product API
		public function wc_rest_prepare_product( $response, $object, $request )
		{
			$register_id = $_GET['register'];
			$register = wc_pos_get_register( $register_id );
			$reg_detail = json_decode( $register->detail, true );
			$wh_id = $reg_detail['assigned_warehouse'];
			
			$product_data = $response->get_data();
			if( ! $product_data ) return $response;

			$item_id = get_post_meta( $product_data['id'], 'item_id', true );
			if( $item_id )
			{
				//$item = apply_filters( 'wcwh_get_item', [ 'id'=>$item_id ], [], true, [ 'uom'=>1, 'usage'=>1 ] );
				$item = apply_filters( 'wcwh_get_item_by_pos', $item_id );

				$scheme = [];
				if( $reg_detail['assigned_client'] )
				{
					$scheme = [ 'client_code'=>$reg_detail['assigned_client'] ];
				}
				$prices = apply_filters( 'wcwh_get_price', $item_id, $wh_id, $scheme );

				$product_data['usable'] = ( $item )? true : false;
				$product_data['force_sell'] = ( in_array( $item['sellable'], [ 'force' ] ) )? true : false;
				$product_data['detail'] = ( $item )? $item : [];
				$product_data['prices'] = ( $prices )? $prices['unit_price'] : 0;
				$product_data['price_code'] = ( $prices )? $prices['price_code'] : '';
				$product_data['is_virtual'] = ( $item['virtual'] )? true : false;

				if( $item )
				{
					$product_data['gtin'] = $item['_sku'];
					$product_data['item_code'] = $item['code'];
					$product_data['item_serial'] = $item['serial'];

					if( is_json( $item['serial2'] ) ) $item['serial2'] = json_decode( stripslashes( $item['serial2'] ), true );
					if( $item['serial2'] && ! is_array( $item['serial2'] ) ) $item['serial2'] = [ $item['serial2'] ];
					$product_data['item_serial2'] = $item['serial2'];

					$product_data['label_name'] = $item['label_name'];
					$product_data['indo_name'] = $item['indo_name'];

					if( $item['virtual'] )
					{
						$vStock = apply_filters( 'wcwh_count_available_itemize', $item_id );
						$product_data['stocks_qty'] = $vStock;
						if( $vStock <= 0 ) $product_data['usable'] = false;
					}
				}
			}
			
			$response->set_data( $product_data );
			
			return $response;
		}
		//Pos Customer API
		public function wc_rest_prepare_customer( $response, $user_data, $request )
		{
			$register_id = $_GET['register'];
			$register = wc_pos_get_register( $register_id );
			$reg_detail = json_decode( $register->detail, true );

			$customer_data = $response->get_data();
			if( ! $customer_data ) return $response;

			$customer_id = get_user_meta( $customer_data['id'], 'customer_id', true );
			if( $customer_id )
			{
				$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'group'=>1, 'member'=>1, 'meta'=>['sapuid','sapuid_date','last_day'], 'usage'=>1 ] );

				$today = strtotime( date( 'Y-m-d H:i:s' ) );	
				$last_day = strtotime( date( 'Y-m-d', strtotime( date( 'Y-m-d' )." +10 year" ) ) );
				if( !empty( $customer['last_day'] ) )
				{
					$last_day = strtotime( $customer['last_day'] );
				}
				
				if( $customer && 
					( empty( $customer['last_day'] ) || 
						( !empty( $customer['last_day'] ) && $today < $last_day ) 
					) 
				)
				{
					$customer['active'] = true;
					//$customer['sapuid'] = ( $customer['uid'] )? $customer['uid'] : $customer['sapuid'];
					//$customer['sapuid_date'] = get_customer_meta( $customer_id, 'sapuid_date', true );
				}
				else
				{
					$customer['active'] = false;
				}
				
				$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $customer_id, $customer );

				$customer_data['active'] = $customer['active'];
				$customer_data['first_name'] = $customer['name'];
				$customer_data['member_id'] = ( $customer )? $customer['serial'] : '';
				$customer_data['member_code'] = ( $customer )? $customer['code'] : '';
				$customer_data['member_data'] = ( $customer )? $customer : array();
				$customer_data['member_credits'] = ( $user_credits )? $user_credits : array();
				if( $customer['last_day'] ) $customer_data['last_day'] = $customer['last_day'];

				//membership
				$customer_data['is_membership'] = false;
				if( $customer['member_serial'] )
				{
					$user_debits = apply_filters( 'wc_credit_limit_get_client_debits', $customer_id, $customer );

					$customer_data['is_membership'] = true;
					$customer_data['membership_serial'] = ( $customer['member_serial'] )? $customer['member_serial'] : '';
					$customer_data['member_credits'] = ( $user_debits )? $user_debits : array();
				}
			}
			
			$response->set_data( $customer_data );
			
			return $response;
		}
		
		public function rest_customer_query( $prepared_args = [], $request = [] )
		{	
			if( ! $prepared_args || ! $request ) return $prepared_args;
			
			$prepared_args['meta_query'] = [
				'relation' => 'OR',
				[
					'key' => 'nickname',
					'value' => $request['search'],
					'compare' => 'LIKE',
				],
				[
					'key' => 'member_id',
					'value' =>$request['search'],
					'compare' => 'LIKE',
				],
				[
					'key' => 'code',
					'value' => $request['search'],
					'compare' => 'LIKE',
				],
			];

			return $prepared_args;
		}

		public function add_order_note( $note, $order = array(), $metas = array() ) 
		{
			if ( ! $note || ! $order ) {
				return 0;
			}

			if( is_numeric( $order ) )
			{
				$order = wc_get_order( $order );
			}

			$user_id = get_current_user_id();
			//$data = get_userdata( $user_id );

			$commentdata = apply_filters(
				'woocommerce_new_order_note_data',
				array(
					'comment_post_ID'      => $order->get_id(),
					'comment_author'       => $user_id,
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_content'      => $note,
					'comment_agent'        => 'WooCommerce',
					'comment_type'         => 'order_note',
					'comment_parent'       => 0,
					'comment_approved'     => 1,
				),
				array(
					'order_id'         => $order->get_id(),
					'is_customer_note' => 0,
				)
			);

			$comment_id = wp_insert_comment( $commentdata );

			if ( $metas && $comment_id ) {
				foreach( $metas as $key => $value )
				{
					add_comment_meta( $comment_id, $key, $value );
				}
				
			}

			return $comment_id;
		}
		
		public function override_checkout_fields( $fields ) 
		{
			$fields['billing'] = $this->customizing_checkout_fields( $fields['billing'] );
			$fields['shipping'] = $this->customizing_checkout_fields( $fields['shipping'] );
			
			return $fields;
		}
			public function customizing_checkout_fields( $fields )
			{	
				foreach( $fields as $key => $field ){
					$fields[$key]['required'] = false;
					
					if( $key == 'billing_first_name' || $key == 'shipping_first_name' ){
						$fields[$key]['label'] = 'Name';
						$fields[$key]['class'] = array( 'form-row-wide' );
					}
					if( $key == 'billing_state' || $key == 'shipping_state' ){
						$fields[$key]['label'] = 'State';
					}
					if( $key == 'billing_last_name' || $key == 'shipping_last_name' ){
						unset( $fields[$key] );
					}
					if( $key == 'billing_company' || $key == 'shipping_company' ){
						unset( $fields[$key] );
					}
				}
				return $fields;
			}
		
		//custom woocommerce "generated field" type (label)
		public function woocommerce_admin_field_label( $value )
		{
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php echo esc_html( $value['title'] ); ?></label>
				</th>
				<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
					<label
						id="<?php echo esc_attr( $value['id'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						style="<?php echo esc_attr( $value['css'] ); ?>"
					><?php echo ( $value['html'] )? $value['value'] : esc_html( $value['value'] ); ?></label>
				</td>
			</tr>
			<?php
		}
		//custom woocommerce "generated field" type (hidden)
		public function woocommerce_admin_field_hidden( $value )
		{
			?>
			<tr valign="top">
				<th style="padding:0"></th>
				<td style="padding:0">
					<input
						name="<?php echo esc_attr( $value['name'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						type="<?php echo esc_attr( $value['type'] ); ?>"
						value="<?php echo esc_attr( $value['value'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
					/>
				</td>
			</tr>
			<?php
		}

		public function email_settings( $settings = array() )
		{
			$i = 0;	$tempend = array();
			foreach( $settings as $setting ){
				if( $setting['type'] === "sectionend" ){
					$tempend = $setting;
					unset( $settings[$i] );
				}
				$i++;
			}
			
			$settings[] = array(
				'title'       => __( 'Corporate Info', 'woocommerce' ),
				'desc'        => __( 'Corporate information for required documents', 'woocommerce' ),
				'id'          => 'woocommerce_email_corpinfo_text',
				'css'         => 'width:500px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce' ),
				'type'        => 'textarea',
				'autoload'    => false,
				'desc_tip'    => true
			);
			$settings[] = array(
				'title'       => __( 'Secondary Info', 'woocommerce' ),
				'desc'        => __( 'Corporate Secondary information for required documents', 'woocommerce' ),
				'id'          => 'woocommerce_email_secondinfo_text',
				'css'         => 'width:500px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce' ),
				'type'        => 'textarea',
				'autoload'    => false,
				'desc_tip'    => true
			);

			$settings[] = array(
				'title'       => __( 'Receipt image', 'woocommerce' ),
				'desc'        => __( 'Url image for receipt template', 'woocommerce' ),
				'id'          => 'woocommerce_email_receipt_image',
				'placeholder' => __( 'N/A', 'woocommerce' ),
				'type'        => 'text',
				'autoload'    => false,
				'desc_tip'    => true
			);
			$settings[] = array(
				'title'       => __( 'Receipt Header Info', 'woocommerce' ),
				'desc'        => __( 'Receipt Header Info', 'woocommerce' ),
				'id'          => 'woocommerce_email_receipt_header_text',
				'css'         => 'width:500px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce' ),
				'type'        => 'textarea',
				'autoload'    => false,
				'desc_tip'    => true
			);
			/*$settings[] = array(
				'title'       => __( 'Receipt Footer Info', 'woocommerce' ),
				'desc'        => __( 'Receipt Footer Info', 'woocommerce' ),
				'id'          => 'woocommerce_email_receipt_footer_text',
				'css'         => 'width:500px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce' ),
				'type'        => 'textarea',
				'autoload'    => false,
				'desc_tip'    => true
			);*/
			$settings[] = $tempend;
			
			return $settings;
		}
		
	/*	====================================================
	 *	Current plugin action hooks
	 */
	public function perform_action_hook()
	{
			//common document listing
		add_action( 'warehouse_common_form_details', array( $this, 'warehouse_common_form_details' ), 10, 4 );
			
			//woocommerce admin orders add columns
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_shop_order_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column' , array( $this, 'custom_orders_list_column_content' ), 20, 2 );
	}
		
		public function warehouse_common_form_details( $columns, $details, $hides, $args = array() ){
			include_once( 'listing/doc-listing.php' );
			$inst = new WCWH_Doc_List( $columns, $details, $hides, $args );
			$inst->prepare_items();
			$inst->display();
		}
		
		public function custom_shop_order_column($columns)
		{
			$reordered_columns = array();

			// Inserting columns to a specific location
			foreach( $columns as $key => $column){
				$reordered_columns[$key] = $column;
				if( $key ==  'order_number' ){
					// Inserting after "Status" column
					$reordered_columns['customer_col'] = __( 'Customer','theme_domain');
				}
			}
			return $reordered_columns;
		}
		
		public function custom_orders_list_column_content( $column, $post_id )
		{
			switch ( $column )
			{
				case 'customer_col' :
					// Get custom post meta data
					$customer_id = get_post_meta( $post_id, 'customer_id', true );
					if( $customer_id )
					{
						$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [] );

						if( $customer )
						{
							echo $customer['name'].'<br>'.$customer['code'];
						}
					}

				break;
			}
		}
		
	/*	====================================================
	 *	Current plugin get info hooks
	 */
	public function get_info_hook(){
			//datas filter by search
		add_filter( 'listing_data_search_filter', array( $this, 'data_search_filter' ), 10, 3 );
		
			//get order docno
		add_filter( 'woocommerce_order_number', array( $this, 'get_order_docno' ), 10, 1);
		
			//get user data
		add_filter( 'warehouse_get_userdata', array( $this, 'get_userdata' ), 10, 2 );

			//woocommerce search filter
		add_filter( 'woocommerce_shop_order_search_fields', array( $this, 'woocommerce_shop_order_custom_search' ) );
	}
		
		/**
		 *	datas filter by search	//match cond "OR"
		 */
		public function data_search_filter( $datas, $search = array(), $cond = 'AND' ){	//$search = array( 'field' => 'keyword' )
			$filter = array();
		
			if( sizeof( $search ) <= 0 )	//no key to search? return ori data set
				return $datas;
			
			$i = 0;
			foreach ( $datas as $data ) {
				$match = array();
				foreach( $search as $key => $s ){
					if ( !is_array( $data[$key] ) && preg_match( "/{$s}/i", (string)$data[$key] ) )
						$match[$key] = true;

					else
						$match[$key] = false;
				}
				switch( strtoupper( $cond ) ){
					case "OR":
						$status = false;
						foreach( $match as $key => $stat ){
							if( $stat )
								$status = true;
						}
						if( $status )
							$filter[] = $datas[$i];
					break;
					case "AND":
						$status = true;
						foreach( $match as $key => $stat ){
							if( !$stat )
								$status = false;
						}
						if( $status )
							$filter[] = $datas[$i];
					break;
				}
				$i++;
			}
			return $filter;
		}
		
		/**
		 *	get order docno
		 */
		public function get_order_docno( $order_id ){
			if( !$order_id )
				return false;
			
			$docno = get_post_meta( $order_id, '_order_number', true );
			$order_id = ( !empty( $docno ) )? $docno : $order_id;
			
			return $order_id;
		}
		
		/**
		 *	get user data	by user id in users tbl
		 */
		public function get_userdata( $id, $field ){
			$user = get_userdata( $id );
			$data = '';
			switch( $field ){
				case 'name':
					$data = !empty( $user->first_name )? esc_attr( $user->first_name ).( !empty( $user->last_name )? ' '.esc_attr( $user->first_name ) : '' ) : esc_attr( $user->display_name );
				break;
			}
			
			return $data;
		}

		/**
		 *	order page meta filter
		 */
		public function woocommerce_shop_order_custom_search( $search_fields ) 
		{
		 	$search_fields[] = '_order_number';

		 	return $search_fields;
		}

}

new WCWH_Hooks( $refs );
}