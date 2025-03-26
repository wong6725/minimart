<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_servicecharge';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

	<div class="form-row">
        <div class="col form-group">
        <?php
            $options = [ 'bank_in'=>'Bank-In'];
            wcwh_form_field( $prefixName.'[type]', [ 'id'=>'', 'type'=>'select', 'label'=>'Type of Service Charge', 'required'=>true, 'attrs'=>[], 'class'=> ['select2Strict'], 'options'=> $options], $datas['type'], $view); 
        ?>
        </div>
        <div class="col form-group">
        <?php
            wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'label'=>'Service Charge Code', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $datas['code'], true); 

            wcwh_form_field( $prefixName.'[code]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                    $datas['code'], true); 
        ?>
        </div>
    </div>

    <div class="detail-container">
        <div class="form-row">
            <div class="col form-group">
                <?php            
                    $options = options_data( get_woocommerce_currencies() ) ;

                    wcwh_form_field( $prefixName.'[from_currency]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'From Currency', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options
                        ], 
                        ( $datas['from_currency'] )? $datas['from_currency'] : 'MYR', $view 
                    ); 
                ?>
            </div>
            <div class="col form-group">
                <?php 
                    $options = options_data( get_woocommerce_currencies() );
                    $options['DEF'] = 'Default All Currency';
                    
                    wcwh_form_field( $prefixName.'[to_currency]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'To Currency', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options, 'description'=> 'Default = Apply to All Currency'
                        ], 
                        ( $datas['to_currency'] )? $datas['to_currency'] : 'DEF', $view 
                    );
                ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[from_amt]', 
                    [ 'id'=>'', 'label'=>'From Amount', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                    $datas['from_amt'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
                <?php 
                wcwh_form_field( $prefixName.'[to_amt]', 
                    [ 'id'=>'', 'label'=>'To Amount', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                    $datas['to_amt'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[charge]', 
                    [ 'id'=>'', 'label'=>'Charge (RM)', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                    $datas['charge'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                /*if( $datas['since'] ) $since = date( 'm/d/Y', strtotime( $datas['since'] ) );

                wcwh_form_field( $prefixName.'[since]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective Since', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$since.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['since'], $view 
                );*/ 
            ?>
            </div>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[desc]', 
                [ 'id'=>'', 'type'=>'textarea','label'=>'Desc', 'required'=>false, 'attrs'=>[], ], 
                $datas['desc'], $view 
            ); 
        ?>
        </div>
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>