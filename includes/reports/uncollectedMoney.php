<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_UncollectedMoney_Rpt" ) ) 
{
	
class WCWH_UncollectedMoney_Rpt extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "UncollectedMoney";

	public $tplName = array(
		'export' => 'exportUncollectedMoney',
		'print' => 'printUncollectedMoney',
	);
	
	protected $tables = array();

	public $seller = 0;
	public $filters = array();
	public $noList = false;

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		$this->set_db_tables();
	}
	
	public function set_db_tables()
	{
		global $wpdb, $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"document" 			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",
			"items"				=> $prefix."items",
			"doc_runningno"		=> $prefix."doc_runningno",
			"temp_pos"			=> "temp_pos",
		);
	}


	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function action_handler( $action, $datas = array(), $obj = array(), $transact = true )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	if( $transact ) wpdb_start_transaction( $this->db_wpdb );

        	$isSave = false;
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );
			$date_format = get_option( 'date_format' );

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					switch( $datas['export_type'] )
					{
						case 'details':
							$datas['filename'] = 'Money Collector Report ';
						break;
					}
					
					$datas['nodate'] = 1;
					//$datas['dateformat'] = 'YmdHis';
					if( $datas['from_date'] ) $datas['filename'].= date( $date_format, strtotime( $datas['from_date'] ) );
					if( $datas['to_date'] )  $datas['filename'].= " - ".date( $date_format, strtotime( $datas['to_date'] ) );
					
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['from_date_month'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date_month'] ) );
					if( !empty( $datas['to_date_month'] ) ) $params['to_date'] = date( 'Y-m-t H:i:s', strtotime( $datas['to_date_month']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
				break;
				case "print":
					$params = [];
					if( !empty( $datas['seller'] ) ) $params['seller'] = $datas['seller'];
					if( !empty( $datas['from_date'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date'] ) );
					if( !empty( $datas['to_date'] ) ) $params['to_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['to_date']." 23:59:59" ) );
					if( !empty( $datas['from_date_month'] ) ) $params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $datas['from_date_month'] ) );
					if( !empty( $datas['to_date_month'] ) ) $params['to_date'] = date( 'Y-m-t H:i:s', strtotime( $datas['to_date_month']." 23:59:59" ) );
					if( !empty( $datas['customer'] ) ) $params['customer'] = $datas['customer'];
					if( !empty( $datas['category'] ) ) $params['category'] = $datas['category'];
					if( !empty( $datas['product'] ) ) $params['product'] = $datas['product'];
					if( !empty( $datas['export_type'] ) ) $params['export_type'] = $datas['export_type'];
					//pd( $params );
					$this->print_handler( $params, $datas );
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
            if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }
        finally
        {
        	if( $succ )
                if( $transact ) wpdb_end_transaction( true, $this->db_wpdb );
            else 
                if( $transact ) wpdb_end_transaction( false, $this->db_wpdb );
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}


	/**
	 *	Import Export
	 *	---------------------------------------------------------------------------------------------------
	 */
	protected function im_ex_default_column( $params = array() )
	{
		$default_column = array();

		//$default_column['title'] = [];

		//$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$type = $params['export_type'];
		unset( $params['export_type'] );
		switch( $type )
		{
			case 'details':
				if($params['seller'])
				{
					$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$params['seller'] ], [], true, [ 'meta'=>['mc_cutoff'] ] );
					$params['wh'] = $wh['code'];
					
				}else
				{
					$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'meta'=>['mc_cutoff'] ] );
					$params['wh'] = $curr_wh ['code'];
				}
				$params['doc_type'] = 'money_collector';

				if( $wh && $wh['mc_cutoff'] )
				{
					$params['from_date'] = date( 'Y-m-d H:i:s', strtotime( $wh['mc_cutoff'] ) );
				}

				//---------------
				$f = $params; unset( $f['seller'] );
				$results = $this->pre_finding( $f );
				if( $results )
				{
					if( strtotime( $results['from_period']." 00:00:00" ) < strtotime( $params['from_date'] ) )
						$params['from_date'] = $results['from_period']." 00:00:00";
					if( strtotime( $results['to_period']." 23:59:59" ) > strtotime( $params['to_date'] ) )
						$params['to_date'] = $results['to_period']." 23:59:59";
				}
				//---------------

				$this->temporary_pos_cash( $params, true );
				$amt = $this->get_pos_cash( $params, [] );
				
				unset( $params['seller'] );
				$datas = $this->get_collected_money_report( $params, $order );

				$collected = 0;
				foreach( $datas as $i => $row )
				{
					$collected+= $row['collected_amt'];
				}

				if( $amt )
				{
					$row = [
						'doc_id' => '',
						'docno' => 'Overall',
						'date' => '',
						'from_period' => $amt['from_period'],
						'to_period' => $amt['to_period'],
						'cash_sales' => $amt['cash_sales'],
						'collector' => '',
						'collected_amt' => $collected,
						'balance' => round_to( $amt['cash_sales'] - $collected, 2 ),
					];

					$datas[] = $row;
				}
				
				return $datas;
			break;
		}
	}

	public function print_handler( $params = array(), $opts = array() )
	{
		$datas = $this->export_data_handler( $params );
		
		$type = $params['export_type'];
		unset( $params['export_type'] );
		$date_format = get_option( 'date_format' );
		$currency = get_woocommerce_currency_symbol();//$currency = get_woocommerce_currency();
		
		switch( $type )
		{
			case 'uncollected_money':
				$datas['uncollected'] = $this->get_pos_cash($params);
				$filename = "Uncollected Money";
				if( $params['from_date'] ) $filename.= " ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $filename.= " - ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				if( ! $this->seller )
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'company'=>1 ] );
				else
					$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true, [ 'company'=>1 ] );

				$document = [];
				$document['config'] = $opts;
				$document['header'] = 'Uncollected Money';
				$document['heading']['company'] = $warehouse['comp_name'];
				$document['heading']['title'] = 'Uncollected Money';
				
				if( $params['from_date'] ) $document['heading']['title'].= " From ".date_i18n( $date_format, strtotime( $params['from_date'] ) );
				if( $params['to_date'] )  $document['heading']['title'].= " to ".date_i18n( $date_format, strtotime( $params['to_date'] ) );
				
				$user_info = get_userdata( get_current_user_id() );
				$document['heading']['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d' ) ) );
				$document['heading']['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;

				$document['detail_title'] = [
					'Date' => [ 'width'=>'35%', 'class'=>['leftered'] ],
					'Document' => [ 'width'=>'25%', 'class'=>['centered'] ],
					//'Paid('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					//'Change('.$currency.')' => [ 'width'=>'10%', 'class'=>['rightered'] ],
					'Collect Person' => [ 'width'=>'15%', 'class'=>['centered']],
					'Collect Amount('.$currency.')' => [ 'width'=>'15%', 'class'=>['centered'] ],
					// 'Bank In Amount('.$currency.')' => [ 'width'=>'15%', 'class'=>['centered'] ],
				];
				if( $datas )
				{
					$amt = 0; 
					$total_bankin_amt = 0; 
					$total_amt = 0;
					$uncollected_amt = $datas['uncollected'][0]['amt_cash'];
					unset($datas['uncollected']);
					$details = [];
					foreach( $datas as $i => $data )
					{
						$row = [

                        'date' => [ 'value'=>$data['date'], 'class'=>['leftered'] ],
                        'docno' => [ 'value'=>$data['docno'], 'class'=>['leftered']  ],
                        //'amt_paid' => [ 'value'=>$data['amt_paid'], 'class'=>['rightered'], 'num' => 1 ],
                        //'amt_change' => [ 'value'=>$data['amt_change'], 'class'=>['rightered'], 'num' => 1 ],
                        'withdraw_person' => [ 'value'=>$data['withdraw_person'], 'class'=>['rightered'] ],
                        'amt' => [ 'value'=>$data['amt'], 'class'=>['rightered'], 'num' => 1 ],
                        // 'bankin_amt' => [ 'value'=>$data['bankin_amt'], 'class'=>['rightered'], 'num' => 1 ],

						];

						$details[] = $row;

						//totals
						$total_amt += $data['amt'];
						$total_bankin_amt += $data['bankin_amt'];
						
					}

					
					$final_uncollected_amt= floatval($uncollected_amt) - floatval($total_amt);

					$details[] = [

						'date' => [ 'value' => 'TOTAL:', 'class' => ['leftered', 'bold']  ],
						'docno' => [ 'value'=>'', 'class'=>['leftered']  ],
                        'withdraw_person' => [ 'value'=>'', 'class'=>['rightered'] ],
						'amt' => [ 'value'=>$total_amt, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						// 'bankin_amt' => [ 'value'=>$total_bankin_amt, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						

					];

					$details[] = [

						'date' => [ 'value' => 'Uncollected Amount:', 'class' => ['leftered', 'bold']  ],
						'docno' => [ 'value'=>'', 'class'=>['leftered']  ],
                        'withdraw_person' => [ 'value'=>'', 'class'=>['rightered'] ],
						'amt' => [ 'value'=>$final_uncollected_amt, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						// 'bankin_amt' => [ 'value'=>'', 'class'=>['rightered', 'bold'] ],
						
						

					];

					$details[] = [

						'date' => [ 'value' => 'Total Amount:', 'class' => ['leftered', 'bold']  ],
						'docno' => [ 'value'=>'', 'class'=>['leftered']  ],
                        'withdraw_person' => [ 'value'=>'', 'class'=>['rightered'] ],
						'amt' => [ 'value'=>$uncollected_amt, 'class'=>['rightered', 'bold'], 'num' => 1 ],
						// 'bankin_amt' => [ 'value'=>'', 'class'=>['rightered', 'bold'] ],
						
						

					];

					$document['detail'] = $details;
				}
				//pd($document);
				ob_start();
							
					do_action( 'wcwh_get_template', 'template/doc-summary-general.php', $document );
				
				$content.= ob_get_clean();
				//echo $content;exit;
				if( is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) ){
					$paper = [ 'size' => 'A4', 'orientation' => $opts['orientation']? $opts['orientation'] : 'portrait' ];
					$args = [ 'filename' => $filename ];
					do_action( 'dompdf_generator', $content, $paper, array(), $args );
				}
				else{
					echo $content;
				}
			break;
		}

		exit;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_latest()
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		if( ! $this->seller ) return;

		$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$this->seller ], [], true );
		if( ! $curr_wh || $curr_wh['indication'] ) return;
		
		$dbname = get_warehouse_meta( $this->seller, 'dbname', true );
		$dbname = ( $dbname )? $dbname."." : "";

		$cond = $wpdb->prepare( "AND post_type = %s AND post_status = %s ", "pos_temp_register_or", "publish" );
		$ord = "ORDER BY post_date DESC ";
		$l = "LIMIT 0,1 ";
		$sql = "SELECT * FROM {$dbname}{$wpdb->posts} WHERE 1 {$cond} {$ord} {$l}";
		$result = $wpdb->get_row( $sql , ARRAY_A );

		if( $result )
		{
			$now = strtotime( current_time( 'mysql' ) );
			$latest_record = strtotime( $result['post_date'] );
			$max_diff_sec = 86400; //1day
			if( (int)$now - (int)$latest_record >= 86400 )
			{
				echo "<span class='required toolTip' title='Data delayed for more than 24 hours, data might failed to sync back from site.'>Latest data: {$result['post_date']}</span>";
			}
			else
			{
				echo "<span class='toolTip' title='Latest site data found.'>Latest data: {$result['post_date']}</span>";
			}
		}

		$cutoff = get_warehouse_meta( $this->seller, 'mc_cutoff', true );
		if( $cutoff )
		{
			echo "<br><span class='toolTip' title='Cut Off Period'>Cut Off Date: {$cutoff}</span>";
		}
	}

	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'export':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="export" data-tpl="<?php echo $this->tplName['export'] ?>" 
					data-title="<?php echo $actions['export'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Report"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
			case 'print':
			?>
				<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['print'] ?>" 
					data-title="<?php echo $actions['print'] ?> Report" data-modal="wcwhModalImEx" 
					data-actions="close|printing" 
					title="<?php echo $actions['print'] ?> Report"
				>
					<i class="fa fa-print" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form( $type = 'details' )
	{
		$action_id = 'uncollected_money_rpt_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'details':
				do_action( 'wcwh_templating', 'report/export-uncollected-money-report.php', $this->tplName['export'], $args );
			break;
		}
	}

	public function printing_form( $type = 'details' )
	{
		$action_id = 'uncollected_money_rpt_export';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['print'],
			'section'	=> $action_id,
			'isPrint'	=> 1,
		);

		if( $this->filters ) $args['filters'] = $this->filters;

		switch( strtolower( $type ) )
		{
			case 'details':
				do_action( 'wcwh_templating', 'report/export-uncollected-money-report.php', $this->tplName['print'], $args );
			break;
		}
	}

	/**
	 *	Details
	 */
	public function uncollected_money_rpt( $filters = array(), $order = array() )
	{
		$action_id = 'uncollected_money_rpt';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
		<?php
			include_once( WCWH_DIR."/includes/reports/uncollectedMoneyList.php" ); 
			$Inst = new WCWH_UncollectedMoney_Report();
			$Inst->seller = $this->seller;
			
			$date_from = current_time( 'Y-m-1' );
			$date_to = current_time( 'Y-m-t' );
			
			$filters['from_date'] = empty( $filters['from_date'] )? $date_from : $filters['from_date'];
			$filters['to_date'] = empty( $filters['to_date'] )? $date_to : $filters['to_date'];
			
			$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['from_date'] ) );
			$filters['to_date'] = date( 'Y-m-d H:i:s', strtotime( $filters['to_date']." 23:59:59" ) );

			if( $this->seller ) $filters['seller'] = $this->seller;
			if($filters['seller'])
			{
				$wh = apply_filters( 'wcwh_get_warehouse', [ 'id'=>$filters['seller'] ], [], true, [ 'meta'=>['mc_cutoff'] ] );
				$filters['wh'] = $wh['code'];
			}else
			{
				$curr_wh = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'meta'=>['mc_cutoff'] ] );
				$filters['wh'] = $curr_wh ['code'];
			}
			$filters['doc_type'] = 'money_collector';

			if( $wh && $wh['mc_cutoff'] && strtotime( $filters['from_date'] ) < strtotime( $wh['mc_cutoff'] ) )
			{
				$filters['from_date'] = date( 'Y-m-d H:i:s', strtotime( $wh['mc_cutoff'] ) );
			}

			$Inst->filters = $this->filters = $filters;
			$Inst->advSearch = array( 'isOn'=>1 );
			//$Inst->paginate = false;
			$Inst->per_page_limit = 1000;
			$Inst->set_args( [ 'off_footer'=>true ] );

			$Inst->styles = [
				'.collected_amt, .balance' => [ 'text-align'=>'right !important' ],
            ];

			//$order = $Inst->get_data_ordering();

			//---------------
			$f = $filters; unset( $f['seller'] );
			$results = $this->pre_finding( $f );
			if( $results )
			{
				if( strtotime( $results['from_period']." 00:00:00" ) < strtotime( $filters['from_date'] ) )
					$filters['from_date'] = $results['from_period']." 00:00:00";
				if( strtotime( $results['to_period']." 23:59:59" ) > strtotime( $filters['to_date'] ) )
					$filters['to_date'] = $results['to_period']." 23:59:59";
			}
			//---------------

			$datas = [];
			if( ! $this->noList )
			{
				$this->temporary_pos_cash( $filters, true );
				$amt = $this->get_pos_cash( $filters, [] );
				$Inst->overall = $amt;

				unset( $filters['seller'] );
				$datas = $this->get_collected_money_report( $filters, $order );
				
				// pass uncollected amount to the 1st data
				$datas = ( $datas )? $datas : array();
			}
			
			$Inst->set_details( $datas );
			$Inst->render();
		?>
		</form>
		<?php
	}

	
	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function pre_finding( $filters = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		if($args && $args['dbname'])
		{
			$dbname = $args['dbname'].".";
		}
		
		$field = "MIN( DATE_FORMAT( f.meta_value, '%Y-%m-%d' ) ) AS from_period, MAX( DATE_FORMAT( g.meta_value, '%Y-%m-%d' ) ) AS to_period ";
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} f ON f.doc_id = a.doc_id AND f.meta_key = 'from_period' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} g ON g.doc_id = a.doc_id AND g.meta_key = 'to_period' ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s AND a.status >= 6 ", $filters['doc_type'] );

		if(isset($filters['wh']))
		{
			$cond.=  "AND a.warehouse_id = '".$filters['wh']."' ";
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( f.meta_value >= %s OR g.meta_value >= %s )", $filters['from_date'], $filters['from_date'] );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( f.meta_value <= %s OR g.meta_value <= %s )", $filters['to_date'], $filters['to_date'] );
		}

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} ";
		$results = $wpdb->get_row( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_collected_money_report( $filters = [], $order = [], $args = [] )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		if($args && $args['dbname'])
		{
			$dbname = $args['dbname'].".";
		}
		
		$field = "a.doc_id as doc_id, a.docno as docno, DATE_FORMAT( a.doc_date, '%Y-%m-%d' ) AS date ";
		$field.= ", DATE_FORMAT( f.meta_value, '%Y-%m-%d' ) AS from_period, DATE_FORMAT( g.meta_value, '%Y-%m-%d' ) AS to_period ";
		$field.= ", ROUND( SUM( tp.amt_cash ), 2 ) AS cash_sales, d.meta_value as collector, c.meta_value as collected_amt ";
		$field.= ", ROUND( SUM( tp.amt_cash ) - c.meta_value, 2 ) AS balance ";
		
		$table = "{$dbname}{$this->tables['document']} a ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_items']} b ON b.doc_id = a.doc_id AND b.status > 0 ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} c ON c.doc_id = a.doc_id AND c.meta_key = 'amt' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} d ON d.doc_id = a.doc_id AND d.meta_key = 'withdraw_person' ";
		//$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} e ON b.doc_id = a.doc_id AND e.item_id = b.item_id AND e.meta_key = 'bankin_amt'";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} f ON f.doc_id = a.doc_id AND f.meta_key = 'from_period' ";
		$table.= "LEFT JOIN {$dbname}{$this->tables['document_meta']} g ON g.doc_id = a.doc_id AND g.meta_key = 'to_period' ";

		$table.= "LEFT JOIN {$this->tables['temp_pos']} tp ON DATE_FORMAT( tp.date, '%Y-%m-%d' ) >= DATE_FORMAT( f.meta_value, '%Y-%m-%d' ) AND DATE_FORMAT( tp.date, '%Y-%m-%d' ) <= DATE_FORMAT( g.meta_value, '%Y-%m-%d' ) ";
		
		$cond = $wpdb->prepare( "AND a.doc_type = %s ", $filters['doc_type'] );
		$cond.= "AND a.status >= 6 ";

		if(isset($filters['wh']))
		{
			$cond.=  "AND a.warehouse_id = '".$filters['wh']."' ";
		}
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( f.meta_value >= %s OR g.meta_value >= %s )", $filters['from_date']." 00:00:00", $filters['from_date']." 00:00:00" );
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND ( f.meta_value <= %s OR g.meta_value <= %s )", $filters['to_date']." 23:59:59", $filters['to_date']." 23:59:59" );
		}

		$grp = "GROUP BY a.docno ";
	
		//order
		if( empty( $order ) )
		{
			$order = [ 'a.doc_date' => 'ASC' ];
		} 
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord = "ORDER BY ".implode( ", ", $o )." ";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}

	public function get_pos_cash( $filters = [], $args = [] )
	{
		global $wmch;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$field = "DATE_FORMAT( '{$filters['from_date']}', '%Y-%m-%d' ) AS from_period
			, DATE_FORMAT( '{$filters['to_date']}', '%Y-%m-%d' ) AS to_period, SUM( a.amt_cash ) AS cash_sales  ";
		$table = "{$this->tables['temp_pos']} a ";
		$cond = "";

		/*if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.date >= %s ", $filters['from_date']);
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.date <= %s ", $filters['to_date']);
		}*/

		$grp = "";
		$ord = "";

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";
		$results = $wpdb->get_row( $sql , ARRAY_A );
		
		return $results;
	}

	public function temporary_pos_cash( $filters = [], $run = false, $args = [] )
	{
		global $wmch;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		@set_time_limit(1800);

		//filter empty
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[ $key ] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		if( isset( $filters['seller'] ) )
		{
			$dbname = get_warehouse_meta( $filters['seller'], 'dbname', true );
			$dbname = ( $dbname )? $dbname."." : "";
		}

		$field = "DATE_FORMAT( a.post_date, '%Y-%m-%d' ) AS date ";
		$field.= ", ROUND( SUM( f.meta_value - g.meta_value ), 2 ) AS amt_cash ";
		$table = "{$dbname}{$wpdb->posts} a ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} b ON b.post_id = a.ID AND b.meta_key = '_order_total' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} c ON c.post_id = a.ID AND c.meta_key = '_payment_method' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} d ON d.post_id = a.ID AND d.meta_key = 'wc_pos_id_register' ";
		//$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} e ON e.post_id = a.ID AND e.meta_key = '_pos_session_id' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} f ON f.post_id = a.ID AND f.meta_key = 'wc_pos_amount_pay' ";
		$table.= "LEFT JOIN {$dbname}{$wpdb->postmeta} g ON g.post_id = a.ID AND g.meta_key = 'wc_pos_amount_change' ";
		
		$cond = $wpdb->prepare( "AND a.post_type = %s AND b.meta_value IS NOT NULL ", 'shop_order' );
		$cond.= "AND a.post_status IN( 'wc-processing', 'wc-completed' ) ";
		
		if( isset( $filters['from_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date >= %s ", $filters['from_date']);
		}
		if( isset( $filters['to_date'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.post_date <= %s ", $filters['to_date']);
		}
		/*if( isset( $filters['payment_method'] ) )
		{
			$cond.= $wpdb->prepare( "AND c.meta_value = %s ", $filters['payment_method'] );
		}*/
		/*if( isset( $filters['register'] ) )
		{
			$cond.= $wpdb->prepare( "AND d.meta_value = %s ", $filters['register'] );
		}*/
		/*if( isset( $filters['session'] ) )
		{
			$cond.= $wpdb->prepare( "AND e.meta_value = %s ", $filters['session'] );
		}*/
		
		$grp = "GROUP BY date";
		
        $ord = "ORDER BY date ASC ";

		$select = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} ";

		$query = "CREATE TEMPORARY TABLE IF NOT EXISTS {$this->tables['temp_pos']} ";
		$query.= "AS ( {$select} ) ";

		if( $run ) $query = $wpdb->query( $query );

		/*$s = "SELECT * FROM {$this->tables['temp_pos']} ";
		$results = $wpdb->get_results( $s , ARRAY_A );
		rt($results);*/
		
		return $query;
	}

	public function drop_temporary_pos_cash()
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$drop = "DROP TEMPORARY TABLE {$this->tables['temp_pos']} ";
    	$succ = $wpdb->query( $drop );
	}
	
	
} //class

}