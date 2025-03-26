<?php

if ( !defined("ABSPATH") )
    exit;
	
if ( !class_exists( "WCWH_Pages" ) )
{

class WCWH_Pages
{	
	protected $refs;
	protected $setting;
	protected $pg_id = 0;
	protected $section = 'default';
	public $description = '';

	protected $warehouse;

	public $Notices;

	public function __construct() 
	{
		global $wcwh;
		$this->refs = ( $refs )? $refs : $wcwh->get_plugin_ref();
		$this->setting = $wcwh->get_setting();

		$this->Notices = new WCWH_Notices();
	}

	public function __destruct()
	{
		unset($this->refs);
		unset($this->setting);
		unset($this->Notices);
		unset($this->warehouse);
		unset($this->description);
		unset($this->section);
	}

	public function set_page_id( $pg_id = 0 )
	{
		$this->pg_id = $pg_id;
	}

	public function set_section( $section )
	{
		$this->section = strtolower( $section );
	}

	public function set_description( $text )
	{
		$this->description = $text;
	}

	public function set_warehouse( $wh )
	{
		if( ! $wh ) return;

		if( is_array( $wh ) )
		{
			$this->warehouse = $wh;
		}
		else if( is_numeric( $wh ) )
		{
			$this->warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$wh, 'status'=>1 ] );
		}

		$wh = $this->warehouse;
		$this->warehouse['capability'] = ( !empty( $wh['capability'] ) && is_json( $wh['capability'] ) )? json_decode( $wh['capability'], true ) : array();

		$metas = get_warehouse_meta( $wh['id'] );
		$this->warehouse = $this->combine_meta_data( $this->warehouse, $metas );

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
			$this->setting = WCWH_Function::get_setting( '', '', $this->warehouse['id'] );
	}
		public function combine_meta_data( $datas = array(), $metas = array() )
		{
			if( ! $datas || ! $metas ) return $datas;

			foreach( $metas as $key => $value )
			{
				$datas[$key] = is_array( $value )? ( ( count( $value ) <= 1 )? $value[0] : $value ) : $value;
				if( is_json( $args['data'][$key] ) )
				{
					$datas[$key] = json_decode( $args['data'][$key], true );
				}
			}

			return $datas;
		}

	protected function authorized( $succ = true )
	{
		if( ! $this->pg_id || empty( $_REQUEST['page'] ) || $_REQUEST['page'] != $this->pg_id )
		{ 
			$this->Notices->set_notice( 'unauthorized', 'error' );
			$succ = false;
		}

		//if( defined( 'WCWH_DEBUG' ) && WCWH_DEBUG )
			//$this->Notices->set_notice( 'debug', 'info' );
		
		return $succ;
	}

	public function enqueue()
	{
		//wp_enqueue_style( 'bootstrap-style' );
		
		wp_enqueue_script( 'popper.min' );
		wp_enqueue_script( 'bootstrap.min' );
		wp_enqueue_script( 'detect-agent' );
		wp_enqueue_script( 'jquery_select2' );
		wp_enqueue_script( 'jquery-ui' );
		//wp_enqueue_script( 'datedropper' );
		wp_enqueue_script( 'datepicker' );
		wp_enqueue_script( 'jquery_validate' );
		wp_enqueue_script( 'jquery_numeric' );
		wp_enqueue_script( 'jquery_barcodelistener' );
		wp_enqueue_script( 'wc-pos-js-barcode' );
		wp_enqueue_script( 'wc-pos-js-qr-code' );
		wp_enqueue_script( 'qr-scan' );
		wp_enqueue_script( 'chartjs' );
		wp_enqueue_script( 'randcolor' );
		wp_enqueue_style('thickbox');
		wp_enqueue_script('media-upload');
		wp_enqueue_script('thickbox');
		wp_enqueue_script('jquery_blockUI');
		
		wp_enqueue_script( 'wcwh-main-scripts' );
		wp_enqueue_script( 'custom-script' );
	}


	/**
	 *	Pages 	
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function page()
	{
		do_action( 'wcwh_page_init' );

		$this->enqueue();
		
		$succ = true;
		if( $succ ) $succ = $this->authorized();
		//print_data($this->warehouse);
	?>
		<div class="wcwh-main wrap wcwh-page">
			<h2>
				<?php echo get_admin_page_title(); ?>
				<?php if( $this->description ) echo '<sup class="toolTip" title="'.$this->description.'"> ? </sup>'; ?>
			</h2>
			
			<div class="wcwh-container">
			<?php
				if( $succ )
				{
					$section = $this->section;
					if( method_exists( $this, $section ) )
						$this->$section();
					else
						$this->default_section();
				}
			?>
			</div>
		</div>
	<?php
		if( $succ )
		{
			$this->load_segments();
		}

	?>
		<div class="notice-container">
			<?php if( is_admin() ) $this->Notices->notices(); ?>
		</div>
		<a class="scrollTo btn btn-xs btn-primary" data-target="#wpbody" title="Back To Top">&nbsp;<i class="fa fa-chevron-up" aria-hidden="true"></i>&nbsp;</a>
	<?php
	}
		public function load_segments()
		{
			do_action( 'wcwh_get_template', 'segment/modalForm.php' );
			do_action( 'wcwh_get_template', 'segment/modalView.php' );
			do_action( 'wcwh_get_template', 'segment/modalList.php' );
			do_action( 'wcwh_get_template', 'segment/modalConfirm.php' );
			do_action( 'wcwh_get_template', 'segment/modalPrint.php' );
			do_action( 'wcwh_get_template', 'segment/modalImEx.php' );
			do_action( 'wcwh_get_template', 'segment/modalReview.php' );
			do_action( 'wcwh_get_template', 'segment/qr-barcode.php' );
			do_action( 'wcwh_get_template', 'segment/modalOpts.php' );

			$notice = [ 'dismissable' => true, 'notice_type' => '{noticeType}', 'message' => '{noticeMessage}' ];
			do_action( 'wcwh_templating', 'segment/notice.php', 'notice', $notice );

			$args = [ 'token' => apply_filters( 'wcwh_generate_token', $this->section_id ) ];
			do_action( 'wcwh_templating', 'form/remark-form.php', 'remark', $args );

			do_action( 'wcwh_templating', 'segment/option.php', 'modalOption', $notice );
		}


	/**
	 *	Default Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function default_section()
	{
		$this->Notices->set_notice( 'oops', 'info' );
	}

	public function wh_debug()
	{
		global $wpdb;
		define( "developer_debug", 1 );

		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => 'Debuging',
				'sync' => 'Sync',
				//'log' => 'Log',
				'item_converse' => 'Item Converse',
				'credit_report' => 'Credit Report',
				//'customer_count' => 'Customer Count',
				'margining_sale' => 'Margining Sale',
				'pos_correction' => 'POS Correction',
				'upload_photo'=>'Upload Photos',
				'customer_photos' => 'Customer Photos',
				'weighted_migrate' => 'Weighted Migration',
				'weighted_test' => 'Weighted Test',
			],	//key=>title 
		];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		switch( strtolower( $onTab ) )
		{
			case 'sync':
				//debug schedule action
				wcwh_scheduled_actions();
			break;
			case 'log':
				global $wpdb;

				$fld = "a.* ";
				$tbl = "{$wpdb->prefix}wcwh_activity_log a ";
				$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";
				$results = $wpdb->get_results( $sql , ARRAY_A );
				
				if( $results )
				{	
					foreach( $results as $i => $values )
					{	
						echo "Log ID: ".$values['id']."<br>";
						echo "Section: ".$values['section']."<br>";
						echo "Action: ".$values['action']."<br>";
						$ref_id = 0;

						if( $values['data'] )
						{
							$values['data'] = json_decode( $values['data'], true );

							if( $values['data'] )
							{
								foreach( $values['data'] as $key => $vals )
								{
									if( in_array( $key, [ 'id', 'ID', 'doc_id' ] ) )
									{
										$ref_id = $vals;
									}
								}
							}

							if( ! $ref_id && !empty( $values['data']['form'] ) )
							{	
								if( ! is_array( $values['data']['form'] ) )
								{
									$param = urldecode( $values['data']['form'] );
									parse_str($param, $output);
								}
								else
								{
									$output = $values['data']['form'];
								}
								
								if( $output )
								{
									foreach( $output as $key => $vals )
									{
										if( is_array( $vals ) )
										{
											foreach( $vals as $k => $v )
											{
												if( in_array( $k, [ 'id', 'ID', 'doc_id' ] ) )
												{
													$ref_id = $v;
												}
											}
										}
										else
										{
											if( in_array( $key, [ 'id', 'ID', 'doc_id' ] ) )
											{
												$ref_id = $vals;
											}
										}

										if( $ref_id != 0 ) break;
									}
								}
							}

							if( $ref_id && $ref_id > 0 )
							{
								$wpdb->update( 
									"{$wpdb->prefix}wcwh_activity_log", 
									[ 'ref_id' => $ref_id ], 
									[ 'id' => $values['id'] ] 
								);
							}
							echo '<hr>';
						}
					}
				}
			break;
			case 'item_converse':
				if( $_POST['submit'] )
				{
					$items = apply_filters( 'wcwh_get_item', [ 'status'=>'all' ], [], false, [] );
					if( $items )
					{
						if ( !class_exists( "WCWH_Item_Class" ) ) include_once( WCWH_DIR . "/includes/classes/item.php" ); 
						$Inst = new WCWH_Item_Class();

						foreach( $items AS $i => $item )
						{
							$Inst->item_converse( $item['id'] );
						}
					}
				}
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Update All Item Converse</h4>
						
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
			break;
			case 'reset_wcwh_export':
				if( $_GET['reset_directory'] )
				{
					$dir = wp_upload_dir( null, true );
					$dirname = $dir['basedir'].'/wcwh_export';
					$files = glob( $dirname.'/*' ); // get all file names
					foreach( $files as $file )
					{
						if( is_file( $file ) ) 
						{
							unlink( $file );
						}
					}
				}
			break;
			case 'credit_report':
				if( $_POST['submit'] && isset( $_REQUEST['credit_report'] ) && !empty( $_REQUEST['credit_report'] ) )
				{
					update_option( 'wcwh_debug_credit_report_export', $_REQUEST['credit_report'] );
					
					if( ! class_exists( 'WCWH_CustomerCredit_Rpt' ) ) include_once( WCWH_DIR . "/includes/reports/customerCredit.php" ); 
					$Inst = new WCWH_CustomerCredit_Rpt();
					$Inst->exportDirectory = true;

					foreach( $_REQUEST['credit_report'] as $id => $vals )
					{
						if( !empty( $vals['from'] ) && !empty( $vals['to'] ) )
						{
							$curMonth = $_REQUEST['month'].'-'.$vals['to'];
							$prevMonth = date( 'Y-m', strtotime( $_REQUEST['month']." -1 month" ) ).'-'.$vals['from'];
							$f = [
								'seller' => $_REQUEST['seller'],
								'from_date' => $prevMonth,
								'to_date' => $curMonth,
								'acc_type' => $id,
								'export_type' => 'summary',
							];
							$Inst->action_handler( 'export', $f );
						}
					}
				}

				$opts = get_option( 'wcwh_debug_credit_report_export', [] );

				$acc_types = apply_filters( 'wcwh_get_account_type', [], [], false, [] );

				$sellers = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], false, [ 'usage'=>1 ] );
				$seller_opts = options_data( $sellers, 'id', [ 'code', 'name' ], '' );

				$dirname = get_site_url().'/wp-content/uploads/wcwh_export';

				$params = $_GET;
				$params['tab'] = 'reset_wcwh_export';
				$params['reset_directory'] = 1;
				$reset = admin_url( "admin.php".add_query_arg( $params, '' ) );
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Bulk Export Credit Report Setting</h4>
						<a href="<?php echo $dirname; ?>">Export Directory</a> | 
						<a href="<?php echo $reset; ?>">Reset Directory</a>
						<div class="form-row">
				        	<div class="col form-group">
							<?php
				        		wcwh_form_field( 'month', 
				                    [ 'id'=>'month', 'type'=>'text', 'label'=>'To Month', 'required'=>false, 'class'=>['doc_date', 'picker'],
										'attrs'=>[ 'data-dd-hide-day=1', 'data-dd-format="Y-m"' ], 'offClass'=>true
				                    ], 
				                    isset( $_REQUEST['month'] )? $_REQUEST['month'] : '', $view 
				                ); 
						    ?>
							</div>
							<div class="col form-group">
							<?php
				        		wcwh_form_field( 'seller', 
				                    [ 'id'=>'seller', 'type'=>'select', 'label'=>'Outlet', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
				                        'options'=> $seller_opts, 'offClass'=>true
				                    ], 
				                	$seller, $view 
				                ); 
						    ?>
							</div>
						</div>
						<?php
							if( $acc_types )
				        	{
				        		foreach( $acc_types as $i => $acc_type )
				        		{
				        		?>
				        			<div class="form-row">
				        				<div class="col form-group">
				        		<?php
				        			wcwh_form_field( 'credit_report['.$acc_type['id'].'][from]', 
						                [ 'id'=>'', 'type'=>'number', 'label'=>$acc_type['code'].' FROM', 'required'=>false, 'attrs'=>[], 'class'=>[],
					                    	'placeholder'=>'Day From', 'description'=>'' ], 
						                $opts[$acc_type['id']]['from'], $view 
						            ); 
						        ?>
						        		</div>
						        		<div class="col form-group">
						        <?php
						            wcwh_form_field( 'credit_report['.$acc_type['id'].'][to]', 
						                [ 'id'=>'', 'type'=>'number', 'label'=>$acc_type['code'].' To', 'required'=>false, 'attrs'=>[], 'class'=>[],
					                    	'placeholder'=>'Day To', 'description'=>'' ], 
						                $opts[$acc_type['id']]['to'], $view 
						            ); 
				        		?>
				        				</div>
				        			</div>
				        		<?php
				        		}
				        	}
						?>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
			break;
			case 'customer_count':
				global $wpdb;

				$sql = "SELECT a.id, a.code, ma.meta_value AS seq
				FROM {$wpdb->prefix}wcwh_customer a 
				LEFT JOIN {$wpdb->prefix}wcwh_customermeta ma ON ma.customer_id = a.id AND ma.meta_key = 'serial_seq' ";

				$result = $wpdb->get_results( $sql , ARRAY_A );
				if( $result )
				{
					foreach( $result as $i => $row )
					{
						$seq = ( $row['seq'] )? $row['seq'] : 1;
						for( $j = 1; $j <= $seq ;$j++ )
						{
							$serial = $row['code'].str_pad( $j, 3, '0', STR_PAD_LEFT );echo "<br>";
							$cc = apply_filters( 'wcwh_update_customer_count', $row['id'], $serial, 0, 0, '+' );
							$cc = apply_filters( 'wcwh_update_customer_count', $row['id'], $serial, 0, 0, '-' );
						}
					}
				}
			break;
			case 'margining_sale':
				if ( !class_exists( "WCWH_StockMovementWA_Class" ) ) require_once( WCWH_DIR . "/includes/classes/stock-movement-wa.php" );
				$Inst = new WCWH_StockMovementWA_Class();

				$Inst->stock_movement_handler( '1025-MWT3', 10110 );

				/*$from_month = '2020-11';
				$to_month = '2021-11';
				$month = $from_month; 
		        while( $month !== date( 'Y-m', strtotime( $to_month." +1 month" ) ) )
		        {
		            $succ = $Inst->margining_sales_handling( $month, '1025-MWT3', 'def' );

		            $month = date( 'Y-m', strtotime( $month." +1 month" ) );
		        }*/
				
			break;
			case 'pos_correction':
				if( $_POST['submit'] && $_REQUEST['product_from'] > 0 && $_REQUEST['product_to'] > 0 )
				{
					@set_time_limit(3600);
					
					$product_from = ( $_REQUEST['product_from'] )? $_REQUEST['product_from'] : '';
					$product_to = ( $_REQUEST['product_to'] )? $_REQUEST['product_to'] : '';

					global $wpdb;

					$cond = $wpdb->prepare( "AND i.id = %s ", $product_to );
					$sql = "SELECT p.ID AS post_id, i.*
						FROM {$wpdb->prefix}wcwh_items i
						LEFT JOIN ( 
							SELECT a.ID, pa.meta_value AS item_id
							FROM {$wpdb->prefix}posts a 
							LEFT JOIN {$wpdb->prefix}postmeta pa ON pa.post_id = a.ID AND pa.meta_key = 'item_id'
							WHERE 1 AND a.post_type = 'product'
						) p ON p.item_id = i.id
						WHERE 1 {$cond} ";

					$to = $wpdb->get_row( $sql , ARRAY_A );
					if( $to )
					{
						$cond = $wpdb->prepare( "AND ma.meta_value = %s ", $product_from );
						$sql = "SELECT a.ID, b.order_item_id, aa.meta_value AS warehouse_id, ab.meta_value AS docno
							, ac.meta_value AS customer_id, a.post_date AS order_date
							, ma.meta_value AS item_id, mb.meta_value AS prdt_id, mc.meta_value AS uom, md.meta_value AS qty 
							, me.meta_value AS unit, mf.meta_value AS uprice, mg.meta_value AS price, mh.meta_value AS total
							FROM {$wpdb->prefix}posts a 
							LEFT JOIN {$wpdb->prefix}postmeta aa ON aa.post_id = a.ID AND aa.meta_key = 'wc_pos_warehouse_id'
							LEFT JOIN {$wpdb->prefix}postmeta ab ON ab.post_id = a.ID AND ab.meta_key = '_order_number'
							LEFT JOIN {$wpdb->prefix}postmeta ac ON ac.post_id = a.ID AND ac.meta_key = 'customer_id'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_items b ON b.order_id = a.ID
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta ma ON ma.order_item_id = b.order_item_id AND ma.meta_key = '_items_id'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta mb ON mb.order_item_id = b.order_item_id AND mb.meta_key = '_product_id'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta mc ON mc.order_item_id = b.order_item_id AND mc.meta_key = '_uom'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta md ON md.order_item_id = b.order_item_id AND md.meta_key = '_qty'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta me ON me.order_item_id = b.order_item_id AND me.meta_key = '_unit'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta mf ON mf.order_item_id = b.order_item_id AND mf.meta_key = '_uprice'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta mg ON mg.order_item_id = b.order_item_id AND mg.meta_key = '_price'
							LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta mh ON mh.order_item_id = b.order_item_id AND mh.meta_key = '_line_total'
							WHERE 1 AND a.post_type = 'shop_order' AND a.post_status IN ( 'wc-processing', 'wc-completed' ) {$cond} ";

						$result = $wpdb->get_results( $sql , ARRAY_A );
						if( $result )
						{
							$deletes = []; $price_logs = [];
							foreach( $result as $row )
							{
								$header_item = [ 'doc_type'=>'sales', 'warehouse_id'=> $row['warehouse_id'] ];
								$strg = apply_filters( 'wcwh_get_system_storage', 0, $header_item, $row );

								$rowd = array(
									'sales_item_id'   	=> $row['order_item_id'], 
									'warehouse_id' 		=> $row['warehouse_id'], 
									'strg_id'			=> $strg,
								);
								$deletes[] = $rowd;
								
								$rowa = array(
									'sales_item_id'   	=> $row['order_item_id'], 
									'order_id'   		=> $row['ID'], 
									'docno'				=> $row['docno'],
									'warehouse_id' 		=> $row['warehouse_id'], 
									'strg_id'			=> $strg,
									'customer' 			=> ( $row['customer_id'] > 0 )? $row['customer_id'] : 0,
									'sales_date' 		=> $row['order_date'], 
									'prdt_id' 			=> $to['id'],
									'uom'				=> $to['_uom_code'],
									'qty' 				=> $row['qty'],
									'unit'				=> ( $row['unit'] )? $row['qty'] * $row['unit'] : 0,
									'uprice' 			=> $row['uprice'],
									'price' 			=> $row['price'],
									'total_amount' 		=> $row['total'],
								);
								$price_logs[] = $rowa;
								
								wc_update_order_item_meta( $row['order_item_id'], '_items_id', $to['id'] );
								wc_update_order_item_meta( $row['order_item_id'], '_product_id', $to['post_id'] );
								wc_update_order_item_meta( $row['order_item_id'], '_uom', $to['_uom_code'] );
							}
							rt($deletes);rt($price_logs);

							if( $deletes )
							{
								$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'delete' , $deletes );
							}

							if( $price_logs )
							{
								$succ = apply_filters( 'warehouse_stocks_sprice_action_filter' , 'save' , $price_logs );
							}
						}
					}
				}
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>POS Transfer Item</h4>

						<div class="segment col-md-4">
							<label class="" for="flag">From Item </label><br>
							<?php
								$filters = [];
								$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );
								
				                wcwh_form_field( 'product_from', 
				                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
				                        'options'=> $options,
				                    ], 
				                    $product_from, $view 
				                ); 
							?>
						</div>

						<div class="segment col-md-4">
							<label class="" for="flag">To Item </label><br>
							<?php
								$filters = [];
								//$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );
								
				                wcwh_form_field( 'product_to', 
				                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
				                        'options'=> $options,
				                    ], 
				                    $product_to, $view 
				                ); 
							?>
						</div>
						
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
			break;
			case 'upload_photo':
				$succ = false;
				$files = $_FILES;
				$customer = $_REQUEST['customer'];
				if( $_POST['submit'] && $files>0 && $customer>0)
				{
					if(sizeof($files['attachments']['name'])!=sizeof($customer))
					{
						$this->Notices->set_notice( 'Number of photo and customer did not match', 'warning' );
					}else{
						if( !empty( $files ))
							{
								$Inst = new WCWH_Files();
								if ( !class_exists( "WCWH_Customer_Class" ) ) include_once( WCWH_DIR . "/includes/classes/customer.php" ); 
								$Inst_c = new WCWH_Customer_Class( $this->db_wpdb );
								$filename = [];
								$error_count=0;
								foreach($files['attachments']['name'] as $key => $value)
								{
									$filename[$key] = strstr($value,'.',true);
								}

								foreach($customer as $key=>$value)
								{
									$cus = apply_filters( 'wcwh_get_customer', ['id'=>$value], [], true, [ 'usage'=>1 ] );
									$arr_key = array_search(strtolower($cus['uid']),$filename);
									if($arr_key>-1)
									{
										$split_files=[];
										$split_files['attachments']['name'][0] = $files['attachments']['name'][$arr_key];
										$split_files['attachments']['type'][0] = $files['attachments']['type'][$arr_key];
										$split_files['attachments']['tmp_name'][0] = $files['attachments']['tmp_name'][$arr_key];
										$split_files['attachments']['error'][0] = $files['attachments']['error'][$arr_key];
										$split_files['attachments']['size'][0] = $files['attachments']['size'][$arr_key];

										$result = $Inst->upload_files( $split_files, 'wh_customer', $value );
										if( $result )
										{
											$succ = $Inst->attachment_handler( $result,'wh_customer', $value, true );
											if($succ) $Inst_c->update_metas( $value, ['attachment'=> maybe_serialize( $result )] );
											
										}
									}else
									{
										$this->Notices->set_notice( $cus['code'].' does not have matched filename', 'error' );
										$error_count +=1;
										echo $cus['code']. ", ";
										continue;
									}
									
								}

								if($error_count)
								{
									if($succ)$this->Notices->set_notice( sizeof($customer) - $error_count.' out of '.sizeof($customer).' customer photo successfully uploaded', 'warning' );
								}else
								{
									if($succ)$this->Notices->set_notice( 'success', 'success' );
								}
								
								
								
							}
					}
				}
				?>
				<form action="" method="post" enctype="multipart/form-data">
				    <div class='form-rows-group'>
				        <h4>Bulk Upload Photo</h4>
				        <div class="form-row">
				            <div class="col form-group">

				                <?php
								$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
							wcwh_form_field( 'customer[]', 
					                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
					                        'options'=> $options, 'multiple'=>1
					                    ], 
					                    isset( $_REQUEST['customer'] )? $_REQUEST['customer'] : '', $view 
					                );
									?>
				            </div>
				        </div>
						<div class="form-row">
				            <div class="col form-group">

							<?php 
				                wcwh_form_field( 'attachments[]', 
				                    [ 'id'=>'', 'type'=>'file', 'label'=>'Photo', 'required'=>false, 'attrs'=>['accept = image/*'], 'multiple'=>1], 
				                     '', $view 
				                ); 

				                if( $_REQUEST['attachments'] )
				                {
				                    ?>
				                    <table class="wp-list-table widefat striped">
				                    <?php
				                    // foreach( $_REQUEST['attachments'] as $i => $attach )
				                    // {
				                    //     $attach['i'] = $i;
				                    //     $attach['view'] = $view;
				                    //     $tpl = apply_filters( 'wcwh_get_template_content', 'segment/attachments-row.php', $attach );

				                    //     echo $tpl = str_replace( $find, $replace, $tpl );

				                    //     echo "<br>";
				                    // }
				                    ?>
				                    </table>
				                    <?php
				                }
				            ?>
								</div>
								</div>
				        <div class="form-row">
				            <div class="col form-group">
				                <?php submit_button( 'Submit' ); ?>
				            </div>
				        </div>

				</form>
			<?php
			break;
			case 'customer_photos':
				if( $_POST['submit'] )
				{
					$is_debug = false;
					
					$filter = $_REQUEST['customer'];
					if( $filter )
					{
						$customers = apply_filters( 'wcwh_get_customer', [ 'id'=>$filter ], [], false, [ 'usage'=>1 ] );
					}
					else
					{
						$customers = apply_filters( 'wcwh_get_customer', [], [], false, [] );
					}
					//rt($customer);

					$directory = wp_upload_dir();
					$dir_path = $directory['basedir'];
					$dir_path.= "/tki_photos/*.*";

					$all_files = glob( $dir_path ); $imgs_group = [];
					foreach( $all_files as $i => $file )
					{
						$filename = basename( $file );
						
						$text = str_replace( " ", "_", $filename );
						preg_match_all( '/[0-9]+/', $text, $matches );
						$possible_uids = [];
						if( $matches && $matches[0] )
						{
							foreach( $matches[0] as $match )
							{
								$match = substr( $match, -6 )."<br>";
								$possible_uids[] = trim( ltrim( $match, "0" ) );
							}
						}
						
						preg_match_all( '/[a-zA-Z ]+/', $filename, $matches );
						$possible_text = [];
						if( $matches && $matches[0] )
						{
							foreach( $matches[0] as $match )
							{
								$possible_text[] = trim( strtoupper( $match ) );
							}
						}

						$imgs_group[] = [
							'uid' => $possible_uids,
							'text' => $possible_text,
							'filename' => $filename,
							'ext' => strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ),
							'path' => $file,
							'time' => filemtime($file),
							'type' => filetype($file),
							'size' => filesize($file),
						];
					}
					
					if( $customers && $imgs_group )
					{
						$Inst = new WCWH_Files();
						if ( !class_exists( "WCWH_Customer_Class" ) ) include_once( WCWH_DIR . "/includes/classes/customer.php" ); 
						$Inst_c = new WCWH_Customer_Class( $this->db_wpdb );

						$debug = []; $success = []; $failed = []; $not_found = [];
						foreach( $customers as $customer )
						{
							$uid = trim( ltrim( substr( $customer['uid'], -6 ), "0" ) )."<br>";
							if( $uid )
							{	
								$tki_imgs = []; $latest_img = []; $name_img = [];
								foreach( $imgs_group as $j => $img )
								{
									if( in_array( $uid, $img['uid'] ) )
									{
										$tki_imgs[] = $img;
										if( sizeof($latest_img) <= 0 || 
											( sizeof($latest_img) > 0 && $img['time'] > $latest_img['time'] ) 
										)
										{
											$latest_img = $img;
										}
									}
									
									if( in_array( trim( strtoupper( $customer['name'] ) ), $img['text'] ) )
									{
										if( sizeof($name_img) <= 0 || 
											( sizeof($name_img) > 0 && $img['time'] > $name_img['time'] ) 
										)
										{
											$name_img = $img;
										}
									}
								}
								
								if( ! $latest_img && $name_img ) 
								{
									$latest_img = $name_img;
								}
								
								$attched = get_customer_meta( $customer['id'], 'attachment', true );
								
								if( $latest_img && empty( $attched ) )
								{
									$upload = [];
									$upload['attachments']['name'][0] = $latest_img['filename'];
									$upload['attachments']['type'][0] = $latest_img['type'];
									$upload['attachments']['tmp_name'][0] = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $latest_img['path'] );
									$upload['attachments']['error'][0] = 0;
									$upload['attachments']['size'][0] = $latest_img['size'];
									
									if( ! $is_debug )
									{
										$result = $Inst->copy_files( $upload, 'wh_customer', $customer['id'] );
										if( $result )
										{
											$success[] = [
												'id' => $customer['id'],
												'name' => $customer['name'],
												'uid' => $customer['uid'],
												'code' => $customer['code'],
												'image' => $upload,
											];
											$succ = $Inst->attachment_handler( $result, 'wh_customer', $customer['id'], true );
											if($succ) $Inst_c->update_metas( $customer['id'], ['attachment'=> maybe_serialize( $result )] );
										}
										else
										{
											$failed[] = [
												'id' => $customer['id'],
												'name' => $customer['name'],
												'uid' => $customer['uid'],
												'code' => $customer['code'],
												'image' => $upload,
											];
										}
									}										
									else
									{
										$debug[] = [
											'id' => $customer['id'],
											'name' => $customer['name'],
											'uid' => $customer['uid'],
											'code' => $customer['code'],
											'image' => $upload,
										];
									}
								}
								else
								{
									$not_found[] = [
										'code' => $customer['code'],
										'id' => $customer['id'],
										'name' => $customer['name'],
										'uid' => $customer['uid'],
									];
								}
							}
						}
						
						if( $is_debug )
						{
							echo "Debug ".sizeof($debug)."<br>";
							pd($debug);
						}
						
						echo "Success ".sizeof($success)."<br>";
						pd( $success );
						echo "Failed ".sizeof($failed)."<br>";
						pd( $failed );
						echo "Not Found ".sizeof($not_found)."<br>";
						rt( $not_found );
					}
				}
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Customer bulk upload image</h4>
						<div class="form-row">
							<div class="col form-group">
					            <?php
									$options = options_data( apply_filters( 'wcwh_get_customer', $filter, [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'uid', 'name', 'status_name' ], '' );
									wcwh_form_field( 'customer[]', 
						                [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
						                        'options'=> $options, 'multiple'=>1
						                ], 
						                isset( $_REQUEST['customer'] )? $_REQUEST['customer'] : '', $view 
						            );
								?>
					        </div>
				    	</div>
						
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
			break;
			case 'weighted_migrate':
				include_once( WCWH_DIR . "/includes/classes/migrate.php" );

				$Inst = new WC_Weighted_Migrate();
				$Inst->looping();
				//$Inst->migrate_handler();
			break;
			case 'weighted_test':
				include_once( WCWH_DIR . "/includes/classes/migrate.php" );

				$Inst = new WC_Weighted_Migrate();
				$Inst->test();
			break;
			case '':
			default:
				echo "<h4>For Developer Testing / Debuging</h4>";
				
				//maybe using
				//update_option( 'EGT_warehouse', maybe_serialize( [ '1009-PMN', '1018-IFP', '1024-VPK', '1037-UBB', '1036-TSM' ] ) );
				//update_option( 'EGT_warehouse_item', maybe_serialize( [ 'H0001443' ] ) );
				
				include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Inst = new WCWH_PurchaseRequest_Controller();
				$Inst->pos_purchase_request();
			break;
		}
	}

	public function wh_check()
	{
		global $wpdb;
		define( "developer_check", 1 );

		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => 'Testing',
				'price' => 'price',
				'customer' => 'Customer',
				'last_cron' => 'Last Cron',
				'period' => 'Period',
				'check_mailer' => "Check Mailer",
				'remote' => 'Remote',
				'check_db_table' => 'Check DB',
				//'doc_integration' => 'DOC integration',
			],	//key=>title 
		];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		switch( strtolower( $onTab ) )
		{
			case 'price':
				$sellers = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1 ] );
				$seller_opts = options_data( $sellers, 'code', [ 'code', 'name' ], '' );

				$client_opts = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );

				$seller = ( $_REQUEST['seller'] )? $_REQUEST['seller'] : '';
				$client = ( $_REQUEST['client_code'] )? $_REQUEST['client_code'] : '';
				$on_date = ( $_REQUEST['on_date'] )? $_REQUEST['on_date'] : '';
				$product = ( $_REQUEST['product'] )? $_REQUEST['product'] : '';
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check Pricing</h4>
						<div class="segment col-md-4">
							<label class="" for="flag">By Seller</label>
							<?php
								wcwh_form_field( 'seller', 
				                    [ 'id'=>'seller', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
				                        'options'=> $seller_opts, 'offClass'=>true
				                    ], 
				                	$seller, $view 
				                ); 
							?>
						</div>
						<div class="segment col-md-4">
							<label class="" for="flag">By Client</label>
							<?php
								wcwh_form_field( 'client_code', 
				                    [ 'id'=>'client_code', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
				                        'options'=> $client_opts, 'offClass'=>true
				                    ], 
				                    $client, $view 
				                ); 
							?>
						</div>
						<div class="segment col-md-4">
							<label class="" for="flag">Price On Date</label><br>
							<?php
								wcwh_form_field( 'on_date', 
				                    [ 'id'=>'on_date', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>['doc_date', 'picker'], 
										'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'offClass'=>true
				                    ], 
				                    $on_date, $view 
				                ); 
							?>
						</div>
						<div class="segment col-md-4">
							<label class="" for="flag">By Item </label><br>
							<?php
								$filters = [];
								$options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );
								
				                wcwh_form_field( 'product', 
				                    [ 'id'=>'', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
				                        'options'=> $options,
				                    ], 
				                    $product, $view 
				                ); 
							?>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
				$price = apply_filters( 'wcwh_get_price', $product, $seller, [ 'client_code'=>$client ], $on_date );
				pd( $price );
			break;
			case 'customer':
				$customer_id = ( $_REQUEST['customer_id'] )? $_REQUEST['customer_id'] : 1;
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check Customer Credit Info</h4>
						<div class="form-row">
	        				<div class="col form-group">
							<?php
								$options = options_data( apply_filters( 'wcwh_get_customer', [ 'status'=>1 ] ), 'id', [ 'code', 'uid', 'name' ] );
			                
				                wcwh_form_field( 'customer_id', 
				                    [ 'id'=>'', 'type'=>'select', 'label'=>'Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
				                        'options'=> $options
				                    ], 
				                    $customer_id, $view 
				                ); 
							?>
							</div>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
				/* Check Customer */
				if( $customer_id )
				{
					$customer = apply_filters( 'wcwh_get_customer', [ 'id'=>$customer_id ], [], true, [ 'parent'=>1, 'company'=>1, 'group'=>1, 'usage'=>1 ] );

					if( $customer )
					{
						$customer['active'] = true;
						$customer['sapuid'] = get_customer_meta( $customer_id, 'sapuid', true );
						$customer['sapuid_date'] = get_customer_meta( $customer_id, 'sapuid_date', true );
					}
					else
					{
						$customer['active'] = false;
					}
					
					$user_credits = apply_filters( 'wc_credit_limit_get_client_credits', $customer_id, $customer );
					pd($user_credits);
					echo "<br/>";
				}
			break;
			case 'last_cron':
				$whs = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'meta'=>['dbname'] ] );
				if( $whs )
				{
					global $wpdb;
					
					$union = [];
					$sql = "SELECT 'SELF' AS wh, a.* FROM {$wpdb->prefix}options a WHERE a.option_name = 'wcwh_last_scheduled' ";
					$union[] = $sql;
					foreach( $whs as $wh )
					{
						if( $wh['parent'] > 0 && ! empty( $wh['dbname'] ) )
						{
							$sql = "SELECT '{$wh['code']}' AS wh, a.* FROM {$wh['dbname']}.{$wpdb->prefix}options a WHERE a.option_name = 'wcwh_last_scheduled' ";
							$union[] = $sql;
						}
					}
					
					if( $union )
					{
						$query = implode( "  UNION ALL  ", $union );
						
						$result = $wpdb->get_results( $query , ARRAY_A );
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
						rt($result);
					}
				}

				$sync = apply_filters( 'wcwh_get_sync', ['handshake'=>0, 'status'=>1], false, [] );
				echo "<br><br><h4>Unsynced ".count($sync)."</h4>";
				rt($sync);

				$sql = "SELECT h.doc_id, h.docno, h.doc_date, h.post_date, h.status, h1.meta_value AS target
				FROM {$wpdb->prefix}wcwh_document h 
				LEFT JOIN {$wpdb->prefix}wcwh_document_meta h1 ON h1.doc_id = h.doc_id AND h1.item_id = 0 AND h1.meta_key = 'supply_to_seller'
				WHERE 1 AND h.doc_type = 'delivery_order' AND h.status >= 6 
				AND ( h1.meta_value IS NOT NULL AND h1.meta_value != '' ) AND h1.meta_value != '1025-MWT3'
				AND h.doc_id NOT IN (
					SELECT s.ref_id
					FROM {$wpdb->prefix}wcwh_syncing s 
					WHERE 1 AND s.section = 'wh_delivery_order' AND s.direction = 'out' AND s.status > 0
				)
				AND h.doc_date >= '2024-11-01'";
				$result = $wpdb->get_results( $sql , ARRAY_A );
				echo "<br><br><h4>Missed Queue DO</h4>";
				rt($result);
			break;
			case 'period':
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
				$seller = ( $_REQUEST['seller'] )? $_REQUEST['seller'] : $wh['id'];
				$date = ( $_REQUEST['date'] )? $_REQUEST['date'] : current_time('Y-m-d');
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check Period</h4>
						<div class="form-row">
							<div class="col form-group">
							<?php
								$options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] ), 'id', [ 'code', 'name' ] );
                
				                wcwh_form_field( 'seller', 
				                    [ 'id'=>'', 'type'=>'select', 'label'=>'Seller', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
				                        'options'=> $options
				                    ], 
				                    $seller, $view 
				                ); 
							?>
							</div>
	        				<div class="col form-group">
							<?php
								wcwh_form_field( 'date', 
				                    [ 'id'=>'', 'type'=>'text', 'label'=>'Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
				                    $_REQUEST['date'], $view 
				                ); 
							?>
							</div>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
				/* Check Credit Period */
				$range = apply_filters( 'wcwh_get_credit_period', 26, 0, 1, $date, $seller );
				pd($range);
				echo "<br/>";
			break;
			case 'check_mailer':
				$recipient = ( $_REQUEST['recipient'] )? $_REQUEST['recipient'] : '';
				if( !empty( $recipient ) )
				{
					$args = [
						'recipient' => $recipient,
						'message' => "Mailing Function Testing, Please Ignore.",
					];
					do_action( 'wcwh_set_email', $args );
					do_action( 'wcwh_trigger_email', [] );
				}

				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check Mailer</h4>
						<div class="form-row">
	        				<div class="col form-group">
							<?php
								wcwh_form_field( 'recipient', 
				                    [ 'id'=>'', 'type'=>'text', 'label'=>'Recipient (email)', 'required'=>false, 'attrs'=>[], ], 
				                    $_REQUEST['recipient'], $view 
				                ); 
							?>
							</div>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
			break;
			case 'remote':
				$remote_url = $_REQUEST['url'];
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check Remote Connection</h4>
						<div class="form-row">
	        				<div class="col form-group">
							<?php
								wcwh_form_field( 'url', 
				                    [ 'id'=>'', 'type'=>'text', 'label'=>'URL', 'required'=>false ], 
				                    $remote_url, $view 
				                ); 
							?>
							</div>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
				/* Check Remote Connection */
				if( $remote_url )
				{
					$datas = [
						'handshake' => 'wcwh_check_api',
						'secret' => md5( 'wcx1'.md5( 'test_'.'wcwh_check_api' ) ),
						'datas' => 'Sample Data',
					];
					pd($datas);

					add_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10, 4 );
					$response = wp_remote_post( $remote_url, [ 'timeout'=>60, 'body'=>$datas, 'sslverify' => false ] );
					remove_filter( 'airplane_mode_allow_http_api_request', array( $this, 'allow_api_request' ), 10 );
					if( ! is_wp_error( $response ) ) 
					{
						$response = json_decode( wp_remote_retrieve_body( $response ), true );
						pd( $response );
					}
					else
					{
						pd($response);
					}
				}
			break;
			case 'check_db_table':
				//define( "developer_debug", 1 );

				$sellers = apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1 ] );
				$seller_opts = [];
				if( $sellers )
				{
					foreach( $sellers as $seller )
					{
						if( $seller['parent'] > 0 )
							$seller_opts[ $seller['code'] ] = $seller['code'].' - '.$seller['name'];
					}
				}
				
				$seller = ( $_REQUEST['seller'] )? $_REQUEST['seller'] : '';
				$custom_db = ( $_REQUEST['custom_db'] )? $_REQUEST['custom_db'] : '';
				?>
				<form action="" method="post">
					<div class='form-rows-group'>
						<h4>Check DB table data count</h4>
						<div class="segment col-md-4">
							<label class="" for="flag">By Seller</label>
							<?php
								wcwh_form_field( 'seller', 
				                    [ 'id'=>'seller', 'type'=>'select', 'label'=>'', 'required'=>false, 'attrs'=>[], 'class'=>['select2Strict'],
				                        'options'=> $seller_opts, 'offClass'=>true
				                    ], 
				                	$seller, $view 
				                ); 
							?>
						</div>
						<div class="segment col-md-4">
							<label class="" for="flag">Custom DB</label><br>
							<?php
								wcwh_form_field( 'custom_db', 
				                    [ 'id'=>'custom_db', 'type'=>'text', 'label'=>'', 'required'=>false, 'class'=>[], 
										'attrs'=>[], 'offClass'=>true
				                    ], 
				                    $custom_db, $view 
				                ); 
							?>
						</div>
						<div class="form-row">
	        				<div class="col form-group">
								<?php submit_button( 'Submit' ); ?>
							</div>
						</div>
					</div>
				</form>
				<?php
					if( $seller )
					{
						$remote = apply_filters( 'wcwh_api_request', 'check_db_table', 1, $seller );
						
						if( $remote['succ'] )
						{
							echo 'Remote Success<br>';
							$remote_tbls = [];
							if( $remote['result']['datas'] )
							{
								foreach( $remote['result']['datas'] as $i => $row )
								{
									$remote_tbls[ $row['TABLE_NAME'] ] = $row;
								}
							}

							if( ! $custom_db )
							{
								$wh = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$seller ], [], true );
								$dbname = get_warehouse_meta( $wh['id'], 'dbname', true );
							}
							else
								$dbname = $custom_db;
							
							echo "Compare DB name: ".$dbname."<br><br>";
							
							if ( !class_exists( "WCWH_SYNC_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/syncCtrl.php" );
 							$Inst = new WCWH_SYNC_Controller();
 							
 							$identical = []; 
 							$col_diff = []; 
 							$data_diff = []; 
 							$own_none = [];
 							$other_none = [];

 							$own_tbls = $Inst->dbCount( $dbname );
 							if( $own_tbls )
 							{
 								foreach( $own_tbls as $i => $row )
 								{
 									if( $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_NAME'] == $row['TABLE_NAME'] )
 									{
 										$diff = 0;
 										if( $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_ROWS'] != $row['TABLE_ROWS'] )
 										{
 											$data_diff[] = [
 												'table' => $row['TABLE_NAME'],
 												'own_side' => $row['TABLE_ROWS'],
 												'remote_side' => $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_ROWS'],
 												'diff' => $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_ROWS'] - $row['TABLE_ROWS'],
 											];
 											$diff = 1;
 										}

 										if( $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_COLS'] != $row['TABLE_COLS'] )
 										{
 											$col_diff[] = [
 												'table' => $row['TABLE_NAME'],
 												'own_side' => $row['TABLE_COLS'],
 												'remote_side' => $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_COLS'],
 												'diff' => $remote_tbls[ $row['TABLE_NAME'] ]['TABLE_COLS'] - $row['TABLE_COLS'],
 											];
 											$diff = 1;
 										}

 										if( ! $diff )
 										{
 											$identical[] = $row;
 										}

 										unset( $remote_tbls[ $row['TABLE_NAME'] ] );
 									}
 									else
 									{
 										$other_none[] = $row['TABLE_NAME'];
 									}
 								}

 								$own_none = array_keys( $remote_tbls );
 							}

 							echo "<br>Data Differences:";
							rt($data_diff);

							echo "<br>Column Differences:";
							rt($col_diff);

							echo "<br>Remote Side None:";
							pd($other_none);						

 							echo "<br>Own Side None:";
 							pd($own_none);

 							echo "<br>Indentical:";
 							rt($identical);
						}
						else
							echo 'Remote Failed<br>';
					}
			break;
			case 'doc_integration':
				/*
				SELECT a.docno, a.count AS dc, b.count AS store, a.client, b.client
				FROM (
					SELECT h.docno, h.doc_id, COUNT(d.item_id) AS count, ma.meta_value AS client
					FROM wp_stmm_wcwh_document h
					LEFT JOIN wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
					LEFT JOIN wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' 
					WHERE 1 AND h.doc_type = 'delivery_order' AND h.status >= 6 AND ma.meta_value IN ( 'C0011', 'C0012' )
					GROUP BY h.docno
					ORDER BY h.docno ASC
				) a
				LEFT JOIN (
					SELECT h.docno, h.doc_id, COUNT(d.item_id) AS count, ma.meta_value AS client
					FROM mnmart02.wp_stmm_wcwh_document h
					LEFT JOIN mnmart02.wp_stmm_wcwh_document_items d ON d.doc_id = h.doc_id AND d.status > 0
					LEFT JOIN mnmart02.wp_stmm_wcwh_document_meta ma ON ma.doc_id = h.doc_id AND ma.item_id = 0 AND ma.meta_key = 'client_company_code' 
					WHERE 1 AND h.doc_type = 'delivery_order' AND h.status >= 6 AND ma.meta_value IN ( 'C0011', 'C0012' )
					GROUP BY h.docno
					ORDER BY h.docno ASC
				) b ON b.docno = a.docno
				WHERE 1 AND a.count != b.count OR b.count IS NULL
				ORDER BY a.docno ASC
				*/
			break;
			case '':
			default:
				echo "<h4>For Functional Checking Purpose</h4>";
				echo "<h5>Go to other tabs for checking respective feature.</h5>";
			break;
		}
	}
		public function allow_api_request( $status = true, $url = '', $args = [], $url_host = '' )
		{
			return true;
		}


	/**
	 *	Dashboard Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_dashboard()
	{
		$this->wh_todo();
	}

	public function wh_todo()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		if( ! class_exists( 'WCWH_TODO_Controller' ) ) include_once( WCWH_DIR."/includes/controller/todoCtrl.php" ); 
		$Inst = new WCWH_TODO_Controller();

		$arrange = $Inst->Logic->get_arrangement( ['status'=>1] );
		if( $arrange )
		{
			$t = array();
			foreach( $arrange as $i => $arr )
			{
				$t[ $arr['action_type'] ] = $this->refs['action_type'][ $arr['action_type'] ];
			}
			$t['history'] = "Todo History";
			$tabs['tabs'] = $t;
		}
		
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-tab="<?php echo $onTab; ?>" 
		>
			<div class="wcwh-content">
			<?php 
				$first = '';
				foreach( $tabs['tabs'] as $key => $tab )
				{
					$first = empty( $first )? $key : $first;
					$onTab = empty( $onTab )? $first : $onTab;
				}

				switch( strtolower( $onTab ) )
				{
					case 'approval':
						$Inst->set_listview( $onTab );
						$Inst->view_listing();
					break;
					case 'processing':
						$Inst->set_listview( $onTab );
						$Inst->view_listing();
					break;
					case 'confirmation':
						$Inst->set_listview( $onTab );
						$Inst->view_listing();
					break;
					case 'history':
						$Inst->view_history_listing();
					break;
				}
				
			?>
			</div>
		</div>
	<?php
	}


	/**
	 *	Company Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_company()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/companyCtrl.php" ); 
				$Inst = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Warehouse Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_warehouse()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/warehouseCtrl.php" ); 
				$Inst = new WCWH_Warehouse_Controller();

				include_once( WCWH_DIR."/includes/controller/companyCtrl.php" );
				$Comp = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php $Comp->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Comp->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Supplier Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_supplier()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/supplierCtrl.php" ); 
				$Inst = new WCWH_Supplier_Controller();

				//include_once( WCWH_DIR."/includes/controller/companyCtrl.php" );
				//$Comp = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php //$Comp->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php //$Comp->view_form(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Client Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_client()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/clientCtrl.php" ); 
				$Inst = new WCWH_Client_Controller();

				//include_once( WCWH_DIR."/includes/controller/companyCtrl.php" );
				//$Comp = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php //$Comp->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php //$Comp->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 *	Vending Machine Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_vending_machine()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/vendingMachineCtrl.php" ); 
				$Inst = new WCWH_VendingMachine_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Brand Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_brand()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/brandCtrl.php" ); 
				$Inst = new WCWH_Brand_Controller();

				//include_once( WCWH_DIR."/includes/controller/companyCtrl.php" );
				//$Comp = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php //$Comp->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php //$Comp->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_criteria()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/criteriaCtrl.php" ); 
				$Inst = new WCWH_Criteria_Controller();

				//include_once( WCWH_DIR."/includes/controller/companyCtrl.php" );
				//$Comp = new WCWH_Company_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php //$Comp->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php //$Comp->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Asset Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_asset()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array( ''=>'Assets', 'movement'=> 'Asset Movements' ),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php
				switch( strtolower( $onTab ) ){
					case 'movement':
						include_once( WCWH_DIR."/includes/controller/assetMovementCtrl.php" ); 
						$Inst = new WCWH_AssetMovement_Controller();
						?>
						<div class="action-group row">
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_listing(); ?>

							<div class="template-container">
							</div>
						</div>
						<?php
					break;
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/assetCtrl.php" ); 
						$Inst = new WCWH_Asset_Controller();

						include_once( WCWH_DIR."/includes/controller/itemCategoryCtrl.php" );
						$Cat = new WCWH_ItemCategory_Controller();
						?>
						<div class="action-group row">
							<div class="col-md-10">
								<?php $Inst->view_fragment(); ?>
								<?php $Cat->view_fragment(); ?>
							</div>
							<div class="col-md-2 rightered">
							</div>
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_listing(); ?>

							<div class="template-container">
								<?php $Inst->view_form(); ?>
								<?php $Cat->view_form(); ?>

								<?php $Inst->print_tpl(); ?>
							</div>
						</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}


	/**
	 *	Items Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_items()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemCtrl.php" ); 
				$Inst = new WCWH_Item_Controller();

				include_once( WCWH_DIR."/includes/controller/itemGroupCtrl.php" );
				$Group = new WCWH_ItemGroup_Controller();

				include_once( WCWH_DIR."/includes/controller/storeTypeCtrl.php" );
				$Store = new WCWH_StoreType_Controller();

				include_once( WCWH_DIR."/includes/controller/itemCategoryCtrl.php" );
				$Cat = new WCWH_ItemCategory_Controller();

				include_once( WCWH_DIR."/includes/controller/uomCtrl.php" );
				$Uom = new WCWH_UOM_Controller();

				include_once( WCWH_DIR."/includes/controller/brandCtrl.php" );
				$Brand = new WCWH_Brand_Controller();

				//include_once( WCWH_DIR."/includes/controller/supplierCtrl.php" );
				//$Supplier = new WCWH_Supplier_Controller();

				//$Inst->import_data();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php $Group->view_fragment(); ?>
					<?php $Store->view_fragment(); ?>
					<?php $Cat->view_fragment(); ?>
					<?php $Uom->view_fragment(); ?>
					<?php $Brand->view_fragment(); ?>
					<?php //$Supplier->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Group->view_form(); ?>
					<?php $Store->view_form(); ?>
					<?php $Cat->view_form(); ?>
					<?php $Uom->view_form(); ?>
					<?php //$Supplier->view_form(); ?>
					<?php $Brand->view_form(); ?>

					<?php $Inst->print_tpl(); ?>

					<?php $Inst->view_row(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_item_scan()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemscan_Ctrl.php" ); 
				$Inst = new WCWH_Item_Scan_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="appendResult container p-2"></div>

				<div class="template-container">
					<?php $Inst->display_item(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_reprocess_item()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/reprocessItemCtrl.php" ); 
				$Inst = new WCWH_ReprocessItem_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_itemize()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemizeCtrl.php" ); 
				$Inst = new WCWH_Itemize_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>

					<?php $Inst->import_form(); ?>
					<?php //$Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_items_group()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemGroupCtrl.php" ); 
				$Inst = new WCWH_ItemGroup_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_items_store_type()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/storeTypeCtrl.php" ); 
				$Inst = new WCWH_StoreType_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_items_category()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemCategoryCtrl.php" ); 
				$Inst = new WCWH_ItemCategory_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_items_order_type()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_items_order_type";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/orderTypeCtrl.php" ); 
				$Inst = new WCWH_OrderType_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_items_relation()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_items_relation";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemRelCtrl.php" ); 
				$Inst = new WCWH_ItemRel_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	//Item Expiry
	public function wh_item_expiry()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/itemExpiryCtrl.php" ); 
				$Inst = new WCWH_ItemExpiry_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment( 'save-category' ); ?>
					<?php $Inst->view_fragment( 'save-item' ); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_item_form(); ?>
					<?php $Inst->view_category_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_uom()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/uomCtrl.php" ); 
				$Inst = new WCWH_UOM_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_uom_conversion()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/uomConversionCtrl.php" ); 
				$Inst = new WCWH_UOMConversion_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}
	

	/**
	 *	Pricing Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_pricing()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [ 
				'' => 'Latest Price', 
				'manage-price' => 'Manage Price', 
			],	//key=>title 
		);

		if( current_user_cans( ['access_wh_margin'] ) ) $tabs['tabs']['manage-margin'] = 'Manage Margin';
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'manage-price':
						include_once( WCWH_DIR."/includes/controller/pricingCtrl.php" ); 
						$Inst = new WCWH_Pricing_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
									<?php $Inst->view_fragment( 'export' ); ?>
									<?php $Inst->view_fragment( 'import' ); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php $Inst->import_form(); ?>
									<?php $Inst->export_form(); ?>
								</div>
							</div>
						<?php
					break;
					case 'manage-margin':
						if( current_user_cans( ['access_wh_margin'] ) ):
							include_once( WCWH_DIR."/includes/controller/marginCtrl.php" ); 
							$Inst = new WCWH_Margin_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
									<?php //$Inst->view_fragment( 'export' ); ?>
									<?php //$Inst->view_fragment( 'import' ); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php //$Inst->import_form(); ?>
									<?php //$Inst->export_form(); ?>
								</div>
							</div>
						<?php
						endif;
					break;
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/pricingCtrl.php" ); 
						$Inst = new WCWH_Pricing_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
								</div>
								<div class="col-md-2 rightered">
									<?php $Inst->view_fragment( 'export' ); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->latest_price_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php $Inst->export_form(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_purchase_pricing()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [ 
				'' => 'Purchase Price', 
				'manage-price' => 'Manage Purchase Price', 
			],	//key=>title 
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'manage-price':
						include_once( WCWH_DIR."/includes/controller/purchasePricingCtrl.php" ); 
						$Inst = new WCWH_PurchasePricing_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>
								</div>
							</div>
						<?php
					break;
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/purchasePricingCtrl.php" ); 
						$Inst = new WCWH_PurchasePricing_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->latest_price_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_promo()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/promoCtrl.php" ); 
				$Inst = new WCWH_Promo_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Customer Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_customer()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/customerCtrl.php" ); 
				$Inst = new WCWH_Customer_Controller();
				$Inst->set_warehouse( $wh );

				include_once( WCWH_DIR."/includes/controller/customerGroupCtrl.php" );
				$Group = new WCWH_CustomerGroup_Controller();
				$Group->set_warehouse( $wh );

				include_once( WCWH_DIR."/includes/controller/customerJobCtrl.php" );
				$Job = new WCWH_CustomerJob_Controller();
				$Job->set_warehouse( $wh );

				include_once( WCWH_DIR."/includes/controller/originGroupCtrl.php" );
				$Origin = new WCWH_OriginGroup_Controller();
				$Origin->set_warehouse( $wh );

				include_once( WCWH_DIR."/includes/controller/accountTypeCtrl.php" );
				$Acc = new WCWH_AccountType_Controller();
				$Acc->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					<?php $Job->view_fragment(); ?>
					<?php $Group->view_fragment(); ?>
					<?php $Origin->view_fragment(); ?>
					<?php $Acc->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
					<?php $Inst->view_fragment( 'print' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Job->view_form(); ?>
					<?php $Group->view_form(); ?>
					<?php $Origin->view_form(); ?>
					<?php $Acc->view_form(); ?>

					<?php $Inst->print_tpl(); ?>
					<?php $Inst->printing_form(); ?>
					<?php $Inst->printing_multi_form(); ?>
					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_customer_group()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/customerGroupCtrl.php" ); 
				$Inst = new WCWH_CustomerGroup_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_customer_job()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/customerJobCtrl.php" ); 
				$Inst = new WCWH_CustomerJob_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_origin_group()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/originGroupCtrl.php" ); 
				$Inst = new WCWH_OriginGroup_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_account_type()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/accountTypeCtrl.php" ); 
				$Inst = new WCWH_AccountType_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Credit Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_credit()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/creditCtrl.php" ); 
				$Inst = new WCWH_Credit_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment( 'save-group' ); ?>
					<?php if( $this->setting['general']['use_customer'] ) $Inst->view_fragment( 'save-customer' ); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_customer_form(); ?>
					<?php $Inst->view_group_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_credit_term()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/creditTermCtrl.php" ); 
				$Inst = new WCWH_CreditTerm_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_credit_topup()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/creditTopupCtrl.php" ); 
				$Inst = new WCWH_CreditTopup_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_payment_method()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/paymentMethodCtrl.php" ); 
				$Inst = new WCWH_PaymentMethod_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_payment_term()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/paymentTermCtrl.php" ); 
				$Inst = new WCWH_PaymentTerm_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 *	Membership Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_membership()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/membershipCtrl.php" ); 
				$Inst = new WCWH_Membership_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'export' ); ?>
					<?php //$Inst->view_fragment( 'print' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>

					<?php $Inst->print_tpl(); ?>
					<?php //$Inst->printing_form(); ?>
					<?php //$Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_member_topup()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/memberTopupCtrl.php" ); 
				$Inst = new WCWH_MemberTopup_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Bank In Service Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_bankin_service()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );

		//------???
		if(!$seller) $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		//---?????
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" data-wh="<?php echo $wh['code'] ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/bankinserviceCtrl.php" ); 
				$Inst = new WCWH_BankInService_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment('print'); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
					<?php $Inst->bis_form(); ?>
					<?php $Inst->multiBIS_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_bankin_collector()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );

		//------???
		if(!$seller) $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		//---?????
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" data-wh="<?php echo $wh['code'] ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/bankinCollectorCtrl.php" ); 
				$Inst = new WCWH_BankInCollector_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
					<?php $Inst->bic_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_service_charge()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/serviceChargeCtrl.php" ); 
				$Inst = new WCWH_ServiceCharge_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>

					<?php $Inst->export_form(); ?>
					<?php $Inst->import_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_bankin_info()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/bankininfoCtrl.php" ); 
				$Inst = new WCWH_BankInInfo_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_exchange_rate()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [ 
				'' => 'Latest Exchange Rate', 
				'manage-exchangeRate' => 'Manage Exchange Rate', 
			],	//key=>title 
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
		
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) ){
					case 'manage-exchangeRate':
					default:
						include_once( WCWH_DIR."/includes/controller/exchangeRateCtrl.php" ); 
						$Inst = new WCWH_ExchangeRate_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>						
								</div>
								<div class="col-md-2 rightered">
									<?php $Inst->view_fragment( 'export' ); ?>
									<?php $Inst->view_fragment( 'import' ); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>

									<?php $Inst->export_form(); ?>
									<?php $Inst->import_form(); ?>

								</div>
							</div>
						<?php
					break;
					case '':
						include_once( WCWH_DIR."/includes/controller/exchangeRateCtrl.php" ); 
						$Inst = new WCWH_ExchangeRate_Controller();
						?>
							<div class="action-group row">
								<div class="col-md-10">
								</div>
								<div class="col-md-2 rightered">
									<?php $Inst->view_fragment( 'export' ); ?>
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->latest_exchange_rate_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>

									<?php $Inst->export_latest_form(); ?>

								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}


	/**
	 *	Tool Requisition
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_tool_request()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );

		if( !$seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" data-wh="<?php echo $wh['code'] ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/toolRequestCtrl.php" ); 
				$Inst = new WCWH_ToolRequest_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment('print'); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
					<?php $Inst->tr_form(); ?>
					<?php $Inst->multiTR_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_tool_request_fulfilment()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_tool_request_fulfilment";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>">
			<?php
					$report_types = [
						'summary' => [ 'title'=>'Tool Request Fulfilment', 'permission'=>'access_wh_tool_request_fulfilment', 'desc'=> 'Tool Request Fulfilment' ],
					];
					$inner = [
						'id' => 'wcwhInnerTab', 
						'header' => 'Report Type: ',
						'tabs' => [],
						'desc' => [],
					];
					$i = 0; $main_key = '';
					foreach( $report_types as $key => $rpt_type )
					{
						if( current_user_cans( [ $rpt_type['permission'] ] ) )
						{
							$k = ( $i == 0 )? '' : $key;
							$main_key = ( $i == 0 )? $key : $main_key;
							$inner['tabs'][ $k ] = $rpt_type['title'];
							$inner['desc'][ $k ] = $rpt_type['desc'];

							$i++;
						}
					}

					do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
					$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
					$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
				?>

				<?php 
					include_once( WCWH_DIR . "/includes/reports/toolRequestFulfilment.php" ); 
					$Inst = new WCWH_ToolRequestFulfilment_Rpt();
					if( $warehouse[ $onTab ]['id'] )
					{
						$Inst->seller = $warehouse[ $onTab ]['id'];
					}
				?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php //$Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php
					switch( $onSect )
					{
						case 'summary':
						default:
							//$Inst->noList = true;
							$Inst->tool_request_fulfilment();
							//$Inst->export_form( $onSect );
							//$Inst->printing_form( $onSect );
						break;
					}
				?>
			</div>
		</div>
	<?php
	}

	public function wh_tool_request_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_tool_request_rpt";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>">
			<?php
					$report_types = [
						'details' => [ 'title'=>'Tool Request Detail Report', 'permission'=>'access_wh_tool_request_rpt', 'desc'=> 'Tool Request Detail Report' ],
						'summary' => [ 'title'=>'Tool Request Summary Report', 'permission'=>'access_wh_tool_request_rpt', 'desc'=> 'Tool Request Summary Report' ],
					];
					$inner = [
						'id' => 'wcwhInnerTab', 
						'header' => 'Report Type: ',
						'tabs' => [],
						'desc' => [],
					];
					$i = 0; $main_key = '';
					foreach( $report_types as $key => $rpt_type )
					{
						if( current_user_cans( [ $rpt_type['permission'] ] ) )
						{
							$k = ( $i == 0 )? '' : $key;
							$main_key = ( $i == 0 )? $key : $main_key;
							$inner['tabs'][ $k ] = $rpt_type['title'];
							$inner['desc'][ $k ] = $rpt_type['desc'];

							$i++;
						}
					}

					do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
					$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
					$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
				?>

				<?php 
					include_once( WCWH_DIR . "/includes/reports/toolRequestReport.php" ); 
					$Inst = new WCWH_ToolRequestReport_Rpt();
					if( $warehouse[ $onTab ]['id'] )
					{
						$Inst->seller = $warehouse[ $onTab ]['id'];
					}
				?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php
					switch( $onSect )
					{
						case 'summary':
						default:
							$Inst->noList = true;
							$Inst->tool_request_summary_report();
							$Inst->export_form( $onSect );
							//$Inst->printing_form( $onSect );
						break;
						case 'details':
							$Inst->noList = true;
							$Inst->tool_request_report();
							$Inst->export_form( $onSect );
							//$Inst->printing_form( $onSect );
						break;
					}
				?>
			</div>
		</div>
	<?php
	}


	/**
	 *	Parts Requisition
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_parts_request()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );

		if( !$seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" data-wh="<?php echo $wh['code'] ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/partsRequestCtrl.php" ); 
				$Inst = new WCWH_PartsRequest_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment('print'); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	Profile Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_profile()
	{

	}

	public function wh_maintain_user()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" >
			<?php 
				include_once( WCWH_DIR."/includes/controller/userCtrl.php" ); 
				$Inst = new WCWH_User_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_permission()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) )
				{
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/permissionCtrl.php" ); 
						$Inst = new WCWH_Permission_Controller();

						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_roles()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) )
				{
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/roleCtrl.php" ); 
						$Inst = new WCWH_Role_Controller();

						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php $Inst->view_form(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}


	/**
	 *	Supports Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_support()
	{

	}

	public function wh_stage()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/stageCtrl.php" ); 
				$Inst = new WCWH_Stage_Controller();
			?>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>
			</div>
		</div>
	<?php
	}

	public function wh_sync()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/syncCtrl.php" ); 
				$Inst = new WCWH_SYNC_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-4">
					<?php $Inst->view_fragment( 'save' ); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-6">
				<?php 
					$last_scheduled = get_option( 'wcwh_last_scheduled' );
					$last_synced = get_option( 'wcwh_last_sync' );
				?>
					<span>Last Schedule Run: <?php echo $last_scheduled; ?></span><br>
					<span>Last Sync Receive: <?php echo $last_synced; ?></span>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_logs()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/activityLogCtrl.php" ); 
				$Inst = new WCWH_ActivityLog_Controller();
			?>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>
			</div>
		</div>
	<?php
	}

	public function wh_mail_logs()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/mailLogCtrl.php" ); 
				$Inst = new WCWH_MailLog_Controller();
			?>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>
			</div>
		</div>
	<?php
	}


	/**
	 *	Configuration Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_config()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array( 
				'' => 'General', 
				'runningno' => 'Doc Running No.',
				'stockout' => 'Stockout',
				'scheme' => 'Scheme', 
				'section' => 'Section',
				'status' => 'Status',
			), 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) ){
					case 'runningno':
						include_once( WCWH_DIR."/includes/controller/runningNoCtrl.php" ); 
						$Inst = new WCWH_RunningNo_Controller();
					break;
					case 'stockout':
						include_once( WCWH_DIR."/includes/controller/stockoutCtrl.php" ); 
						$Inst = new WCWH_Stockout_Controller();
					break;
					case 'scheme':
						include_once( WCWH_DIR."/includes/controller/schemeCtrl.php" ); 
						$Inst = new WCWH_Scheme_Controller();
					break;
					case 'section':
						include_once( WCWH_DIR."/includes/controller/sectionCtrl.php" ); 
						$Inst = new WCWH_Section_Controller();
					break;
					case 'status':
						include_once( WCWH_DIR."/includes/controller/statusCtrl.php" ); 
						$Inst = new WCWH_Status_Controller();
					break;
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/settingCtrl.php" ); 
						$Inst = new WCWH_Setting_Controller();
					break;
				}
			?>
			
			<div class="action-group">
				<?php $Inst->view_fragment(); ?>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		
		</div>
	<?php
	}

	public function wh_arrangement()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array( 
				'' => 'Todo Arrangement',
				'todo_action' => 'Todo Action',
			), 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				switch( strtolower( $onTab ) ){
					case 'todo_action':
						include_once( WCWH_DIR."/includes/controller/todoActionCtrl.php" ); 
						$Inst = new WCWH_TodoAction_Controller();
					break;
					case '':
					default:
						include_once( WCWH_DIR."/includes/controller/todoArrangementCtrl.php" ); 
						$Inst = new WCWH_TodoArrangement_Controller();
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_template()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [], 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>">
			<?php 
				include_once( WCWH_DIR."/includes/controller/templateCtrl.php" ); 
				$Inst = new WCWH_Template_Controller();
			?>
			
			<div class="action-group">
				<?php $Inst->view_fragment(); ?>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		
		</div>
	<?php
	}


	/**
	 *	---------------------------------------------------------------------------------------------------
	 *	Outlets Sections
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_inventory()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/inventoryCtrl.php" ); 
				$Inst = new WCWH_Inventory_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
				$Purchase = new WCWH_PurchaseRequest_Controller();
				$Purchase->set_warehouse( $this->warehouse );
			?>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/adjustmentCtrl.php" ); 
				$Adj = new WCWH_Adjustment_Controller();
				$Adj->set_warehouse( $this->warehouse );
			?>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
				$SC = new WCWH_SaleOrder_Controller();
				$SC->set_warehouse( $this->warehouse );
			?>

			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Supplier->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Purchase->view_row(); ?>
					<?php $Adj->view_row(); ?>
					<?php $SC->view_row(); ?>

					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_purchase_request()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => 'Purchase Request',
											
			],	//key=>title 
		);

		if( $this->warehouse['indication'] && !$this->warehouse['view_outlet'] && current_user_cans( ['access_wh_ordering_pr'] ))
		{
			$tabs['tabs']['orderingpr'] = 'Purchase Request Ordering';
		}
		if( current_user_cans( ['access_wh_closing_pr'] ) )
		{
			$tabs['tabs']['closingpr'] = 'Closing Purchase Request';
		}
		if( current_user_cans( ['access_wh_remote_cpr'] ) )
		{
			$tabs['tabs']['remotecpr'] = 'Remote Closing Purchase Request';
		}
		
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				$vfrag = true;
				$vfrag_transient = true;
				$vreference = true;
				$vform = true;
				$vrow = true;
				switch ( strtolower( $onTab ) ) 
				{
					case 'orderingpr':
						include_once( WCWH_DIR . "/includes/controller/orderingPRCtrl.php" );
						$Inst = new WCWH_OrderingPR_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$vfrag = false;
						$vfrag_transient = false;
						$vreference = false;
					break;
					case 'closingpr':
						include_once( WCWH_DIR . "/includes/controller/closingPRCtrl.php" );
						$Inst = new WCWH_ClosingPR_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$vfrag_transient = false;
						$vrow = false; 
					break;
					case 'remotecpr':
						include_once( WCWH_DIR . "/includes/controller/remoteCPRCtrl.php" );
						$Inst = new WCWH_RemoteCPR_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$vfrag = false;
						$vfrag_transient = false;
						$vreference = false;
						$vform = false;
						$vrow = false;
					break;					
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/purchaseRequestCtrl.php" ); 
						$Inst = new WCWH_PurchaseRequest_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$vreference = false;
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php if($vfrag)$Inst->view_fragment(); ?>
					<?php if($vreference)$Inst->view_reference(); ?>
				</div>
				<div class="col-md-2 rightered" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ).'_transient' ?>">
					<?php if($vfrag_transient)$Inst->view_fragment('transient'); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php if($vform)$Inst->view_form(); ?>
					<?php if($vrow) $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_purchase_order()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => 'Purchase Order',
				'p_cdnote' => 'Credit/Debit Note',
			],	//key=>title 
		);

		if( ! current_user_cans( ['access_wh_purchase_cdnote'] ) ) unset( $tabs['tabs']['p_cdnote'] );
		
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php
			switch( strtolower( $onTab ) )
			{
				case 'p_cdnote':
					include_once( WCWH_DIR . "/includes/controller/purchaseCDNoteCtrl.php" ); 
					$Inst = new WCWH_PurchaseCDNote_Controller();
					$Inst->set_warehouse( $this->warehouse );
					?>
						<div class="action-group row">
							<div class="col-md-10 row">
								<?php $Inst->view_reference(); ?>
								<?php $Inst->view_fragment(); ?>
							</div>
							<div class="col-md-2 rightered">
								<?php $Inst->view_fragment( 'export' ); ?>
								<?php //$Inst->view_fragment( 'import' ); ?>
							</div>
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_listing(); ?>

							<div class="template-container">
								<?php //$Inst->view_form(); ?>
								<?php $Inst->view_row(); ?>

								<?php //$Inst->import_form(); ?>
								<?php $Inst->export_form(); ?>
								<?php $Inst->cn_form(); ?>
								<?php $Inst->dn_form(); ?>
							</div>
						</div>
					<?php
				break;
				case '':
				default:
					include_once( WCWH_DIR . "/includes/controller/purchaseOrderCtrl.php" ); 
					$Inst = new WCWH_PurchaseOrder_Controller();
					$Inst->set_warehouse( $this->warehouse );
					?>
						<div class="action-group row">
							<div class="col-md-8 row">
								<?php $Inst->view_reference(); ?>
								<?php $Inst->view_fragment(); ?>
							</div>
							<div class="col-md-2 rightered" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ).'_transient' ?>">
								<?php $Inst->view_fragment('transient'); ?>
							</div>
							<div class="col-md-2 rightered">
								<?php $Inst->view_fragment( 'export' ); ?>
								<?php //$Inst->view_fragment( 'import' ); ?>
							</div>
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_listing(); ?>

							<div class="template-container">
								<?php //$Inst->view_form(); ?>
								<?php $Inst->view_row(); ?>

								<?php //$Inst->import_form(); ?>
								<?php $Inst->export_form(); ?>
								<?php $Inst->po_form(); ?>
							</div>
						</div>
					<?php
				break;
			}		
			?>
		</div>
	<?php
	}

	public function wh_self_bill()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
			'isStep' => 1
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$doc_type = 'sale_order';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_doc_type="<?php echo $doc_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/selfBillCtrl.php" ); 
						$Inst = new WCWH_SelfBill_Controller();
						$Inst->set_warehouse( $this->warehouse );
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_sales_order()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [ 
				'' => 'Sales Order',
				'scd_note' => 'Credit/Debit Note',
				'delivery_order' => 'Delivery Order',
				'good_issue' => 'Goods Issue',
				'do_revise' => 'DO Revise',
			],	//key=>title 
			'isStep' => 1
		);

		if( ! current_user_cans( ['access_wh_do_revise'] ) ) unset( $tabs['tabs']['do_revise'] );
		if( ! current_user_cans( ['access_wh_sale_cdnote'] ) ) unset( $tabs['tabs']['scd_note'] );

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$doc_type = 'sale_order';

		$issue_type = 'delivery_order';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_doc_type="<?php echo $doc_type; ?>"
		>
			<?php 
				$isSO = false; $isDO = false; $isCD = false;
				switch( strtolower( $onTab ) )
				{
					case 'scd_note':
						include_once( WCWH_DIR . "/includes/controller/saleCDNoteCtrl.php" ); 
						$Inst = new WCWH_SaleCDNote_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$isCD = true;
						//$Inst->ref_doc_type = $doc_type;
					break;
					case 'good_issue':
						include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_doc_type = $doc_type;
						$Inst->ref_issue_type = $issue_type;
						$Inst->set_warehouse( $this->warehouse );
					break;
					case 'delivery_order':
						include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
						$Inst = new WCWH_DeliveryOrder_Controller();
						$Inst->ref_doc_type = $doc_type;
						$Inst->set_warehouse( $this->warehouse );
						$isDO = true;
					break;
					case 'do_revise':
						include_once( WCWH_DIR . "/includes/controller/doReviseCtrl.php" ); 
						$Inst = new WCWH_DORevise_Controller();
						$Inst->ref_doc_type = $doc_type;
						$Inst->set_warehouse( $this->warehouse );
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/saleOrderCtrl.php" ); 
						$Inst = new WCWH_SaleOrder_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$isSO = true;
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php 
						$Inst->view_row();
						if( ! in_array( strtolower( $onTab ), ['scd_note','good_issue', 'delivery_order', 'do_revise'] ) ) 
							$Inst->view_custom_row(); 

						if( method_exists( $Inst, 'fee_row' ) ) $Inst->fee_row();
					?>
					<?php if( $isSO ) $Inst->pl_form(); ?>
					<?php if( $isSO ) $Inst->inv_form(); ?>
					<?php if( $isDO ) $Inst->do_form(); ?>
					
					<?php if( $isCD ) $Inst->cn_form(); ?>
					<?php if( $isCD ) $Inst->dn_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_e_invoice()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
			'isStep' => 1
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$doc_type = 'sale_order';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_doc_type="<?php echo $doc_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/eInvoiceCtrl.php" ); 
						$Inst = new WCWH_EInvoice_Controller();
						$Inst->set_warehouse( $this->warehouse );
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->inv_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_sales_return()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/saleReturnCtrl.php" ); 
				$Inst = new WCWH_SaleReturn_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_transfer_order()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [ 
				'' => 'Transfer Order',
				'delivery_order' => 'Delivery Order',
				'good_issue' => 'Goods Issue',
			],	//key=>title 
			'isStep' => 1
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$doc_type = 'transfer_order';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_doc_type="<?php echo $doc_type; ?>"
		>
			<?php 
				$isTO = false; $isDO = false;
				switch( strtolower( $onTab ) )
				{
					case 'good_issue':
						include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_doc_type = $doc_type;
						$Inst->set_warehouse( $this->warehouse );
					break;
					case 'delivery_order':
						include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
						$Inst = new WCWH_DeliveryOrder_Controller();
						$Inst->ref_doc_type = $doc_type;
						$Inst->set_warehouse( $this->warehouse );
						$isDO = true;
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/transferOrderCtrl.php" ); 
						$Inst = new WCWH_TransferOrder_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$isTO = true;
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php if( $isTO ) $Inst->pl_form(); ?>
					<?php if( $isDO ) $Inst->do_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_good_issue()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		);

		$t = []; $main = '';
		if( current_user_cans( [ 'save_own_use_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Company Use'; $main = 'own_use'; }
			else $t['own_use'] = 'Company Use';
		}
		if( current_user_cans( [ 'save_other_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Other'; $main = 'other'; }
			else $t['other'] = 'Other';
		}
		if( current_user_cans( [ 'save_vending_machine_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Vending Machine'; $main = 'vending_machine'; }
			else $t['vending_machine'] = 'Vending Machine';
		}
		if( current_user_cans( [ 'save_reprocess_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Reprocess'; $main = 'reprocess'; }
			else $t['reprocess'] = 'Reprocess';
		}
		if( current_user_cans( [ 'save_block_stock_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Block Stock'; $main = 'block_stock'; }
			else $t['block_stock'] = 'Block Stock';
		}
		if( current_user_cans( [ 'save_stock_transfer_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Stock Transfer'; $main = 'stock_transfer'; }
			else $t['stock_transfer'] = 'Stock Transfer';
		}
		if( current_user_cans( [ 'save_direct_consume_wh_good_issue' ] ) && $this->setting[ 'wh_good_issue' ]['use_direct_consume'] )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Direct Consume'; $main = 'direct_consume'; }
			else $t['direct_consume'] = 'Direct Consume';
		}
		if( current_user_cans( [ 'access_delivery_order_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Delivery Order'; $main = 'delivery_order'; }
			else $t['delivery_order'] = 'Delivery Order';
		}
		if( current_user_cans( [ 'save_transfer_item_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Transfer Item'; $main = 'transfer_item'; }
			else $t['transfer_item'] = 'Transfer Item';
		}
		if( current_user_cans( [ 'save_replaceable_wh_good_issue' ] ) )
		{
			if( ! sizeof( $t ) ){ $t[''] = 'Replaceable'; $main = 'returnable'; }
			else $t['returnable'] = 'Replaceable';
		}
		$tabs['tabs'] = $t;

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$onType = ( !empty( $onTab ) )? $onTab : $main;
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_issue_type="<?php echo $onType; ?>" 
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
				$Inst = new WCWH_GoodIssue_Controller();
				$Inst->ref_issue_type = $onType;
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<?php
					switch( $onType )
					{
						case 'delivery_order':
						?><div class="col-md-10 row"><?php
							$Inst->view_reference();
							$Inst->view_fragment();
						?></div><?php
						break;
						case 'own_use':
						case 'other':
						case 'vending_machine':
						case 'reprocess':
						case 'block_stock':
						case 'stock_transfer':
						case 'direct_consume':
						?>
							<div class="col-md-10">
								<?php $Inst->view_fragment( $onType ); ?>
							</div>
						<?php
						break;
					}
				?>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php $Inst->do_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_issue_return()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/issueReturnCtrl.php" ); 
				$Inst = new WCWH_IssueReturn_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
					<?php $Inst->view_fragment( 'save_item' ); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_reprocess()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => "Goods Issue",
				'reprocess' => "Reprocess",
			],	//key=>title 
			'isStep' => 1
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$issue_type = 'reprocess';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_issue_type="<?php echo $issue_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'reprocess':
						include_once( WCWH_DIR . "/includes/controller/reprocessCtrl.php" ); 
						$Inst = new WCWH_Reprocess_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10 row">
									<?php $Inst->view_reference(); ?>
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
						<?php
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_issue_type = $issue_type;
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php //$Inst->view_reference(); ?>
									<?php $Inst->view_fragment( $issue_type ); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
						<?php
					break;
				}
			?>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php //$Inst->print_tpl(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	///////////////////////////////////////-----JEFF----////////////////////////////////////////////////////
	public function wh_simplify_reprocess()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => "Reprocess",
				'good_issue' => "Goods Issue",
			],	//key=>title 
			'isStep' => 1
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$issue_type = 'reprocess';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_issue_type="<?php echo $issue_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'good_issue':
					include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_issue_type = $issue_type;
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php //$Inst->view_reference(); ?>
									<?php //$Inst->view_fragment( $issue_type ); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>
								<div class="template-container">
									<?php //$Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php //$Inst->print_tpl(); ?>
								</div>
							</div>
						<?php						
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/reprocessCtrlv2.php" ); 
						$Inst = new WCWH_Simplify_Reprocess_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>
								<div class="template-container">
									<?php //$Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php $Inst->print_tpl(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_delivery_order()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => 'Delivery Order',
				'do_revise' => 'DO Revise',
			],
			'isStep' => 1
		);

		if( ! current_user_cans( ['access_wh_do_revise'] ) ) unset( $tabs['tabs']['do_revise'] );

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				$isDO = false;
				switch( strtolower( $onTab ) )
				{
					case 'do_revise':
						include_once( WCWH_DIR . "/includes/controller/doReviseCtrl.php" ); 
						$Inst = new WCWH_DORevise_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$Inst->ref_doc_type = $doc_type;
					break;
					case 'delivery_order':
					default:
						include_once( WCWH_DIR . "/includes/controller/deliveryOrderCtrl.php" ); 
						$Inst = new WCWH_DeliveryOrder_Controller();
						$Inst->set_warehouse( $this->warehouse );
						$Inst->ref_doc_type = $doc_type;
						$isDO = true;
					break;
				}
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php if( method_exists( $Inst, 'import_form' ) ) $Inst->import_form(); ?>
					<?php if( method_exists( $Inst, 'export_form' ) ) $Inst->export_form(); ?>
					<?php if( $isDO ) $Inst->do_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_good_receive()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/goodReceiveCtrl.php" ); 
				$Inst = new WCWH_GoodReceive_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
					<?php $Inst->view_expiry_row(); ?>

					<?php $Inst->print_tpl(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_good_return()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => "Return by DO",
				'by_gr' => "Return by GR",
				'good_return' => "Goods Return",
			],	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/goodReturnCtrl.php" ); 
				$Inst = new WCWH_GoodReturn_Controller();
				$Inst->set_warehouse( $this->warehouse );

				switch( strtolower( $onTab ) )
				{
					case 'good_return':
					?>
						<div class="action-group row">
							<div class="col-md-10 row">
								<?php //$Inst->view_reference(); ?>
								<?php //$Inst->view_fragment(); ?>
								<div class="col-md-2">
								<?php $Inst->view_fragment( 'save_item' ); ?>
								</div>
							</div>
							<div class="col-md-2 rightered">
							</div>
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_listing(); ?>

							<div class="template-container">
								<?php //$Inst->view_form(); ?>
								<?php $Inst->view_row(); ?>
								<?php $Inst->rtn_form(); ?>
							</div>
						</div>
					<?php
					break;
					case 'by_gr':
					?>
						<div class="action-group row">
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_ref_gr_listing(); ?>

							<div class="template-container">
								<?php //$Inst->view_form(); ?>
								<?php $Inst->view_row(); ?>
							</div>
						</div>
					<?php
					break;
					case '':
					default:
					?>
						<div class="action-group row">
						</div>

						<div class="wcwh-content">
							<?php $Inst->view_ref_do_listing(); ?>

							<div class="template-container">
								<?php //$Inst->view_form(); ?>
								<?php $Inst->view_row(); ?>
							</div>
						</div>
					<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_stock_adjust()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/adjustmentCtrl.php" ); 
				$Inst = new WCWH_Adjustment_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php $Inst->import_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_stocktake()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/stocktakeCtrl.php" ); 
				$Inst = new WCWH_StockTake_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php //$Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php $Inst->import_form(); ?>
					<?php $Inst->export_form(); ?>
					<?php $Inst->printing_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_stock_movement_rectify()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/smRectifyCtrl.php" ); 
				$Inst = new WCWH_SM_Rectify_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'import' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>

					<?php $Inst->import_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_block_stock()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => "Goods Issue",
				'block_stock' => "Block Stock",
				'block_action' => "Block Stock Action",
			],	//key=>title 
			'isStep' => 1
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$issue_type = 'block_stock';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_issue_type="<?php echo $issue_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'block_stock':
						include_once( WCWH_DIR . "/includes/controller/blockStockCtrl.php" ); 
						$Inst = new WCWH_BlockStock_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10 row">
									<?php $Inst->view_reference(); ?>
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
						<?php
					break;
					case 'block_action':
						include_once( WCWH_DIR . "/includes/controller/blockActionCtrl.php" ); 
						$Inst = new WCWH_BlockAction_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10 row">
									<?php $Inst->view_reference(); ?>
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
						<?php
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_issue_type = $issue_type;
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php //$Inst->view_reference(); ?>
									<?php $Inst->view_fragment( $issue_type ); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>
						<?php
					break;
				}
			?>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_transfer_item()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [
				'' => "Transfer Item",
				'by_gr' => "Transfer Item by GR",
				'good_issue' => "Goods Issue",
			],	//key=>title 
			'isStep' => 0
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$issue_type = 'transfer_item';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-ref_issue_type="<?php echo $issue_type; ?>"
		>
			<?php 
				switch( strtolower( $onTab ) )
				{
					case 'good_issue':
						include_once( WCWH_DIR . "/includes/controller/goodIssueCtrl.php" ); 
						$Inst = new WCWH_GoodIssue_Controller();
						$Inst->ref_issue_type = $issue_type;
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php //$Inst->view_reference(); ?>
									<?php //$Inst->view_fragment( $issue_type ); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php //$Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php //$Inst->print_tpl(); ?>
								</div>
							</div>
						<?php
					break;
					case 'by_gr':
						include_once( WCWH_DIR . "/includes/controller/transferItemCtrl.php" ); 
						$Inst = new WCWH_TransferItem_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_ref_gr_listing(); ?>

								<div class="template-container">
									<?php //$Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>
								</div>
							</div>
						<?php
					break;
					case '':
					default:
						include_once( WCWH_DIR . "/includes/controller/transferItemCtrl.php" ); 
						$Inst = new WCWH_TransferItem_Controller();
						$Inst->set_warehouse( $this->warehouse );
						?>
							<div class="action-group row">
								<div class="col-md-10">
									<?php //$Inst->view_reference(); ?>
									<?php $Inst->view_fragment(); ?>
								</div>
								<div class="col-md-2 rightered">
								</div>
							</div>

							<div class="wcwh-content">
								<?php $Inst->view_listing(); ?>

								<div class="template-container">
									<?php //$Inst->view_form(); ?>
									<?php $Inst->view_row(); ?>

									<?php //$Inst->print_tpl(); ?>
								</div>
							</div>
						<?php
					break;
				}
			?>
		</div>
	<?php
	}

	public function wh_pos_transact()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/posTransactCtrl.php" ); 
				$Inst = new WCWH_PosTransact_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php //$Inst->view_reference(); ?>
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php //$Inst->view_form(); ?>
					<?php //$Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_pos_do()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],
		);

		$wh = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
		
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/posDOCtrl.php" ); 
				$Inst = new WCWH_PosDO_Controller();
				$Inst->set_warehouse( $wh);
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>
				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->do_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_storage()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/storageCtrl.php" ); 
				$Inst = new WCWH_Storage_Controller();
				$Inst->set_warehouse( $this->warehouse );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_acc_period()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_acc_period";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/accPeriodCtrl.php" ); 
				$Inst = new WCWH_AccPeriod_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_stocktake_close()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_stocktake_close";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/stockTakeCloseCtrl.php" ); 
				$Inst = new WCWH_StockTakeClose_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}
	
	//--------22/11/2022 Repleaceable
	/**
	 *	Repleaceable Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_repleaceable()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 0 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
		if( !$wh ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-wh="<?php echo $wh['code'] ?>"
		>

		<?php
			$report_types = [
				'gas_tong' 	=> [ 'title'=>'Gas Tong', 'permission'=>'access_wh_repleaceable', 'desc'=> 'Gas Tong' ],
				
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Returnable Item: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR."/includes/controller/repleaceableCtrl.php" ); 
				$Inst = new WCWH_Repleaceable_Controller();
				$Inst->set_warehouse( $wh );
			?>

			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'gas_tong':
						$Inst->view_form();
						$Inst->view_listing();
					break;
					
				}
			?>
			</div>
		</div>
	<?php
	}

	//--------22/11/2022 Repleaceable

	//Margining
	public function wh_margining()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_margining";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/marginingCtrl.php" ); 
				$Inst = new WCWH_Margining_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_sect(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 *	---------------------------------------------------------------------------------------------------
	 *	Reports
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_reports()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_reports";

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/queryReport.php" ); 
				$Inst = new WCWH_QueryReport();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->query_report();
				$Inst->export_form();
			?>
			</div>
		</div>
	<?php
	}

	//wh_customer_rpt
	public function wh_customer_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_customer_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Purchase Summary', 'permission'=>'access_customer_purchases_wh_reports', 'desc'=> 'Customer Purchases Summary' ],
				'customer_purchases'	=> [ 'title'=>'Purchase Details', 'permission'=>'access_customer_purchases_wh_reports', 'desc'=> 'Customer Purchases' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/customerPurchases.php" ); 
				$Inst = new WCWH_CustomerPurchases_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->noList = true;
						$Inst->customer_purchases_summary();
						$Inst->export_form( $onSect );
					break;
					case 'customer_purchases':
						$Inst->noList = true;
						$Inst->customer_purchases_report();
						$Inst->export_form( 'purchase' );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_credit_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_credit_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Credit Summary', 'permission'=>'view_credit_wh_reports', 'desc'=> 'Credit Summary Report' ],
				'details'	=> [ 'title'=>'Credit Details', 'permission'=>'view_credit_detail_wh_reports', 'desc'=> 'Credit Details Report' ],
				'acc_type'	=> [ 'title'=>'Credit By Acc Type', 'permission'=>'view_credit_acc_type_wh_reports', 'desc'=> 'Credit Report By Account Types' ],
				'credit_limit'	=> [ 'title'=>'Credit Limit', 'permission'=>'view_credit_limit_wh_reports', 'desc'=> 'Credit Limit Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/customerCredit.php" ); 
				$Inst = new WCWH_CustomerCredit_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php if( ! in_array( $onSect, [ 'acc_type', 'credit_limit' ] ) ) $Inst->view_fragment( 'print' ); ?>
					<?php if( ! in_array( $onSect, [ 'acc_type' ] ) ) $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->noList = true;
						$Inst->customer_credit_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'details':
						$Inst->noList = true;
						$Inst->customer_credit_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'credit_limit':
						$Inst->noList = true;
						$Inst->customer_credit_limit_report();
						$Inst->export_form( $onSect );
					break;
					case 'acc_type':
						$Inst->noList = true;
						$Inst->customer_credit_acc_type_report();
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_tool_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_tool_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_tool_wh_reports', 'desc'=> 'Tool & Equipment Credit Summary Report' ],
				'details'	=> [ 'title'=>'Details', 'permission'=>'view_tool_detail_wh_reports', 'desc'=> 'Tool & Equipment Credit Details Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/customerTool.php" ); 
				$Inst = new WCWH_CustomerTool_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->noList = true;
						$Inst->customer_tool_report();
						$Inst->export_form( $onSect );
						//$Inst->printing_form( $onSect );
					break;
					case 'details':
						$Inst->noList = true;
						$Inst->customer_tool_detail_report();
						$Inst->export_form( $onSect );
						//$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_receipt_count()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_receipt_count";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/receiptCount.php" ); 
				$Inst = new WCWH_ReceiptCount_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->noList = true;
				$Inst->receipt_count_report();
				$Inst->export_form();
				$Inst->printing_form();
			?>
			</div>
		</div>
	<?php
	}

	public function wh_pos_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_pos_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Daily Summary', 'permission'=>'view_pos_summary_wh_reports', 'desc'=> 'POS Sales Daily Summary' ],
				'sales'		=> [ 'title'=>'Receipt Summary', 'permission'=>'view_pos_sales_wh_reports', 'desc'=> 'POS Sales Info' ],
				'details'	=> [ 'title'=>'Receipt Details', 'permission'=>'view_pos_sales_detail_wh_reports', 'desc'=> 'POS Sales With Items Details' ],
				'category'	=> [ 'title'=>'Category Sales', 'permission'=>'view_pos_category_sales_wh_reports', 'desc'=> 'POS Items Sold By Category' ],
				'items'		=> [ 'title'=>'Items Sales', 'permission'=>'view_pos_item_sales_wh_reports', 'desc'=> 'POS Items Sold' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/posSales.php" ); 
				$Inst = new WCWH_POSSales_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'sales':
						$Inst->noList = true;
						$Inst->pos_sales_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'details':
						$Inst->noList = true;
						$Inst->pos_sales_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'category':
						$Inst->noList = true;
						$Inst->pos_category_sales_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'items':
						$Inst->noList = true;
						$Inst->pos_item_sales_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'summary':
					default:
						$Inst->noList = true;
						$Inst->pos_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_pos_cost_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_pos_cost_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'items'		=> [ 'title'=>'Items Cost & Sales', 'permission'=>'view_pos_item_sales_wh_reports' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/posCost.php" ); 
				$Inst = new WCWH_POSCost_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'items':
					default:
						$Inst->noList = true;
						$Inst->pos_item_cost_report();
						$Inst->export_form( $onSect );
						//$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_purchase_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_purchase_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' => [ 'title'=>'PO Summary', 'permission'=>'view_po_summary_wh_reports', 'desc'=> 'Purchase Order Summary' ],
				'payment_method' => [ 'title'=>'PO by Payment Method', 'permission'=>'view_po_payment_method_wh_reports', 'desc'=> 'Purchase Order by Payment Method' ],
				'e_payment' => [ 'title'=>'SAP e-Payment', 'permission'=>'view_po_epayment_wh_reports', 'desc'=> 'e-Payment for SAP' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/purchaseReport.php" ); 
				$Inst = new WCWH_Purchase_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'e_payment':
						$Inst->noList = true;
						$Inst->sap_e_payment_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'payment_method':
						$Inst->noList = true;
						$Inst->po_payment_method_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'summary':
					default:
						$Inst->noList = true;
						$Inst->po_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	//Bank In Service report
	public function wh_bankin_service_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_bankin_service_rpt";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'bankin_daily' => [ 'title'=>'Daily Summary', 'permission'=>'view_daily_summary_wh_bankin_service_rpt', 'desc'=> 'Daily Remittance Money Summary Report' ],
				'bankin' => [ 'title'=>'Remittance Report Detail', 'permission'=>'view_detail_wh_bankin_service_rpt', 'desc'=> 'Remittance Money Detail Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>

			<?php 
				include_once( WCWH_DIR . "/includes/reports/bankinReport.php" ); 
				$Inst = new WCWH_BankIn_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php
					switch( $onSect )
					{
						case 'bankin_daily':
							$Inst->noList = true;
							$Inst->bank_in_daily_summary();
							$Inst->export_form( $onSect );
							$Inst->printing_form( $onSect );
						break;
						case 'bankin':
						default:
							$Inst->noList = true;
							$Inst->bank_in_report();
							$Inst->export_form( $onSect );
							$Inst->printing_form( $onSect );
						break;
					}
				?>
			</div>
		</div>
	<?php
	}

	public function wh_sales_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_sales_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Sales Order Listing', 'permission'=>'view_so_summary_wh_reports', 'desc'=> 'Sales Summary' ],
				'delivery_order' => [ 'title'=>'Sales Order with DO', 'permission'=>'view_so_delivey_wh_reports'
					, 'desc'=> 'Sales with Delivery Order' ],
				'po_sales' => [ 'title'=>'Sales Order by PO', 'permission'=>'view_so_po_wh_reports'
					, 'desc'=> 'Sales by Purchase Order' ],
				'canteen_einvoice' => [ 'title'=>"Minimart E-Invoice", 'permission'=>'view_so_canteen_einv_wh_reports'
					, 'desc'=> 'Minimart Related SAP e-Invoice' ],
				'non_canteen_einvoice' => [ 'title'=>"Direct Sales E-Invoice", 'permission'=>'view_so_xcanteen_einv_wh_reports'
					, 'desc'=> 'Direct Sales SAP e-Invoice' ],
				'unimart_einvoice' => [ 'title'=>"Unimart E-Invoice", 'permission'=>'view_so_xcanteen_einv_wh_reports'
					, 'desc'=> 'Unimart SAP e-Invoice' ],
				'setting'	=> [ 'title'=>'Setting', 'permission'=>'view_so_xcanteen_einv_wh_reports', 'desc'=> 'Report Setting' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/salesReport.php" ); 
				$Inst = new WCWH_Sales_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php if( ! in_array( $onSect, [ 'unimart_einvoice' ] ) ) $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
					<?php if( in_array( $onSect, [ 'canteen_einvoice', 'non_canteen_einvoice', 'unimart_einvoice' ] ) ) $Inst->view_fragment( 'export_sap' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'setting':
						$Inst->report_setting();
					break;
					case 'unimart_einvoice':
						$Inst->noList = true;
						$Inst->so_sap_unimart_einvoice();
						$Inst->export_form( $onSect );
						$Inst->export_form( $onSect.'_sap' );
					break;
					case 'canteen_einvoice':
						$Inst->noList = true;
						$Inst->so_sap_canteen_einvoice();
						$Inst->export_form( $onSect );
						$Inst->export_form( $onSect.'_sap' );
						$Inst->printing_form( $onSect );
					break;
					case 'non_canteen_einvoice':
						$Inst->noList = true;
						$Inst->so_sap_non_canteen_einvoice();
						$Inst->export_form( $onSect );
						$Inst->export_form( $onSect.'_sap' );
						$Inst->printing_form( $onSect );
					break;
					case 'delivery_order':
						$Inst->noList = true;
						$Inst->so_delivery_order_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'po_sales':
						$Inst->noList = true;
						$Inst->so_po_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'summary':
					default:
						$Inst->noList = true;
						$Inst->so_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_momawater_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_momawater_rpt";

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_mwt_summary_wh_reports', 'desc'=> 'MOMAwater Summary Report' ],
				'detail'	=> [ 'title'=>'Detail', 'permission'=>'view_mwt_detail_wh_reports', 'desc'=> 'MOMAwater Detail Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/momawater.php" ); 
				$Inst = new WCWH_MOMAwater_Rpt();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'detail':
						$Inst->noList = true;
						$Inst->momawater_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'summary':
					default:
						$Inst->noList = true;
						$Inst->momawater_summary_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_discrepancy_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_discrepancy_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'discrepancy' 	=> [ 'title'=>'Discrepancy', 'permission'=>'view_discrepancy_wh_reports', 'desc'=> 'Discrepancy Report' ],
				'delivery_order' 	=> [ 'title'=>'DO Discrepancy', 'permission'=>'access_discrepancy_wh_reports', 'desc'=> 'Delivery Order Discrepancy Report' ],
				'good_return' 	=> [ 'title'=>'GT Discrepancy', 'permission'=>'view_gt_discrepancy_wh_reports', 'desc'=> 'Good Return Discrepancy Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/discrepancy.php" ); 
				$Inst = new WCWH_Discrepancy_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'good_return':
						$Inst->noList = true;
						$Inst->gt_discrepancy_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'delivery_order':
						$Inst->noList = true;
						$Inst->do_discrepancy_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'discrepancy':
					default:
						$Inst->noList = true;
						$Inst->discrepancy_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_stock_aging_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_stock_aging_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		//$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'stock_aging' 	=> [ 'title'=>'Stock Aging', 'permission'=>'view_stock_aging_wh_reports', 'desc'=> 'Stock Aging Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/stockAging.php" ); 
				$Inst = new WCWH_StockAging_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'stock_aging':
					default:
						$Inst->noList = true;
						$Inst->stock_aging_report();
						$Inst->export_form( $onSect );
						//$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}
	
	// in/out
	public function wh_inout_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];
	
		$rpt_section = "wh_inout_rpt";
	
		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
	
		$tabs['tabs'] = $sellers['tabs'];
	
		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'stock_inout'	=> [ 'title'=>'In/Out Report', 'permission'=>'view_stock_inout_wh_reports', 'desc'=> 'Stock In/Out Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];
	
					$i++;
				}	
			}
	
			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/stockInOut.php" ); 
				$Inst = new WCWH_StockInOut_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10"></div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'stock_inout':
						$Inst->noList = true;
						$Inst->stock_inout_report();
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_movement_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_movement_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'stock_movement' 	=> [ 'title'=>'Stock Movement', 'permission'=>'view_stock_movement_wh_reports', 'desc'=> 'Stock Movement Report' ],
				'movement_summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_movement_summary_wh_reports', 'desc'=> 'Movement Summary Report' ],
				'fifo_movement' 	=> [ 'title'=>'FIFO Movement', 'permission'=>'view_fifo_movement_wh_reports', 'desc'=> 'FIFO Movement Report (Old)' ],
				'stock_in'			=> [ 'title'=>'Stock In +', 'permission'=>'view_stock_in_wh_reports', 'desc'=> 'Stock In Report' ],
				'stock_out'			=> [ 'title'=>'Stock Out -', 'permission'=>'view_stock_out_wh_reports', 'desc'=> 'Stock Out Report' ],
				'adjustment'		=> [ 'title'=>'Adjustment +/-', 'permission'=>'view_adjustment_wh_reports', 'desc'=> 'Stock Adjustment Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/stockMovement.php" ); 
				$Inst = new WCWH_StockMovement_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'stock_movement':
						$Inst->noList = true;
						$Inst->stock_movement_report();
						$Inst->export_form( $onSect );
					break;
					case 'movement_summary':
						$Inst->noList = true;
						$Inst->movement_summary_report();
						$Inst->export_form( $onSect );
					break;
					case 'fifo_movement':
						$Inst->noList = true;
						$Inst->fifo_movement_report();
						$Inst->export_form( $onSect );
					break;
					case 'stock_in':
						$Inst->noList = true;
						$Inst->stock_move_in_report();
						$Inst->export_form( $onSect );
					break;
					case 'stock_out':
						$Inst->noList = true;
						$Inst->stock_move_out_report();
						$Inst->export_form( $onSect );
					break;
					case 'adjustment':
						$Inst->noList = true;
						$Inst->stock_adjustment_report();
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}
	
	//Transaction Log
	public function wh_transaction_log_rpt()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "transaction_log_report";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/transactionLogCtrl.php" ); 
				$Inst = new WCWH_TransactionLog_Controller();
				$Inst->set_warehouse( $warehouse[ $onTab ]['code'] );
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_balance_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_balance_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'stock_balance' 	=> [ 'title'=>'Stock Balance', 'permission'=>'view_stock_balance_wh_reports', 'desc'=> 'Stock Balance Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/stock-balance.php" ); 
				$Inst = new WCWH_StockBalance_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
				else
				{
					$wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
					$Inst->seller = $wh['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'stock_balance':
						$Inst->noList = true;
						$Inst->stock_balance_report();
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_itemize_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_itemize_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/itemizeReport.php" ); 
				$Inst = new WCWH_Itemize_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->noList = true;
				$Inst->itemize_report();
				$Inst->export_form();
				$Inst->printing_form();
			?>
			</div>
		</div>
	<?php
	}

	public function wh_reorder_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_reorder_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/reorderReport.php" ); 
				$Inst = new WCWH_Reorder_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->noList = true;
				$Inst->reorder_report();
				$Inst->export_form();
			?>
			</div>
		</div>
	<?php
	}

	public function wh_unprocessed_doc_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_unprocessed_doc_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/unprocessDoc.php" ); 
				$Inst = new WCWH_UnprocessedDoc_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->noList = true;
				$Inst->unprocessed_doc_report();
				$Inst->export_form( $onSect );
			?>
			</div>
		</div>
	<?php
	}

	public function wh_foodboard_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_foodboard_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		$custom_title = [ '' => 'Others' ];
		foreach( $tabs['tabs'] as $key => $title ) 
		{
			if( ! empty( $custom_title[ $key ] ) ) $tabs['tabs'][ $key ] = $custom_title[ $key ];
		}

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'detail' 	=> [ 'title'=>'Detail', 'permission'=>'view_fb_detail_wh_reports', 'desc'=> 'FoodBoard Detail Report' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_fb_category_wh_reports', 'desc'=> 'FoodBoard Category Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/foodBoard.php" ); 
				$Inst = new WCWH_FoodBoard_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'detail':
						$Inst->noList = true;
						$Inst->foodboard_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'category':
						$Inst->noList = true;
						$Inst->foodboard_category_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_estate_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_estate_rpt";

		$sellers = $this->get_seller_as_tabs( 0, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		$custom_title = [ '' => 'Others' ];
		foreach( $tabs['tabs'] as $key => $title ) 
		{
			if( ! empty( $custom_title[ $key ] ) ) $tabs['tabs'][ $key ] = $custom_title[ $key ];
		}

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'detail' 	=> [ 'title'=>'Detail', 'permission'=>'view_et_detail_wh_reports', 'desc'=> 'Estate Detail Report' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_et_category_wh_reports', 'desc'=> 'Estate Category Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/estate.php" ); 
				$Inst = new WCWH_Estate_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'detail':
						$Inst->noList = true;
						$Inst->estate_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'category':
						$Inst->noList = true;
						$Inst->estate_category_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_estate_expenses_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_estate_expenses_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'detail' 	=> [ 'title'=>'Detail', 'permission'=>'view_et_detail_wh_reports', 'desc'=> 'Estate Detail Report' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_et_category_wh_reports', 'desc'=> 'Estate Category Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/estateExpenses.php" ); 
				$Inst = new WCWH_EstateExpenses_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'detail':
						$Inst->noList = true;
						$Inst->estate_expenses_detail_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'category':
						$Inst->noList = true;
						$Inst->estate_expenses_category_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_et_price_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_et_price_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'foodboard' 	=> [ 'title'=>'Foodboard Pricing', 'permission'=>'view_fb_price_wh_reports', 'desc'=> 'Foodboard Pricing Report' ],
				'estate'		=> [ 'title'=>'Estate Pricing', 'permission'=>'view_et_price_wh_reports', 'desc'=> 'Estate Pricing Report' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/etPricing.php" ); 
				$Inst = new WCWH_Estate_Pricing_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'foodboard':
						$Inst->noList = true;
						$Inst->foodboard_pricing_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
					case 'estate':
						$Inst->noList = true;
						$Inst->estate_pricing_report();
						$Inst->export_form( $onSect );
						$Inst->printing_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_trade_in_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_trade_in_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/tradeIn.php" ); 
				$Inst = new WCWH_TradeIn_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->noList = true;
				$Inst->trade_in_report();
				$Inst->export_form( $onSect );
			?>
			</div>
		</div>
	<?php
	}

	public function wh_intercom_company_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_intercom_company_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'report' 	=> [ 'title'=>'Report', 'permission'=>'access_intercom_company_wh_reports', 'desc'=> 'Generate report for exporting to SAP' ],
				'setting'		=> [ 'title'=>'Setting', 'permission'=>'access_intercom_company_wh_reports', 'desc'=> 'Configuration or data mapping' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/intercom.php" ); 
				$Inst = new WCWH_Intercom_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php if( ! in_array( $onSect, ['setting'] ) ) $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'report':
					default:
						$Inst->noList = true;
						$Inst->intercom_company_report();
						$Inst->export_form( 'company' );
						//$Inst->printing_form( 'company' );
					break;
					case 'setting':
						$Inst->set_action_type( 'intercom_company_report' );
						$Inst->company_setting();
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_intercom_worker_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_intercom_worker_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'report' 	=> [ 'title'=>'Report', 'permission'=>'access_intercom_worker_wh_reports', 'desc'=> 'Generate report for exporting to SAP' ],
				'setting'		=> [ 'title'=>'Setting', 'permission'=>'access_intercom_worker_wh_reports', 'desc'=> 'Configuration or data mapping' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/intercom.php" ); 
				$Inst = new WCWH_Intercom_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php if( ! in_array( $onSect, ['setting'] ) ) $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'report':
					default:
						$Inst->noList = true;
						$Inst->intercom_worker_report();
						$Inst->export_form( 'worker' );
						//$Inst->printing_form( 'worker' );
					break;
					case 'setting':
						$Inst->set_action_type( 'intercom_worker_report' );
						$Inst->worker_setting();
					break;
				}
			?>
			</div>
		</div>
	<?php
	}
	

		public function get_seller_as_tabs( $needPos = false, $section = '', $type = 'wh_reports' )
		{
			$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
			if( $needPos )
				$warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1, 'has_pos'=>1 ], [ 'id'=>'ASC' ], false, [ 'meta'=>['dbname', 'has_pos'] ] );
			else
				$warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [ 'id'=>'ASC' ], false, [ 'meta'=>['dbname', 'has_pos'] ] );
			
			$current = [];
			$warehouse = [];
			$tabs = [];

			if( $warehouses )
			{
				if( !empty( $curr_wh ) )
				{
					foreach( $warehouses as $wh )//access_1009-PMN_pos_wh_reports
					{
						$allow = true;
						if( ! current_user_cans( [ "access_wcwh_{$wh['code']}" ] ) ) $allow = false;
						
						if( $section && $type && current_user_cans( [ "overide_{$section}_{$type}" ] ) ) $allow = true;
						if( $section && $type 
							&& current_user_cans( [ "overide_{$section}_{$type}" ] ) 
							&& ! current_user_cans( [ "access_{$wh['code']}_{$section}_{$type}" ] ) 
						) $allow = false;
						
						if( ( ! $curr_wh['parent'] && $allow ) || ( $curr_wh['code'] == $wh['code'] && $allow ) )
						{
							if( $wh['indication'] && ! $wh['parent'] )
							{
								$current = $wh;
								$warehouse[ '' ] = $wh;
								continue;
							}
							else if( $wh['indication'] && $wh['parent'] )
							{
								$current = $wh;
								$warehouse[ '' ] = $wh;
								break;
							}
							else
							{
								if( $wh['dbname'] )
								{
									if( empty( $warehouse[ '' ] ) ) 
										$warehouse[ '' ] = $wh;
									else
										$warehouse[ $wh['code'] ] = $wh;
								}
							}
						}
					}
				}
				else
				{
					foreach( $warehouses as $wh )
					{
						$allow = true;
						if( ! current_user_cans( [ "access_wcwh_{$wh['code']}" ] ) ) $allow = false;

						if( $section && $type && current_user_cans( [ "overide_{$section}_{$type}" ] ) ) $allow = true;
						if( $section && $type 
							&& current_user_cans( [ "overide_{$section}_{$type}" ] ) 
							&& ! current_user_cans( [ "access_{$wh['code']}_{$section}_{$type}" ] ) 
						) $allow = false;
						
						if( $allow )
						{
							if( empty( $warehouse[ '' ] ) ) 
								$warehouse[ '' ] = $wh;
							else
								$warehouse[ $wh['code'] ] = $wh;
						}
					}
				}
				
				if( $warehouse )
				{
					if( !empty( $current ) )
						$tabs[''] = $current['code'].', '.$current['name'];
					foreach( $warehouse as $c => $wh )
					{
						if( empty( $tabs[''] ) ) 
							$tabs[ '' ] = $wh['name'];
						else
							$tabs[ $c ] = $wh['name'];
					}
				}
			}

			return [ 'warehouse'=>$warehouse, 'tabs'=>$tabs ];
		}


	/**
	 *	---------------------------------------------------------------------------------------------------
	 *	Charts
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_charts()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$cht_section = "wh_charts";

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/charts/queryChart.php" ); 
				$Inst = new WCWH_QueryChart();
			?>
			<div class="action-group row">
				<div class="col-md-10">
					
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				$Inst->query_chart();
				$Inst->export_form();
			?>
			</div>
		</div>
	<?php
	}
	
	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//
	public function wh_pos_overall_chart()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$display_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_pos_overall_summary_wh_charts', 'desc'=> 'POS Overall Summary' ],
				'category' 	=> [ 'title'=>'Category', 'permission'=>'view_pos_overall_summary_wh_charts', 'desc'=> 'POS Overall Sales By Category' ],
				'item' 	=> [ 'title'=>'Item', 'permission'=>'view_pos_overall_summary_wh_charts', 'desc'=> 'POS Overall Sales By Item' ],
			];
			
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'POS Overall: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $display_types as $key => $dpl_type )
			{
				if( current_user_cans( [ $dpl_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $dpl_type['title'];
					$inner['desc'][ $k ] = $dpl_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/charts/posOverallChart.php" ); 
				$Inst = new WCWH_POSOverallChart();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->pos_overall_summary( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
					case 'category':
						$Inst->pos_overall_category( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
					case 'item':
						$Inst->pos_overall_item( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}
	//-------- 7/9/22 jeff Chart Overall Sales by Item/Category -----//

	public function wh_pos_chart()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$cht_section = "wh_pos_chart";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$display_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_pos_summary_wh_charts', 'desc'=> 'POS Sales Summary' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_pos_category_wh_charts', 'desc'=> 'POS Sales Category' ],
				'item'		=> [ 'title'=>'Item', 'permission'=>'view_pos_item_wh_charts', 'desc'=> 'POS Sales Item' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'POS Sales: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $display_types as $key => $dpl_type )
			{
				if( current_user_cans( [ $dpl_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $dpl_type['title'];
					$inner['desc'][ $k ] = $dpl_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/charts/posSalesChart.php" ); 
				$Inst = new WCWH_POSSalesChart();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->pos_sales_summary( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
					case 'category':
						$Inst->pos_sales_category( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
					case 'item':
						$Inst->pos_sales_item( [ 'initial'=>1 ] );
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_foodboard_chart()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$cht_section = "wh_foodboard_chart";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$display_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_foodboard_summary_wh_charts', 'desc'=> 'FoodBoard Summary Chart' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_foodboard_category_wh_charts', 'desc'=> 'FoodBoard Category Chart' ],
				'item'		=> [ 'title'=>'Item', 'permission'=>'view_foodboard_item_wh_charts', 'desc'=> 'FoodBoard Item Chart' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'FoodBoard ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $display_types as $key => $dpl_type )
			{
				if( current_user_cans( [ $dpl_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $dpl_type['title'];
					$inner['desc'][ $k ] = $dpl_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/charts/foodBoardChart.php" ); 
				$Inst = new WCWH_FoodBoardChart();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->foodboard_summary();
						$Inst->export_form( $onSect );
					break;
					case 'category':
						$Inst->foodboard_category();
						$Inst->export_form( $onSect );
					break;
					case 'item':
						$Inst->foodboard_item();
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_estate_chart()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$cht_section = "wh_estate_chart";

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$display_types = [
				'summary' 	=> [ 'title'=>'Summary', 'permission'=>'view_estate_summary_wh_charts', 'desc'=> 'FoodBoard Summary Chart' ],
				'category'		=> [ 'title'=>'Category', 'permission'=>'view_estate_category_wh_charts', 'desc'=> 'FoodBoard Category Chart' ],
				'item'		=> [ 'title'=>'Item', 'permission'=>'view_estate_item_wh_charts', 'desc'=> 'FoodBoard Item Chart' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'FoodBoard ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $display_types as $key => $dpl_type )
			{
				if( current_user_cans( [ $dpl_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $dpl_type['title'];
					$inner['desc'][ $k ] = $dpl_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/charts/estateChart.php" ); 
				$Inst = new WCWH_EstateChart();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			<?php
				switch( $onSect )
				{
					case 'summary':
						$Inst->estate_summary();
						$Inst->export_form( $onSect );
					break;
					case 'category':
						$Inst->estate_category();
						$Inst->export_form( $onSect );
					break;
					case 'item':
						$Inst->estate_item();
						$Inst->export_form( $onSect );
					break;
				}
			?>
			</div>
		</div>
	<?php
	}


	/**
	 *	---------------------------------------------------------------------------------------------------
	 *	Others
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function wh_pos_session()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posSessionCtrl.php" ); 
				$Inst = new WCWH_POSSession_Controller();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_pos_order()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>" 
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posOrderCtrl.php" ); 
				$Inst = new WCWH_PosOrder_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_pos_cdn()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>" 
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posCDNCtrl.php" ); 
				$Inst = new WCWH_PosCDN_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10 row">
					<?php $Inst->view_reference(); ?>
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_pos_price()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posPriceCtrl.php" ); 
				$Inst = new WCWH_PosPrice_Controller();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	//POS Credit
	public function wh_pos_credit()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posCreditCtrl.php" ); 
				$Inst = new WCWH_PosCredit_Controller();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					
				</div>
				<div class="col-md-2 rightered">
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->export_form(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_pos_cash_withdrawal()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		if(!$warehouse)
		{
			$warehouse= apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		}
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code']?$wh['code']:$warehouse['code'] ?>" 
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/posCashWithdrawalCtrl.php" ); 
				$Inst = new WCWH_PosCashWithdrawal_Controller();
				$Inst->set_warehouse( $wh?$wh:$warehouse);
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
					
				</div>
				<div class="col-md-2 rightered">
				
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->cw_form(); ?>
					<?PHP $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_money_collector()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$sellers = $this->get_seller_as_tabs( 1 );
		$warehouse = $sellers['warehouse'];
		if(!$warehouse)
		{
			$warehouse= apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true );
		}
		$tabs['tabs'] = $sellers['tabs'];

		if( current_user_cans( [ 'manage_options' ] ) )
			$tabs['tabs']['setting'] = "Setting";

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code']?$wh['code']:$warehouse['code'] ?>" 
		>
			<?php 
				include_once( WCWH_DIR."/includes/controller/moneyCollectorCtrl.php" ); 
				$Inst = new WCWH_MoneyCollector_Controller();
				if( $wh ) $Inst->set_warehouse( $wh?$wh:$warehouse);
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php if( $onTab != 'setting' ) $Inst->view_fragment(); ?>
					
				</div>
				<div class="col-md-2 rightered">
				
				</div>
			</div>

			<div class="wcwh-content">
			<?php
				switch( $onTab )
				{
					case 'setting':
						$Inst->setting();
					break;
					default:
						$Inst->view_listing();
					?>
						<div class="template-container">
							<?php $Inst->view_form(); ?>
							<?php $Inst->mc_form(); ?>
							<?php $Inst->view_row() ?>
						</div>
					<?php
					break;
				}
			?>
			</div>
		</div>
	<?php
	}

	public function wh_uncollected_money_rpt()
	{
		$tabs = [
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		];

		$rpt_section = "wh_uncollected_money_rpt";

		$sellers = $this->get_seller_as_tabs( 1, $rpt_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $this->warehouse['code'] ?>" data-diff_seller="<?php echo $warehouse[ $onTab ]['id']; ?>"
		>
		<?php
			$report_types = [
				'details'	=> [ 'title'=>'Money Collector Report', 'permission'=>'access_wh_uncollected_money_rpt', 'desc'=> 'Money Collector Report' ],
				//'summary' 	=> [ 'title'=>'Uncollected Money Summary', 'permission'=>'access_wh_uncollected_money_rpt', 'desc'=> 'Uncollected Money Summary' ],
			];
			$inner = [
				'id' => 'wcwhInnerTab', 
				'header' => 'Report Type: ',
				'tabs' => [],
				'desc' => [],
			];
			$i = 0; $main_key = '';
			foreach( $report_types as $key => $rpt_type )
			{
				if( current_user_cans( [ $rpt_type['permission'] ] ) )
				{
					$k = ( $i == 0 )? '' : $key;
					$main_key = ( $i == 0 )? $key : $main_key;
					$inner['tabs'][ $k ] = $rpt_type['title'];
					$inner['desc'][ $k ] = $rpt_type['desc'];

					$i++;
				}	
			}

			do_action( 'wcwh_get_template', 'segment/inner-tabs.php', $inner );
			$onSect = ( !empty( $inner['tabs'] ) && isset( $_GET['section'] ) )? $_GET['section'] : '';
			$onSect = ( !empty( $onSect ) )? $onSect : $main_key; 
		?>
			<?php 
				include_once( WCWH_DIR . "/includes/reports/uncollectedMoney.php" ); 
				$Inst = new WCWH_UncollectedMoney_Rpt();
				if( $warehouse[ $onTab ]['id'] )
				{
					$Inst->seller = $warehouse[ $onTab ]['id'];
				}
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_latest(); ?>
				</div>
				<div class="col-md-2 rightered">
					<?php //$Inst->view_fragment( 'print' ); ?>
					<?php $Inst->view_fragment( 'export' ); ?>
				</div>
			</div>
			
			<div class="wcwh-content">
			 <?php
			 	switch($onSect)
				{
					case 'details':
						$Inst->noList = true;
						$Inst->uncollected_money_rpt();
						$Inst->export_form();
						//$Inst->printing_form();
					break;
				}
				
			
				?>
			</div>
		</div>
	<?php
	}

	public function wh_search_tin()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => [],	//key=>title 
		);

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		if( $tabs['tabs'] ) $t_key = array_keys( $tabs['tabs'] );
		$d_tab = !empty( $tabs['default'] )? $tabs['default'] : ( $t_key ? $t_key[0] : '' );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : $d_tab;
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>"
			data-tab="" 
		>
			<?php
				include_once( WCWH_DIR."/includes/controller/searchTinCtrl.php" ); 
				$Inst = new WCWH_SearchTin_Controller();
			?>
			<div class="action-group row">
				<div class="col-md-10">
				</div>
				<div class="col-md-2 rightered">
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>
			</div>
		</div>
	<?php
	}
	
	//Task Schedule
	public function wh_task_schedule()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_task_schedule";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/taskScheduleCtrl.php" ); 
				$Inst = new WCWH_TaskSchedule_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php $Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php $Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

	public function wh_task_checklist()
	{
		$tabs = array( 
			'id' => 'wcwhPageTab', 
			'tabs' => array(),	//key=>title 
		);

		$tab_section = "wh_task_checklist";

		$sellers = $this->get_seller_as_tabs( 0, $tab_section, $tab_section );
		$warehouse = $sellers['warehouse'];
		$tabs['tabs'] = $sellers['tabs'];

		do_action( 'wcwh_get_template', 'segment/page-tabs.php', $tabs );
		$onTab = ( !empty( $tabs['tabs'] ) && isset( $_GET['tab'] ) )? $_GET['tab'] : '';

		$seller = $warehouse[ $onTab ]['id'];
		if( $seller ) $wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$seller ], [], true );
	?>
		<div class="wcwh-section" id="<?php echo $this->section.( $onTab ? '_'.$onTab : '' ); ?>" 
			data-wh="<?php echo $wh['code'] ?>"
		>
			<?php 
				include_once( WCWH_DIR . "/includes/controller/taskChecklistCtrl.php" ); 
				$Inst = new WCWH_TaskChecklist_Controller();
				$Inst->set_warehouse( $wh );
			?>
			<div class="action-group row">
				<div class="col-md-10">
					<?php //$Inst->view_fragment(); ?>
				</div>
				<div class="col-md-2 rightered">
					
				</div>
			</div>

			<div class="wcwh-content">
				<?php $Inst->view_listing(); ?>

				<div class="template-container">
					<?php $Inst->view_form(); ?>
					<?php //$Inst->view_row(); ?>
				</div>
			</div>
		</div>
	<?php
	}

}

}
