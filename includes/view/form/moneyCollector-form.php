<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_form';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>"
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>"
    novalidate>
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
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Document Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
                <?php 
                if( $view && empty( $datas['posting_date'] ) ) $datas['posting_date'] = $datas['post_date'];
                if($datas['posting_date'] ) $posting_date = date( 'm/d/Y', strtotime( $datas['posting_date'] ) ); 

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
                if( $datas['from_period'] ) $from_period = date( 'm/d/Y', strtotime( $datas['from_period'] ) );
                else $from_period = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[from_period]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'From Period', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$from_period.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ($datas['from_period'])? date( 'Y-m-d', strtotime( $datas['from_period'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
                <?php 
                if( $datas['to_period'] ) $to_period = date( 'm/d/Y', strtotime( $datas['to_period'] ) );
                else $to_period = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[to_period]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'To Period', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$to_period.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ($datas['to_period'])? date( 'Y-m-d', strtotime( $datas['to_period'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[remark]', 
                    [ 'id'=>'', 'type'=>'textarea', 'label'=>'Remark', 'required'=>false, 'attrs'=>[] ], 
                    $datas['remark'], 0 
                ); 

                
            ?>
            </div>
            </div>
        <div class="header-container">
            <h5>Collection Information</h5>
            <div class="form-row">
                <?php 
                $def_from = date( 'm/d/Y', strtotime( current_time( 'Y-m-1' ) ) );
                $def_to = date( 'm/d/Y', strtotime( current_time( 'Y-m-t' ) ) );
            ?>

                <div class="col form-group">

                    <?php 
                wcwh_form_field( $prefixName.'[withdraw_person]', 
                    [ 'id'=>'', 'label'=>'Collector Name', 'required'=>true, 'attrs'=>[]], 
                    $datas['withdraw_person'], $view 
                ); 
                 
            ?>

                </div>

                <div class="col form-group">
                </div>
            </div>

            <div class="form-row">
                <div class="col form-group">
                    <?php 
                wcwh_form_field( $prefixName.'[amt]', 
                    [ 'id'=>'', 'label'=>'Collect Amount', 'required'=>true, 'attrs'=>[],'class'=>['numonly']], 
                    $datas['amt'], $view 
                ); 

                if($view && $datas['amt'])
                {
                    wcwh_form_field( $prefixName.'[amt]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[]], 
                    $datas['amt'], $view ); 
                }
              
               
            ?>
                </div>
                <div class="col form-group">
                </div>
            </div>


            <div class="form-row">
                <div class="col form-group">
                    <?php 
                wcwh_form_field( $prefixName.'[warehouse_id]', 
                    [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                    $datas['warehouse_id'], $view 
                ); 
            ?>
                </div>
            </div>

        </div>

        <!--<div class="detail-container">
            <h5>Bank In Information</h5>
            <?php if($args['action']=='update') $view = false ?>

            <?php if(!$view): ?>
            <div class="form-row">
                <div class="col-6">
                    <?php 
                wcwh_form_field( $prefixName.'[available_amt]', 
                    [ 'id'=>'', 'label'=>'Available Amount(Bank in)', 'required'=>false, 'attrs'=>[]], 
                    $datas['available_amt'], 1 
                ); 
            ?>
                </div>
                <div class="col-2">
                    <br>
                    <?php echo ' <a class="btn btn-sm btn-primary dynamic-element" data-source="" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >New Bank In +</a>'; ?>
                </div>

            </div>

            <table class="details wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th class="num"></th>
                        <th class="bankin_person">Bank In Person</th>
                        <th class="bankin_date">Date</th>
                        <th class="bankin_Amt">Bank In Amount</th>
                        <th class="action"></th>
                    </tr>
                </thead>
                <tbody id="item_row" class="sortable_row">
                    <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'bankin_person'=>'{bankin_person}',
                            'bankin_date'=>'{bankin_date}',
                            'bankin_amt'=>'{bankin_amt}',
                            'item_id' => '{item_id}',
                        ];

                        // $filter = [ 'id'=>$row['product_id'] ];
                        // if( $args['seller'] ) $filter['seller'] = $args['seller'];
                        // $row['line_item'] = ( $row['line_item'] )? $row['line_item'] : apply_filters( 'wcwh_get_item', $filter, [], true, [ 'uom'=>1, 'category'=>1, 'isUnit'=>1 ] );
                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['item_id'], 
                            'bankin_person'=>$row['bankin_person']?$row['bankin_person']:'',
                            'bankin_date'=>$row['bankin_date']?$row['bankin_date']:'',
                            'bankin_amt'=>$row['bankin_amt']?$row['bankin_amt']:0,
                            'item_id' => $row['item_id'],
                        ];
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/cashWithdrawal-row.php' );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                        
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
        </div>-->



        <?php if( $datas['doc_id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
        <?php endif; ?>

        <?php if( ! $args['get_content'] ): ?>
        <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>