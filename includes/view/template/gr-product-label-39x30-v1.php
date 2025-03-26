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
                width: 39mm;
                height: 29mm;
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
                width: 39mm;
                height: 29mm;
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
        }
        p, article
        {
            font-size:10px;
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
            width:60px;
            height:60px;
            left:0;
            right:0;
            margin: auto;
        }
        .nowrap
        {
            width:36mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
    <div class="content-container centered">
        <p class="nowrap">{item}</p>
        <p>{serial}</p>
        <article class="placeqrbarcode" style="height: 17mm;">
        </article>
    </div>
</div>
<div class="page-break"></div>