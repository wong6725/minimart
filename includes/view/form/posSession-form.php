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

    <div class="detail-container">
        <h5>Closing Information</h5>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  $datas['display_name']  ?></span>
                <label for="" class=" control-label">Cashier Name:</label>    
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
                <span id="" class="view form-control "><?=  date( 'Y-m-d', strtotime( $datas['closed']))  ?></span>
                <label for="" class=" control-label">Closing Date:</label>    
                <?php 
                    wcwh_form_field( $prefixName.'[id]', 
                    [ 'type'=>'hidden', 'required'=>true, 'attrs'=>[] ], 
                    $datas['id'], $view 
                ); 
                ?>       
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                 wcwh_form_field( $prefixName.'[opening]', 
                    [ 'type'=>'text', 'label'=>'Opening','required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                    $datas['opening'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                 wcwh_form_field( $prefixName.'[closing]', 
                    [ 'type'=>'text', 'label'=>'Closing','required'=>true, 'attrs'=>[], 'class'=>['numonly','{fraction}','onfocusFocus','enterNextRow'] ], 
                    $datas['closing'], $view 
                ); 
                
            ?>
            </div>

        </div>
    </div>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>


