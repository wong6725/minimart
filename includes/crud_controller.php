<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WCWH_CRUD_Controller 
{	
	protected $refs;
	protected $setting = [];

	protected $db_wpdb;

	protected $prefix = "";
	protected $tbl = "";

	protected $primary_key = "";
	protected $default_fields = array();
	protected $unique_fields = array();
	
	public $inclPrimary = false;
	public $exportDirectory = false;

	protected $exportDirName = 'wcwh_export';

	protected $unneeded_fields = [ 'action', 'token' ];
	
	public function __construct()
	{
		$this->set_wpdb();

		$this->set_constants();
	}

	public function __destruct()
	{
		unset($this->db_wpdb);
		unset($this->refs);
		unset($this->setting);
	}

	public function set_constants()
	{
		global $wcwh;
		$this->refs = ( $this->refs )? $this->refs : $wcwh->get_plugin_ref();
		$this->setting = $wcwh->get_setting();

		$this->prefix = $wcwh->prefix;
	}

	public function set_wpdb( $username = '', $password = '', $database = '', $host = '' )
	{
		global $wpdb, $table_prefix;
		$this->db_wpdb = $wpdb;
		
		if( !empty( $username ) && isset( $password ) && !empty( $database ) && !empty( $host ) )
		{
			$this->db_wpdb = new wpdb( $username, $password, $database, $host );
			$this->db_wpdb->set_prefix( $table_prefix );

			if( $this->refs['wpdb_tables'] )
			{
				foreach( $this->refs['wpdb_tables'] as $key => $tbl )
				{
					$this->db_wpdb->$key = $tbl;
				}
			}
		}

		$this->set_logic();
	}

	public function set_logic()
	{

	}

	public function set_prefix( $prefix = "" )
	{
		$this->prefix = $prefix;
	}

	protected function get_primaryKey()
	{
		return $this->primary_key;
	}

	protected function get_defaultFields()
	{
		return $this->default_fields;
	}

	protected function get_uniqueFields()
	{
		return $this->unique_fields;
	}

	protected function get_unneededFields()
	{
		return $this->unneeded_fields;
	}
	
	/**
	 *	Sanitizing data recursively
	 * 	@param array / string $datas
	 */
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
			//return _sanitize_text_fields( htmlspecialchars( stripslashes( $datas ) ), true );
			return _sanitize_text_fields( stripslashes( $datas ), true );
		}
	}

	/**
	 *	To Extract Meta & Trim Meta from Data
	 */
	public function extract_data( $datas = array() )
	{
		$def_keys = array_keys( $this->get_defaultFields() );
		array_push( $def_keys, $this->get_primaryKey() );
		
		$metas = array();
		foreach( $datas as $key => $value )
		{
			if( !in_array( $key, $def_keys ) )
			{
				$metas[$key] = $value;
				unset( $datas[$key] );
			}
		}
		
		return array( 'datas' => $datas, 'metas' => $metas );
	}

	/**
	 *	Remove un-needed field for data entry
	 */
	public function trim_fields( $datas = array(), $deep = false )
	{
		if( ! $datas ) return false;
		
		foreach( $datas as $key => $value )
		{
			if( ! is_array( $value ) )
			{
				if( in_array( $key, $this->get_unneededFields() ) )
					unset( $datas[$key] );
			}
			else
			{
				if( $deep )
				{	
					$datas[$key] = self::trim_fields( $value, $deep );
				}
			}
		}

		return $datas;
	}
	
	/**
	 *	Convert array data to json
	 */
	public function json_encoding( $datas = array() )
	{
		foreach( $datas as $key => $value )
		{
			if( is_array( $value ) )
				$datas[$key] = ( $value )? json_encode( $value ) : '';
		}

		return $datas;
	}
	
	/**
	 *	Convert json data to array
	 */
	public function json_decoding( $string = "" )
	{
		foreach( $datas as $key => $value )
		{
			if( is_json( $value ) )
				$datas[$key] = json_decode( $value, true );
		}

		return $datas;
	}

	/**
	 *	Combine metas into row data
	 */
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
	
	/**
	 *	Rearrange array by data key
	 */
	public function rearrange_array_by_key( $datas = array(), $key = '' )
	{
		if( ! $datas || ! $key ) return $datas;
		
		$arr_result = array();
		foreach( $datas as $item )
		{
			$row = (array)$item;
			$arr_result[ $row[ $key ] ] = $row;
		}

		return $arr_result;
	}
	
	/*
	*	bind array to Predefined Header, Item Column
	*/
	public function bind_data_predefined( $file_data , $default_column )
	{
		if( ! $file_data || ! $default_column )
			return false;
		$new_file_data = array();

		foreach( $file_data as $i => $row ) 
		{
			$data = array();
			$irow = array_values( $row );

			foreach( $default_column as $j => $col ) 
			{
				$data[ $col ] = $irow[$j];
			}
			array_push( $new_file_data, $data );
		}

		return $new_file_data ;
	}

	/*
	*	validate required data fields
	*/
	public function validate_import_data( $datas = array(), $required = array() )
	{
		if( ! $datas || ! $required ) return false;

		$succ = true;
		foreach( $datas as $i => $data )
		{
			foreach( $required as $key )
			{
				if( empty( $data[ $key ] ) )
				{
					$succ = false;
					break;
				}
			}
		}

		return $succ;
	}
	
	/*
	*	Seperate array to header/detail
	*/
	public function seperate_import_data( $file_data , $header_col , $header_unique , $detail_col )
	{
		$result_data = array();
		$header_data = array();
		$header_exist = array();
		$row_index = 0 ; $header_index = 0;

		foreach( $file_data as $i => $line_data ) 
		{
			//check duplicate header
			$unique = array();
			for ( $j = 0; $j < count( $header_unique ); $j++ ) 
			{
				$unique[ $header_unique[$j] ] = $line_data[ $header_unique[$j] ];
			}
			$header_string = implode( '|' , $unique );
			if( array_key_exists( $header_string, $header_exist ) ) 
			{
				$header_index = $header_exist[ $header_string ];
			}
			else
			{
				$data = array();
				for ( $j = 0; $j < count( $header_col ); $j++ ) 
				{
					$data[ $header_col[$j] ] = !empty( $line_data[ $header_col[$j] ] )? $line_data[ $header_col[$j] ] : '';
				}
				$result_data[$row_index]['header'] = $data;
				$header_index = $row_index;
				$header_exist[$header_string] = $header_index; //header row index
				$row_index++;
			}
			$itm_data = array();
			for ( $j = 0; $j < count( $detail_col ); $j++ ) {
				$itm_data[ $detail_col[$j] ] = !empty( $line_data[ $detail_col[$j] ] )? $line_data[ $detail_col[$j] ] : '';
			}
			$result_data[ $header_index ]['detail'][] = $itm_data;
		}
		return $result_data;
	}

	public function validate( $action, $datas = array(), $obj = array() )
	{
		return true;
	}
	
	public function after_handler( $outcome, $action, $datas = array(), $metas = array(), $obj = array() )
	{
		return $outcome;
	}
	

	/**
	 *	CRUD Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_prefix()
	{
		global $wcwh;

		return $this->prefix = ( $this->prefix )? $this->prefix : $wcwh->prefix;
	}

	public function create( $datas )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$succ = $wpdb->insert( $prefix.$this->tbl, $datas );
		
		return ( $succ )? $wpdb->insert_id : $succ;
	}
	
	public function update( $id = 0, $datas = array(), $args = array() )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		
		$find = array();
		if( $id && $this->primary_key )
		{
			$find = array( $this->primary_key => $id );
			if( ! $this->inclPrimary ) unset( $datas[$this->primary_key] );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}
		
		return $wpdb->update( $prefix.$this->tbl, $datas, $find );
	}
	
	public function delete( $id, $args = array() )
	{
		if( ! $id && ! $args  ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$find = array();
		if( $id && $this->primary_key )
		{
			$find = array( $this->primary_key => $id );
		}

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}

		if( ! $find ) return false;
		
		return $wpdb->delete( $prefix.$this->tbl, $find );
	}
	
	public function update_status( $id, $status = 0, $args = array() )
	{
		if( ! $id && ! $args ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$find = array();
		if( $id && $this->primary_key )
		{
			$find = array( $this->primary_key => $id );
		}

		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}

		if( ! $find ) return false;

		return $wpdb->update( $prefix.$this->tbl, array( 'status' => $status ), $find );
	}
	
	public function select( $id = 0, $args = array() )
	{
		if( ! $id && ! $args ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$fld = " * "; 
		$table = " {$prefix}{$this->tbl} ";
		$cond = $wpdb->prepare(" WHERE %d ", 1 );

		if( $id > 0 && $this->primary_key )
		{
			$cond .= $wpdb->prepare(" AND {$this->primary_key} = %d ", $id );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .=  " AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
			}
		}

		$sql = "SELECT {$fld} FROM {$table} {$cond} ;";
		
		return $wpdb->get_row( $sql , ARRAY_A );
	}
	
	public function selects( $id = 0, $args = array(), $order = array() )
	{
		if( ! $id && ! $args ) return false;

		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();

		$fld = " * "; 
		$table = " {$prefix}{$this->tbl} ";
		$cond = $wpdb->prepare(" WHERE %d ", 1 );
		
		if( $id > 0 && $this->primary_key )
		{
			$cond .= $wpdb->prepare(" AND {$this->primary_key} = %d ", $id );
		}
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				if( is_array( $val ) )
					$cond .= " AND {$key} IN ('" .implode( "','", $val ). "') ";
				else
					$cond .= $wpdb->prepare( " AND {$key} = %s ", $val );
			}
		}

		$ords = "";
		if( !empty( $order ) )
		{
			if( is_array( $order ) )
			{
				$order_collection = array();
				foreach( $order as $i => $order_by )
				{
					$order_collection[] = $order_by['order_by']." ".( $order_by['order']? $order_by['order'] : 'DESC' );
				}

				if( $order_collection )
				{
					$ords = " ORDER BY ".implode( ", ", $order_collection );
				}
			}
			else
			{
				$ords = " ORDER BY ".$order;
			}
		}
		
		$sql = "SELECT {$fld} FROM {$table} {$cond} {$ords} ;";
		
		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function rawSelect( $sql )
	{
		if( ! $sql ) return false;
		
		$wpdb = $this->db_wpdb;

		return $wpdb->get_row( $sql , ARRAY_A );
	}

	public function rawSelects( $sql )
	{
		if( ! $sql ) return false;
		
		$wpdb = $this->db_wpdb;

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	public function rawUpdate( $datas = array(), $args = array(), $table = "" )
	{
		if( ! $datas ) return false;
		
		$wpdb = $this->db_wpdb;
		$prefix = $this->get_prefix();
		$table = empty( $table )? $this->tbl : $table;

		$find = array();
		
		if( !empty( $args ) )
		{
			foreach( $args as $key => $val )
			{
				$find[$key] = $val;
			}
		}
		
		return $wpdb->update( $prefix.$table, $datas, $find );
	}

	public function rawQuery( $sql )
	{
		if( ! $sql ) return false;

		$wpdb = $this->db_wpdb;

		return $wpdb->query( $sql );
	}

	/*
	SELECT a.TABLE_NAME, a.TABLE_ROWS, a.DATA_LENGTH , b.TABLE_COLS 
	FROM INFORMATION_SCHEMA.TABLES a 
	LEFT JOIN (
		SELECT TABLE_SCHEMA, TABLE_NAME, COUNT(COLUMN_NAME) AS TABLE_COLS
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE 1 AND TABLE_SCHEMA = 'minimart_ifp'
		GROUP BY TABLE_NAME
	) b ON b.TABLE_SCHEMA = a.TABLE_SCHEMA AND b.TABLE_NAME = a.TABLE_NAME
	WHERE 1 AND a.TABLE_SCHEMA = 'minimart_ifp'
	*/
	public function dbCount( $dbname = '' )
	{
		$wpdb = $this->db_wpdb;
		$dbname = !empty( $dbname )? $dbname : $wpdb->dbname;

		$fld = "a.TABLE_NAME, a.TABLE_ROWS, a.DATA_LENGTH, b.TABLE_COLS  ";
		$tbl = "INFORMATION_SCHEMA.TABLES a ";

		$f = "TABLE_SCHEMA, TABLE_NAME, COUNT(COLUMN_NAME) AS TABLE_COLS ";
		$t = "INFORMATION_SCHEMA.COLUMNS ";
		$c = $wpdb->prepare( "AND TABLE_SCHEMA = %s ", $dbname );
		$g = "GROUP BY TABLE_NAME ";
		$subSql = "SELECT {$f} FROM {$t} WHERE 1 {$c} {$g} ";
		$tbl.= "LEFT JOIN ( {$subSql} ) b ON b.TABLE_SCHEMA = a.TABLE_SCHEMA AND b.TABLE_NAME = a.TABLE_NAME ";
		
		$cond = $wpdb->prepare( "AND a.TABLE_SCHEMA = %s ", $dbname );

		$sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cond} ";

		return $wpdb->get_results( $sql , ARRAY_A );
	}

	/**
	 *	Export / Import Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function export_data( $args = array(), $params = array() )
	{
		$delimiter = ( $args['delimiter'] )? $args['delimiter'] : ",";
		$hasHeader = ( isset( $args['header'] ) )? $args['header'] : true;
		$ext = "";

		if( defined('CBXPHPSPREADSHEET_PLUGIN_NAME') && file_exists( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' ) ) 
		{
			@set_time_limit(3600);
			//Include PHPExcel
			require_once( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' );

			//now take instance
			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet = $spreadsheet->getActiveSheet();

			$ext = strtolower( $args['file_type'] );
			switch( $ext )
			{
				case 'csv':
				case 'txt':
					$writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
					$writer->setDelimiter($delimiter);
					$writer->setEnclosure('"');
					$writer->setLineEnding("\r\n");
					$writer->setSheetIndex(0);
					if( ! $this->exportDirectory ) header("Content-type: text/{$ext}; charset=UTF-8");
				break;
				case 'xls':
					$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
					if( ! $this->exportDirectory ) header('Content-Type: application/vnd.ms-excel');
				break;
				case 'xlsx':
				default:
					$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
					if( ! $this->exportDirectory ) header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				break;
			}
			if( ! $ext ) $ext = 'xlsx';

			$path = "php://temp";
			$filename = !empty( $args['filename'] )? $args['filename'] : "items_";
			if( ! $args['nodate'] ) $filename.= current_time( !empty( $args['dateformat'] )? $args['dateformat'] : "YmdHis" );
			$filename.= ".".$ext;

			if( $params['datas'] ) $datas = $params['datas'];
			else
			{
				if( method_exists( $this, 'export_data_handler' ) )
				$datas = $this->export_data_handler( $params );
			}

			$i = 0;
			$def_column = ( method_exists( $this, 'im_ex_default_column' ) )? $this->im_ex_default_column( $params ) : [];
			if( $hasHeader || ! $datas )
			{	
				$i++;
				$j = 0;
				$titles = [];
				if( empty( $def_column['title'] ) && $datas )
				{
					$tit = [];
					foreach( $datas[0] as $key => $val )
					{
						$tit[] = $key;
					}
					$def_column['title'] = $tit;
				}
				if( ! empty( $def_column['title'] ) ) $titles = array_merge( $titles, $def_column['title'] );
				if( ! empty( $titles ) )
				{
					foreach( $titles as $title )
					{
						$sheet->setCellValue( getAlphaFromNumber( $j ).$i, html_entity_decode( $title ) );
						$j++;
					}
				}
			}

			if( $datas )
			{
				foreach( $datas as $row )
				{
					$i++;
					$j = 0;
					foreach( $row as $col )
					{
						$sheet->setCellValue( getAlphaFromNumber( $j ).$i, html_entity_decode( $col ) );
						$j++;
					}
				}
			}

			$writer->setPreCalculateFormulas(false);
			
			if( $this->exportDirectory )
			{
				$dir = wp_upload_dir( null, true );
				$dirname = $dir['basedir'].'/'.$this->exportDirName;
			    if ( ! file_exists( $dirname ) ) wp_mkdir_p( $dirname );
			    
			    $dirname.= '/'.$filename;
			    if( $datas ) $writer->save($dirname);
			}
			else
			{
				header('Content-Disposition: attachment;filename="'.$filename.'"');
				header('Cache-Control: max-age=0');

				$writer->save('php://output');

				exit();
			}
		}
		else
		{
			echo 'No Supported Export Library';
			return false;
		}
	}

	public function import_data( $files = null, $args = array() )
	{
		if( ! $files ) return false;
		$succ = true;

		@set_time_limit(3600);

		$params = $args;
		if( is_array( $args['header'] ) && ! empty( $params['header'] ) ) $args = $args['header'];
		$delimiter = ( $args['delimiter'] )? $args['delimiter'] : ",";
		$hasHeader = ( $args['header'] )? $args['header'] : true;

		if( method_exists( $this, 'im_ex_default_column' ) && method_exists( $this, 'import_data_handler' ) && 
			defined('CBXPHPSPREADSHEET_PLUGIN_NAME') && file_exists( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' ) 
		) {
			//Include PHPExcel
			require_once( CBXPHPSPREADSHEET_ROOT_PATH . 'lib/vendor/autoload.php' );

			//now take instance
			$def_column = $this->im_ex_default_column( $params );
			foreach( $files as $file )
			{	
				$arr_file = explode('.', $file['name']);
        		$extension = end( $arr_file );

				switch( $extension )
				{
					case 'csv':
						$reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
						$reader->setInputEncoding( 'UTF-8' );
						$reader->setDelimiter( $delimiter );
						$reader->setEnclosure( '"' );
						$reader->setSheetIndex( 0 );
					break;
					case 'xls':
						$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
					break;
					case 'xlsx':
						$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
					break;
					default:
						return false;
					break;
				}
				
				$spreadsheet = $reader->load( $file['tmp_name'] );
        		$sheetData = $spreadsheet->getActiveSheet()->toArray();

				if( $sheetData )
				{
					$sheetData = $this->bind_data_predefined( $sheetData, $def_column['default'] );
					if( $hasHeader )
					{	
						if( count( $def_column['default'] ) != count( array_filter( $sheetData[0] ) ) ) 
						{
							$this->Notices->set_notice( 'Data column does not match', 'error' );
							return false;
						}
						unset( $sheetData[0] );
					}
					else
					{
						if( count( $def_column['default'] ) != count( $sheetData[0] ) ) 
						{
							$this->Notices->set_notice( 'Data column does not match', 'error' );
							return false;
						}
					}
					
					$succ = $this->import_data_handler( $sheetData, $params );
				}
			}
		}
		else
		{
			$succ = false;
			$this->Notices->set_notice( 'No Supported Import Library', 'error' );
		}

		return $succ;
	}

	public function files_grouping( $files = [] )
	{
		if( ! $files ) return $files;

		$grouped = [];
		foreach( $files as $key => $file )
		{
			foreach( $file as $i => $val )
			{
				$grouped[$i][$key] = $val;
			}
		}

		return $grouped;
	}

	/**
	 *	Print Section
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function print_form( $id = 0, $function = '' )
	{
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		
		ob_start();

			do_action( 'wcwh_get_template', 'template/doc-header.php' );
			
			if( !empty( $function ) )
	            $this->$function( $id );
	        else   
	    		$this->view_form( $id, false, true );

			do_action( 'wcwh_get_template', 'template/doc-footer.php' );

		$content.= ob_get_clean();
		
		if( is_plugin_active( 'dompdf-generator/dompdf-generator.php' ) ){
			$paper = [ 'size' => 'A4', 'orientation' => 'landscape' ];
			$args = [ 'filename' => $this->section_id ];
			
			do_action( 'dompdf_generator', $content, $paper, array(), $args );
		}
		else{
			echo $content;
		}
	}
	
} //class