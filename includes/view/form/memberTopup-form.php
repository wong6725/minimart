<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="header-container">
        <h5>Header</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['docno'], ( $args['action'] == 'save' )? 1 : $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                /*$curr = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'indication'=>1 ], [], true, [ 'usage'=>1, 'company'=>1 ] );
                if( $curr['parent'] > 0 ) $whs = [ $curr ];
                else
                    $whs = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1 ], [], false, [ 'usage'=>1, 'company'=>1 ] );
                $options = options_data( $whs, 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Estate', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $datas['warehouse_id'], $view 
                ); */

                $not_acc_type = $args['setting']['wh_customer']['non_editable_by_acc_type'];
                $filter = [ 'status'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                if( $not_acc_type ) $filter['not_acc_type'] = $not_acc_type;

                $options = options_data( apply_filters( 'wcwh_get_membership', $filter, [], false, [ 'account'=>1 ] ), 'id', [ 'uid', 'code', 'serial', 'acc_name', 'name' ] );

                wcwh_form_field( $prefixName.'[member_id]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Member', 'required'=>true, 'attrs'=>[], 'class'=>['select2'], 
                        'options'=> $options 
                    ], 
                    $datas['member_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[amount]', 
                    [ 'id'=>'', 'label'=>'Amount (RM)', 'required'=>true, 'attrs'=>[], 'class'=>['numonly','positive-number'], 
                        'options'=> $options 
                    ], 
                    $datas['amount'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'attachments', 
                    [ 'id'=>'', 'type'=>'file', 'label'=>'Attachments', 'required'=>( $datas['attachment'] )? false : true, 'attrs'=>[], 'multiple'=>1 ], 
                    '', $view 
                ); 

                if( $datas['attachment'] )
                {
                    ?>
                    <table class="wp-list-table widefat striped">
                    <?php
                    foreach( $datas['attachment'] as $i => $attach )
                    {
                        $attach['i'] = $i;
                        $attach['view'] = $view;
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/attachments-row.php', $attach );

                        echo $tpl = str_replace( $find, $replace, $tpl );

                        echo "<br>";
                    }
                    ?>
                    </table>
                    <?php
                }
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
            <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
            </div>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>