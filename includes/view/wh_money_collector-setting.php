<?php 
	$prefixName = $args['option_name'];

	$datas = $args['datas'];
	//pd($datas);
?>
<form id="actionSetting" class="" action="<?php echo admin_url(sprintf(basename($_SERVER['REQUEST_URI']))); ?>" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate >
	<div class='form-rows-group'>
    	<h5>Reminder Settings</h5>
    	<div class="form-row">
    		<div>
		        <?php 
		            wcwh_form_field( $prefixName.'[use_reminder]', 
		                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Use Email Reminder? ', 'required'=>false, 'attrs'=>[] ], 
		                $datas['use_reminder'], $view 
		            ); 
		        ?>
	        	</div>
    	</div>
    	<div class="form-row">
	        <div class="col form-group">
	        	<?php 
	                wcwh_form_field( $prefixName.'[emails]', 
	                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Send reminder to Email(s)', 'required'=>false, 'attrs'=>[], 'class'=>[], 'placeholder'=>'Eg. a@suburtiasa.com,b@suburtiasa.com', 'description'=>'Apply multiple emails by comma separated' ], 
	                    $datas['emails'], $view 
	                ); 
	            ?>
	        </div>
	    </div>
	</div>

	<?php
	    $warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [ 'id'=>'ASC' ], false, [ 'meta'=>['dbname', 'has_pos'] ] );

	    if( $warehouses ):
	    	foreach( $warehouses as $wh ):
	    		if( empty( $wh['dbname'] ) ) continue;

	    	?>
	    		<div class='form-rows-group'>
	    			<h5><?php echo $wh['code']." ; ".$wh['name'] ?></h5>
	    			<div class="form-row">
	        			<div class="col form-group flex-row flex-align-center">
	        			<?php 
				            wcwh_form_field( $prefixName.'['.$wh['code'].'][needed]', 
				                [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Needed? ', 'required'=>false, 'attrs'=>[] ], 
				                $datas[ $wh['code'] ]['needed'], $view 
				            ); 
				        ?>
	        			</div>
	        			<div class="col form-group">
				        	<?php 
				                wcwh_form_field( $prefixName.'['.$wh['code'].'][emails]', 
				                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Send reminder to Email(s)', 'required'=>false, 'attrs'=>[], 'class'=>[], 'placeholder'=>'Eg. a@suburtiasa.com,b@suburtiasa.com', 'description'=>'Apply multiple emails by comma separated' ], 
				                    $datas[ $wh['code'] ]['emails'], $view 
				                ); 
				            ?>
				        </div>
	        		</div>

	        		<div class="form-row">
	        			<div class="col form-group">
	        			<?php 
				            wcwh_form_field( $prefixName.'['.$wh['code'].'][period]', 
				                [ 'id'=>'', 'type'=>'number', 'label'=>'Reminder Period (Days)', 'required'=>false, 'class'=>['numonly'] ], 
				                $datas[ $wh['code'] ]['period'], $view 
				            ); 
				        ?>
	        			</div>
	        			<div class="col form-group">
	        			<?php 
				            wcwh_form_field( $prefixName.'['.$wh['code'].'][amount]', 
				                [ 'id'=>'', 'type'=>'number', 'label'=>'Reminder Amount (RM)', 'required'=>false, 'class'=>['numonly'] ], 
				                $datas[ $wh['code'] ]['amount'], $view 
				            ); 
				        ?>
	        			</div>
	        		</div>
	        	</div>
	    	<?php
	    	endforeach;
	    endif;
	?>

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