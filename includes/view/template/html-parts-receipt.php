<?php
    $doc = $args['doc'];
    $register_ID = $args['register_ID'];
    
    $register = $args['register'];
    $register_name = $args['register_name'];

    $receipt_ID = $args['receipt_ID'];

    $receipt_style = $args['receipt_style'];
    $receipt_options = $args['receipt_options'];
    $attachment_image_logo = $args['attachment_image_logo'];
?>
<html>
<head>
    <meta charset="utf-8">
    <title><?php _e('Spare Parts Request', 'woocommerce-point-of-sale'); ?></title>
    <style>
        @media print {
        <?php if ($receipt_options['receipt_width'] == '0') { ?>
            .pos_receipt {
                min-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                background: transparent !important;
            }

        <?php } else { ?>
            .pos_receipt {
                width: <?php echo $receipt_options['receipt_width'] ?>mm;
                margin: auto;
                padding: 0;
                background: transparent !important;
            }

        <?php } ?>
            @page {
                margin: 0;
            }
        }
        @media screen {
            <?php if ($receipt_options['receipt_width'] == '0') { ?>
            .pos_receipt {
                min-width: 100%;
                width: 100%;
                margin: 0;
                padding: 0;
                background: #fff;
            }

        <?php } else { ?>
            .pos_receipt_body {
                background: #23282d;
            }
            .pos_receipt {
                width: <?php echo $receipt_options['receipt_width'] ?>mm;
                margin: auto;
                padding: 2mm;
                background: #fff !important;
            }
            .break:not(:last-child) {
                display: block;
                background: #23272c;
                height: 30px;
                width: calc(100% + 4mm);
                position: relative;
                margin: 0 -2mm;
                margin-bottom: 2mm;
            }
        .pos_receipt {
            height: min-content;
        }

        <?php } ?>
        }

        .pos_receipt, table.order-info, table.receipt_items, table.customer-info, 
        #pos_receipt_title, #pos_receipt_address, #pos_receipt_contact, #pos_receipt_header, #pos_receipt_footer, #pos_receipt_tax, #pos_receipt_info, #pos_receipt_items, 
        #pos_receipt_tax_breakdown, table.tax_breakdown, #pos_receipt_wifi, .display-socials,
        #print_voucher, table.voucher_breakdown{
            font-family: "Helvetica Neue",sans-serif;
            line-height: 1.25;
            font-size: 10px;
            background: transparent;
            color: #000000;
            box-shadow: none;
            text-shadow: none;
            margin: 0;
        }
        table.voucher_breakdown .serial{font-size:13px;}

        #pos_receipt_logo {
            text-align: center;
        }

        #print_receipt_logo {
            height: 50px;
            width: auto;
        }

        .pos_receipt h1,
        .pos_receipt h2,
        .pos_receipt h3,
        .pos_receipt h4,
        .pos_receipt h5,
        .pos_receipt h6 {
            margin: 0;
        }

        table.customer-info, table.order-info, table.receipt_items, table.tax_breakdown, table.voucher_breakdown {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        table.receipt_items tbody tr,
        table.receipt_items thead tr,
        table.order-info tbody tr {
            border-bottom: 1px dotted #eee;
        }
        table.order-info tbody tr:last-child {
            border: none;
        }

        table.receipt_items tfoot {
            border-top: 1px solid #000;
        }

        table.customer-info th, table.order-info th,
        table.customer-info td, table.order-info td, table.receipt_items td,
        table.tax_breakdown td, table.tax_breakdown th,
        table.voucher_breakdown td, table.voucher_breakdown th{
            padding: 2px 1px;
        }

        strong, b {
            font-weight: 600;
        }

        table.receipt_items thead th {
            padding: 5px 1px;
        }

        table.receipt_items td {
            vertical-align: top;
        }

        #pos_receipt_info {
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        table.order-info th {
            text-align: left;
            width: 33%;
            vertical-align: top;
        }

        table.receipt_items tr .column-product-image {
            text-align: center;
            white-space: nowrap;
        }

        table.receipt_items .column-product-image img {
            height: auto;
            margin: 0;
            max-height: 20px;
            max-width: 20px;
            vertical-align: middle;
            width: auto;
        }

        table.receipt_items tfoot td small.includes_tax {
            display: none;
        }

        table.receipt_items tfoot th {
            vertical-align: top;
            padding: 2px 1px;
        }

        table.receipt_items thead th {
            text-align: left;
        }
        table.tax_breakdown thead th:first-child,
        table.tax_breakdown tbody td:first-child, 
        table.voucher_breakdown thead th:first-child,
        table.voucher_breakdown tbody td:first-child{
            text-align: left !important;
        }

        table.receipt_items tfoot th,
        table.tax_breakdown tfoot th,
        table.tax_breakdown tbody td,
        table.tax_breakdown thead th,
        table.voucher_breakdown thead th,
        table.voucher_breakdown tbody td,{
            text-align: right;
        }

        table.receipt_items th:last-child,
        table.receipt_items td:last-child,
        table.tax_breakdown th:last-child,
        table.tax_breakdown td:last-child,
        table.voucher_breakdown th:last-child,
        table.voucher_breakdown td:last-child,
        th.product-price {
            text-align: right !important;
        }

        #pos_customer_info, #pos_receipt_title, #pos_receipt_logo, #pos_receipt_contact, #pos_receipt_tax, #pos_receipt_header, #pos_receipt_items, .display-socials, #pos_receipt_address, #pos_receipt_info, #pos_receipt_tax_breakdown, #pos_receipt_wifi {
            margin-bottom: 10px;
        }
        #pos_receipt_items {
            border-top: 1px solid #000;
        }
        #pos_receipt_header, #pos_receipt_title, #pos_receipt_footer {
            text-align: center;
        }

        #pos_receipt_barcode,
        #pos_receipt_tax_breakdown,
        #pos_voucher_section
        {
            border-top: 1px solid #000;
        }
        #pos_receipt_barcode #print_barcode img {
            height: 100px;
        }
        .attribute_receipt_value {
            line-height: 1.5;
            float: left;
        }

        .break {
            page-break-after: always;
        }

        .woocommerce-help-tip {
            display: none;
        }

        td.product-price,
        td.product-amount {
            text-align: right;
        }
        
        #print_voucher
        {
            padding-top:6px;
        }
        table.voucher_breakdown .serial{ letter-spacing:1px; }
        .receipt_items td.product-name{ font-size:9px; }
        .vempty{ height:80px; }
        .rightered{ text-align:right; }
    </style>

    <?php if (isset($receipt_style)) {
        ?>
        <style id="receipt_style">
            <?php
                foreach ($receipt_style as $style_key => $style) {
                    if ( isset($receipt_options[$style_key]) ){
                        $k = $receipt_options[$style_key];
                        if( isset( $style[$k] ) ){
                            echo $style[$k];
                        }
                    }
                }
            ?>

            <?php echo $receipt_options['custom_css']; ?>
            @media print {
            }
        </style>
        <?php
    }
    ?>
</head>

    <?php
        $str_find = array( 'currency' => $order->get_currency, 'symbol' => get_woocommerce_currency_symbol( $order->get_currency ) );
        $str_replace = array( 'currency' => '', 'symbol' => '' );
    ?>
     
    <body>
        <div class="pos_receipt_body">
            <div class="pos_receipt" id="pos_receipt">
                <div id="pos_receipt_title">
                    <?php echo $receipt_options['receipt_title']; ?>
                </div>
                <div id="pos_receipt_logo">
                    <img src="<?php echo $attachment_image_logo[0]; ?>"
                        id="print_receipt_logo" <?php echo (!$receipt_options['logo']) ? 'style="display: none;"' : ''; ?>>
                </div>
               
                <div id="pos_receipt_header">
                    Spare Parts Request Form
                </div>
                <div id="pos_receipt_info">
                    <table class="order-info">
                        <tbody>
                            <tr>
                                <th>Doc No.</th>
                                <td><?php echo $doc['docno']; ?></td>
                            </tr>
                        
                            <tr>
                                <th>Date</th>
                                <td>
                                    <?php 
                                        $format = isset($receipt_options['order_date_format']) && !empty($receipt_options['order_date_format']) ? $receipt_options['order_date_format'] : "jS F Y";
                                        echo date_i18n( $format, $doc['doc_date'] ); 
                                    ?>
                                </td>
                            </tr>

                            <tr>
                                <th>Requestor</th>
                                <td>
                                    <?php echo esc_html($doc['customer_serial']); ?>
                                    <?php echo "<br>".esc_html($doc['customer_name']); ?>
                                </td>
                            </tr>
                        
                        <?php if( $doc['remark'] ): ?>
                            <tr>
                                <th>Remark</th>
                                <td><?php echo wptexturize(str_replace("\n", '<br/>', $doc['remark'] )); ?></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="pos_receipt_items">
                    <table class="receipt_items">
                        <thead>
                        <tr>
                            <th><?php _e('Items', 'wc_point_of_sale'); ?></th>
                            <th><?php _e('Qty', 'wc_point_of_sale'); ?></th>
                            <th class="product-price"><?php _e('Price', 'wc_point_of_sale'); ?></th>
                            <th><?php _e('Total', 'wc_point_of_sale'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                    <?php
                        $items = $doc['details']; $total = 0;
                        foreach( $items as $i => $item ){
                            $total+= $item['sale_amt'];
                    ?>
                            <tr>
                                <td class="product-name">
                                    <strong><?php echo $name = esc_html($item['prdt_name']); ?></strong>
                                    <small class="product-sku"><?php echo ($item['sku']) ? '<br>' . esc_html($item['sku']) : ''; ?></small>
                                </td>
                                <td><?php echo $item['bqty']; ?></td>
                                <td class="product-price">
                                    <?php
                                        if (isset($item['sprice'])) {
                                            echo round_to( $item['sprice'], 2, true );
                                        }
                                    ?>
                                </td>
                                <td class="product-amount">
                                    <?php
                                        if (isset($item['sale_amt'])) {
                                            echo round_to( $item['sale_amt'], 2, true );
                                        }
                                    ?>
                                </td>
                            </tr>
                    <?php } ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row" colspan="3" class="rightered">
                                    Total (RM):
                                </th>
                                <td>
                                    <?php echo round_to( isset($doc['total_amount'])? $doc['total_amount'] : $total, 2, true ); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="pos_receipt_barcode">
                    <center>
                        <p id="print_barcode">
                            <br>
                            <?php $qrcode = apply_filters( 'qrcode_img_data', $doc['docno'], 'M' ); ?>
                            <img src="<?php echo $qrcode ?>" >
                        </p>
                    </center>
                </div>
                <?php
                ?>
                <div id="pos_receipt_footer">
                    
                </div>
            </div>
        </div>
    </body>
<p class="break">
</html>