<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_sync';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[id]', 
                [ 'id'=>'id', 'label'=>'ID', 'type'=>'label', 'attrs'=>[] ], 
                $datas['id'], true 
            ); 
            wcwh_form_field( $prefixName.'[id]', 
                [ 'id'=>'id', 'label'=>'ID', 'type'=>'hidden', 'attrs'=>[] ], 
                $datas['id'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = [ 'out'=>'OUT', 'in'=>'IN' ];

            wcwh_form_field( $prefixName.'[direction]', 
                [ 'id'=>'', 'type'=>'select', 'label'=>'Direction', 'required'=>true, 'attrs'=>[], 'options'=> $options ], 
                $datas['direction'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[remote_url]', 
                [ 'id'=>'', 'label'=>'Remote Url', 'required'=>false, 'attrs'=>[] ], 
                $datas['remote_url'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $options = options_data( apply_filters( 'wcwh_get_warehouse', [ 'status'=>1 ], [], false, [] ), 'code', [ 'code', 'name' ] );
            
            if( $options )
            {
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Warehouse', 'required'=>true, 'attrs'=>[], 'class'=>[ 'select2Strict' ],
                        'options'=> $options
                    ], 
                    $datas['wh_code'], $view 
                ); 
            }
            else
            {
                wcwh_form_field( $prefixName.'[wh_code]', 
                    [ 'id'=>'', 'label'=>'Warehouse', 'required'=>true, 'attrs'=>[] ], 
                    $datas['wh_code'], $view 
                );
            } 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            $sections = get_sections();
            $opts = [];
            if( $sections )
            {
                foreach( $sections as $i => $section )
                {
                    if( $section['push_service'] > 0 ) $opts[ $section['section_id'] ] = $section['section_id'].", ".$section['desc'];
                }
            }

            if( !empty( $opts ) )
            {
                wcwh_form_field( $prefixName.'[section]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Section', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict'],
                        'options'=> $opts
                    ], 
                    $datas['section'], $view 
                ); 
            }
            else
            {
                wcwh_form_field( $prefixName.'[section]', 
                    [ 'id'=>'', 'label'=>'Section', 'required'=>true, 'attrs'=>[] ], 
                    $datas['section'], $view 
                ); 
            }
            
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ref]', 
                [ 'id'=>'', 'label'=>'Ref No', 'required'=>false, 'attrs'=>[] ], 
                $datas['ref'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( $prefixName.'[ref_id]', 
                [ 'id'=>'', 'label'=>'Ref. Doc. ID', 'required'=>true, 'attrs'=>[] ], 
                $datas['ref_id'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'ip_address', 'label'=>'IP Addr', 'type'=>'label', 'attrs'=>[] ], 
                $datas['ip_address'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'details', 'label'=>'Details', 'type'=>'label', 'attrs'=>[] ], 
                $datas['details'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'status', 'label'=>'Status', 'type'=>'label', 'attrs'=>[] ], 
                $datas['status'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'handshake', 'label'=>'Handshake', 'type'=>'label', 'attrs'=>[] ], 
                $datas['handshake'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'created_at', 'label'=>'Created At', 'type'=>'label', 'attrs'=>[] ], 
                $datas['created_at'], $view 
            ); 
        ?>
        </div>
    </div>

    <div class="form-row">
        <div class="col form-group">
        <?php 
            wcwh_form_field( '', 
                [ 'id'=>'lupdate_at', 'label'=>'last Update', 'type'=>'label', 'attrs'=>[] ], 
                $datas['lupdate_at'], $view 
            ); 
        ?>
        </div>
    </div>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>