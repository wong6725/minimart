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
                    $datas['docno'], 1 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                if( $datas['doc_date'] ) $doc_date = date( 'm/d/Y', strtotime( $datas['doc_date'] ) );
                else $doc_date = date( 'm/d/Y', strtotime( current_time( 'mysql' ) ) );

                wcwh_form_field( $prefixName.'[doc_date]', 
                    [ 'id'=>'', 'type'=>'text', 'label'=>'Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$doc_date.'"' ], 'class'=>['doc_date', 'picker'] ], 
                    ( $datas['doc_date'] )? date( 'Y-m-d', strtotime( $datas['doc_date'] ) ) : "", $view 
                ); 
            ?>
            </div>
        </div>

    <?php if( ! $view ): ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'info', 
                    [ 'id'=>'', 'type'=>'label', 'label'=>'Please make sure Remittance Money Service Document posted to proceed:', 'required'=>false, 'attrs'=>[] ], 
                    '', 1 
                ); 
            ?>
            </div>
        </div>
    <?php endif; ?>

        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_bankin_servises', [], [ 'a.docno'=>'ASC' ], false, [ 'meta'=>[ 'sender_name', 'total_amount' ], 'usage'=>1, 'posting'=>1, 'selection'=>1, 'incl'=>$datas['ref_ids'] ] ), 'doc_id', [ 'docno', 'sender_name', 'total_amount' ] );
                
                wcwh_form_field( $prefixName.'[from_doc]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'From Doc No.', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict','from_doc'], 'placeholder'=>'Docno, Sender Name, Amount (RM)',
                        'options'=> $options
                    ], 
                    $datas['from_doc'], $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( $prefixName.'[to_doc]', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Until Doc No.', 'required'=>true, 'attrs'=>[], 'class'=>['select2Strict','to_doc'], 'placeholder'=>'Docno, Sender Name, Amount (RM)',
                        'options'=> $options
                    ], 
                    $datas['to_doc'], $view 
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

    <?php if( $view ): ?>
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
    <?php endif; ?>

    <?php if( $datas['doc_id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[doc_id]" value="<?php echo $datas['doc_id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>

<style>
    li.select2-results__option:empty {
    display: none;
}
</style>
<script>
jQuery(function($){
    var prefix = '<?php echo $args['prefix']; ?>';
    $( document ).on( 'change', 'select.from_doc', function()
    {
        var elem = $( this );
        var doc_id = elem.val();
        var doc_text = elem.find( "option:selected" ).text();
        var array_doc_text = doc_text.split(",");
        var currentNo = array_doc_text[0].split( prefix );
        currentNo = currentNo[1];

        var ids = [];
        elem.find( 'option' ).each( function( index ) 
        {
            var value = $( this ).val();
            if( value.length <= 0 ) return;

            var text = $( this ).html();
            var arrayText = text.split(",");
            var docno = arrayText[0];
            var running = docno.split( prefix );
            running = running[1];

            if( parseInt(running) >= parseInt(currentNo) )
            {
                ids.push( value );
            }
        });

        $( 'select.to_doc.select2Strict' ).select2({
            templateResult: function(option) {
                if( ids.length <= 0 || ( ids.length > 0 && $.inArray( option.id, ids ) >= 0 ) )
                {
                    return option.text;
                }
                return false;
            }
        });

        var value2 = $( 'select.to_doc' ).val();
        if( value2.length > 0 )
        {
            if( ids.length > 0 && $.inArray( value2, ids ) < 0 )
            {
                $( 'select.to_doc' ).val( ids[0] ).trigger('change');
            }
        }
    });


});
</script>