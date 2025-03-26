<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_item_expiry';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <?php if( $args['scheme'] == "item" ): //not use ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filters = [];
                if( $this->seller ) $filters['seller'] = $this->seller;
                $options = options_data( apply_filters( 'wcwh_get_item', $filters, [], false, [ 'uom'=>1, 'usage'=>0, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name', 'status_name' ], '' );
                
                wcwh_form_field( $prefixName.'[ref_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Item', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['ref_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[scheme]', 
                    [ 'id'=>'scheme', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                    $args['scheme'], $view 
                ); 
            ?>
        </div>
    </div>
   <div class="form-row">
        <div class="col form-group">
            <?php 
                $options = ['day' => 'Day', 'month' => 'Month', 'year' => 'Year'];
                $periods = ['/\bday\b/','/\bmonth\b/','/\byear\b/'];

                foreach ($periods as $period) {
                    preg_match($period, $datas['shelf_life'], $match);
                    if (isset($match[0])) {
                        $datas['expiry_period'] = $match[0];
                        break; // Exit the loop after finding the first match
                    }
                }
                
                wcwh_form_field( $prefixName.'[expiry_period]', 
                    [ 'id'=>' ', 'type'=>'select', 'label'=>'Expiry Period', 'required'=>true, 'attrs'=>[],
                        'options'=> $options
                    ], 
                    $datas['expiry_period'], $view 
                ); 
            ?>
        </div>

        <div class="col form-group">
        <?php 
            $datas['shelf_life'] = trim(str_replace(['+', 'day', 'month', 'year'], '', $datas['shelf_life']));

            wcwh_form_field( $prefixName.'[shelf_life]', 
                [ 'id'=>'', 'label'=>'Shelf Life', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['shelf_life'], $view 
            ); 
        ?>
        </div>
    </div>

    <?php elseif( $args['scheme'] == "item_category" ): ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $this->seller ) $filter['seller'] = $this->seller;
                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'id', [ 'slug', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[ref_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Item Category', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['ref_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[scheme]', 
                    [ 'id'=>'scheme', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                    $args['scheme'], $view 
                ); 
            ?>
        </div>
    </div>
    
    <div class="form-row">
        <div class="col form-group">
            <?php 
                $options = ['day' => 'Day', 'month' => 'Month', 'year' => 'Year'];
                $periods = ['/\bday\b/','/\bmonth\b/','/\byear\b/'];

                foreach ($periods as $period) {
                    preg_match($period, $datas['shelf_life'], $match);
                    if (isset($match[0])) {
                        $datas['expiry_period'] = $match[0];
                        break; // Exit the loop after finding the first match
                    }
                }
                
                wcwh_form_field( $prefixName.'[expiry_period]', 
                    [ 'id'=>' ', 'type'=>'select', 'label'=>'Expiry Period', 'required'=>true, 'attrs'=>[],
                        'options'=> $options
                    ], 
                    $datas['expiry_period'], $view 
                ); 
            ?>
        </div>

        <div class="col form-group">
        <?php 
            $datas['shelf_life'] = trim(str_replace(['+', 'day', 'month', 'year'], '', $datas['shelf_life']));

            wcwh_form_field( $prefixName.'[shelf_life]', 
                [ 'id'=>'', 'label'=>'Shelf Life', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                $datas['shelf_life'], $view 
            ); 
        ?>
        </div>
    </div>

    <?php endif; ?>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>