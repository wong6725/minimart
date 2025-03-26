<?php
//Written by trainee-Steven based on customerCredit.php for generating query report
if ( !defined( "ABSPATH" ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_QueryReport" ) ) 
{
	
class WCWH_QueryReport extends WCWH_CRUD_Controller
{	
	public $Notices;
	public $className = "QueryReport";

	public $tplName = array(
		'export' => 'exportQueryReport',
	);
	
	protected $tables = array();

	public $filters = array();

	public function __construct()
	{
		parent::__construct();

		$this->Notices = new WCWH_Notices();
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

			$action = strtolower( $action );
        	switch ( $action )
        	{
				case "export":
					$params = [];

					if( !empty( $datas['rQuery'] ) ) $params['rQuery'] = $datas['rQuery'];
					//$this->export_data_handler( $params );
					$succ = $this->export_data( $datas, $params );
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

		$default_column['title'] = [];

		$default_column['default'] = [];

		return $default_column;
	}

	protected function export_data_handler( $params = array() )
	{
		$order = [];

		$rQuery = $params['rQuery'];
		$rQuery = $rQuery? $rQuery : get_transient( get_current_user_id().$this->className );

		return $this->get_query_report( $rQuery );
	}


	/**
	 *	View
	 *	---------------------------------------------------------------------------------------------------
	 */
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
					data-title="<?php echo $actions['export'] ?> Query Report" data-modal="wcwhModalImEx" 
					data-actions="close|export" 
					title="<?php echo $actions['export'] ?> Query Report"
				>
					<i class="fa fa-download" aria-hidden="true"></i>
				</button>
			<?php
			break;
		}
	}

	public function export_form()
	{
		$action_id = 'query_report';
		$args = array(
			'hook'		=> $action_id.'_submission',
			'action'	=> 'export',
			'token'		=> apply_filters( 'wcwh_generate_token', $action_id ),
			'tplName'	=> $this->tplName['export'],
			'section'	=> $action_id,
		);

		if( $this->filters ) $args['filters'] = $this->filters;
		do_action( 'wcwh_templating', 'report/export-query-report.php', $this->tplName['export'], $args );
	}

	public function query_report( $filters = array(), $order = array() )
	{
		$action_id = 'query_report';
		$token = apply_filters( 'wcwh_generate_token', $action_id );
		?>
		<form class="listing-form <?php echo $action_id; ?>-listing-form" action="" method="post" id="edit-<?php echo $action_id; ?>" data-section="<?php echo $action_id; ?>-listing-form" 
			data-token="<?php echo $token; ?>" data-hook="<?php echo $action_id; ?>"
		>
			<div class="row">
		        <div class="col-md-12">
		        	<label class="" for="flag">Raw Query</label><br>
		            <?php
		            	$rQuery = '';
		            	if( !empty($filters['rQuery']) ){
		            		$filters['rQuery'] = str_replace("\\", "", $filters['rQuery']);
		            		$this->filters = $filters;
		            		$rQuery = $filters['rQuery'];
		            	}
		                wcwh_form_field( 'filter[rQuery]', 
		                    [ 'id'=>'rQuery', 'type'=>'textarea', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
		                    $rQuery, $view 
		                );
		            ?>
		        </div>
	    	</div>
		<?php
			include_once( WCWH_DIR."/includes/listing.php" );
			$Inst = new WCWH_Listing();
			$Inst->advSearch = array( 'isOn'=>1 );
			$Inst->per_page_limit = 1000;

			$datas = $this->get_query_report( $rQuery );
			$datas = ( $datas )? $datas : array();
			if( $datas ) set_transient( get_current_user_id().$this->className, $rQuery );

			$Inst->search_box( 'Submit', 's' );
			$keys = array();
			if( !empty($datas) ){
				$keys = array_keys( $datas[0] );
			}
			echo $Inst->get_listing( $keys, $datas );		
		?>
		</form>
		<?php
	}
	/**
	 *	Logic
	 *	---------------------------------------------------------------------------------------------------
	 */
	public function get_query_report( $rQuery )
	{
		global $wcwh;
		$wpdb = $this->db_wpdb;

		$sql = trim( $rQuery, ' /*!`@#$%^&-=+_<>?\\' );//Trim space and special characters
		$sql = str_replace("\\", "", $sql);
		$results = $wpdb->get_results( $sql , ARRAY_A );
		
		return $results;
	}
	
	
} //class

}