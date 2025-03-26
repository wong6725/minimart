<?php
if ( !defined("ABSPATH") ) exit;

$prefixName = '_detail';
?>
<div id="print_label" class="pos_label printable">
    <style>
        @media screen
        {
            .pos_label 
            {
                width: 40mm;
                height: 50mm;
                margin: 0;
                padding: 0;
                border:1px solid #b9b9b9;
            }
        }
        @media print
        {
            @page
            {
                /*size: 39mm 29mm;*/
                margin: 0; 
                padding: 0;
            }
            .pos_label, body
            {
                width: 40mm;
                height: 50mm;
                margin: 0;
                padding: 0;
                position:relative;
                top:0;
                left:0;
            }
            .page-break
            { 
                margin:0;
                padding:0;
                height:0;
                page-break-after: always; 
            }
        }
        .content-container
        {   
            padding:0px;
            margin:0px;
            margin-top:1px;
        }
        p, article
        {
            font-size:12px;
            margin:0px;
            padding:0px;
        }
        p
        {
            line-height:1;
            font-weight: bold;
            padding-bottom:3px;
        }
        .centered
        {
            text-align: center;
        }
        article #barcode, article #qrcode 
        {
            width:100%;
            height:100%;
            text-align: center;
        }
        article #qrcode img
        {
            width:120px;
            height:120px;
            left:0;
            right:0;
            margin: auto;
        }
        .nowrap
        {
            width:38mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-top: 6px;
        }
    </style>
<?php if( $args['data'] ): ?>
    <div class="content-container centered">
        <p class="nowrap"><?php echo $args['data']['estate']; ?></p>
        <p>&nbsp;</p>
        <article class="placeqrbarcode" style="height: 30mm;">
            <?php
                $qrcode = apply_filters( 'qrcode_img_data', $args['data']['serial'], $args['quality'], $args['size'], $args['frame'] );
            ?>
            <svg style="display: none" id="barcode"></svg>
            <div style="" id="qrcode" title="<?php echo $args['data']['serial']; ?>">
                <canvas width="128" height="128" style="display: none;"></canvas>
                <img style="display: block;" src="<?php echo $qrcode ?>">
            </div>
        </article>
        <p>&nbsp;</p>
        <p class="nowrap"><?php echo $args['data']['name']; ?></p>
    </div>
<?php else: ?>
    <div class="content-container centered">
        <p class="nowrap">{estate}</p>
        <p>&nbsp;</p>
        <article class="placeqrbarcode" style="height: 30mm;">
        </article>
        <p>&nbsp;</p>
        <p class="nowrap">{name}</p>
    </div>
<?php endif; ?>
</div>
<div class="page-break"></div>