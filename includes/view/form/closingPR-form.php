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
                if( !empty( $datas['ref_doc_date'] ) )
                {
                    $min_date = date( 'm/d/Y', strtotime( $datas['ref_doc_date'] ) );
                    $min_date = 'data-dd-min-date="'.$min_date.'"';

                    $max_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );
                    $max_date = 'data-dd-max-date="'.$max_date.'"';
                }
                else
                {
                    $min_date = ''; $max_date = '';
                }

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 
                        'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"', $min_date, $max_date ], 
                        'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                );  
            ?>
            </div>
            <div class="col form-group">
            <?php 
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
                if( $datas['ref_doc_id'] )
                {
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Purchase Request No.', 'required'=>false, 'attrs'=>[] ], 
                        $datas['purchase_doc'], true 
                    );  
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                        $datas['purchase_doc'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[purchase_doc]', 
                        [ 'id'=>'', 'label'=>'Purchase Request No.', 'required'=>false, 'attrs'=>[],'class'=>[] ], 
                        $datas['purchase_doc'], $view 
                    );  
                }
                wcwh_form_field( $prefixName.'[ref_doc_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['ref_doc_id'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
                
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
        <?php if( ! $view ): ?>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Item</th>
                    <th class="uom">UOM</th>
                    <th class="ref_qty" >PR Qty <sup class="toolTip" title="Qty of PR"> ? </sup></th>
                    <th class="qty">GR Used Qty</th>
                    <th class="ref_qty" >PR Bal Qty <sup class="toolTip" title="Balance of PR"> ? </sup></th>
                    <th class="action">Closing</th>
                </tr>
            </thead>
            <tbody id="item_row" class="sortable_row">
            <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        $filter = [ 'id'=>$row['product_id'] ];
                        if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1 ] );                      

                        echo '<tr data-seq="'.$i.'" data-id="'.$row['product_id'].'" class="row'.$i.' dragged_row">';
                        echo '<td class="num handle"></td>';
                        echo '<td>'.$row['line_item']['code'].' - '.$row['line_item']['name'].'</td>';
                        echo '<td>'.$row['line_item']['uom_code'].'</td>';
                        echo '<td>'.$row['ref_pr_bqty'].'</td>';
                        echo '<td>'.$row['gr_bqty'].'</td>';
                        echo '<td>'.$row['ref_pr_balance'].'</td>';
                        echo '<td>';
                        if(!$row['closed_item_row'])
                        {
                            wcwh_form_field( '_detail['.$i.'][closing]', [ 'id'=>'', 'type'=>'checkbox', 'required'=>false, 'attrs'=>[] ], $row['closed']? '1' : '', $view );
                        }
                        else
                        {
                            echo '&#10003;';
                        }

                        wcwh_form_field( '_detail['.$i.'][product_id]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['product_id'], $view);

                        wcwh_form_field( '_detail['.$i.'][bqty]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['bqty'], $view);

                        wcwh_form_field( '_detail['.$i.'][ref_pr_bqty]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['ref_pr_bqty'], $view);

                        wcwh_form_field( '_detail['.$i.'][ref_pr_balance]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['ref_pr_balance'], $view);

                        wcwh_form_field( '_detail['.$i.'][item_id]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['item_id'], $view);

                        wcwh_form_field( '_detail['.$i.'][ref_doc_id]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['ref_doc_id'], $view);

                        wcwh_form_field( '_detail['.$i.'][ref_item_id]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], $row['ref_item_id'], $view);

                        wcwh_form_field( '_detail['.$i.'][_item_number]', [ 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>['sortable_item_number'] ], $row['_item_number'], $view);

                        echo '</td>';
                        echo '</tr>';
                    }
                }
            ?>
            </tbody>
        </table>
        <?php else: ?>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render'];
                ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if( $datas['doc_id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>