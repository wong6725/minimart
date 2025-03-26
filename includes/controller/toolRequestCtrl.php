<?php
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_ToolRequest_Class" ) ) include_once( WCWH_DIR . "/includes/classes/tool-request.php" );

if ( !class_exists( "WCWH_ToolRequest_Controller" ) ) 
{

class WCWH_ToolRequest_Controller extends WCWH_CRUD_Controller
{
	protected $section_id = "wh_tool_request";

	public $Notices;
	public $className = "ToolRequest_Controller";

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
		$this->Logic = new WCWH_ToolRequest_Class( $this->db_wpdb );
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
						else
						{
							$tki = apply_filters( 'wcwh_get_customer', [ 'id'=>$datas['header']['customer_id'] ], [], true, [] );
							if( ! $tki || ! $tki['status'] )
							{
								$succ = false;
								$this->Notices->set_notice( 'Selected receiver are currently mark as Trashed!', 'warning' );
							}
						}

						$hav_bqty = false; $hav_price = true; $prdt_ids = [];
						foreach( $datas['detail'] as $row )
						{
							if( $row['bqty'] > 0 ) $hav_bqty = true;
							if( ! $row['sprice'] ) $hav_price = false;

							$prdt_ids[] = $row['product_id'];
						}

						if( $prdt_ids && !empty( $this->setting[ $this->section_id ]['acc_type_to_limit'] ) 
							&& !empty( $this->setting[ $this->section_id ]['group_to_limit'] )
							&& in_array( $datas['header']['acc_code'], $this->setting[ $this->section_id ]['acc_type_to_limit'] ) 
						){
							$items = apply_filters( 'wcwh_get_item', [ 'id'=>$prdt_ids ], [], false, [] );
							if( $items )
							{
								foreach( $items as $i => $item )
								{
									if( ! in_array( $item['grp_id'], $this->setting[ $this->section_id ]['group_to_limit'] ) )
									{
										$igroup = apply_filters( 'wcwh_get_item_group', [ 'id'=>$this->setting[ $this->section_id ]['group_to_limit'] ], [], false, [] );
										$g = [];
										if( $igroup )
											foreach( $igroup as $grp )
												$g[] = $grp['name'];

										$succ = false;
										$this->Notices->set_notice( 'Selected receiver only allow to add Item Group as ('.implode(', ', $g ).')', 'warning' );
										break;
									}
								}
							}
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
					
					if( $ids )
					{
						$has_attach = true;
						foreach( $ids as $id )
						{
							$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id ], [], false, [ 'usage'=>1 ] );
							if( ! $attachs )
							{
								$has_attach = false;
								break;
							}
						}

						if( ! $has_attach )
						{
							$succ = false;
							$this->Notices->set_notice( 'Required attachment for posting!', 'warning' );
						}
					}

					
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
					$attachment = $datas['attachment'];
					$files = $_FILES;

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
							if( !empty( $attachment ) )
							{
								$succ = $this->Files->attachment_handler( $attachment, $this->section_id, $doc_id );
							}
							if( !empty( $files ) )
							{
								$fr = $this->Files->upload_files( $files, $this->section_id, $doc_id );
								if( $fr )
								{
									update_document_meta( $doc_id, 'attachments', maybe_serialize( $fr ) );
								}
								else{
									$succ = false;
									$this->Notices->set_notice( 'File Upload Failed', 'error' );
								}
							}

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
				case "print":
					if( sizeof( $datas['id'] ) > 0 )
					{
						$datas['docID'] = $datas['id'];
						if( ! $datas['type'] ) $datas['type'] = 'tool_request';
					}

					if($datas['docID'] || $datas['id'] == 0) //When print multiple form (print icon at Top right corner ).
					{
						//status = 0 will be unset in document.php (Line 1426)
						//so use process and store in processing_stat array.
					
						//Check if 'all' not been seleted
						if($datas['status'] && !in_array('all', $datas['status']))
						{
							foreach ($datas['status'] as $key => $value)
							{
								$array[] = $value;
							}
							$this->Logic->processing_stat = $array;
							$datas['status'] = 'process';
						}
						else // direct print all doc
						{
							$datas['status'] = [6,9];
						}

						switch( strtolower( $datas['type'] ) )
						{
							case 'tool_request':
							default:
								$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
								if($datas['InExclude'] == 1) //exclude
								{
									$doc = $this->Logic->get_header( [ 'not_doc_id'=> $datas['docID'],'status'=>$datas['status']], [], false, [] );
								}
								else
								{
									
									$doc = $this->Logic->get_header( [ 'doc_id'=> $datas['docID'],'status'=>$datas['status']], [], false, [] );
								}
								
								if( !empty($doc) )
								{
									foreach ($doc as $key => $value)
									{
										$doc[$key]['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
										//metas
										$metas = $this->Logic->get_document_meta( $value['doc_id'] );
										$doc[$key] = $this->combine_meta_data( $doc[$key], $metas );

										$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc[$key]['warehouse_id'] ], [], true, [ ] );
										$filter = [ 'uid'=>$doc[$key]['customer_uid'] ];
										//if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
										$doc[$key]['customer'] = apply_filters( 'wcwh_get_customer', $filter, [], true, [ 'job'=>1 ] );
										if( $value['status'] > 0 )
											$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$value['doc_id'] ], ['grp_name'=>'DESC' ,'idx' => 'ASC', 'a.item_id' => 'ASC' ], false, [ 'uom'=>1, 'usage'=>1, 'group'=>1 ] );
										else
											$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$value['doc_id'] ], ['grp_name'=>'DESC' ,'idx' => 'ASC', 'a.item_id' => 'ASC' ], false, [ 'uom'=>1,'group'=>1] );

										if( $datas['details'] )
										{	
											$total_qty = 0; $total_amt = 0;
											$datas['items']=array();
											foreach( $datas['details'] as $i => $item )
											{
												$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
												$datas['details'][$i]['row_id'] = $item['item_id'];

												$datas['details'][$i]['item_name'] = $item['prdt_name'];
												
												$detail_metas = $this->Logic->get_document_meta($value['doc_id'], '', $item['item_id'] );
												$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );

												$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
												$total_qty += round_to( $item['bqty'], 2 );
												$total_amt += round_to( $datas['details'][$i]['sale_amt'], 2 );
											}
											$doc[$key]['total_qty'] = $total_qty;
											$doc[$key]['total_amt'] = $total_amt;
										}
										$doc[$key]['details'] =$datas['details'];
										$date_format = "Y.m.d H:i:s";//get_option( 'date_format' );
										$user_info = get_userdata( get_current_user_id() );
										$doc[$key]['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d H:i:s' ) ) );
										$doc[$key]['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
										
										$params['detail'][] = $doc[$key];
										$params['warehouse'] = $warehouse;
									}

									switch( strtolower( $datas['paper_size'] ) )
									{
										case 'default':
										default:
											ob_start();
											do_action( 'wcwh_get_template', 'template/doc-tool-request.php', $params );
											$content.= ob_get_clean();
											
											if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
											{
												echo $content;
											}
											else
											{
												$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
												$args = [ 'filename' => 'Tools Requisition Forms' ];
												do_action( 'dompdf_generator', $content, $paper, array(), $args );
											}
										break;
									}
								}
								else
								{
									$succ = false;
									echo "Document(s) not found.";
								}						
							break;
						}
						exit;
					}
					else
					{
						if( empty( $datas['type'] ) ) $this->print_form( $datas['id'] );

						$id = $datas['id'];
						switch( strtolower( $datas['type'] ) )
						{
							case 'tool_request':
								$params = [ 'setting' => $this->setting, 'section' => $this->section_id, ];
								$doc = $this->Logic->get_header( [ 'doc_id' => $id], [], true, [] );
						
								if( $doc )
								{
									$doc['post_date'] = !empty( (int)$datas['post_date'] ) ? $datas['post_date'] : "";
									//metas
									$metas = $this->Logic->get_document_meta( $id );
									$doc = $this->combine_meta_data( $doc, $metas );

									$warehouse = apply_filters( 'wcwh_get_warehouse', [ 'code'=>$doc['warehouse_id'] ], [], true, [ ] );
										$filter = [ 'uid'=>$doc['customer_uid'] ];
										//if( $this->warehouse['id'] && $this->view_outlet ) $filter['seller'] = $this->warehouse['id'];
										$doc['customer'] = apply_filters( 'wcwh_get_customer', $filter, [], true, [ 'job'=>1 ] );
										if( $doc['status'] > 0 )
											$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$doc['doc_id'] ], ['grp_name'=>'DESC' ,'idx' => 'ASC', 'a.item_id' => 'ASC' ], false, [ 'uom'=>1, 'usage'=>1, 'group'=>1] );
										else
											$datas['details'] = $this->Logic->get_detail( [ 'doc_id'=>$doc['doc_id'] ], ['grp_name'=>'DESC' ,'idx' => 'ASC', 'a.item_id' => 'ASC'], false, [ 'uom'=>1,'group'=>1] );

										if( $datas['details'] )
										{	$total_qty = 0; $total_amt = 0;
											foreach( $datas['details'] as $i => $item )
											{
												$datas['details'][$i]['num'] = current_user_cans( ['wh_admin_support'] )? "<span title='{$item['doc_id']} - {$item['item_id']}'>".($i+1).".</span>" : ($i+1).".";
												$datas['details'][$i]['row_id'] = $item['item_id'];
												$datas['details'][$i]['item_name'] = $item['prdt_name'];
												$detail_metas = $this->Logic->get_document_meta($doc['doc_id'], '', $item['item_id'] );
												$datas['details'][$i] = $this->combine_meta_data( $datas['details'][$i], $detail_metas );
												$datas['details'][$i]['bqty'] = round_to( $item['bqty'], 2 );
												$total_qty += round_to( $item['bqty'], 2 );
												$total_amt += round_to( $datas['details'][$i]['sale_amt'], 2 );
											}
											$doc['total_qty'] = $total_qty;
											$doc['total_amt'] = $total_amt;
										}
										
										$doc['details'] =$datas['details'];
										$date_format = "Y.m.d H:i:s";//get_option( 'date_format' );
										$user_info = get_userdata( get_current_user_id() );
										$doc['print_on'] = date_i18n( $date_format, strtotime( current_time( 'Y-m-d H:i:s' ) ) );
										$doc['print_by'] = ( $user_info->first_name )? $user_info->first_name : $user_info->display_name;
										
										$params['detail'][] = $doc;
										$params['warehouse'] = $warehouse;
								}

								switch( strtolower( $datas['paper_size'] ) )
								{
									case 'default':
									default:
										ob_start();
										do_action( 'wcwh_get_template', 'template/doc-tool-request.php', $params );
			
										$content.= ob_get_clean();
										
										if( ! is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) || $datas['html'] > 0 )
										{
											echo $content;
										}
										else
										{
											$paper = [ 'size' => 'A4', 'orientation' => 'portrait' ];
											$args = [ 'filename' => 'Tools Requisition Form' ];
											do_action( 'dompdf_generator', $content, $paper, array(), $args );
										}
										break;
								}
							break;
						}
						exit;
					}
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

	public function get_tool_request( $filters = [] )
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
			$datas = $this->Logic->get_header( [ 'docno' => $filters['docno'], 'doc_type'=>'tool_request' ], [], false, [ 'posting'=>1 ] );
		}
		else
		{
			$datas = $this->Logic->get_header( [ 'doc_type'=>'tool_request' ], [], false, [ 'posting'=>1 ] );
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

	public function tool_request_completion( $succ, $datas = [] )
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
			//if( $not_acc_type ) $filters['not_acc_type'] = $not_acc_type;
			$employee = apply_filters( 'wcwh_get_customer', $filters, [], false, ['account'=>1] );
			
	        echo '<div id="tool_request_reference_content" class="col-md-8">';
	        echo '<select id="tool_request_reference" class="select2 triggerChange barcodeTrigger" data-change="#tool_request_action" data-placeholder="Employer ID/ Serial/ Acc Type/ Name">';
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
				<button id="tool_request_action" class="display-none btn btn-sm btn-primary linkAction" title="Add <?php echo $actions['save'] ?> Tools Requisition"
					data-title="<?php echo $actions['save'] ?> Tools Requisition" 
					data-action="tool_request_reference" data-service="<?php echo $this->section_id; ?>_action" 
					data-modal="wcwhModalForm" data-actions="close|submit" 
					data-source="#tool_request_reference" data-strict="yes"
				>
					<?php echo $actions['save'] ?> Tools Requisition
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

		do_action( 'wcwh_get_template', 'form/tool-request-form.php', $args );
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
			$datas = $this->Logic->get_header( [ 'doc_id' => $id, 'doc_type'=>'tool_request' ], [], true, [] );
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

				$attachs = $this->Files->get_infos( [ 'section_id'=>$this->section_id, 'ref_id'=>$id, 'seller'=>$args['seller'] ], [], false, [ 'usage'=>1 ] );
				if( $attachs )
				{
					if( $args['seller'] )
					{
						foreach( $attachs as $x => $attach )
						{
							if( $this->warehouse['api_url'] ) $attachs[$x]['api_url'] = $this->warehouse['api_url'];
						}
					}
					$datas['attachment'] = $attachs;
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
		        	'period' => 'Instalment (Mth)',
		        ];

		        $args['render'] = $Inst->get_listing( $cols, 
		        	$datas['details'], 
		        	[], 
		        	$hides, 
		        	[ 'off_footer'=>true, 'list_only'=>true ]
		        );					
			}

			if( $isView ) $fulfilments = $this->Logic->get_tool_request_fulfilment( $id, $this->warehouse['id'] );
		}

		if( $templating )
		{
			do_action( 'wcwh_templating', 'form/tool-request-form.php', $this->tplName['new'], $args );
		}
		else
		{
			do_action( 'wcwh_get_template', 'form/tool-request-form.php', $args );
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
		do_action( 'wcwh_templating', 'segment/toolRequest-row.php', $this->tplName['row'] );
	}

	public function tr_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['tr'],
			'section'	=> $this->section_id,
			'isPrint'	=> 1,
		);
	
		do_action( 'wcwh_templating', 'form/toolRequest-print-form.php', $this->tplName['tr'], $args );
	}

	public function multiTR_form()
	{
		$args = array(
			'hook'		=> $this->section_id.'_submission',
			'action'	=> 'print',
			'token'		=> apply_filters( 'wcwh_generate_token', $this->section_id ),
			'tplName'	=> $this->tplName['multiTR'],
			'section'	=> $this->section_id,
			'seller'	=> $this->warehouse['id'],
			'isPrint'	=> 1,
		);
	
		do_action( 'wcwh_templating', 'form/toolRequest-print-multi-form.php', $this->tplName['multiTR'], $args );
	}

	public function view_listing( $filters = array(), $order = array() )
	{
		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		?>
		<form class="listing-form <?php echo $this->section_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $this->section_id; ?>" data-section="<?php echo $this->section_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $this->section_id; ?>_listing" 
		>
		<?php
		include_once( WCWH_DIR . "/includes/listing/toolRequestListing.php" ); 
		$Inst = new WCWH_ToolRequest_Listing();
		$Inst->set_warehouse( $this->warehouse );
		$Inst->set_section_id( $this->section_id );
		$Inst->useFlag = $this->useFlag;
		
		$count = $this->Logic->count_statuses();
		if( $count ) $Inst->viewStats = $count;

		$Inst->filters = $filters;
		$Inst->advSearch_onoff();

		$order = $Inst->get_data_ordering();
		$limit = $Inst->get_data_limit();

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