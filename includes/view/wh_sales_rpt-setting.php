<?php 
	$prefixName = $args['option_name'];

	$datas = $args['datas'];
?>
<form id="actionSetting" class="" action="<?php echo admin_url(sprintf(basename($_SERVER['REQUEST_URI']))); ?>" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate >
	
<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>	
	<div class='form-rows-group'>
    	<h5>Minimart E-Invoice Setting</h5>
    	<div class="form-row">
	        <div class="col form-group">
	        <?php 
	        	$clients = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ] , '' );

	            wcwh_form_field( $prefixName.'[minimart_einvoice][client][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Minimart eInvoice Client', 'required'=>false, 'attrs'=>[], 
	                	'class'=>['select2','modalSelect'], 'options'=> $clients, 'multiple'=>1 ],
	                $datas['minimart_einvoice']['client'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group">
	        <?php 
	        	$items = options_data( apply_filters( 'wcwh_get_item', [], [], false, [ 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );

	            wcwh_form_field( $prefixName.'[minimart_einvoice][item_calc_profit][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Minimart eInvoice Item Amt by Profit', 'required'=>false, 'attrs'=>[], 
	                	'class'=>['select2','modalSelect'], 'options'=> $items, 'multiple'=>1 ],
	                $datas['minimart_einvoice']['item_calc_profit'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>

	<div class='form-rows-group'>
    	<h5>Direct Sales E-Invoice Setting</h5>
    	<div class="form-row">
	        <div class="col form-group">
	        <?php 
	            wcwh_form_field( $prefixName.'[direct_sales_einvoice][exclude_client][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Direct Sales eInvoice Exclude Client', 'required'=>false, 'attrs'=>[], 
	                	'class'=>['select2', 'modalSelect'], 'options'=> $clients, 'multiple'=>1 ], 
	                $datas['direct_sales_einvoice']['exclude_client'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group">
	        <?php 
	            wcwh_form_field( $prefixName.'[direct_sales_einvoice][item_calc_profit][]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Direct Sales eInvoice Item Amt by Profit', 'required'=>false, 'attrs'=>[], 
	                	'class'=>['select2','modalSelect'], 'options'=> $items, 'multiple'=>1 ],
	                $datas['direct_sales_einvoice']['item_calc_profit'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	</div>
<?php endif; ?>

<!-- unimart_einvoice -->
	<div class='form-rows-group'>
		<h5>Unimart E-Invoice Setting</h5>
	
	<?php
		$filter = [];
		if( $datas['unimart_einvoice']['warehouse'] ) $filter['seller'] = $datas['unimart_einvoice']['warehouse'];
		$acc_types = options_data( apply_filters( 'wcwh_get_account_type', $filter, [], false, [] ), 'code', [ 'code' ], '' );

		if( empty( $clients ) )
			$clients = options_data( apply_filters( 'wcwh_get_client', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'code', 'name' ], '' );
	?>

	<?php if( current_user_cans( ['wh_admin_support'] ) ): ?>	
	    <div class="form-row">
	        <div class="col form-group">
	        <?php
	        	$options = options_data( apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [], false, [] ), 'id', [ 'code','name' ], '' );
                
	            wcwh_form_field( $prefixName.'[unimart_einvoice][warehouse]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'POS Warehouse', 'required'=>false, 'attrs'=>[], 'class'=>[ 'select2','modalSelect' ],
	                    'options'=> $options,
	                ], 
	                $datas['unimart_einvoice']['warehouse'], $view 
	            ); 
	        ?>
	        </div>
	        <div class="col form-group">
	        <?php 
	            wcwh_form_field( $prefixName.'[unimart_einvoice][client]', 
	                [ 'id'=>'', 'type'=>'select', 'label'=>'Unimart eInvoice Client', 'required'=>false, 'attrs'=>[], 
	                	'class'=>['select2', 'modalSelect'], 'options'=> $clients ], 
	                $datas['unimart_einvoice']['client'], $view 
	            ); 
	        ?>
	        </div>
	    </div>
	    <hr>
	<?php endif; ?>

    	<h5>Unimart Report Relationship</h5>

        <div id="unimart_rel">
        <?php
        	$i = 0;
        	if( !empty( $acc_types ) && !empty( $datas['unimart_einvoice']['mapping'] ) )
        	{
        		$rel = [];
        		foreach( $datas['unimart_einvoice']['mapping'] as $j => $mapping )
        		{
        			$rel[ $mapping['acc_type'] ] = $mapping['client'];
        		}

        		foreach( $acc_types as $code => $title )
        		{
        		?>

<div class="row element_row" data-seq="<?php echo $i ?>" >
	<div class="col-md-2">
	    <div class="form-row">
		    <div class="col form-group">
		    	<?php 
		    		echo $code;

		            wcwh_form_field( $prefixName.'[unimart_einvoice][mapping]['.$i.'][acc_type]', 
		                [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 
		                	'class'=>[] ], 
		                $code, $view 
		            ); 
		        ?>
		   	</div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-row">
		   	<div class="col form-group">
		    	<?php 
		            wcwh_form_field( $prefixName.'[unimart_einvoice][mapping]['.$i.'][client]', 
		                [ 'id'=>'', 'type'=>'select', 'label'=>'Customer Related', 'required'=>false, 'attrs'=>[], 
		                	'class'=>['select2','modalSelect'], 'options'=> $clients, 'multiple'=>1 ], 
		                $rel[ $code ], $view 
		            ); 
		        ?>
		   	</div>
		</div>
   </div>
   
</div>

        		<?php
        			$i++;
        		}
        	}
        ?>
        </div>
    </div>
<!-- unimart_einvoice -->

	<?php  
		wcwh_form_field( $prefixName.'[last_update]', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        current_time( 'mysql' ), $view 
	    ); 
	    wcwh_form_field( 'token', 
	        [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
	        $args['token'], $view 
	    ); 
	?>

	<?php submit_button(); ?>

</form>