<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_exchange_rate';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false,'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['docno'], ( $args['action'] == 'save' || $args['action'] == 'update' )? 1 : $view 
                ); 

                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'type'=>'hidden', 'required'=>false,'attrs'=>[], 'description'=>'Leave blank for auto generate' ], 
                    $datas['docno'], $view 
                ); 

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
                    
                    wcwh_form_field( $prefixName.'[to_currency]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'To Currency', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options
                        ], 
                        ( $datas['to_currency'] )? $datas['to_currency'] : 'IDR', $view 
                    ); 
                ?>
            </div>
        </div>
    
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[rate]', 
                    [ 'id'=>'','label'=>'Exchange Rate', 'required'=>true, 'attrs'=>[], 'class'=>['numonly', 'positive-integer'], "description"=> '(Maximun 3 decimal places)' ], 
                    $datas['rate'], $view 
                ); 
            ?>
            </div>

            <div class="col form-group">
            <?php 
                $now = date( 'Y-m-d', strtotime( current_time( 'mysql' ) ) );
                wcwh_form_field( $prefixName.'[since]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective Date (YYYY-MM-DD)', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker']  ], 
                    ($datas['since'])? date( 'Y-m-d', strtotime( $datas['since'] ) ) : $now, $view 
                ); 
            ?>
            </div>
        </div>
    </div>
    
    <div class="form-row">
        <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[desc]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Description', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                    ], 
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



<!--
    
<div class="col form-group">
<?php 
/*
    wcwh_form_field( $prefixName.'[title]', 
        [ 'id'=>'', 'label'=>'Title', 'required'=>false, 'attrs'=>[] ], 
        $datas['title'], $view 
    ); 
    */
?>
</div>

<div class="col form-group">
<?php 
/*
    wcwh_form_field( $prefixName.'[base]', 
        [ 'id'=>'', 'label'=>'Base', 'required'=>false, 'attrs'=>[] ], 
        $datas['base'], $view 
    ); 
    */
?>
</div>
-->
