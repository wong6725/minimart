<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_template';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[tpl_code]', 
                [ 'id'=>'', 'label'=>'Template Code', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                $datas['tpl_code'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[tpl_path]', 
                [ 'id'=>'', 'label'=>'Template System Path', 'required'=>true, 'attrs'=>[], 'class'=>[] ], 
                ( $datas['tpl_path'] )? $datas['tpl_path'] : 'template/', $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[tpl_file]', 
                [ 'id'=>'', 'label'=>'Template Filename With Extension', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $datas['tpl_file'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            if( $datas['from_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['from_date'] ) );

            wcwh_form_field( $prefixName.'[from_date]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'From Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                ( $datas['from_date'] )? date( 'Y-m-d', strtotime( $datas['from_date'] ) ) : "", $view 
            ); 
        ?>
        </div>
        <div class="col form-group">
        <?php 
            if( $datas['to_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['to_date'] ) );

            wcwh_form_field( $prefixName.'[to_date]', 
                [ 'id'=>'', 'type'=>'text', 'label'=>'To Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                ( $datas['to_date'] )? date( 'Y-m-d', strtotime( $datas['to_date'] ) ) : "", $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[remarks]', 
                [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                $datas['remarks'], $view 
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