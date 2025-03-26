<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Weighted_Migrate extends WCWH_CRUD_Controller
{
	private $temp_data = [];
	private $className = 'WC_Weighted_Migrate';
	
	protected $tables = [];

	public $Notices;

	public function __construct() 
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
		
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"items" 			=> $prefix."items",
			"itemsmeta"			=> $prefix."itemsmeta",
			"items_tree"		=> $prefix."items_tree",
			"items_converse"	=> $prefix."converse",
			
			"transaction" 		=> $prefix."transaction",
			"transaction_items" => $prefix."transaction_items",
			"transaction_out"	=> $prefix."transaction_out_ref",
			"transaction_meta"	=> $prefix."transaction_meta",
			"transaction_conversion"	=> $prefix."transaction_conversion",
			"transaction_weighted"		=> $prefix."transaction_weighted",

			"document"			=> $prefix."document",
			"document_items"	=> $prefix."document_items",
			"document_meta"		=> $prefix."document_meta",

			"inventory"			=> $prefix."inventory",

			"storage"			=> $prefix."storage",
		);

		$this->user_id = get_current_user_id();
	}

	public $processing_list = [];
	public $skiped_list = [];

	public function processing( $stat, $msg, $data )
	{
		$this->processing_list[] = [
			'doc_id' => $data['doc_id'],
			'stat' => $stat,
			'msg' => $msg,
			'docno' => $data['docno'],
			'post_date' => $data['post_date'],
			'ref_id' => $data['ref_doc_id'],
			'ref_doc' => $data['ref_doc'],
			'ref_type' => $data['ref_doc_type'],
		];
	}

	public function test()
	{
		/*$doc_id = '44204';
		$doc_type = 'purchase_credit_note';

		wpdb_start_transaction ();

		$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'post_cndn', $doc_type, $doc_id );	
		if( ! $succ )
		{
			pd( apply_filters( 'wcwh_inventory_get_notices', true ) );
		}

		wpdb_end_transaction( true );*/

		$transact_item = apply_filters( 'warehouse_get_inventory_transaction_item_weighted_price', 313, '1025-MWT3', 1 );
		pd($transact_item);
			$price = $transact_item['bal_price'];
			//if( $transact_item['product_id'] != $item['product_id'] ) 
				echo $price = round_to( $transact_item['converse_uprice'], 2 );

		echo $amt = round( $price * 300, 2 );
	}

	/*
	No handler needed: delivery_order, good_issue, block_action, good_return
	delivery_order: ucost, tcost

	Handler needed: good_receive, reprocess, do_revise, transfer_item, block_stock, stock_adjust, pos_transactions, purchase_debit_note, purchase_credit_note
	
	27708, 17124, 158725, 296187, 145035	|26918
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_tx_inventory LIKE wp_stmm_wcwh_inventory; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction LIKE wp_stmm_wcwh_transaction; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction_conversion LIKE wp_stmm_wcwh_transaction_conversion; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction_items LIKE wp_stmm_wcwh_transaction_items; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction_meta LIKE wp_stmm_wcwh_transaction_meta; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction_out_ref LIKE wp_stmm_wcwh_transaction_out_ref; 
	CREATE TABLE wp_stmm_wcwh_tx_transaction_weighted LIKE wp_stmm_wcwh_transaction_weighted; 

	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_inventory LIKE wp_stmm_wcwh_inventory; 
	INSERT INTO wp_stmm_wcwh_pv_inventory SELECT * FROM wp_stmm_wcwh_inventory;
	
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_transaction LIKE wp_stmm_wcwh_transaction; 
	INSERT INTO wp_stmm_wcwh_pv_transaction SELECT * FROM wp_stmm_wcwh_transaction;
	
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_transaction_conversion LIKE wp_stmm_wcwh_transaction_conversion; 
	INSERT INTO wp_stmm_wcwh_pv_transaction_conversion SELECT * FROM wp_stmm_wcwh_transaction_conversion;
	
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_transaction_items LIKE wp_stmm_wcwh_transaction_items; 
	INSERT INTO wp_stmm_wcwh_pv_transaction_items SELECT * FROM wp_stmm_wcwh_transaction_items;
	
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_transaction_meta LIKE wp_stmm_wcwh_transaction_meta; 
	INSERT INTO wp_stmm_wcwh_pv_transaction_meta SELECT * FROM wp_stmm_wcwh_transaction_meta;
	
	SET SQL_MODE='ALLOW_INVALID_DATES';
	CREATE TABLE wp_stmm_wcwh_pv_transaction_out_ref LIKE wp_stmm_wcwh_transaction_out_ref; 
	INSERT INTO wp_stmm_wcwh_pv_transaction_out_ref SELECT * FROM wp_stmm_wcwh_transaction_out_ref;

	CREATE TABLE IF NOT EXISTS `wp_stmm_wcwh_tx_migration` (
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`doc_id` bigint(20) NOT NULL DEFAULT 0,
		`docno` varchar(20) NOT NULL DEFAULT '',
		`doc_date` varchar(50) NOT NULL DEFAULT '',
		`post_date` varchar(50) NOT NULL DEFAULT '',
		`doc_stat` int(3) NOT NULL DEFAULT 1,
		`doc_type` varchar(50) NOT NULL DEFAULT '',
		`hid` bigint(20) NOT NULL DEFAULT 0,
		`doc_post_date` varchar(50) NOT NULL DEFAULT '', 
		`plus_sign` varchar(10) NOT NULL DEFAULT '',
		`t_stat` varchar(10) NOT NULL DEFAULT '',
		`post_at` varchar(50) NOT NULL DEFAULT '', 
		PRIMARY KEY (`id`),
		KEY `doc_id` (`doc_id`,`doc_type`,`post_at`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

	INSERT INTO wp_stmm_wcwh_tx_migration ( `doc_id`, `docno`, `doc_date`, `post_date`, `doc_stat`, `doc_type`, `hid`, `doc_post_date`, `plus_sign`, `t_stat`, `post_at`)
	SELECT h.doc_id, h.docno, h.doc_date, h.post_date, h.status AS doc_stat, h.doc_type
				, t.hid, t.doc_post_date, t.plus_sign, t.status AS t_stat, IFNULL( sd.action_at, t.lupdate_at ) AS post_at
				FROM wp_stmm_wcwh_transaction t 
				LEFT JOIN wp_stmm_wcwh_document h ON h.doc_id = t.doc_id AND h.status > 0
				LEFT JOIN wp_stmm_wcwh_stage_header sh ON sh.ref_id = h.doc_id AND sh.status > 0 AND sh.ref_type = CONCAT('wh_', t.doc_type)
				LEFT JOIN wp_stmm_wcwh_stage_details sd ON sd.stage_id = sh.id AND sd.status > 1 AND sd.id = (
					SELECT s.id
					FROM wp_stmm_wcwh_stage_details s 
					WHERE 1 AND s.stage_id = sh.id AND s.action = 'post'
					ORDER BY action_at DESC LIMIT 0,1
				)
				WHERE 1 AND t.status > 0 
				UNION ALL
				SELECT h.doc_id, h.docno, h.doc_date, h.post_date, h.status AS doc_stat, h.doc_type
				, 0 AS hid, '' AS doc_post_date, '' AS plus_sign, '' AS t_stat, h.post_date AS post_at
				FROM wp_stmm_wcwh_document h
				WHERE 1 AND h.doc_type IN('purchase_credit_note','purchase_debit_note') AND h.status >= 6

	//
	ALTER TABLE wp_stmm_wcwh_tx_inventory RENAME wp_stmm_wcwh_inventory;
	ALTER TABLE wp_stmm_wcwh_tx_transaction RENAME wp_stmm_wcwh_transaction;
	ALTER TABLE wp_stmm_wcwh_tx_transaction_conversion RENAME wp_stmm_wcwh_transaction_conversion;
	ALTER TABLE wp_stmm_wcwh_tx_transaction_items RENAME wp_stmm_wcwh_transaction_items;
	ALTER TABLE wp_stmm_wcwh_tx_transaction_meta RENAME wp_stmm_wcwh_transaction_meta;
	ALTER TABLE wp_stmm_wcwh_tx_transaction_out_ref RENAME wp_stmm_wcwh_transaction_out_ref;
	ALTER TABLE wp_stmm_wcwh_tx_transaction_weighted RENAME wp_stmm_wcwh_transaction_weighted;

	//testing truncating
	TRUNCATE wp_stmm_wcwh_tx_inventory;
	TRUNCATE wp_stmm_wcwh_tx_transaction;
	TRUNCATE wp_stmm_wcwh_tx_transaction_conversion;
	TRUNCATE wp_stmm_wcwh_tx_transaction_items;
	TRUNCATE wp_stmm_wcwh_tx_transaction_meta;
	TRUNCATE wp_stmm_wcwh_tx_transaction_out_ref;
	TRUNCATE wp_stmm_wcwh_tx_transaction_weighted;

	SELECT h.doc_id, h.docno, h.doc_date, h.post_date, h.status AS doc_stat, h.doc_type
			, t.hid, t.doc_post_date, t.plus_sign, t.status AS t_stat, IFNULL( sd.action_at, t.lupdate_at ) AS post_at
			FROM wp_stmm_wcwh_transaction t 
			LEFT JOIN wp_stmm_wcwh_document h ON h.doc_id = t.doc_id AND h.status > 0
			LEFT JOIN wp_stmm_wcwh_stage_header sh ON sh.ref_id = h.doc_id AND sh.status > 0 AND sh.ref_type = CONCAT('wh_', t.doc_type)
			LEFT JOIN wp_stmm_wcwh_stage_details sd ON sd.stage_id = sh.id AND sd.status > 1 AND sd.id = (
				SELECT s.id
				FROM wp_stmm_wcwh_stage_details s 
				WHERE 1 AND s.stage_id = sh.id AND s.action = 'post'
				ORDER BY action_at DESC LIMIT 0,1
			)
			WHERE 1 AND t.status > 0 
			UNION ALL
			SELECT h.doc_id, h.docno, h.doc_date, h.post_date, h.status AS doc_stat, h.doc_type
			, 0 AS hid, '' AS doc_post_date, '' AS plus_sign, '' AS t_stat, h.post_date AS post_at
			FROM wp_stmm_wcwh_document h
			WHERE 1 AND h.doc_type IN('purchase_credit_note','purchase_debit_note') AND h.status >= 6
	*/
	public function looping()
	{
		$loop = 50;
		for( $i = 1; $i <= $loop; $i++ )
		{
			if( ! $this->migrate_handler() ) break;
		}
	}
	public function migrate_handler()
	{
		global $wpdb;

		$cond = "";
		$limit = 50;

		$sql = "SELECT a.*, h1.meta_value AS ref_doc_id, h2.meta_value AS ref_doc_type, h3.meta_value AS ref_doc 
		FROM wp_stmm_wcwh_tx_migration a 
		LEFT JOIN wp_stmm_wcwh_tx_transaction t ON t.doc_id = a.doc_id AND t.doc_type = a.doc_type AND t.status > 0
		LEFT JOIN wp_stmm_wcwh_document_meta h1 ON h1.doc_id = a.doc_id AND h1.item_id = 0 AND h1.meta_key = 'ref_doc_id'
		LEFT JOIN wp_stmm_wcwh_document_meta h2 ON h2.doc_id = a.doc_id AND h2.item_id = 0 AND h2.meta_key = 'ref_doc_type'
		LEFT JOIN wp_stmm_wcwh_document_meta h3 ON h3.doc_id = a.doc_id AND h3.item_id = 0 AND h3.meta_key = 'ref_doc'
		WHERE 1 AND t.hid IS NULL {$cond}
		ORDER BY a.post_at ASC LIMIT 0,{$limit} ";

		$transactions = $wpdb->get_results( $sql , ARRAY_A );
		if( ! $transactions ) return;
		//rt($transactions);	exit;

		$this->processing_list = [];

		@set_time_limit(3600);

		$fail_count = 0;
		$success_list = [];
		foreach( $transactions as $i => $transaction )
		{
			$doc_id = $transaction['doc_id'];
			$doc_type = $transaction['doc_type'];

			/*if( $this->skiped_list && in_array( $doc_type, [ 'delivery_order', 'good_issue', 'block_action', 'good_return' ] ) )
			{
				if( ! $this->skiped_list[ $doc_id ] )
	        	{
	        		//echo "<h3>---- Skip:{$doc_id} {$transaction['docno']} {$transaction['doc_type']} ----</h3>";
	        		$this->skiped_list[ $doc_id ] = $transaction;

	        		$this->processing( 0, 'Skipped', $transaction );
	        	}
				continue;
			}*/

			$succ = true; $result = [ 'succ'=>true ];
			wpdb_start_transaction();
			try
        	{
        		$done_skip = false;
        		//before transaction
        		switch( $doc_type )
				{
					case 'good_receive':
						$result = $this->good_receive( $doc_id, $transaction );
					break;
					case 'reprocess':
						$result = $this->reprocess( $doc_id, $transaction );
					break;
					case 'do_revise':
						$result = $this->do_revise( $doc_id, $transaction );
					break;
					case 'transfer_item':
						$result = $this->transfer_item( $doc_id, $transaction );
					break;
					case 'block_stock':
						$result = $this->block_stock( $doc_id, $transaction );
					break;
					case 'stock_adjust':
						$result = $this->stock_adjust( $doc_id, $transaction );
					break;
					case 'purchase_debit_note':
						$result = $this->purchase_debit_note( $doc_id, $transaction );
						$done_skip = true;
					break;
					case 'purchase_credit_note':
						$result = $this->purchase_credit_note( $doc_id, $transaction );
						$done_skip = true;
					break;
					/*case 'pos_transactions':
						$succ = $this->pos_transactions( $doc_id, $transaction );
					break;*/
					default:
						$result = [ 'succ'=>true ];
					break;
				}
				//before handler failed
				if( ! $result['succ'] )
				{
					$succ = false;
					//echo "<h3>Handler Failed {$doc_id} {$transaction['docno']}</h3>";
					//pd($transaction);
					
					$this->processing( -5, $result['msg'], $transaction );
				}

				if( $done_skip && $succ )
				{
					$this->processing( 10, 'CD Success', $transaction );
				}
				if( ! $done_skip && $succ )
				{
					//if transacted, then skip
					$can_transact = true;
					$t_exists = apply_filters( 'wcwh_get_exist_inventory_transaction', $doc_id, $doc_type );
					if( $t_exists )
					{
						//echo "<h3>Transaction Exist {$doc_id} {$transaction['docno']}</h3>";
						$can_transact = false;
					}
					if( $can_transact )
					{
						if( $succ ) $succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $doc_type, $doc_id );
						if( ! $succ )
						{
							//echo "<h3>Transact Failed {$doc_id} {$transaction['docno']}</h3>";
							//pd($transaction);

	        				$this->processing( -10, 'Failed', $transaction );
						}

						//after transaction
						if( $succ )
						{
							switch( $doc_type )
							{
								case 'delivery_order':
									$succ = $this->delivery_order( $doc_id, $transaction );
								break;
							}

							$success_list[ $doc_id ] = $transaction;

	        				$this->processing( 10, 'Success', $transaction );
						}
					}
				}
        	}
        	catch (\Exception $e) 
	        {
	            $succ = false;
	        }
	        finally
	        {
	        	if( $succ )
	        	{
	                wpdb_end_transaction( true );
	        	}
	            else 
	            {
	                wpdb_end_transaction( false );
	                $fail_count++;
	            }
	        }
	        if( ! $succ )
	        {
	        	echo "<h3>---- Error:{$doc_id} {$transaction['docno']} {$transaction['doc_type']} ----</h3>";
	        	pd( apply_filters( 'wcwh_inventory_get_notices', true ) );

	        	if( ! in_array( $transaction['doc_type'], [ 'purchase_debit_note', 'purchase_credit_note' ] ) )
	        	{
	        		//echo "<h2>---- Operation Continue ----</h2>";	
	        		if( ! $this->skiped_list[ $doc_id ] )
	        		{
	        			//echo "<h3>---- Skip:{$doc_id} {$transaction['docno']} {$transaction['doc_type']} ----</h3>";
	        			//$this->skiped_list[ $doc_id ] = $transaction;

	        			$this->processing( 0, 'Skipped', $transaction );
	        		}
	        		continue;
	        	}
	        	else
	        	{
	        		echo "<h2>---- Operation Stopped ----</h2>";
	        		break;
	        	}
	        }

	        //-----------------------------------------------------------------------------------------
	        
	        /*if( $this->skiped_list )
			{
				$this->skip_handler();
			}*/
		}

		echo "<h1>Success: ".sizeof( $success_list )." | Failed: {$fail_count}</h1>";

		//rt( $success_list );

		echo "<h1>+++===  Operation: ===+++</h1>";
		rt($this->processing_list);

		if( $fail_count >= $limit ) return false;

		return true;
	}

	public function skip_handler()
	{
		global $wpdb;

		foreach( $this->skiped_list as $doc_id => $transaction )
		{					
			$doc_type = $transaction['doc_type'];

			$succ = true; $result = [ 'succ'=>true ];
			wpdb_start_transaction();
			try
		 	{
		 		$done_skip = false;
        		//before transaction
        		switch( $doc_type )
				{
					case 'good_receive':
						$result = $this->good_receive( $doc_id, $transaction );
					break;
					case 'reprocess':
						$result = $this->reprocess( $doc_id, $transaction );
					break;
					case 'do_revise':
						$result = $this->do_revise( $doc_id, $transaction );
					break;
					case 'transfer_item':
						$result = $this->transfer_item( $doc_id, $transaction );
					break;
					case 'block_stock':
						$result = $this->block_stock( $doc_id, $transaction );
					break;
					case 'stock_adjust':
						$result = $this->stock_adjust( $doc_id, $transaction );
					break;
				}
				//before handler failed
				if( ! $result['succ'] )
				{
					$succ = false;
					//echo "<h3>Handler Failed {$doc_id} {$transaction['docno']}</h3>";
					//pd($transaction);
					
					$this->processing( -5, $result['msg'], $transaction );
				}

				$can_transact = true;
				$t_exists = apply_filters( 'wcwh_get_exist_inventory_transaction', $doc_id, $doc_type );
				if( $t_exists )
				{
					//echo "<h3>Skip Transaction Exist {$doc_id} {$transaction['docno']}</h3>";
					$can_transact = false;
				}
				if( $succ && $can_transact )
				{
					if( $succ ) $succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $doc_type, $doc_id );
					if( ! $succ )
					{
	     				$this->processing( -10, 'Skiped Failed', $transaction );
					}

					//after transaction
					if( $succ )
					{
						switch( $doc_type )
						{
							case 'delivery_order':
								$succ = $this->delivery_order( $doc_id, $transaction );
							break;
						}

						unset( $this->skiped_list[ $doc_id ] );

	     				$this->processing( 10, 'Skiped Success', $transaction );
					}
				}
			}
			catch (\Exception $e) 
		    {
		        $succ = false;
		    }
		    finally
		    {
		    	if( $succ )
		    	{
		            wpdb_end_transaction( true );
		    	}
		        else 
		        {
		            wpdb_end_transaction( false );
		        }
		    }
		    if( ! $succ )
		    {
		    	//echo "<h3>---- Skipped:{$doc_id} {$transaction['docno']} {$transaction['doc_type']} ----</h3>";
		    	break;
		    }
		}
	}

	//------------------------------------------------------------
	
	/*
		block_action (get total_amount & uprice from block_action)
		issue_return (get DO ucost, tcost / transaction total_cost)
		
		sale_credit_note (get DO ucost, tcost / transaction total_cost) ref_do_item_id, ref_do_doc_id

		sale_return (get GT > DO ucost, tcost / transaction total_cost)
	*/
	public function good_receive( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'GR Doc Fail' ];
		} 

		if( ! $transaction['ref_doc_type'] ) $transaction['ref_doc_type'] = $doc['ref_doc_type'];
		if( ! $transaction['ref_doc_id'] ) $transaction['ref_doc_id'] = $doc['ref_doc_id'];
		
		if( in_array( $transaction['ref_doc_type'], [ 'block_action', 'sale_return', 'sale_credit_note', 'issue_return' ] ) )	
		{
			$display = []; $title = "";
			switch( $transaction['ref_doc_type'] )
			{
				case 'block_action':
					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'ref_transact'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'GR Det Fail' ];
					}

					foreach( $details as $i => $row )
					{
						if( ! $row['ref_tran_prdt_id'] )
						{
							return [ 'succ'=>false, 'msg'=>'GR Tran Fail' ];
						}

						$price = round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 );
						if( $row['ref_tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $row['ref_tran_prdt_id']
								, round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						$display[] = $row;
						$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
				break;
				case 'sale_credit_note':
					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice', 'ref_do_item_id' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'GR>SCN Det Fail' ];
					}
					
					foreach( $details as $i => $row )
					{
						if( $row['ref_do_item_id'] > 0 )
						{
							$det = apply_filters( 'wcwh_get_doc_detail', [ 'item_id'=>$row['ref_do_item_id'] ], [], true, [ 'transact'=>1 ] );
							if( $det )
							{
								if( ! $det['tran_prdt_id'] )
								{
									return [ 'succ'=>false, 'msg'=>'GR>SCN Transact Fail' ];
								}

								$price = round( $det['weighted_total'] / $det['tran_bqty'], 5 );
								if( $det['tran_prdt_id'] != $row['product_id'] )
								{
									$price = round( apply_filters( 'wcwh_item_uom_conversion'
										, $det['tran_prdt_id']
										, round( $det['weighted_total'] / $det['tran_bqty'], 5 )
										, $row['product_id'], 'amt' 
									), 5 );
								}
								$amt = round( $price * $row['bqty'], 2 );

								update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
								update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

								$row['new_amt'] = $amt;
								$row['new_price'] = $price;
								$display[] = $row;
								$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
							}
						}
					}
				break;
				case 'sale_return':
					$gt_doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc['ref_doc_id'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'delivery_doc' ] ] );
					if( ! $gt_doc )
					{
						return [ 'succ'=>false, 'msg'=>'GR>GT Doc Fail' ];
					}
					if( $gt_doc['delivery_doc'] ) 
						$do_doc = apply_filters( 'wcwh_get_doc_header', [ 'docno'=>$gt_doc['delivery_doc'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
					/*if( ! $do_doc )
					{
						return [ 'succ'=>false, 'msg'=>'GR>GT>DO Doc Fail' ];
					}*/
					
					$doDetail = [];
					if( $do_doc )
					{
						$do_detail = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$do_doc['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
						if( ! $do_detail )
						{
							return [ 'succ'=>false, 'msg'=>'GR>GT>DO Det Fail' ];
						}
						foreach( $do_detail as $i => $row )
						{
							if( ! $row['tran_prdt_id'] )
							{
								return [ 'succ'=>false, 'msg'=>'GR>GT>DO Det Fail' ];
							}
							$doDetail[ $row['product_id'] ] = $row;
						}
						foreach( $doDetail as $prdt_id => $row )
						{
							$parent_child = apply_filters( 'wcwh_get_item_tree', $prdt_id, 1 );
							if( $parent_child )
							{
								foreach( $parent_child as $each )
								{
									$converts = apply_filters( 'wcwh_item_uom_conversion', $each['id'] );
									if( $converts )
									{
										foreach( $converts as $pid => $convert )
										{
											if( $pid != $prdt_id )
											{
												$doDetail[ $pid ] = $row;
											}
										}
									}
								}
							}
						}
					} 

					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'GR Det Fail' ];
					}

					foreach( $details as $i => $row )
					{
						$row['weighted_total'] = ( $row['weighted_total'] )? $row['weighted_total'] : $row['total_amount'];
						$row['tran_bqty'] = ( $row['tran_bqty'] )? $row['tran_bqty'] : $row['bqty'];
						
						$det = $row;
						if( $doDetail[ $row['product_id'] ] )
						{
							$det = $doDetail[ $row['product_id'] ];
						}

						$price = round( $det['weighted_total'] / $det['tran_bqty'], 5 );
						if( $det['tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $det['tran_prdt_id']
								, round( $det['weighted_total'] / $det['tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						$display[] = $row;
						$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
				break;
				case 'issue_return':
					$gt_doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc['ref_doc_id'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id' ] ] );
					if( ! $gt_doc )
					{
						return [ 'succ'=>false, 'msg'=>'GR>IR Doc Fail' ];
					}
					if( $gt_doc['ref_doc_id'] ) 
						$do_doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$gt_doc['ref_doc_id'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
					/*if( ! $do_doc )
					{
						return [ 'succ'=>false, 'msg'=>'GR>IR>DO Doc Fail' ];
					}*/
					
					$doDetail = [];
					if( $do_doc )
					{
						$do_detail = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$do_doc['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );
						if( ! $do_detail )
						{
							return [ 'succ'=>false, 'msg'=>'GR>IR>DO Det Fail' ];
						}
						foreach( $do_detail as $i => $row )
						{
							if( ! $row['tran_prdt_id'] )
							{
								return [ 'succ'=>false, 'msg'=>'GR>IR>DO Det Fail' ];
							}
							$doDetail[ $row['product_id'] ] = $row;
						}
						foreach( $doDetail as $prdt_id => $row )
						{
							$parent_child = apply_filters( 'wcwh_get_item_tree', $prdt_id, 1 );
							if( $parent_child )
							{
								foreach( $parent_child as $each )
								{
									$converts = apply_filters( 'wcwh_item_uom_conversion', $each['id'] );
									if( $converts )
									{
										foreach( $converts as $pid => $convert )
										{
											if( $pid != $prdt_id )
											{
												$doDetail[ $pid ] = $row;
											}
										}
									}
								}
							}
						}
					} 

					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'GR Det Fail' ];
					}

					foreach( $details as $i => $row )
					{
						$row['weighted_total'] = ( $row['weighted_total'] )? $row['weighted_total'] : $row['total_amount'];
						$row['tran_bqty'] = ( $row['tran_bqty'] )? $row['tran_bqty'] : $row['bqty'];
						
						$det = $row;
						if( $doDetail[ $row['product_id'] ] )
						{
							$det = $doDetail[ $row['product_id'] ];
						}

						$price = round( $det['weighted_total'] / $det['tran_bqty'], 5 );
						if( $det['tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $det['tran_prdt_id']
								, round( $det['weighted_total'] / $det['tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						$display[] = $row;
						$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
				break;
			}

			if( $display )
			{
				echo "<h5>updated GR {$title}</h5>";
				rt($display);
			}
		}

		return [ 'succ'=>true ];
	}

	/*
		good_issue
	*/
	public function reprocess( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";
		global $wpdb;

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'RP Doc Fail' ];
		} 

		if( ! $transaction['ref_doc_type'] ) $transaction['ref_doc_type'] = $doc['ref_doc_type'];
		if( ! $transaction['ref_doc_id'] ) $transaction['ref_doc_id'] = $doc['ref_doc_id'];

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'RP Det Fail' ];
		}

		foreach( $details as $i => $row )
		{
			$detail_metas = get_document_meta( $row['doc_id'], '', $row['item_id'] );
		    $row = $this->combine_meta_data( $row, $detail_metas );
		    foreach( $detail_metas as $key => $val )
		    {
		    	if( strpos( $key, 'material_cost_' ) !== false )
				{
					$ref_item_id = str_replace( 'material_cost_', '', $key );
				    $row['material'][$ref_item_id]['cost'] = $val[0];
				}
				if( strpos( $key, 'material_uqty_' ) !== false )
				{
				    $ref_item_id = str_replace( 'material_uqty_', '', $key );
				    $row['material'][$ref_item_id]['uqty'] = $val[0];
				}
				if( strpos( $key, 'wastage_qty_' ) !== false )
				{
				    $ref_item_id = str_replace( 'wastage_qty_', '', $key );
				    $row['material'][$ref_item_id]['lqty'] = $val[0];
				}
		    }

		    if( !empty( $row['material'] ) )		//reprocessed
		    {
		    	$amt = 0; $bunit = 0;
		    	foreach( $row['material'] as $ref_item_id => $vals )
		    	{
		    		$det = apply_filters( 'wcwh_get_doc_detail', [ 'item_id'=>$ref_item_id ], [], true, [ 'transact'=>1 ] );
		    		if( $det )
		    		{
		    			if( ! $det['tran_prdt_id'] )
						{
							return [ 'succ'=>false, 'msg'=>'RP material ref Tran Fail' ];
						}

						$vals['uqty'] = ( $vals['uqty'] )? $vals['uqty'] : $det['bqty'];
						$vals['lqty'] = ( $vals['lqty'] )? $vals['lqty'] : 0;

						$price = round( $det['weighted_total'] / $det['bqty'], 5 );
						$amt+= $cost = round( $price * $vals['uqty'], 2 );
						$bunit+= $det['bqty'] - $vals['lqty'];

						update_document_meta( $doc_id, 'material_cost_'.$ref_item_id, $cost, $row['item_id'] );
		    		}
		    	}
		    	$price = round( $amt / $row['bqty'], 5 );

		    	update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
				update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

				if( $row['bunit'] <= 0 && $bunit )
				{
					$update_items_sql = $wpdb->prepare( "UPDATE {$this->tables['document_items']} set bunit = %s WHERE item_id = %d AND status != 0 ", $bunit, $row['item_id'] );
					$update = $wpdb->query( $update_items_sql );
					if ( false === $update ) {
						return [ 'succ'=>false, 'msg'=>'RP update bunit ref Tran Fail' ];
					}
				}

				$row['new_amt'] = $amt;
				$row['new_price'] = $price;
				//$display[] = $row;
				//$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
		    }
		    else 		//leftover material
		    {
		    	if( $row['ref_item_id'] )
		    	{
		    		$det = apply_filters( 'wcwh_get_doc_detail', [ 'item_id'=>$row['ref_item_id'] ], [], true, [ 'transact'=>1 ] );
		    		if( $det )
		    		{
		    			if( ! $det['tran_prdt_id'] )
						{
							return [ 'succ'=>false, 'msg'=>'RP ref Tran Fail' ];
						}

						$price = round( $det['weighted_total'] / $det['tran_bqty'], 5 );
						if( $det['tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $det['tran_prdt_id']
								, round( $det['weighted_total'] / $det['tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						//$display[] = $row;
						//$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
		    	}
		    }
		}

		if( $display )
		{
			echo "<h5>updated RP {$title}</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}

	/*
		delivery_order
	*/
	public function do_revise( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'DR Doc Fail' ];
		} 

		if( ! $transaction['ref_doc_type'] ) $transaction['ref_doc_type'] = $doc['ref_doc_type'];
		if( ! $transaction['ref_doc_id'] ) $transaction['ref_doc_id'] = $doc['ref_doc_id'];

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'ref_transact'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'DR Det Fail' ];
		}

		foreach( $details as $i => $row )
		{
			if( ! $row['ref_tran_prdt_id'] )
			{
				return [ 'succ'=>false, 'msg'=>'DR>DO Tran Fail' ];
			}

			$price = round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 );
			if( $row['ref_tran_prdt_id'] != $row['product_id'] )
			{
				$price = round( apply_filters( 'wcwh_item_uom_conversion'
					, $row['ref_tran_prdt_id']
					, round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 )
					, $row['product_id'], 'amt' 
				), 5 );
			}
			$amt = round( $price * $row['bqty'], 2 );

			update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
			update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

			$row['new_amt'] = $amt;
			$row['new_price'] = $price;
			//$display[] = $row;
			//$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
		}

		if( $display )
		{
			echo "<h5>updated DR {$title}</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}

	/*
		good_issue
	*/
	public function transfer_item( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'TI Doc Fail' ];
		} 

		if( ! $transaction['ref_doc_type'] ) $transaction['ref_doc_type'] = $doc['ref_doc_type'];
		if( ! $transaction['ref_doc_id'] ) $transaction['ref_doc_id'] = $doc['ref_doc_id'];

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'ref_transact'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'TI Det Fail' ];
		}

		foreach( $details as $i => $row )
		{
			if( ! $row['ref_tran_prdt_id'] )
			{
				return [ 'succ'=>false, 'msg'=>'TI>GI Tran Fail' ];
			}

			$price = round( $row['ref_weighted_total'] / $row['bqty'], 5 );
			$amt = round( $row['ref_weighted_total'], 2 );

			update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
			update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

			$row['new_amt'] = $amt;
			$row['new_price'] = $price;
			//$display[] = $row;
			//$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
		}

		if( $display )
		{
			echo "<h5>updated TI {$title}</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}

	/*
		good_issue, good_return
	*/
	public function block_stock( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'BS Doc Fail' ];
		} 

		if( ! $transaction['ref_doc_type'] ) $transaction['ref_doc_type'] = $doc['ref_doc_type'];
		if( ! $transaction['ref_doc_id'] ) $transaction['ref_doc_id'] = $doc['ref_doc_id'];
		
		if( in_array( $transaction['ref_doc_type'], [ 'good_issue', 'good_return' ] ) )	
		{
			$display = []; $title = "";
			switch( $transaction['ref_doc_type'] )
			{
				case 'good_issue':
					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'ref_transact'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'BS>GI Det Fail' ];
					}

					foreach( $details as $i => $row )
					{
						if( ! $row['ref_tran_prdt_id'] )
						{
							return [ 'succ'=>false, 'msg'=>'BS>GI Tran Fail' ];
						}

						$price = round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 );
						if( $row['ref_tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $row['ref_tran_prdt_id']
								, round( $row['ref_weighted_total'] / $row['ref_tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						$display[] = $row;
						$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
				break;
				case 'good_return':
					$gt_doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc['ref_doc_id'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'delivery_doc' ] ] );
					if( ! $gt_doc )
					{
						return [ 'succ'=>false, 'msg'=>'BS>GT Doc Fail' ];
					} 
					if( $gt_doc['delivery_doc'] )
						$do_doc = apply_filters( 'wcwh_get_doc_header', [ 'docno'=>$gt_doc['delivery_doc'] ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
					/*if( ! $do_doc )
					{
						return [ 'succ'=>false, 'msg'=>'BS>GT>DO Doc Fail' ];
					} */
					
					$doDetail = [];
					if( $do_doc )
					{
						$do_detail = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$do_doc['doc_id'] ], [], false, [ 'transact'=>1, 'usage'=>1 ] );

						if( ! $do_detail )
						{
							return [ 'succ'=>false, 'msg'=>'BS>GT>DO Det Fail' ];
						}
						foreach( $do_detail as $i => $row )
						{
							if( ! $row['tran_prdt_id'] )
							{
								return [ 'succ'=>false, 'msg'=>'BS>GT>DO Det Fail' ];
							}
							$doDetail[ $row['product_id'] ] = $row;
						}
						foreach( $doDetail as $prdt_id => $row )
						{
							$parent_child = apply_filters( 'wcwh_get_item_tree', $prdt_id, 1 );
							if( $parent_child )
							{
								foreach( $parent_child as $each )
								{
									$converts = apply_filters( 'wcwh_item_uom_conversion', $each['id'] );
									if( $converts )
									{
										foreach( $converts as $pid => $convert )
										{
											if( $pid != $prdt_id )
											{
												$doDetail[ $pid ] = $row;
											}
										}
									}
								}
							}
						}
					} 

					$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
					if( ! $details )
					{
						return [ 'succ'=>false, 'msg'=>'BS Det Fail' ];
					}

					foreach( $details as $i => $row )
					{
						$row['weighted_total'] = ( $row['weighted_total'] )? $row['weighted_total'] : $row['total_amount'];
						$row['tran_bqty'] = ( $row['tran_bqty'] )? $row['tran_bqty'] : $row['bqty'];
						
						$det = $row;
						if( $doDetail[ $row['product_id'] ] )
						{
							$det = $doDetail[ $row['product_id'] ];
						}

						$price = round( $det['weighted_total'] / $det['tran_bqty'], 5 );
						if( $det['tran_prdt_id'] != $row['product_id'] )
						{
							$price = round( apply_filters( 'wcwh_item_uom_conversion'
								, $det['tran_prdt_id']
								, round( $det['weighted_total'] / $det['tran_bqty'], 5 )
								, $row['product_id'], 'amt' 
							), 5 );
						}
						$amt = round( $price * $row['bqty'], 2 );

						update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
						update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

						$row['new_amt'] = $amt;
						$row['new_price'] = $price;
						$display[] = $row;
						$title = "{$doc_id} {$transaction['docno']} {$transaction['ref_doc_type']}";
					}
				break;
			}

			if( $display )
			{
				echo "<h5>updated BS {$title}</h5>";
				rt($display);
			}
		}

		return [ 'succ'=>true ];
	}

	public function stock_adjust( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1 ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'SJ Doc Fail' ];
		} 

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'SJ Det Fail' ];
		}

		foreach( $details as $i => $row )
		{
			$plus_sign = get_document_meta( $row['doc_id'], 'plus_sign', $row['item_id'], true );
			if( $plus_sign == '-' ) continue;
			
			$transact_item = apply_filters( 'warehouse_get_inventory_transaction_item_weighted_price', $row['product_id'], $doc['warehouse_id'], $row['strg_id'] );
			if( ! $transact_item ) continue;

			$price = $transact_item['bal_price'];
			if( $transact_item['product_id'] != $item['product_id'] ) 
				$price = round_to( $transact_item['converse_uprice'], 2 );

			$amt = round( $price * $row['bqty'], 2 );

			update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
			update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

			$row['new_amt'] = $amt;
			$row['new_price'] = $price;
			$display[] = $row;
			$title = "{$doc_id} {$transaction['docno']}";
		}

		if( $display )
		{
			echo "<h5>updated SJ {$title}</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}

	public function purchase_debit_note( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1 ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'PDN Doc Fail' ];
		}

		$need_inv = get_document_meta( $doc_id, 'inventory_action', 0, true );

		$need_transact = false;
		$detail_items = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>['total_amount'] ] );
		if( $detail_items )
		{
			foreach( $detail_items as $i => $ditem )
			{
				if( $ditem['total_amount'] > 0 )
				{
					$need_transact = true;
				}
			}
		}

		//FIFO Functions Here ON Posting
		if( isset( $doc_id ) && $need_transact && ! $need_inv )
		{
			$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $doc['doc_type'], $doc_id );	
			if( ! $succ )
			{
				return [ 'succ'=>false, 'msg'=>'PDN Handle Fail' ];
			}
		}

		return [ 'succ'=>true ];
	}

	public function purchase_credit_note( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1 ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'PCN Doc Fail' ];
		} 

		$need_inv = get_document_meta( $doc_id, 'inventory_action', 0, true );

		$need_transact = false;
		$detail_items = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>['total_amount'] ] );
		if( $detail_items )
		{
			foreach( $detail_items as $i => $ditem )
			{
				if( $ditem['total_amount'] > 0 )
				{
					$need_transact = true;
				}
			}
		}

		//FIFO Functions Here ON Posting
		if( isset( $doc_id ) && $need_transact && ! $need_inv )
		{
			$succ = apply_filters( 'warehouse_inventory_transaction_filter', 'save', $doc['doc_type'], $doc_id );	
			if( ! $succ )
			{
				return [ 'succ'=>false, 'msg'=>'PCN Handle Fail' ];
			}
		}

		return [ 'succ'=>true ];
	}

	public function pos_transactions( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;
		$display = []; $title = "";

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1 ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'POS Doc Fail' ];
		} 

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'meta'=>[ 'total_amount', 'uprice' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'POS Det Fail' ];
		}

		foreach( $details as $i => $row )
		{
			$price = round( $row['total_amount'] / $row['bqty'], 5 );
			$amt = round( $row['total_amount'], 2 );

			update_document_meta( $doc_id, 'total_amount', $amt, $row['item_id'] );
			update_document_meta( $doc_id, 'uprice', $price, $row['item_id'] );

			$row['new_amt'] = $amt;
			$row['new_price'] = $price;
			//$display[] = $row;
			//$title = "{$doc_id} {$transaction['docno']}";
		}

		if( $display )
		{
			echo "<h5>updated POS {$title}</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}

	//------------------------------------------------------------

	public function delivery_order( $doc_id, $transaction = [] )
	{
		if( ! $doc_id ) return false;

		//delivery_order: ucost, tcost

		$doc = apply_filters( 'wcwh_get_doc_header', [ 'doc_id'=>$doc_id ], [], true, [ 'usage'=>1, 'meta'=>[ 'ref_doc_id', 'ref_doc_type' ] ] );
		if( ! $doc )
		{
			return [ 'succ'=>false, 'msg'=>'DO Doc Fail' ];
		} 

		$details = apply_filters( 'wcwh_get_doc_detail', [ 'doc_id'=>$doc_id ], [], false, [ 'usage'=>1, 'transact'=>1, 'meta'=>[ 'ucost', 'tcost' ] ] );
		if( ! $details )
		{
			return [ 'succ'=>false, 'msg'=>'DO Det Fail' ];
		}
		
		$display = [];
		foreach( $details as $i => $row )
		{
			$price = $row['weighted_price'];
			if( $row['tran_prdt_id'] != $row['product_id'] )
			{
				$price = round( $row['weighted_total'] / $row['bqty'], 5 );
			}
			
			update_document_meta( $doc_id, 'tcost', $row['weighted_total'], $row['item_id'] );
			update_document_meta( $doc_id, 'ucost', $price, $row['item_id'] );

			if( $row['tran_prdt_id'] != $row['product_id'] )
			{
				$row['wtotal'] = $row['weighted_total'];
				$row['wprice'] = $price;
				//$display[] = $row;
			}
		}

		if( $display )
		{
			echo "<h5>updated DO</h5>";
			rt($display);
		}

		return [ 'succ'=>true ];
	}
}
?>