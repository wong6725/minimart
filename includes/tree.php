<?php
if ( !defined("ABSPATH") )
    exit;
	
if ( !class_exists( "WCWH_TreeAction" ) )
{

class WCWH_TreeAction extends WCWH_CRUD_Controller
{
    protected $count = 0;

    public $Notices;
	public $className = "TreeAction";

    public function __construct( $tbl = "" )
    {
    	parent::__construct();

		$this->Notices = new WCWH_Notices();

		$this->set_table( $tbl );
    }

    public function __destruct()
    {
        unset($this->Notices);
        unset($this->tbl);
    }
   	
    public function set_table( $tbl = "" )
    {
    	if( ! $tbl ) return;

        $this->tbl = $tbl;
    }
    

    /*************************
    * Tree Action
    **************************/
    /*
    *   get Tree Path item
    *   eg. get all ancestor for child #5, cond= [ 'descendant'=>5 ]
    *       get all descendant for parent #2, cond= [ 'ancestor'=>2 ]
    *       get immediate descendant for parent #2, cond= [ 'ancestor'=>2, 'level'=> 1 ]
    *       get immediate ancestor for child #5, cond= [ 'descendant'=>5, 'level'=> 1 ]
    */
    public function getTreePaths( $cond, $order = array() )
    {
    	$succ = true;

        if( ! isset( $this->tbl ) )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|getTreePaths" );
        }

        $result = array();
        if( $succ )
        {
        	global $wpdb;
	        $prefix = $this->get_prefix();

	        $fld = "a.* ";
	        $tbl = "{$this->tbl} a ";
	        $cd = "";
	        $ord = "";

            //order
            $order = !empty( $order )? $order : [ 'a.level' => 'ASC', 'a.ancestor' => 'ASC' ];
            $o = array();
            foreach( $order as $order_by => $seq )
            {
                $o[] = "{$order_by} {$seq} ";
            }
            $ord.= "ORDER BY ".implode( ", ", $o )." ";

	        if( ! empty( $cond['descendant'] ) )
	        {
	            $cd .= $wpdb->prepare( "AND a.descendant = %d ", $cond['descendant'] );
	        } 
	        if( ! empty( $cond['ancestor'] ) )
	        {
	            $cd .= $wpdb->prepare( "AND a.ancestor = %d ", $cond['ancestor'] );
	        } 
	        if( ! empty( $cond['level'] ) )
	        {
	            $cd .= $wpdb->prepare( "AND a.level = %d ", $cond['level'] );
	        } 

	        $sql = "SELECT {$fld} FROM {$tbl} WHERE 1 {$cd} {$ord} ;";
	        $result = $wpdb->get_results( $sql , ARRAY_A );
        }

        if( ! empty( $cond['level'] ) && count( $result ) < 2 )
        {
            $result = $result[0];
        } 
        
        return $result;
    }

    /**
     *  Insert Tree Node / Nodes
     */
    public function insertDescendant( $data )
    {
    	$succ = true;
        
        if( empty( $data['descendant'] ) || ! isset( $this->tbl ) )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|insertDescendant" );
        }

        if( $succ )
        {
        	global $wpdb;
	        $prefix = $this->get_prefix();
	        $table = $this->tbl;

	        $query1 = $wpdb->prepare( 
	        	"SELECT %d as ancestor, %d as descendant, 0 as level ", 
	        	$data['descendant'], 
	        	$data['descendant'] 
	        );
	        $query2 = $wpdb->prepare( 
	        	"SELECT a.ancestor, %d as descendant, @rownum := @rownum + 1 AS level FROM {$table} a , (SELECT @rownum := 0) r WHERE a.descendant = %d ", 
	        	$data['descendant'], 
	        	$data['ancestor']
	        );

	        $sql = "INSERT INTO {$table} ( ancestor, descendant, level ) {$query1} UNION ALL {$query2} ;";
	        $result = $wpdb->query( $sql );

        	if ( false === $result )
        	{
            	$succ = false;
            	$this->Notices->set_notice( "create-fail", "error", $this->className."|insertDescendant" );
        	}
        }
        
        return $succ;
    }

    /**
     *  Remove Node and all relation Nodes,
     *  Should not allow remove if has descendant
     */
    public function deleteDescendant( $cond )
    {
    	$succ = true;

        if( ( empty( $cond['ancestor'] ) && empty( $cond['descendant'] ) ) || ! isset( $this->tbl ) )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|deleteDescendant" );
        } 

        if( $succ )
        {
        	global $wpdb;
	        $prefix = $this->get_prefix();
	        $table = $this->tbl;

	        $cd = "";
	        if( ! empty( $cond['ancestor'] ) )
	        {
	            if( is_array( $cond['ancestor'] ) )
	        	{
	        		$cd .= "AND ancestor IN ('" .implode( "','", $cond['ancestor'] ). "') ";
	        	}
	        	else
	        	{
	        		$cd .= $wpdb->prepare( "AND ancestor = %d ", $cond['ancestor'] );
	        	}
	        } 
	        if( ! empty( $cond['descendant'] ) )
	        {
	        	if( is_array( $cond['descendant'] ) )
	        	{
	        		$cd .= "AND descendant IN ('" .implode( "','", $cond['descendant'] ). "') ";
	        	}
	        	else
	        	{
	        		$cd .= $wpdb->prepare( "AND descendant = %d ", $cond['descendant'] );
	        	}
	        } 
	        
	        $sql = "DELETE FROM {$table} WHERE 1 {$cd} ;";
	        $result = $wpdb->query( $sql );
            
        	if( false === $result )
        	{
            	$succ = false;
            	$this->Notices->set_notice( "delete-fail", "error", $this->className."|deleteDescendant" );
        	}
        }

        return $succ;
    }

    /**
     *  Tree Path Action Handle
     *  $update_child : delete ancestor also delete all descendant if needed.
     */
    public function action_handler( $action , $data , $update_child = false )
    {
    	$this->Notices->reset_operation_notice();
        $succ = true;

        $action = strtolower( $action );
        switch( $action )
        {
            case 'save':
            case 'update':
                $exists = false;
                $ancestor = isset( $data['ancestor'] ) ? $data['ancestor'] : "";
                $prev_ancestor = "";
                $chkExists = $this->getTreePaths( [ 'descendant'=> $data['descendant'] ] );

                if( isset( $chkExists ) && $chkExists && count( $chkExists ) > 0 )
                {
                    $exists = true;
                    foreach( $chkExists as $item )
                    {
                        if( $item['level'] == 1 )
                        {
                            $prev_ancestor = $item['ancestor'];
                            break;
                        }
                    }
                }

                if( $exists )   //If Parent Node Exists
                {   
                    if( $ancestor != $prev_ancestor )
                    {
                        if( $prev_ancestor != "" && $ancestor != "" ) //Change parent
                        {
                            $this->ancestorChanged( $data, $update_child );
                        }
                        else if( $prev_ancestor != "" && $ancestor == "" )  //Change & no parent
                        {
                            $this->ancestorChanged( $data, $update_child );
                        }
                        else    //Add New Node with prespect to Ancestor
                        {
                            if( $exists )   //Reset previous Tree Nodes to update latest Tree Nodes
                            {
                                $this->deleteDescendant( ["descendant" => $data["descendant"] ] );
                            }
                            $this->insertDescendant( $data );
                            $this->descendantReassign( $data );
                        }
                    }
                }
                else    //Add New Node, if new and no ancestor
                {   
                    $this->insertDescendant( $data );
                }
            break;
            case 'delete':
                $this->ancestorRemoved( $data, $update_child, true );
            break;
            default:
                $succ = false;
                $this->Notices->set_notice( "invalid-action", "error", $this->className."|action_handler|".$action );
            break;
        }
        
        if( $this->Notices->count_notice( "error" ) > 0 )
            $succ = false;

        return $succ;
    }

    /**
     *  Change of Ancestor & correcting Descendant Nodes path
     */
    public function ancestorChanged( $data, $update_child = false )
    {
        $succ = true;

        if( ! $data )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|action_handler|".$action );
        }

        //Reassign Current descendant parent
        $this->deleteDescendant( [ "descendant" => $data["descendant"] ] );
        $this->insertDescendant( $data );

        //Reassign descendants related to Current
        $this->descendantReassign( $data );
    }

    /**
     *  Correcting Descendant Nodes path
     */
    public function descendantReassign( $data )
    {
        $succ = true;
        $arrayExists = array();

        if( ! $data )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|action_handler|".$action );
        }

        $ChildNodes = $this->getTreePaths( [ 'ancestor'=> $data['descendant'] ] );
        if( $ChildNodes )
        {
            foreach( $ChildNodes as $item )
            {
                $arrayExists[ $item['level'] ][] = $item;
            }
        }
        
        if( $arrayExists )
        {
            foreach( $arrayExists as $lvl => $childExist )
            {
                foreach( $childExist as $item )
                {
                    $directParent = $this->getTreePaths( [ 'descendant'=>$item['descendant'], 'level'=>1 ] );
                    if( $directParent )
                    {
                        $this->deleteDescendant( ["descendant" => $item['descendant'] ] );
                        $this->insertDescendant( ["descendant" => $item['descendant'] , "ancestor" => $directParent['ancestor'] ] );
                    } 
                }
            }
        }
    }

    /**
     *  Remove of Ancestor & correcting Descendant Nodes path
     */
    public function ancestorRemoved( $data, $update_child = false )
    {
        $succ = true;
        $arrayExists = array();

        if( ! $data || empty( $data['descendant'] ) )
        {
            $succ = false;
            $this->Notices->set_notice( "missing-parameter", "error", $this->className."|action_handler|".$action );
        }

        if( $succ )
        {
            if( ! $update_child )
            {
                $directChild = $this->getTreePaths( ['ancestor'=>$data['descendant'], 'level'=>1 ] );
                if( isset( $directChild ) && count( $directChild ) > 0 )    //Not allow Delete if Descendant Nodes exists
                {
                    $succ = false;
                    $this->Notices->set_notice( "delete-fail", "error", $this->className."|action_handler|".$action."|Child/Descendant exists" );
                }
				else    //Delete Node if no Descendant exists
				{
					$this->deleteDescendant( ["descendant" => $data["descendant"] ,"ancestor" => $data["descendant"] ] );
				}
            }
            else
            {
                //Get Descendant related to Current
                $ChildNodes = $this->getTreePaths( [ 'ancestor'=> $data['descendant'] ] );
                
                if( $ChildNodes )
                {
                    foreach( $ChildNodes as $item )
                    {
                        $arrayExists[ $item['descendant'] ] = $item;
                    }

                    $prev_ancestor = 0;
                    foreach( $arrayExists as $descendant => $child )
                    {
                        $directParent = $this->getTreePaths( [ 'descendant'=>$descendant, 'level'=>1 ] );
						
						if( isset( $directParent ) && count( $directParent ) > 0 )    //
						{
                            $this->deleteDescendant( [ 'descendant'=>$descendant ] );

                            if( $child['level'] > 0 )
                            {
                                $ancestor = ( $directParent['ancestor'] == $data['descendant'] || $directParent['descendant'] == $data['descendant'] )? $prev_ancestor : $directParent['ancestor'];
                                $this->insertDescendant( 
                                    [ 
                                        'descendant'=>$descendant, 
                                        'ancestor'=> $ancestor,
                                    ] 
                                );
                            }
                            
                            if( $child['level'] == 0 ) $prev_ancestor = $directParent['ancestor'];
						}
                        else    //Delete Node if no Descendant exists
						{
							$this->deleteDescendant( [ "descendant" => $data["descendant"], "ancestor" => $data["descendant"] ] );
						}
                    }
                }
            }
        }
    }
}

}