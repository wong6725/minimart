<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_scheme';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[section]', 
                [ 'id'=>'', 'label'=>'Section Name', 'required'=>true, 'attrs'=>[] ], 
                $datas['section'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[section_id]', 
                [ 'id'=>'', 'label'=>'Section ID', 'required'=>true, 'attrs'=>[] ], 
                $datas['section_id'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[scheme]', 
                [ 'id'=>'', 'label'=>'Scheme', 'required'=>true, 'attrs'=>[] ], 
                $datas['scheme'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[title]', 
                [ 'id'=>'', 'label'=>'Title', 'required'=>true, 'attrs'=>[] ], 
                $datas['title'], $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[scheme_lvl]', 
                [ 'id'=>'', 'type'=>'number', 'label'=>'Scheme Level', 'required'=>true, 'attrs'=>['min="0"'], 'class'=>['numonly'] ], 
                $datas['scheme_lvl'], $view 
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