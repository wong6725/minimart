<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'id', 'label'=>'ID', 'type'=>'label', 'attrs'=>[] ], 
                $datas['id'], $view 
            ); 
        ?>
        </div>
    </div>

	<div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'ref_type', 'label'=>'Reference Type', 'type'=>'label', 'attrs'=>[] ], 
                $datas['ref_type'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'ref_id', 'label'=>'Reference ID', 'type'=>'label', 'attrs'=>[] ], 
                $datas['ref_id'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'status', 'label'=>'Status', 'type'=>'label', 'attrs'=>[] ], 
                $datas['status'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'proceed_status', 'label'=>'Proceeding', 'type'=>'label', 'attrs'=>[] ], 
                $datas['proceed_status'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'halt_status', 'label'=>'Halting', 'type'=>'label', 'attrs'=>[] ], 
                $datas['halt_status'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'latest_stage', 'label'=>'Latest Stage ID', 'type'=>'label', 'attrs'=>[] ], 
                $datas['latest_stage'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'action', 'label'=>'Action', 'type'=>'label', 'attrs'=>[] ], 
                $datas['action'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'remark', 'label'=>'Remark', 'type'=>'label', 'attrs'=>[] ], 
                $datas['remark'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'activities', 'label'=>'Activities', 'type'=>'label', 'attrs'=>[] ], 
                '', $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            echo $args['render'];
        ?>
        </div>
    </div>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>