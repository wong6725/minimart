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
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_warehouse', [], [], false, [ 'usage'=>1, 'company'=>1 ] ), 'code', [ 'code', 'name' ], '' );
                
                wcwh_form_field( $prefixName.'[seller]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Seller', 'required'=>true, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                        'options'=> $options
                    ], 
                    $datas['seller'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[title]', 
                    [ 'id'=>'', 'label'=>'Promo Title / Description', 'required'=>true, 'attrs'=>[], 'description'=>'A name or description for the promotion' ], 
                    $datas['title'], $view 
                ); 
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

        <h5>Header Rules</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['from_date'] ) $from_date = date( 'm/d/Y', strtotime( $datas['from_date'] ) );

                wcwh_form_field( $prefixName.'[from_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Promo From Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$from_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['from_date'] )? date( 'Y-m-d', strtotime( $datas['from_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['to_date'] ) $to_date = date( 'm/d/Y', strtotime( $datas['to_date'] ) );

                wcwh_form_field( $prefixName.'[to_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Promo Until Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$to_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['to_date'] )? date( 'Y-m-d', strtotime( $datas['to_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ ""=>"Default", "once_per_person"=>"Once Per Person" ];

                wcwh_form_field( $prefixName.'[limit_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Promo Limit Type', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    $datas['limit_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( $prefixName.'[once_per_order]', 
                    [ 'id'=>'', 'type'=>'checkbox', 'label'=>'Only Once Per Order', 'required'=>false, 'attrs'=>[] ], 
                    $datas['once_per_order'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_promo_header', [], [], false, [ 'usage'=>1 ] ), 'id', [ 'docno', 'title' ], '' );
                
                wcwh_form_field( $prefixName.'[share_ctrl][]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Share Control with Promo', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect', 'description'=>'Share control together with other promotion, comma separated docno' ],
                        'options'=> $options, 'multiple'=>1
                    ], 
                    $datas['share_ctrl'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[limit]', 
                    [ 'id'=>'', 'label'=>'Promotion Usage Limit', 'required'=>false, 'attrs'=>[], 'class'=>['numonly','positive-number']
                    , 'description'=>'Usage Limit per promotion matched, leave blank as unlimited' ], 
                    $datas['limit'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ "and"=>"AND", "or"=>"OR" ];

                wcwh_form_field( $prefixName.'[cond_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Promo Condition Matching Type', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $datas['cond_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = [ "and"=>"AND", "or"=>"OR" ];

                wcwh_form_field( $prefixName.'[rule_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Promo Rule Matching Type', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $options
                    ], 
                    $datas['rule_type'], $view 
                ); 
            ?>
            </div>
        </div>
    </div>

    <div class="detail-container">
        <?php if( ! $view ): ?>
        <h5>Promo Condition Matching<span class="required toolTip" title="" data-original-title="required">*</span></h5>
        <div class="actions row">
        <?php
            if( $args['promo_cond_match'] )
            foreach( $args['promo_cond_match'] as $key => $val )
            {
                $hid = ( ! $val['hasItem'] )? 'data-hideitem="display-none"' : 'data-hideitem=""';

                echo '<div class="col-md-2">';
                echo '<a class="btn btn-sm btn-primary dynamic-element" data-source="" data-tpl="'.$args['rowCondTpl'].'TPL" 
                    data-target="#cond_row"
                    data-item_id=""
                    data-match="'.$key.'"
                    data-amount=""
                    data-match_text="'.$val['title'].'" 
                    '.$hid.'
                    >'.$val['title'].'</a>';
                echo '</div>';
            }
        ?>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Achieve</th>
                    <th class="item">Item</th>
                    <th class="unit_price">Amt/Qty</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="cond_row">
            <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        if( $row['type'] != 'condition' ) continue;

                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'item_id' => '{item_id}',
                            'type' => '{type}',
                            'match' => '{match}',
                            'product_id' => '{product_id}', 
                            'amount' => '{amount}', 
                            'type_text' => '{type_text}',
                            'match_text' => '{match_text}',
                            'hideitem' => '{hideitem}',
                        ];

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['id'], 
                            'item_id' => $row['id'],
                            'type' => $row['type'], 
                            'match' => $row['match'], 
                            'product_id' => $row['product_id'],
                            'amount' => $row['amount'],
                            'type_text' => $args['promo_type'][ $row['type'] ]['title'], 
                            'match_text' => $args['promo_cond_match'][ $row['match'] ]['title'], 
                            'hideitem' => ( ! $args['promo_cond_match'][ $row['match'] ]['hasItem'] )? 'display-none' : '',
                        ];
                        $arg = $row;
                        $arg['type'] = 'condition';
                        $arg['prefixName'] = '_condition';
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/promo-row.php', $arg );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </tbody>
        </table>
        <br><br>
        <h5>Promo Rule<span class="required toolTip" title="" data-original-title="required">*</span></h5>
        <div class="actions row">
        <?php
            if( $args['promo_rule_match'] )
            foreach( $args['promo_rule_match'] as $key => $val )
            {
                $hid = ( ! $val['hasItem'] )? 'data-hideitem="display-none"' : 'data-hideitem=""';

                echo '<div class="col-md-2">';
                echo '<a class="btn btn-sm btn-primary dynamic-element" data-source="" data-tpl="'.$args['rowRuleTpl'].'TPL" 
                    data-target="#rule_row"
                    data-item_id=""
                    data-match="'.$key.'"
                    data-amount=""
                    data-match_text="'.$val['title'].'" 
                    '.$hid.'
                    >'.$val['title'].'</a>';
                echo '</div>';
            }
        ?>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Promo</th>
                    <th class="item">Item</th>
                    <th class="unit_price">Amt/Qty</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="rule_row">
            <?php
                if( $datas['details'] )
                {
                    foreach( $datas['details'] as $i => $row )
                    {
                        if( $row['type'] != 'rule' ) continue;

                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'item_id' => '{item_id}',
                            'type' => '{type}',
                            'match' => '{match}',
                            'product_id' => '{product_id}', 
                            'amount' => '{amount}', 
                            'type_text' => '{type_text}',
                            'match_text' => '{match_text}',
                            'hideitem' => '{hideitem}',
                        ];

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['id'], 
                            'item_id' => $row['id'],
                            'type' => $row['type'], 
                            'match' => $row['match'], 
                            'product_id' => $row['product_id'],
                            'amount' => $row['amount'],
                            'type_text' => $args['promo_type'][ $row['type'] ]['title'], 
                            'match_text' => $args['promo_rule_match'][ $row['match'] ]['title'], 
                            'hideitem' => ( ! $args['promo_rule_match'][ $row['match'] ]['hasItem'] )? 'display-none' : '',
                        ];
                        $arg = $row;
                        $arg['type'] = 'rule';
                        $arg['prefixName'] = '_rule';
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/promo-row.php', $arg );
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
    </div>

    <?php if( $datas['id'] ): ?>
		<input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
	<?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>