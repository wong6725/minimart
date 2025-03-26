<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
    <div class='form-rows-group'>
       <h5>Header</h5>
       <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[docno]', 
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['docno'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['doc_date'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[post_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['post_date'], $view 
                ); 
            ?>
            </div>
        </div>

        <!-- <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[stocktake_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Stocktake Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    $datas['stocktake_date'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $hour = [];
                for( $i = 0; $i < 24; $i++ )
                {
                    $hour[$i] = str_pad( $i, 2, "0", STR_PAD_LEFT );
                }
                wcwh_form_field( $prefixName.'[stocktake_hour]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Stocktake Time (H)', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                        'options'=>$hour ], 
                    $datas['stocktake_hour'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $min = [];
                for( $i = 0; $i < 60; $i++ )
                {
                    $min[$i] = str_pad( $i, 2, "0", STR_PAD_LEFT );
                }
                wcwh_form_field( $prefixName.'[stocktake_minute]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Stocktake Time (M)', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                        'options'=>$min ], 
                    $datas['stocktake_minute'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[stocktake_second]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Stocktake Time (S)', 'required'=>false, 'attrs'=>[], 'class'=>[], 
                       'options'=>$min ], 
                    $datas['stocktake_second'], $view 
                ); 
            ?>
            </div>
        </div> -->

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remark]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remark'], $view 
                ); 

                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['warehouse_id'], $view 
                ); 

                wcwh_form_field( $prefixName.'[stocktake_item]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['stocktake_item'], $view 
                ); 

                wcwh_form_field( $prefixName.'[store_type_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['store_type_id'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

	<div class='form-rows-group'>
        <h5>Import Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'import', 
                    [ 'id'=>'import', 'type'=>'file', 'label'=>'Import File (.xlss, .xls)', 'required'=>true, 'attrs'=>[ 'accept=".xlsx, .xls"' ] ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <!--<div class="col form-group">
            <?php 
                /*$options = [ ','=>',', '\t'=>'tab', ';'=>';', '|'=>'|', '||'=>'||' ];

                wcwh_form_field( $prefixName.'[delimiter]', 
                    [ 'id'=>'delimiter', 'type'=>'select', 'label'=>'Delimiter', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    ',', $view 
                ); */
            ?>
            </div>-->
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[header]', 
                    [ 'id'=>'header', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <?php if( $args['doc_id'] > 0 ): ?>
        <?php 
            wcwh_form_field( $prefixName.'[doc_id]', 
                [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                $args['doc_id'], $view 
            ); 
         ?>
    <?php endif; ?>

	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>