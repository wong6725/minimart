<?php 
	$prefixName = $args['option_name'];

	$datas = $args['datas'];
?>
<form id="actionSetting" class="" action="<?php echo admin_url(sprintf(basename($_SERVER['REQUEST_URI']))); ?>" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate >
	<div class='form-rows-group'>
    	<h5>Settings</h5>
    <?php //if( current_user_cans( ['wh_admin_support'] ) ): ?>	
    	<div class="form-row">
	        <div class="col form-group">
	        	<?php 
	                wcwh_form_field( $prefixName.'[company_name]', 
	                    [ 'id'=>'', 'label'=>'Company Name', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
	                    $datas['company_name'], $view 
	                ); 
	            ?>
	        </div>
	        <div class="col form-group">
	        	<?php 
	                wcwh_form_field( $prefixName.'[company_code]', 
	                    [ 'id'=>'', 'label'=>'Company Code', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
	                    $datas['company_code'], $view 
	                ); 
	            ?>
	        </div>
	    </div>
		<div class="form-row">
	        <div class="col form-group">
	        	<?php 
					$options = [ ''=>'Free Text, press Enter for Others', 'IN'=>'IN-Invoice', 'DA'=>'DA-Debit Advise', 'CA'=>'CA-Credit Advise', 'DN'=>'DN-Debit Note', 'CN'=>'CN-Credit Note' ];
	                $keys = array_filter( array_keys($options) );
					if( !in_array( $datas['document_type'], $keys ) ) $options[ $datas['document_type'] ] = $datas['document_type'];
					wcwh_form_field( $prefixName.'[document_type]', 
	                    [ 'id'=>'', 'type'=>'select', 'label'=>'Document Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2Tag'],
							'options'=> $options
						], 
	                    $datas['document_type'], $view 
	                ); 
	            ?>
	        </div>
	        <div class="col form-group">
	        </div>
	    </div>
	<?php //endif; ?>
	</div>

	<?php
		$filter = [ 'status'=>1, 'indication'=>1 ];
		if( $args['seller'] ) $filter['seller'] = $args['seller'];
		$wh = apply_filters( 'wcwh_get_warehouse', $filter, [], true, [ 'meta'=>[ 'estate_customer', 'foodboard_customer' ] ] );
		if( $wh )
		{
			$Customer = is_json( $wh['estate_customer'] )? json_decode( stripslashes( $wh['estate_customer'] ), true ) : $wh['estate_customer'];
			$Foodboard = is_json( $wh['foodboard_customer'] )? json_decode( stripslashes( $wh['foodboard_customer'] ), true ) : $wh['foodboard_customer'];
			$Customer = array_merge( $Customer, $Foodboard );
		}

		$filters = [];
		if( $args['seller'] ) $filters['seller'] = $args['seller'];
		if( $Customer ) $filters['id'] = $Customer;
		$options = options_data( apply_filters( 'wcwh_get_customer', $filters, [], false, [] ), 'id', [ 'code', 'uid', 'name' ], '' );
	?>

	<script type="text/template" id="rowComRelTPL" class="hidden_tpl">
<div class="row element_row" data-seq="{i}" >
	<div class="col-md-2">
	    <div class="form-row">
		    <div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping][{i}][customer_name]', 
		                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Customer Name', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                '', $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-row">
		   	<div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping][{i}][customer_code]', 
		                [ 'id'=>'', 'label'=>'Customer Code', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                '', $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-3">
	    <div class="form-row">
		    <div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping][{i}][customer_desc]', 
		                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                '', $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-row">
		   	<div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping][{i}][customer_mapping][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'Customer Related', 'required'=>false, 'attrs'=>[], 
		                	'class'=>['select2','modalSelect'], 'options'=> $options, 'multiple'=>1 ], 
		                '', $view 
		            ); 
		        ?>
		   	</div>
		</div>
   </div>
  
    <div class="col-md-1">
        <a class="btn btn-sm btn-none-delete remove-row" data-remove=".element_row" title="Remove"><i class="fa fa-trash-alt"></i></a>
    </div>
</div>
	</script>

	<div class='form-rows-group'>
    	<h5>Define Customer & Relationship</h5>
    	<a class="btn btn-sm btn-primary dynamic-element" data-tpl="rowComRelTPL" data-target="#intercom_rel" data-children="div.element_row" data-serial2="">
                Add More Relationship +
        </a>

        <div id="intercom_rel">
        <?php
        	$i = 0;
        	if( $datas['mapping'] )
        		foreach( $datas['mapping'] as $j => $mapping )
        		{
        		?>

<div class="row element_row" data-seq="<?php echo $i ?>" >
	<div class="col-md-2">
	    <div class="form-row">
		    <div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping]['.$i.'][customer_name]', 
		                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Customer Name', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                $mapping['customer_name'], $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-2">
		<div class="form-row">
		   	<div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping]['.$i.'][customer_code]', 
		                [ 'id'=>'', 'label'=>'Customer Code', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                $mapping['customer_code'], $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-3">
	    <div class="form-row">
		    <div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping]['.$i.'][customer_desc]', 
		                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
		                $mapping['customer_desc'], $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-row">
		   	<div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[mapping]['.$i.'][customer_mapping][]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'Customer Related', 'required'=>false, 'attrs'=>[], 
		                	'class'=>['select2','modalSelect'], 'options'=> $options, 'multiple'=>1 ], 
		                $mapping['customer_mapping'], $view 
		            ); 
		        ?>
		   	</div>
		</div>
   </div>
  
    <div class="col-md-1">
        <a class="btn btn-sm btn-none-delete remove-row" data-remove=".element_row" title="Remove"><i class="fa fa-trash-alt"></i></a>
    </div>
</div>

        		<?php
        			$i++;
        		}
        ?>
        </div>
    </div>

	<?php  
		wcwh_form_field( $prefixName.'[last_update]', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        current_time( 'mysql' ), $view 
	    ); 
	    wcwh_form_field( 'token', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        $args['token'], $view 
	    ); 
	    wcwh_form_field( 'seller', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        $args['seller'], $view 
	    ); 
	    wcwh_form_field( 'action_id', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        $args['action_id'], $view 
	    ); 
	?>

	<?php submit_button(); ?>

</form>