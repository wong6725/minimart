<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Files" ) )
{

class WCWH_Files extends WCWH_CRUD_Controller 
{
	protected $section_id = "attachment";

	protected $tbl = "attachment";

	protected $primary_key = "id";

	protected $tables = array();

	public $Notices;
	public $className = "WCWH_Files";

	public $update_tree_child = false;
	public $one_step_delete = false;
	public $useFlag = false;

	//---------------------------------------------
	protected $dir_path;
	protected $dir_url;
	protected $ext = [
		'jpg',
		'jpeg',
		'jfif',
		'png',
		'gif',
		'svg',
		'pdf',
		'xlsx',
		'xls',
		'csv',
		'doc',
		'docx',
		'txt',
		'pps',
		'ppt',
		'pptx',
	];
	protected $ext_converse = [
		'jfif' => 'jpg',
	];
	public $dir_name = 'wcwh_uploads';
	
	public function __construct() 
	{
		parent::__construct();

		if( $db_wpdb ) $this->db_wpdb = $db_wpdb;

		$this->Notices = new WCWH_Notices();

		$this->set_db_tables();

		$directory = wp_upload_dir();
		$this->dir_path = $directory['basedir'];
		$this->dir_url = $directory['baseurl'];
	}

	public function __destruct()
	{
		unset($this->db_wpdb);
		unset($this->Notices);
		unset($this->tables);
	}

	public function set_section_id( $section_id )
	{
		$this->section_id = $section_id;
	}

	public function get_section_id()
	{
		return $this->section_id;
	}

	public function set_db_tables()
	{
		global $wcwh;
		$prefix = $this->get_prefix();

		$this->tables = array(
			"main" 			=> $prefix.$this->tbl,
			"section"		=> $prefix."section",
			"status"		=> $prefix."status",
		);
	}

	protected function get_defaultFields()
	{
		return array(
			'section_id' => '',
			'ref_id' => 0,
			'def_name' => '',
			'sys_name' => '',
			'path' => '',
			'status' => 1,
			'created_by' => 0,
			'created_at' => '',
			'lupdate_by' => 0,
			'lupdate_at' => '',
		);
	}

	public function action_handler( $action, $datas = array(), $metas = array(), $obj = array() )
	{
		if( $this->Notices ) $this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $this->tables || ! $action || ! $datas )
		{
			$succ = false;
			if( $this->Notices ) $this->Notices->set_notice( "missing-parameter", "error", $this->className."|action_handler" );
		}

		$outcome = array();

		if( $succ )
		{
			$exist = array();

			$Tree = array();
			if( $this->tables['tree'] )
			{
				$Tree = new WCWH_TreeAction( $this->tables['tree'] );
			}

			$action = strtolower( $action );
			switch ( $action )
			{
				case "save":
				case "update":
					$id = ( isset( $datas['id'] ) && !empty( $datas['id'] ) )? $datas['id'] : "0";

					if( $id != "0" )	//update
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] != 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							$result = $this->update( $id, $datas );
							if ( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
							else
							{
								if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
							}
						}
					}
					else
					{
						$id = $this->create( $datas );
						if ( ! $id )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "create-fail", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							if( $metas && method_exists( $this, 'update_metas' ) ) $this->update_metas( $id, $metas );
						}
					}

					if( $succ )
					{
						$outcome['id'] = $id;

						//Tree handling
						if( $Tree )
						{
							$tree_data = [ "descendant" => $id, "ancestor" => ( $datas["parent"] == 0 )? "" : $datas["parent"] ];
			    			$child_list = $Tree->getTreePaths( [ "ancestor" => $id ] );

			                if( ! $Tree->action_handler( "save" , $tree_data, $this->update_tree_child ) )
			                {
			                    $succ = false;
			                    if( $this->Notices ) $this->Notices->set_notices( $Tree->Notices->get_operation_notice() );
			                }

			                if( $succ && $this->update_tree_child )
			                {
			                	$succ = $this->update_childs_parent( $tree_data, $child_list );
			                }
						}
					}
				break;
				case "delete":
					$id = $datas['id'];
					if( $id > 0 )
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] > 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							if( isset( $exist['status'] ) )
							{
								if( $exist['status'] == 1 )
								{
									$datas['status'] = 0;
									$result = $this->update( $id, $datas );
									if( false === $result )
									{
										$succ = false;
										if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
									}
								}
							}
							else
							{
								$result = $this->delete( $id );
								if( $result === false )
								{
									$succ = false;
									$this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
								}
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				case "delete-permanent":
					$deleted = false;
					$tree_data = [];
					$child_list = [];

					$id = $datas['id'];
					if( $id > 0 )
					{
						$exist = $this->select( $id );
						if( null === $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							if( $Tree )
							{
								$tree_data = [ "descendant" => $id, "ancestor" => ( $datas["parent"] == 0 )? "" : $datas["parent"] ];
		    					$child_list = $Tree->getTreePaths( [ "ancestor" => $id ] );
							}

							if( isset( $exist['status'] ) )
							{
								if( $this->one_step_delete || ( !$this->one_step_delete && $exist['status'] == 0 ) )
								{
									$datas['status'] = -1;
									if( $this->true_delete )
										$result = $this->delete( $id );
									else
										$result = $this->update( $id, $datas );
									if( $result === false )
									{
										$succ = false;
										if( $this->Notices ) $this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
									}
									else
									{
										if( $this->true_delete && method_exists( $this, 'delete_metas' ) ) $this->delete_metas( $id );
										$deleted = true;
									}
								}
							}
							else
							{
								$result = $this->delete( $id );
								if( $result === false )
								{
									$succ = false;
									$this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action );
								}
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}

					if( $succ && $deleted && $tree_data && $Tree )
	                {
	                    //Tree handling
		                if( ! $Tree->action_handler( "delete" , $tree_data, $this->update_tree_child ) )
		                {
		                    $succ = false;
		                    if( $this->Notices ) $this->Notices->set_notices( $Tree->Notices->get_operation_notice() );
		                }

		                if( $succ && $this->update_tree_child )
		                {
		                	$succ = $this->update_childs_parent( $tree_data, $child_list );
		                }
	                }
				break;
				case "restore":
					$id = $datas['id'];
					if ( $id > 0 )
					{
						$exist = $this->select( $id );
						if( ! $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						if( $succ && $this->useFlag && $exist['flag'] < 0 )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "prevent-action", "error", $this->className."|action_handler|".$action );
						}
						if( $succ ) 
						{
							if( isset( $exist['status'] ) && $exist['status'] == 0 )
							{
								$datas['status'] = 1;

								$result = $this->update( $id, $datas );
								if( false === $result )
								{
									$succ = false;
									if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
								}
							}
							else
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
				default:
					$id = $datas['id'];
					if ( $id > 0 )
					{
						$exist = $this->select( $id );
						if( ! $exist )
						{
							$succ = false;
							if( $this->Notices ) $this->Notices->set_notice( "invalid-record", "error", $this->className."|action_handler|".$action );
						}
						else
						{
							$result = $this->update( $id, $datas );
							if( false === $result )
							{
								$succ = false;
								if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|action_handler|".$action );
							}
						}
					}
					else 
					{
						$succ = false;
						if( $this->Notices ) $this->Notices->set_notice( "invalid-input", "error", $this->className."|action_handler|".$action );
					}

					if( $succ )
					{
						$outcome['id'] = $id;
					}
				break;
			}
		}

		if( $succ && $this->Notices && $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;
		
		$outcome['succ'] = $succ; 
		$outcome['data'] = $datas;
		$outcome['after'] = $this->select( $outcome['id'] );

		return $this->after_handler( $outcome, $action , $datas, $metas, $obj );
	}
	
	public function update_childs_parent( $data, $child_list )
	{
		$succ = true;
		
		if( ! $this->tables['tree'] ) return $succ;

		if( $data && $child_list && empty( $data['ancestor'] ) )
		{
			$Tree = new WCWH_TreeAction( $this->tables['tree'] );

		    foreach( $child_list as $child )
		    {
		    	$newParent = 0;
		    	$directParent = $Tree->getTreePaths( [ 'descendant'=>$child['descendant'], 'level'=>1 ] );

		        if( $directParent && $directParent['descendant'] != $data['descendant'] )
		        {
		        	$newParent = $directParent['ancestor'];
		        }

		        $result = $this->update( $child['descendant'], [ 'parent'=>$newParent ] );
		        if ( false === $result )
				{
					$succ = false;
					if( $this->Notices ) $this->Notices->set_notice( "update-fail", "error", $this->className."|update_childs_parent|".$action );
				}
		    }
		}

		return $succ;
	}

	public function get_infos( $filters = [], $order = [], $single = false, $args = [], $group = [], $limit = [] )
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
		
		$field = "a.* ";
		$table = "{$dbname}{$this->tables['main']} a ";
		$cond = "";
		$grp = "";
		$ord = "";
		$l = "";

		if( isset( $filters['id'] ) )
		{
			if( is_array( $filters['id'] ) )
				$cond.= "AND a.id IN ('" .implode( "','", $filters['id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id = %s ", $filters['id'] );
		}
		if( isset( $filters['not_id'] ) )
		{
			if( is_array( $filters['not_id'] ) )
				$cond.= "AND a.id NOT IN ('" .implode( "','", $filters['not_id'] ). "') ";
			else
				$cond.= $wpdb->prepare( "AND a.id != %s ", $filters['not_id'] );
		}
		if( isset( $filters['section_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.section_id = %s ", $filters['section_id'] );
		}
		if( isset( $filters['ref_id'] ) )
		{
			$cond.= $wpdb->prepare( "AND a.ref_id = %s ", $filters['ref_id'] );
		}
		if( isset( $filters['s'] ) )
		{
			$search = explode( ',', trim( $filters['s'] ) );    
			$search = array_merge( $search, explode( ' ', str_replace( ',', ' ', trim( $filters['s'] ) ) ) );
        	$search = array_filter( $search );

            $cond.= "AND ( ";

            $seg = array();
            foreach( $search as $kw )
            {
                $kw = trim( $kw );
                $cd = array();
                $cd[] = "a.section_id LIKE '%".$kw."%' ";
                $cd[] = "a.def_name LIKE '%".$kw."%' ";
                $cd[] = "a.sys_name LIKE '%".$kw."%' ";

                $seg[] = "( ".implode( "OR ", $cd ).") ";
            }
            $cond.= implode( "OR ", $seg );

            $cond.= ") ";

			unset( $filters['status'] );
		}

		$corder = array();
        //status
        if( ! isset( $filters['status'] ) || ( isset( $filters['status'] ) && strtolower( $filters['status'] ) == 'all' ) )
        {
            unset( $filters['status'] );
            $cond.= $wpdb->prepare( "AND a.status != %d ", -1 );

            $table.= "LEFT JOIN {$dbname}{$this->tables['status']} stat ON stat.status = a.status AND stat.type = 'default' ";
            $corder["stat.order"] = "DESC";
        }
        if( isset( $filters['status'] ) )
        {   
            $cond.= $wpdb->prepare( "AND a.status = %d ", $filters['status'] );
        }

		$isUse = ( $args && $args['usage'] )? true : false;
		if( $isUse )
		{
			$cond.= $wpdb->prepare( "AND a.status > %d ", 0 );
		}

		//group
		if( !empty( $group ) )
		{
	        $grp.= "GROUP BY ".implode( ", ", $group )." ";
		}

		//order
		if( empty( $order ) )
		{
			$order = [ 'a.id' => 'ASC' ];
			$order = array_merge( $corder, $order );
		}
        $o = array();
        foreach( $order as $order_by => $seq )
        {
            $o[] = "{$order_by} {$seq} ";
        }
        $ord.= "ORDER BY ".implode( ", ", $o )." ";

        //limit offset
        if( !empty( $limit ) )
        {
        	$l.= "LIMIT ".implode( ", ", $limit )." ";
        }

		$sql = "SELECT {$field} FROM {$table} WHERE 1 {$cond} {$grp} {$ord} {$l} ;";
		$results = $wpdb->get_results( $sql , ARRAY_A );

		if( $single && count( $results ) > 0 )
		{
			$results = $results[0];
		}
		
		return $results;
	}

	//-----------------------------------------------------------------------------

	public function set_dir( $dir_name )
	{
		$this->dir_name = $dir_name;
	}

	public function set_extension( $ext = [] )
	{
		$this->ext = $ext;
	}

	public function get_path( $dir = true )
	{
		return $this->dir_path.( $dir? "/".$this->dir_name : "" );
	}
	public function get_url( $dir = true )
	{
		return $this->dir_url.( $dir? "/".$this->dir_name : "" );
	}

	public function check_dir( $dir = '', $mk = true )
	{	
		$dir = ( $dir )? $dir : $this->get_path();
		if( ! is_dir( $dir ) ) 
		{
			if( $mk )
			{
				wp_mkdir_p( $dir );
				return true;
			} 

			return false;
		}
		else
			return true;
	}

	public function check_file_uploaded_name( $filename )
	{
	    return (bool) ((preg_match("`^[-0-9A-Z_\.]+$`i",$filename)) ? true : false);
	}

	public function check_file_uploaded_length( $filename )
	{
	    return (bool) ((mb_strlen($filename,"UTF-8") > 225) ? true : false);
	}

	public function check_extension( $ext = '' )
	{
		return (bool) ( in_array( strtolower($ext), $this->ext )? true : false );
	}

	public function restruct_files( $files )
	{
		$new_files = [];
		
		$new_files = $this->files_grouping( $files );
		
		/*foreach( $files['_form']['name']['attachments'] as $i => $val )
		{
			$file = [
				'name' => $val[0],
				'type' => $files['_form']['type']['attachments'][$i][0],
				'tmp_name' => $files['_form']['tmp_name']['attachments'][$i][0],
				'error' => $files['_form']['error']['attachments'][$i][0],
				'size' => $files['_form']['size']['attachments'][$i][0],
			];
			$new_files[] = $file;
		}*/

		return $new_files;
	}

	public function attachment_handler( $datas = [], $section = '', $id = 0, $replace = false )
	{
		$this->Notices->reset_operation_notice();
		$succ = true;

		if( ! $section || ! $id ) return false;

		$files = [];
		$exists = $this->get_infos( [ 'section_id'=>$section, 'ref_id'=>$id ], [], false, [ 'usage'=>1 ] );
		if( $exists )
		{
			foreach( $exists as $i => $file )
			{
				$files[ $file['id'] ] = $file;
			}
		}
		
		if( $datas && $replace )
		{
			unset( $files[ $datas[0] ] );
		}
		else
		{
			if( $datas && $files )
			{
				foreach( $datas as $i => $row )
				{
					if( $files[ $row['attach_id'] ] ) unset( $files[ $row['attach_id'] ] );
				}
			}
		}

		if( !empty( $files ) )
		{
			foreach( $files as $id => $file )
			{
				$dat['id'] = $id;
				$result = $this->action_handler( 'delete', $dat );
				if( ! $result['succ'] )
				{
					$succ = false;
					break;
				}
				else
				{
					unlink( $file['path'].'/'.$file['sys_name'] );
				}
			}
		}

		return $succ;
	}

	public function upload_files( $files, $section = '', $id = 0, $key = 'attachments' )
	{
		if( ! $section || ! $id ) return false;
		if( count( $files[$key]['name'] ) <= 0 ) return false;
		if( ! $this->check_dir() ) return false;

		$dir = $this->get_path();
		$user_id = get_current_user_id();

		if( $files ) $files = $this->restruct_files( $files[$key] );
		if( $files )
		{
			$uploaded = [];
			foreach( $files as $i => $file )
			{
				$file_info = pathinfo( $file['name'] );
				$ext = $file_info['extension'];
				$now = current_time( 'ymdHis' );

				if( ! $this->check_extension( $ext ) ) continue;

				$def_filename = $file['name'];
				if( ! empty( $this->ext_converse[ strtolower( $ext ) ] ) ) $ext = $this->ext_converse[ strtolower( $ext ) ];
				$sys_filename = "{$section}_{$id}_{$i}_{$now}.$ext";

				if( move_uploaded_file( $file['tmp_name'], $dir.'/'.$sys_filename ) )
				{
					$header = [
						'section_id' => $section,
						'ref_id' => $id,
						'def_name' => $def_filename,
						'sys_name' => $sys_filename,
						'path' => $dir,
						'created_by' => $user_id,
						'created_at' => $now,
						'lupdate_by' => $user_id,
						'lupdate_at' => $now,
					];

					$header = wp_parse_args( $header, $this->get_defaultFields() );
					$result = $this->action_handler( 'save', $header );
					if( ! $result['succ'] )
					{
						$succ = false;
					}
					else
					{
						$uploaded[] = $result['id'];
					}
				}
			}

			if( $uploaded ) return $uploaded;
		}

		return false;
	}

	//bulk upload used
	public function copy_files( $files, $section = '', $id = 0, $key = 'attachments' )
	{
		if( ! $section || ! $id ) return false;
		if( count( $files[$key]['name'] ) <= 0 ) return false;
		if( ! $this->check_dir() ) return false;

		$dir = $this->get_path();
		$user_id = get_current_user_id();

		if( $files ) $files = $this->restruct_files( $files[$key] );
		if( $files )
		{
			$uploaded = [];
			foreach( $files as $i => $file )
			{
				$file_info = pathinfo( $file['name'] );
				$ext = $file_info['extension'];
				$now = current_time( 'ymdHis' );

				if( ! $this->check_extension( $ext ) ) continue;

				$def_filename = $file['name'];
				$sys_filename = "{$section}_{$id}_{$i}_{$now}.$ext";

				if( copy( $file['tmp_name'], $dir.'/'.$sys_filename ) )
				{
					$header = [
						'section_id' => $section,
						'ref_id' => $id,
						'def_name' => $def_filename,
						'sys_name' => $sys_filename,
						'path' => $dir,
						'created_by' => $user_id,
						'created_at' => $now,
						'lupdate_by' => $user_id,
						'lupdate_at' => $now,
					];

					$header = wp_parse_args( $header, $this->get_defaultFields() );
					$result = $this->action_handler( 'save', $header );
					if( ! $result['succ'] )
					{
						$succ = false;
					}
					else
					{
						$uploaded[] = $result['id'];
					}
				}
			}

			if( $uploaded ) return $uploaded;
		}

		return false;
	}

	public function render_attachment( $id = 0, $args = [], $obj = [] )
	{
		if( ! $id ) return false;

		$token = apply_filters( 'wcwh_generate_token', $this->section_id );
		$html = "";

		$file = ( $obj )? $obj : $this->get_infos( [ 'id'=>$id ], [], true );
		if( $file )
		{
			$params = [
				'action' => $this->refs['id'].'_view_attachment',
				'id' => $id,
				'token' => $token,
			];
			$src = WCWH_AJAX_URL.add_query_arg( $params, '' );
			if( !empty( $file['api_url'] ) )
			{
				unset( $params['id'] );
				$params['source'] = urlencode( $file['api_url']."wp-content/uploads/{$this->dir_name}/{$file['sys_name']}" );
				$params['fn'] = $file['def_name'];
				$src = WCWH_AJAX_URL.add_query_arg( $params, '' );
			}

			$file_info = pathinfo( $file['def_name'] );
			$ext = $file_info['extension'];

			if( $args['photo'] )
			{
				if( in_array( strtolower($ext), [ 'jpg', 'jpeg', 'jfif', 'svg', 'png', 'gif' ] ) )
				{
					$att = "";
					if( $args['photo'] ) $att.= "style='max-width: {$args['photo']};'";
					$img = "<img src='{$src}' {$att} />";
					$html = "<a href='{$src}' target='blank'>{$img}</a>";
				}
			}elseif($args['print'])
			{
				$html = $src;
			}
			else
			{
				$html.= "<a href='{$src}' target='blank'>{$file['def_name']}</a>";
			}
		}
		
		return $html;
	}

	public function view_attachment( $id = 0, $datas = [] )
	{
		if( $id )
		{
			$attach = $this->get_infos( [ 'id'=>$id ], [], true );
			if( $attach )
			{
				$filename = $attach['def_name'];
				$file_info = pathinfo( $filename );
				$ext = $file_info['extension'];
				$file = $attach['path']."/".$attach['sys_name'];
			}
		}
		else if( !$id && !empty( $datas['source'] ) )
		{
			$datas['source'] = urldecode( $datas['source'] );
			
			$filename = basename( $datas['source'] );
			$file_info = pathinfo( $filename );
			$ext = $file_info['extension'];
			$file = $datas['source'];

			if( $datas['fn'] ) $filename = urldecode( $datas['fn'] );

			/*$sysdir = sys_get_temp_dir();
			if( file_put_contents( $sysdir.'/'.$filename, file_get_contents( $datas['source'] ) ) )
			{
				$file = $sysdir.'/'.$filename;
			} */
		}

		if( $file && $ext )
		{
			switch( strtolower($ext) )
			{
				case 'pdf':
					header("Content-type: application/pdf");
					header('Content-Disposition: inline; filename="'.$filename.'"');
					//header("Content-Length: " . filesize($file));
				break;
				case 'jpg':
				case 'jpeg':
				case 'jfif':
					header("Content-type: image/jpeg");
					header('Content-Disposition: inline; filename="'.$filename.'"');
				break;
				case 'svg':
					header("Content-type: image/svg+xml");
					header('Content-Disposition: inline; filename="'.$filename.'"');
				break;
				case 'png':
				case 'gif':
					header("Content-type: image/".$ext);
					header('Content-Disposition: inline; filename="'.$filename.'"');
				break;
				default:
					header('Content-Disposition: attachment;filename="'.$filename.'"');
				break;
			}
			
			readfile($file);
			exit;
		}
	}

}

if( ! function_exists( 'wcwh_render_attachment' ) )
{
	function wcwh_render_attachment( $id = 0, $args = [], $obj = [] )
	{
		$Inst = new WCWH_Files();

		return $Inst->render_attachment( $id, $args, $obj );
	}
}

}