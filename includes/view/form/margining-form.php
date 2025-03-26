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
                $options = options_data( apply_filters( 'wcwh_get_warehouse', ['status'=>1 ], [], false, [] ), 'code', [ 'code', 'name' ], '' );
                
                if( !empty( $args['wh_code'] ) )
                {
                    wcwh_form_field( $prefixName.'[wh_id]', 
                        [ 'id'=>'', 'type'=>'text', 'label'=>'Company / Outlet', 'required'=>false, 'attrs'=>[] ], 
                        $options[ $args['wh_code'] ], true 
                    );  
                    wcwh_form_field( $prefixName.'[wh_id]', 
                        [ 'id'=>'', 'type'=>'hidden', 'required'=>false, 'attrs'=>[] ], 
                         $args['wh_code'], $view
                    );  
                }
                else
                {
                    wcwh_form_field( $prefixName.'[wh_id]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Company / Outlet', 'required'=>true, 'attrs'=>[], 'class'=>[ 'select2Strict' ],
                            'options'=> $options
                        ], 
                        ( $args['wh_code'] )? $args['wh_code'] : $datas['wh_id'], $view 
                    ); 
                }
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                if( $datas['since'] ) $since = date( 'm/d/Y', strtotime( $datas['since'] ) );

                wcwh_form_field( $prefixName.'[since]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Since Date', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$since.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['since'] )? date( 'Y-m-d', strtotime( $datas['since'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['until'] ) $until = date( 'm/d/Y', strtotime( $datas['until'] ) );

                wcwh_form_field( $prefixName.'[until]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Until Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$until.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ($datas['until'])? date( 'Y-m-d', strtotime( $datas['until'] ) ) : "", $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['effective'] ) $effective = date( 'm/d/Y', strtotime( $datas['effective'] ) );

                wcwh_form_field( $prefixName.'[effective]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Effective Since', 'required'=>true, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$effective.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ($datas['effective'])? date( 'Y-m-d', strtotime( $datas['effective'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php
                wcwh_form_field( $prefixName.'[margin]', 
                    [ 'id'=>'', 'label'=>'Margin (%)', 'required'=>true, 'attrs'=>[], 'class'=>['numonly'] ], 
                    $datas['margin'], $view  
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                $options = [ 'incl'=>'Inclusive', 'excl'=>'Exclusive' ];

                wcwh_form_field( $prefixName.'[inclusive]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Clients Inclusive or Exclusive', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'options'=> $options
                    ], 
                    $datas['inclusive'], $view 
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'DEFAULT'=>'Default', 'ROUND'=>'Round Nearest', 'FLOOR'=>'Nearest Down', 'CEIL'=>'Nearest Up' ];

                wcwh_form_field( $prefixName.'[round_type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Round Type', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'options'=> $options
                    ], 
                    $datas['round_type'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[round_nearest]', 
                    [ 'id'=>'', 'label'=>'Round Nearest', 'required'=>false, 'attrs'=>[], 'class'=>['numonly'], 'description'=>'Nearest 0.1', 'placeholder'=>'0.1' ], 
                    $datas['round_nearest'], $view  
                ); 
            ?>
            </div>
        </div>

        <div class="form-row">
            <div class="col form-group">
            <?php
                $options = [ 'def'=>'Default All', 'with'=>'With SAP PO', 'without'=>'Without SAP PO' ];

                wcwh_form_field( $prefixName.'[po_inclusive]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'With or Without SAP PO', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'options'=> $options
                    ], 
                    $datas['po_inclusive'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                $options = [ 'def'=>'Default', 'adj'=>'Adjustment' ];

                wcwh_form_field( $prefixName.'[type]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Margining Type', 'required'=>false, 'attrs'=>[], 'class'=>[],
                        'options'=> $options
                    ], 
                    $datas['type'], $view 
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
    </div>

    <div class="detail-container">
        <?php if( ! $view ): ?>
        <h5>Apply To Section<span class="required toolTip" title="" data-original-title="required">*</span></h5>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $elements = [];
                if( $args['matters'] )
                    foreach( $args['matters'] as $key => $vals )
                        $elements[ $key ] = $vals['title'];
                if( $elements )
                {
                    echo '<select class="sect-items select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Section(s)">';
                    foreach( $elements as $key => $item )
                    {   
                        echo '<option 
                            value="'.$key.'" 
                            data-item_id="" 
                            data-sub_section="'.$key.'"
                            data-title="'.$item.'" 
                        >'.$item.'</option>';
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".sect-items" data-tpl="'.$args['sectTpl'].'TPL" data-target="#sect_item_row" >Add +</a>'; ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Section</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="sect_item_row">
            <?php
                if( $datas['elements'] )
                {   
                    foreach( $datas['elements'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'item_id' => '{item_id}',
                            'sub_section' => '{sub_section}',
                            'title' => '{title}',
                        ];

                        $replace = [ 
                            'i' => $i, 
                            'item_id' => $row['id'],
                            'sub_section' => $row['sub_section'],
                            'title' => $elements[ $row['sub_section'] ], 
                        ];
                        
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/marginingSect-row.php' );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </tbody>
        </table>
        <br><br>
        
        <?php else: ?>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render_element'];
                ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-container">
        <?php if( ! $view ): ?>
        <h5>Clients with Margin<span class="required toolTip" title="" data-original-title="required">*</span></h5>
        <div class="actions row">
            <div class="col-md-10">
            <?php 
                $clients = apply_filters( 'wcwh_get_client', $filter, [], false, [ 'usage'=>1 ] );
                if( $clients )
                {
                    echo '<select class="pr-items select2 multiple modalSelect" multiple="multiple" data-placeholder="Select Client(s)">';
                    foreach( $clients as $i => $item )
                    {   
                        echo '<option 
                            value="'.$item['id'].'" 
                            data-id="'.$item['id'].'" 
                            data-client_id="'.$item['id'].'" 
                            data-item_id="" 
                            data-client="'.$item['code'].'" 
                            data-client_info="'.$item['code'].' - '.$item['name'].'" 
                            data-margin="";
                        >'.$item['code'].', '.$item['name'].'</option>';
                    }
                    echo '</select>';
                }
            ?>
            </div>
            <div class="col-md-2">
                <?php echo ' <a class="btn btn-sm btn-primary dynamic-action" data-source=".pr-items" data-tpl="'.$args['rowTpl'].'TPL" data-target="#item_row" >Add +</a>'; ?>
            </div>
        </div>
        <table class="details wp-list-table widefat striped">
            <thead>
                <tr>
                    <th class="num"></th>
                    <th class="item">Client</th>
                    <th class="action"></th>
                </tr>
            </thead>
            <tbody id="item_row">
            <?php
                if( $datas['details'] )
                {   
                    foreach( $datas['details'] as $i => $row )
                    {
                        $find = [ 
                            'i' => '{i}', 
                            'id' => '{id}', 
                            'item_id' => '{item_id}',
                            'client' => '{client}',
                            'client_info' => '{client_info}',
                            'margin' => '{margin}',
                        ];

                        $replace = [ 
                            'i' => $i, 
                            'id' => $row['client_id'], 
                            'item_id' => $row['id'],
                            'client' => $row['client'], 
                            'client_info' => $row['client_name'],
                            'margin' => $row['margin'], 
                        ];
                        
                        $tpl = apply_filters( 'wcwh_get_template_content', 'segment/margining-row.php' );
                        echo $tpl = str_replace( $find, $replace, $tpl );
                    }
                }
            ?>
            </tbody>
        </table>
        <br><br>
        
        <?php else: ?>
             <div class="form-row">
                <div class="col form-group">
                <?php 
                    echo $args['render_detail'];
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