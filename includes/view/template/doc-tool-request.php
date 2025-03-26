<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<?php $header = [ 'title'=>'Tools Requisition Forms']; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>
<style>
table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Times New Roman', Times, serif;
    font-size: 12px;
}
td,
th,
tr
 {
    border: 1px solid black;
}
tbody>tr {
    height: 20px;
}
p {
    
    font-size: 12px;
}
h2,
h3 {
    text-align: start;
}

@page
{
    size: A4;
    margin:20px 20px 20px 20px;
}

.page_break
{
    page-break-after:always;
}
</style>

<?php
    $datas = $args['detail'];
    
    $warehouse = $args['warehouse'];
?>

<?php
if($datas):
    $last_key = end(array_keys($datas));
    foreach($datas as $key => $value)
{
?>
<header>
<p style="text-align:right;">Printed On:
        <?php echo $value['print_on'] ?>
    </p>
    <p style="text-align:right;">Printed By:
        <?php echo $value['print_by'] ?>
    </p>
    <table>
        <tr style = "height:30px; border:0px;">
            <th style="width:70%; border:0px;" valign="bottom">
                <h2 style="margin-bottom:0px;"> <?php echo $warehouse['name']." (".$warehouse['code'].")"?></h2>
            </th>
            <th rowspan="2" style="border:0px">
                <?php $qrcode = apply_filters( 'qrcode_img_data', $value['docno'], 'M', 4, 4 ); ?>
                <img src="<?php echo $qrcode ?>"><br>
                <?php echo $value['docno']?>
            </th>
        </tr>
        <tr style="height:60px; border:0px;">
            <td style="border:0px;" valign="top">
                <h1 style="margin-top:0px;">REQUEST TOOLS & EQUIPMENT FORM</h1>
            </td>
        </tr>
    </table>
</header>
<div style="padding: 20px auto 20px auto">
    <table>
        <tr>
            <td style="width:30%">Received By (Name) :</td>
            <td> <?php echo $value['customer']['name']; ?></td>
        </tr>
        <tr>
            <td>IBS/Pers No. :</td>
            <td> <?php echo $value['customer']['uid']." / ".$value['customer']['serial']; ?></td>
        </tr>
        <tr>
            <td>Job/Position :</td>
            <td> <?php echo $value['customer']['cjob_name']; ?></td>
        </tr>
    </table>
</div>
<div>
    <p>Kindly provide the tool and equipment for the following:</p>
</div>
    <table>
        <thead>
            <tr>
                <th class="td" width="5%">No.</th>
                <th class="td leftered" width="10%">Item Group</th>
                <th class="td leftered" width="30%">Items</th>
                <th class="td" width="7%">Unit</th>
                <th class="td" width="7%">UOM</th>
                <th class="td" width="10%">Unit Price (RM)</th>
                <th class="td" width="10%">Total Price (RM)</th>
                <th class="td" width="10%">Instalment (Mth)</th>
                </tr>
        </thead>
        <?php 
        $count = sizeof($value['details']);
?>
        <tbody>
            <?php
            if( $value['details'] )
            foreach( $value['details'] as $i => $item )
            {
                $num = $i+1;
            ?>
                <tr>
                    <td class="td" style="text-align:center; height:20px;"><?php echo $num ?></td>
                    
                    <td class="td"><?php echo $item['grp_name'] ?></td>
                    
                    <td class="td"><?php echo $item['prdt_code']." - ".$item['item_name'] ?></td>
                    <td class="td centered"><?php echo $item['bqty'] ?></td>
                    <td class="td centered"><?php echo $item['uom'] ?></td>
                    <td class="td centered"><?php echo round_to( $item['sprice'], 2, 1, 1 ) ?></td>
                    <td class="td centered"><?php echo round_to( $item['sale_amt'], 2, 1, 1 ) ?></td>
                    <td class="td centered"><?php echo $item['period'] ?></td>
                </tr>
            <?php
            }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right; height:20px"><b>Total:</b></td>
                <td style="text-align:center;"><b><?php echo $value['total_qty'] ?></b></td>
                <td style="text-align:center;"></td>
                <td style="text-align:center;"></td>
                <td style="text-align:center;"><b><?php echo round_to( $value['total_amt'], 2, 1, 1 ) ?></b></td>
                <td class="td centered"></td>
            </tr>
        </tfoot>
    </table>
    <?php if( $value['remark'] ): ?>
        <p>Remark: <?php echo $value['remark']; ?></p>
    <?php endif; ?>
<?php
if($key != $last_key)
{
    echo "<div class='page_break'></div>";
}
}
 endif; ?>

<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>
 
<script>
	window.print();
</script>