<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_PartsRequest_Class" ) ) include_once( WCWH_DIR . "/includes/classes/parts-request.php" );

if ( !class_exists( "WCWH_PartsRequest_Controller" ) ) 
{

class WCWH_PartsRequest_Controller extends WCWH_CRUD_Controller
{
	protected $section_id = "wh_parts_request";

	public $Notices;
	public $className = "PartsRequest_Controller";

	public $Logic;
	public $Files;

	public $tplName = array(
		'new' => 'newTR',
		'row' => 'rowTR',
		'tr' => 'printTR',
		'multiTR' => 'printMultiTR',
	);

	public $useFlag = false;

	protected $warehouse = array();
	protected $view_outlet = false;
	
	public $processing_stat = [ 1 ];

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		$this->Files = new WCWH_Files();

		$this->arrangement_init();
		
		$this->set_logic();
	}

	public function arrangement_init()
	{
		$Inst = new WCWH_TODO_Class();

		$arr = $Inst->get_arrangement( [ 'section'=>$this->section_id, 'action_type'=>'approval', 'status'=>1 ] );
		if( $arr )
		{
			$this->useFlag = true;
		}
	}

	public function set_logic()
	{
		$this->Logic = new WCWH_PartsRequest_Class( $this->db_wpdb );
		$this->Logic->set_section_id( $this->section_id );
		$this->Logic->processing_stat = $this->processing_stat;
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_warehouse( $warehouse = array() )
	{
		$this->warehouse = $warehouse;

		if( ! isset( $this->warehouse['permissions'] ) )
		{
			$metas = get_warehouse_meta( $this->warehouse['id'] );
			$this->warehouse = $this->combine_meta_data( $this->warehouse, $metas );
			
		}

		if( ! $this->warehouse['indication'] && $this->warehouse['view_outlet'] )
		{
			$this->view_outlet = true;
		}

		$this->Logic->setWarehouse( $this->warehouse );
	}

	/**
	 *	Handler
	 *	---------------------------------------------------------------------------------------------------
	 */

	public function validate( $action , $datas = array(), $obj = array() )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $action || $action < 0 )
		{
			$succ = false;
			$this->Notices->set_notice( 'invalid-action', 'warning' );
		}

		if( ! $datas )
		{
			$succ = false;
			$this->Notices->set_notice( 'insufficient-data', 'warning' );
		}

		if( $succ )
		{
			$action = strtolower( $action );
			switch( $action )
			{
				case 'update':
				case 'save':
					if( ! $datas['detail'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}
					else
					{
						if( !$datas['header']['customer_id'] )
						{
							$succ = false;
							$this->Notices->set_notice( 'insufficient-data', 'warning' );						
						}

						$hav_bqty = false; $hav_price = true; $prdt_ids = [];
						foreach( $datas['detail'] as $row )
						{
							if( $row['bqty'] > 0 ) $hav_bqty = true;
							if( ! $row['sprice'] ) $hav_price = false;

							$prdt_ids[] = $row['product_id'];
						}

						if( ! $hav_bqty ) 
						{
							$succ = false;
							$this->Notices->set_notice( 'Please confirm row item qty.', 'warning' );
						}
						if( ! $hav_price ) 
						{
							$succ = false;
							$this->Notices->set_notice( 'There are item without price!', 'warning' );
						}
					}
				break;
				case 'post':
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}

					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
				break;
				case "complete":
				case 'delete':
				case 'unpost':
				case 'approve':
				case 'reject':
				case 'print':				
				case "incomplete":
					if( ! isset( $datas['id'] ) || ! $datas['id'] )
					{
						$succ = false;
						$this->Notices->set_notice( 'no-selection', 'warning' );
					}					
				break;
			}
		}

		return $succ;
	}

	public function action_handler( $action, $datas = array(), $obj = array() )
	{	
		$this->Notices->reset_operation_notice();
		$succ = true;
		$count_succ = 0;

		$outcome = array();

		$datas = $this->trim_fields( $datas );

		try
        {
        	$result = array();
        	$user_id = get_current_user_id();
			$now = current_time( 'mysql' );
			//$succ = false;
			$action = strtolower( $action );
        	switch ( $action )
        	{				
				case "save":
				case "update":
					$datas = $this->data_sanitizing( $datas );
					$header = $datas['header'];
					$detail = $datas['detail'];

					$header['warehouse_id'] = !empty( $header['warehouse_id'] )? $header['warehouse_id'] : $this->warehouse['code'];
					$header['doc_date'] = ( $header['doc_date'] )? date_formating( $header['doc_date'] ) : $now;

					$tki = apply_filters( 'wcwh_get_customer', [ 'id'=>$header['customer_id'] ], [], true, ['account'=>1] );
					if( $tki )
					{
						$header['customer_code'] = $tki['code'];
						$header['customer_uid'] = $tki['uid'];
						$header['acc_code'] = $tki['acc_code'];
						$header['wh_code'] = $tki['wh_code'];
					}

					if( $detail )
					{	
						$tamt = 0;
						foreach( $detail as $i => $row )
						{
							if( ! $row['bqty'] )
							{
								unset( $detail[$i] );
								continue;
							}

							if( $row['sprice'] )
							{
								$detail[$i]['sale_amt'] = round_to( $row['bqty'] * $row['sprice'], 2 );
								$tamt+= $detail[$i]['sale_amt'];
							}
						}
						$header['total_amount'] = $tamt;
					}

					if( $succ )
					{
						$result = $this->Logic->child_action_handle( $action, $header, $detail );
						
						if( ! $result['succ'] )
						{
							$succ = false;
							$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
						}

						if( $succ )
						{
							$outcome['id'][] = $result['id'];
							//$outcome['data'][] = $result['data'];
							$count_succ++;

							$doc_id = $result['id'];

							if( $action == 'save' )
							{
								//Doc Stage
						        $stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
						            'ref_type'		=> $this->section_id,
						            'ref_id'		=> $result['id'],
						            'action'        => $action,
						            'status'    	=> 1,
						        ] );
						    }
						}
					}
				break;
				case "delete":
				case "post":
				case "unpost":
				case "complete":
				case "incomplete":
				case "close":
					$ids = $datas['id'];
					$ids = is_array( $datas["id"] )? $datas["id"] : array( $datas["id"] );
					
					if( $ids )
					{
						foreach( $ids as $id )
						{
							$header = [];
							$header['doc_id'] = $id;
							$result = $this->Logic->child_action_handle( $action, $header );
							if( ! $result['succ'] )
							{
								$succ = false;
								$this->Notices->set_notices( $this->Logic->Notices->get_operation_notice() );
								break;
							}

							if( $succ )
							{
								$outcome['id'][] = $result['id'];
								$count_succ++;

								//Doc Stage
								$dat = $result['data'];
								$stage_id = apply_filters( 'wcwh_doc_stage', 'save', [
								    'ref_type'	=> $this->section_id,
								    'ref_id'	=> $result['id'],
								    'action'	=> $action,
								    'status'    => $dat['status'],
								    'remark'	=> ( $datas['remark'] )? $datas['remark'] : '',
								] );
							}
						}
					}
					else {
						$succ = false;
						$this->Notices->set_notice( 'insufficient-data', 'error' );
					}
					break;
				case "approve":
				case "reject":
				break;
			}

			if( $succ && $this->Notices->count_notice( "error" ) > 0 )
           		$succ = false;

           	//if( is_array( $datas["id"] ) && $count_succ > 0 ) $succ = true;

           	if( $succ && method_exists( $this, 'after_action' ) )
           	{
           		$succ = $this->after_action( $succ, $outcome['id'], $action );
           	}
        }
        catch (\Exception $e) 
        {
            $succ = false;
        }

        $outcome['succ'] = $succ;
		
		return $outcome;
	}

	public function get_parts_request_pre_orders( $filters = [] )
	{
		$datas = [];
		$results = $this->Logic->get_parts_request_pre_orders( $filters );
		if( $results )
		{
			$c = 0;
			foreach( $results as $i => $row )
			{
				$stat = "Posted";
				switch( $row['status'] )
				{
					case 1: $stat = 'Ready'; break;
					case 0: $stat = 'Trashed'; break;
					case 6: $stat = 'Posted'; break;
					case 9: $stat = 'Completed'; break;
				}

				if( ! $datas[ $row['doc_id'] ]['doc_id'] )
				{
					$datas[ $row['doc_id'] ] = [
						'doc_id' => $row['doc_id'],
						'docno' => $row['docno'],
						'doc_date' => $row['doc_date'],
						'total_amount' => $row['total_amount'],
						'remark' => $row['remark'],
						'customer_id' => $row['customer_id'],
						'customer_name' => $row['customer_name'],
						'customer_code' => $row['customer_code'],
						'status' => $stat,
					];
					$c = 0;
				}
				
				$datas[ $row['doc_id'] ]['line_items'][ $c ] = [
					'item_id' => $row['item_id'],
					'product_id' => $row['product_id'],
					'name' => $row['prdt_name'],
					'sku' => $row['sku'],
					'code' => $row['prdt_code'],
					'uom' => $row['prdt_uom'],
					'quantity' => $row['qty'],
					'price' => $row['price'],
					'total' => $row['line_total'],
				];

				$c++;
				$datas[ $row['doc_id'] ]['item_count'] = $c;
			}
		}

		return $datas;
	}

	public function get_parts_request( $filters = [] )
	{
		if( $filters )
		{
			foreach( $filters as $key => $value )
			{
				if( is_numeric( $value ) ) continue;
				if( $value == "" || $value === null ) unset( $filters[$key] );
				if( is_array( $value ) ) $filters[ $key ] = array_filter( $value );
			}
		}

		$datas = [];
		if( isset( $filters['docno'] ) )
		{
			$datas = $this->Logic->get_header( [ 'docno' => $filters['docno'], 'doc_type'=>'parts_request' ], [], false, [ 'posting'=>1 ] );
		}
		else
		{
			$datas = $this->Logic->get_header( [ 'doc_type'=>'parts_request' ], [], false, [ 'posting'=>1 ] );
		}
		
		if( $datas )
		{
			foreach( $datas as $i => $data )
			{
				$data['post_date'] = !empty( (int)$data['post_date'] ) ? $data['post_date'] : "";

				$metas = $this->Logic->get_document_meta( $data['doc_id'] );
				$data = $this->combine_meta_data( $data, $metas );
				if( $data['customer_id'] )
				{
					$filter = [ 'uid'=>$data['customer_uid'] ];
					$customer = apply_filters( 'wcwh_get_customer', $filter, [], true, [ 'get_user'=>1 ] );
					if($customer)
					{
						$data['user_id'] = $customer['user_id'];
						$data['customer_id'] = $customer['id'];
						$data['customer_name'] = $customer['name'];
						$data['customer_code'] = $customer['code'];
						$data['customer_uid'] = $customer['uid'];
						$data['customer_serial'] = $customer['serial'];
					}
				}

				if( $data['status'] > 0 )
					$data['details'] = $this->Logic->get_detail( [ 'doc_id'=>$data['doc_id'] ], [], false, [ 'uom'=>1, 'usage'=>1, 'meta'=>['fulfill_qty', 'sprice', 'sale_amt'] ] );
					
				if( $data['details'] )
			    {	
			        foreach( $data['details'] as $j => $item )
			        {	
			    		$data['details'][$j]['lqty'] = round_to( $item['bqty'] - ( $item['fulfill_qty'] + $item['uqty'] ), 2 );
			    	}
			    }

			    $url = get_rest_url( null, 'wc/v3/customers/'.$data['user_id'] );
				$response = wp_remote_get( $url );
				if ( is_array( $response ) && ! is_wp_error( $response ) ) {
					$headers = $response['headers']; // array of http header lines
					$body    = $response['body']; // use the content
					if( $body ) $data['customer_data'] = json_decode( $body, true );
				}

			    $datas[$i] = $data;
			}
		}

		return $datas;
	}

	public function parts_request_completion( $succ, $datas = [] )
	{
		if( ! $datas || ! $datas['doc_id'] ) return $succ;

		$doc_id = $datas['doc_id'];

		$details = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'uom'=>1, 'usage'=>1, 'meta'=>['fulfill_qty'] ] );
		if( $details )
		{
			$trows = [];
			foreach( $details as $i => $row )
			{
				$trows[ $row['item_id'] ] = $row;
			}

			if( $datas['details'] )
			{
				foreach( $datas['details'] as $i => $item )
				{
					$fulfil_meta = $this->Logic->get_doc_meta( $doc_id, 'fulfill_qty', $item['item_id'] );
					$fulfil = ( $fulfil_meta['meta_value'] != 0 )? $fulfil_meta['meta_value'] : 0;
					if( $item['plus_sign'] === '+' ) $fulfil+= $item['qty'];
					else if( $item['plus_sign'] === '-' ) $fulfil-= $item['qty'];

					if( $fulfil > $trows[ $item['item_id'] ]['bqty'] ) $fulfil = $trows[ $item['item_id'] ]['bqty'];
					else if( $fulfil < 0 ) $fulfil = 0;

					if( $fulfil_meta )
						$this->Logic->update_document_meta( $fulfil_meta['meta_id'], [ 'meta_value'=>$fulfil ] );
					else
						update_document_meta( $doc_id, 'fulfill_qty', $fulfil, $item['item_id'] );
				}
			}
		}

		$details = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'uom'=>1, 'usage'=>1, 'meta'=>['fulfill_qty'] ] );
		if( $details )
		{
			$tqty = 0; $tfty = 0;
			foreach( $details as $i => $item )
			{
				if( $item['status'] <= 0 ) continue;
				$tqty+= $item['bqty'];
				$tfty+= $item['fulfill_qty']+$item['uqty'];
			}

			if( $tfty >= $tqty )
			{
				$action = 'complete';
			}
			else
			{
				$action = 'incomplete';
			}

			$header['doc_id'] = $doc_id;
			$result = $this->Logic->child_action_handle( $action, $header );
			if( ! $result['succ'] )
			{
				$succ = false;
			}
		}

		return $succ;
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function view_reference()
	{
		if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet )
		{
			$not_acc_type = $this->setting['wh_customer']['non_editable_by_acc_type'];
			
			$filters = [ 'status'=>'1'];
			//if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			if( $not_acc_type ) $filters['not_acc_type'] = $not_acc_type;
			$employee = apply_filters( 'wcwh_get_customer', $filters, [], false, ['account'=>1] );
			
	        echo '<div id="parts_request_reference_content" class="col-md-8">';
	        echo '<select id="parts_request_reference" class="select2 triggerChange barcodeTrigger" data-change="#parts_request_action" data-placeholder="Employer ID/ Serial/ Acc Type/ Name">';
	        echo '<option></option>';
	        foreach( $employee as $i => $emp )
	        {
	        	echo '<option 
                            value="'.$emp['code'].'" 
                            data-uid="'.$emp['uid'].'" 
                            data-code="'.$emp['code'].'" 
                            data-serial="'.$emp['serial'].'"
                            data-name="'.$emp['name'].'"
                >'. $emp['uid'].', '.$emp['serial'].', '.$emp['acc_name'].', '.$emp['name'] .'</option>';
	        }
	        echo '</select>';
	        echo '</div>';
		}
	}

	public function view_fragment( $type = 'save' )
	{
		global $wcwh;
		$refs = $wcwh->get_plugin_ref();
		$actions = $refs['actions'];
		
		switch( strtolower( $type ) )
		{
			case 'print':
				if( current_user_cans( [ 'save_'.$this->section_id ] ) ):
				?>
					<button class="btn btn-sm btn-primary toggle-modal" data-action="print" data-tpl="<?php echo $this->tplName['multiTR'] ?>" 
						data-title="<?php echo $actions['print'] ?> Form" data-modal="wcwhModalImEx" 
						data-actions="close|printing" 
						title="<?php echo $actions['print'] ?> Form"
					>
						<i class="fa fa-print" aria-hidden="true"></i>
					</button>
				<?php
				endif;
			break;
			case 'save':
			default:
				if( current_user_cans( [ 'save_'.$this->section_id ] ) && ! $this->view_outlet ):
			?>
				<button id="parts_request_action" class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Spare Parts Request"
					data-title="<?php echo $actions['save'] ?> Spare Parts Request" 
					data-action="parts_request_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#parts_request_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Spare Parts Request
					<i class="fa fa-plus-circle" aria-hidden="true"></i>
				</button>
			<?php
				endif;
			break;
		}
	}

	public function gen_form( $id = 0 )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'wh_id'		=> $this->warehouse['id'],
			'wh_code'	=> $this->warehouse['code'],
		);
		
		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}

		if( $id )
		{
			$filters = [ 'code'=>$id ];
			//if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
			$data = apply_filters( 'wcwh_get_customer', $filters, [], true, ['account'=>1] );
			if($data)
			{
				$args['data']['customer_id'] = $data['id'];
				$args['data']['customer_name'] = $data['name'];
				$args['data']['customer_code'] = $data['code'];
				$args['data']['customer_uid'] = $data['uid'];
				$args['data']['customer_serial'] = $data['serial'];
				$args['data']['acc_code'] = $data['acc_code'];
				$args['data']['wh_code'] = $data['wh_code'];
			}
		}

		do_action( 'wcwh_get_template', 'form/parts-request-form.php', $args );
	}

	public function view_form( $id = 0, $templating = true, $isView = false, $getContent = false )
	{
		$args = array(
			'setting'	=> $this->setting,
			'section'	=> $this->section_id,
			'hook'		=> $this->section_id.'_form',
			'action'	=> 'save',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'new'		=> 'new',
			'tplName'	=> $this->tplName['new'],
			'rowTpl'	=> $this->tplName['row'],
			'wh_id'		=> $this->warehouse['id'],
			'wh_code'	=> $this->warehouse['code'],
			'get_content' => $getContent,
		);
		if( $this->warehouse['id'] && $this->view_outlet ) $args['seller'] = $this->warehouse['id'];

		if( $this->warehouse )
		{
			$args['data']['warehouse_id'] = $this->warehouse['code'];
		}
		
		if( $id )
		{
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'doc_type'=>'parts_request' ], [], true, [] );
			if($datas)
			{
				$datas['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";

				$metas = $this->Logic->get_document_meta( $id );
				$datas = $this->combine_meta_data( $datas, $metas );
				if( $datas['customer_id'] )
				{
					$filters = [ 'code'=>$datas['customer_code'] ];
					//if( $this->warehouse['id'] && $this->view_outlet ) $filters['seller'] = $this->warehouse['id'];
					$data = apply_filters( 'wcwh_get_customer', $filters, [], true, ['account'=>1] );
					if($data)
					{
						$datas['customer_id'] = $data['id'];
						$datas['customer_name'] = $data['name'];
						$datas['customer_code'] = $data['code'];
						$datas['customer_uid'] = $data['uid'];
						$datas['customer_serial'] = $data['serial'];
						$datas['acc_code'] = $data['acc_code'];
						$datas['wh_code'] = $data['wh_code'];
					}
				}

				$Inst = new WCWH_Listing();
				if( $datas['status'] > 0 )
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'usage'=>1, 'group'=>1, 'stocks'=>$this->warehouse['code'] ] );
				else
					$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$id ], [], false, [ 'uom'=>1, 'group'=>1, 'stocks'=>$this->warehouse['code'] ] );

				if( $datas['details'] )
		        {	
		        	$p_items = [];
		        	foreach( $datas['details'] as $i => $item ) $p_items[] = $item['product_id'];
		        	$filter = [ 'id'=>$p_items ];
	                if( $args['seller'] ) $filter['seller'] = $args['seller'];
	                $prices = apply_filters( 'wcwh_get_latest_price', $filter, [], false, [ 'usage'=>1 ] );
	                if( $prices )
	                {
	                	$g_prices = [];
	                	foreach( $prices as $price )
	                	{
	                		$g_prices[ $price['id'] ] = $price;
	                	}
	                }
	                
	                $tbqty = 0; $tamt = 0;
		        	foreach( $datas['details'] as $i => $item )
		        	{
		        		$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
		        		$datas['details'][$i]['row_id'] = $item['item_id'];

		        		$datas['details'][$i]['item_name'] = $item['prdt_name'];
		        		$datas['details'][$i]['prdt_name'] = $item['prdt_code'].' - '.$item['prdt_name'];

		        		$datas['details'][$i]['stocks'] = $item['stock_qty'] - $item['stock_allocated'];
		        		$datas['details'][$i]['unit_price'] = $g_prices[ $item['product_id'] ]['unit_price'];

		        		$datas['details'][$i]['line_item'] = [ 
		        			'name'=>$item['prdt_name'], 'code'=>$item['prdt_code'], 'uom_code'=>$item['uom_code'], 
							'uom_fraction'=>$item['uom_fraction'], 'grp_code'=>$item['grp_code'],
		        		];

						$detail_metas = $this->Logic->get_document_meta( $id, '', $item['item_id'] );
		        		$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

		        		$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
		        		$datas['details'][$i]['fulfill_qty'] = round_to( $datas['details'][$i]['fulfill_qty'] + $datas['details'][$i]['uqty'], 2 );

						$datas['details'][$i]['lqty'] = round_to( $datas['details'][$i]['bqty'] - $datas['details'][$i]['fulfill_qty'], 2 );
		        		
		        		$datas['details'][$i]['sprice'] = round_to( $datas['details'][$i]['sprice'],2 );
		        		$datas['details'][$i]['sale_amt'] = round_to( $datas['details'][$i]['sale_amt'],2 );
		        		if( $isView ) $datas['details'][$i]['sale_amt'] = round_to( $datas['details'][$i]['sale_amt'],2,1,1 );

		        		$tbqty+= $datas['details'][$i]['bqty'];
		        		$tamt+= $datas['details'][$i]['sale_amt'];
		        	}

		        	if( $isView )
		        	{
		        		$datas['details'][] = [
		        			'prdt_name' => 'Total:',
		        			'bqty' => $tbqty,
		        			'sale_amt' => round_to( $tamt,2,1,1 ),
		        		];
		        	}
		        }

				$args['action'] = 'update';
				
				if( $isView ) $args['view'] = true;

				$args['data'] = $datas;
				unset( $args['new'] );	

				$cols = [
		        	'num' => '',
		        	'prdt_name' => 'Item',
		        	'uom_code' => 'UOM',
		        	'sprice' => 'Price',
		        	'bqty' => 'Request Qty',
		        	'sale_amt' => 'Amt (RM)',
		        	'fulfill_qty' => 'Fulfill Qty',
		        	'lqty' => 'Bal Qty',
		        	//'period' => 'Instalment (Mth)',
		        ];

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );					
			}

			if( $isView ) $fulfilments = $this->Logic->get_parts_request_fulfilment( $id, $this->warehouse['id'] );
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/parts-request-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/parts-request-form.php', $args );
		}

		if( $isView && $fulfilments )
		{
			if ( !class_exists( "WCWH_POSSales_Rpt" ) ) include_once( WCWH_DIR . "/includes/reports/posSales.php" );

			$Finst = new WCWH_POSSales_Rpt();

			foreach( $fulfilments as $i => $row )
			{
				$Finst->pos_sales_report_detail( $row['order_id'], [ 'seller'=>$this->warehouse['id'] ] );
			}
		}
	}

	public function view_row()
	{
		do_action( 'wcwh_templating', 'segment/partsRequest-row.php', $this->tplName['row'] );
	}

	public function view_receipt( $doc_id )
	{
		if( ! $doc_id ) return;

		ob_start();

		$datas = $this->Logic->get_header( [ 'doc_id' => $doc_id, 'doc_type'=>'parts_request' ], [], true, [] );
		if($datas)
		{
			$metas = $this->Logic->get_document_meta( $doc_id );
			$datas = $this->combine_meta_data( $datas, $metas );

			$filters = [ 'code'=>$datas['customer_code'] ];
			$data = apply_filters( 'wcwh_get_customer', $filters, [], true, ['account'=>1] );
			if($data)
			{
				$datas['customer_id'] = $data['id'];
				$datas['customer_name'] = $data['name'];
				$datas['customer_code'] = $data['code'];
				$datas['customer_uid'] = $data['uid'];
				$datas['customer_serial'] = $data['serial'];
				$datas['acc_code'] = $data['acc_code'];
				$datas['wh_code'] = $data['wh_code'];
			}

			$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$doc_id ], [], false, [ 'uom'=>1, 'meta'=>[ 'sprice', 'sale_amt' ], 'usage'=>1 ] );
		}

        $args['doc'] = $datas;
        $args['register_ID'] = $datas['register_id'];

        $register = WC_POS()->register()->get_data( $register_ID );
        $args['register'] = $register = $register[0];
        $args['register_name'] = $register['name'];

        $args['receipt_ID'] = $register['detail']['receipt_template'];

        $preview = false;

        $receipt_options = WC_POS()->receipt()->get_data($receipt_ID);
        $args['receipt_style'] = WC_POS()->receipt()->get_style_templates();
        $args['receipt_options'] = $receipt_options = $receipt_options[0];
        $args['attachment_image_logo'] = wp_get_attachment_image_src($receipt_options['logo'], 'full');

        //remove_action('wp_footer', 'wp_admin_bar_render', 1000);

        do_action( 'wcwh_get_template', 'template/html-parts-receipt.php', $args );

    	return ob_get_clean();
	}

	public function tr_form()
	{
		
	}

	public function multiTR_form()
	{
		
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
		include_once( WCWH_DIR . "/includes/listing/partsRequestListing.php" ); 
		$Inst = new WCWH_PartsRequest_Listing();
		$Inst->set_warehouse( $this->warehouse );
		$Inst->set_section_id( $this->section_id );
		$Inst->useFlag = $this->useFlag;
		
		$count = $this->Logic->count_statuses();
		if( $count ) $Inst->viewStats = $count;

		$Inst->filters = $filters;
		$Inst->advSearch_onoff();

		$Inst->bulks = array( 
			'data-section' => $this->section_id,
			'data-tpl' => 'remark',
			'data-service' => $this->section_id.'_action',
			'data-form' => 'edit-'.$this->section_id,
		);

		$meta = [ 'customer_id','customer_uid', 'remark' ];
		
		$order = $Inst->get_data_ordering();
		$limit = $Inst->get_data_limit();
		
		$datas = $this->Logic->get_header( $filters, $order, false, [ 'parent'=>1, 'meta'=>$meta ], [], $limit );
		$datas = ( $datas )? $datas : array();

		$Inst->set_details( $datas );
		$Inst->render();

		?>		
		</form>
		<?php
	}

} //class

}