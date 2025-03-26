<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_item_category';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[name]', 
                [ 'id'=>'', 'label'=>'Category Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['name'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
    	<div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[slug]', 
                [ 'id'=>'', 'label'=>'Code', 'required'=>true, 'attrs'=>[] ], 
                $datas['slug'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                $filter = [];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_item_category', $filter, [], false, [] ), 'term_id', [ 'slug', 'name' ] );
                
                wcwh_form_field( $prefixName.'[parent]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Parent', 'required'=>false, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options
                    ], 
                    $datas['parent'], $view 
                ); 
            ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[description]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[] ], 
                    $datas['description'], $view 
                );
            ?>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>