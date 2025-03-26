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
                [ 'id'=>'', 'label'=>'WH Code', 'type'=>'label', 'attrs'=>[] ], 
                $datas['wh_code'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Page', 'type'=>'label', 'attrs'=>[] ], 
                $datas['page'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Section', 'type'=>'label', 'attrs'=>[] ], 
                $datas['section'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Action', 'type'=>'label', 'attrs'=>[] ], 
                $datas['action'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Actor', 'type'=>'label', 'attrs'=>[] ], 
                $datas['action_by'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Time', 'type'=>'label', 'attrs'=>[] ], 
                $datas['log_at'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'IP Addr', 'type'=>'label', 'attrs'=>[] ], 
                $datas['ip_address'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Browser', 'type'=>'label', 'attrs'=>[] ], 
                $datas['agent'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Parent', 'type'=>'label', 'attrs'=>[] ], 
                $datas['parent'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Status', 'type'=>'label', 'attrs'=>[] ], 
                $datas['status'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'', 'label'=>'Err Info', 'type'=>'label', 'attrs'=>[] ], 
                $datas['error_remark'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $dat = json_decode( $datas['data'], true );
            parse_str( $dat['form'], $dat['form'] );
            parse_str( $dat['listing'], $dat['listing'] );
            
            pd( $dat );
        ?>
        </div>
    </div>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>