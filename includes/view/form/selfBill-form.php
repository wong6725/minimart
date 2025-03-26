<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];
$def_country = ( $args['def_country'] )? $args['def_country'] : 'MY';

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
                    [ 'id'=>'', 'label'=>'Document No.', 'required'=>false, 'attrs'=>[] ], 
                    $datas['docno'], ( $args['action'] == 'save' )? 1 : $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
                else $doc_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 
                        'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
			<div class="col form-group">
            <?php 
                if( $view && empty( $datas['submit_date'] ) ) $datas['submit_date'] = $datas['submit_date'];
                if( $datas['submit_date'] ) $submit_date = date( 'm/d/Y', strtotime( $datas['submit_date'] ) );

                wcwh_form_field( $prefixName.'[submit_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Submit Date', 'required'=>false, 
						'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$submit_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['submit_date'] )? date( 'Y-m-d', strtotime( $datas['submit_date'] ) ) : "", $pview 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $view && empty( $datas['posting_date'] ) ) $datas['posting_date'] = $datas['post_date'];
                if( $datas['posting_date'] ) $posting_date = date( 'm/d/Y', strtotime( $datas['posting_date'] ) );

                wcwh_form_field( $prefixName.'[posting_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Posting Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$posting_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['posting_date'] )? date( 'Y-m-d', strtotime( $datas['posting_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $filter = [ 'self_bill'=>1 ];
                if( $args['seller'] ) $filter['seller'] = $args['seller'];
                $options = options_data( apply_filters( 'wcwh_get_supplier', $filter, [], false, [ 'usage'=>1 ] ), 'code', [ 'code', 'name' ] );
                
                if( $args['action'] == 'update-header' && $datas['supplier_company_code'] )
                {
                    wcwh_form_field( $prefixName.'[supplier_company_code]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Supplier', 'required'=>false, 'attrs'=>[] ], 
                        $options[ $datas['supplier_company_code'] ], true 
                    );  
                    wcwh_form_field( $prefixName.'[supplier_company_code]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                        $datas['supplier_company_code'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[supplier_company_code]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Supplier', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                            'options'=> $options
                        ], 
                        ( $datas['supplier_company_code'] )? $datas['supplier_company_code'] : '', $view 
                    ); 
                }
            ?>
            </div>
            <div class="col form-group">
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $pview = $view;
                if( $args['action'] == 'update-header' ) $pview = 1;
                
                $options = options_data( $args['po'], 'doc_id', [ 'docno', 'doc_date', 'total_amount' ], '' );
                $def = array_keys( $options );
                wcwh_form_field( $prefixName.'[po_doc][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'PO to Consolidate', 'required'=>true, 'attrs'=>[], 'class'=>['select2', 'modalSelect'], 'options'=> $options, 'multiple'=>1  ], 
                    ( $datas['po_doc'] )? $datas['po_doc'] : $def, $pview 
                ); 
            ?>
            </div>
        </div>

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
            ?>
            </div>
        </div>
    
    </div>

    <div class="detail-container">
        <h5>Details</h5>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render'];
                ?>
                </div>
            </div>
    </div>

    <?php if( $datas['doc_id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>