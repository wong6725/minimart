<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>
<?php $header = [ 'title'=>'Customer ID Card']; ?>
<?php do_action( 'wcwh_get_template', 'template/doc-header.php', $header ); ?>
<style>
@page {
    size: A4;
    size:landscape;
    margin: 5mm 5mm 5mm 5mm;
}

@media print {
  footer {page-break-after: always;}
}

#body_content, .text, body, h1, h2, h3, h4, h5, h6, p, b, a, span, div, pre, sup, sub, td, th, li
{
    line-height: 1;
}

#body_content table td, #body_content table th{
    padding:2px;
}
#body_content table td th, #body_content table td th{
    padding:0px;
}
td{
    text-align: start;
}

table.main_sect{
    border: 1px solid black;
    text-align: center;
    border-collapse: collapse;
    table-layout: fixed;
    width: 95mm;
    height:76mm;
    font-size: 10px;
}
.main_sect th, .main_sect td {
    border-right: 1px solid black;
    padding: 0 10px 5px 10px;
}
.inner_sect th, .inner_sect td {
    border: 0px;
}
.inner_sect.front td, .inner_sect.front td b, .inner_sect.front td span, .inner_sect.front th span, 
.inner_sect.back td, .inner_sect.back td b, .inner_sect.back td span, .inner_sect.back th span
{
    font-size: 10px;
}

.row {
  display: flex;
  padding-top: 1px;
  /* margin-left:-5px;
  margin-right:-5px; */
}

.column {
    /* flex:1; */
    padding-right:1px;
}

#card_title{font-size:10px;}
#card_image{
    height:4cm;
    display: block;
    margin-left: auto;
    margin-right: auto;
    margin-bottom:1px;
}

#qrcode{
    width: 2.5cm;
    height:2.5cm;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.desc, .desc p, .desc li
{
    font-size: 9px;
    padding-bottom: 2px;
}
.desc ol 
{
    padding: 0;
    padding-left: 10px;
    margin: 0;
}

.nowrap
{
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display:block;
}
.inner_sect.front .nowrap
{
    max-width:2.5cm;
}

</style>

<?php
$warehouse = apply_filters( 'wcwh_get_warehouse', ['indication'=>1], [], true, [ 'usage'=>1 ] );
$wh_code = explode( "-", $warehouse['code'] );
$wh_code = $wh_code[1];
foreach($args['customer'] as $key => $value)
{
    if($key == 0 || $key%3 == 0)
    {
        echo '<div class="row">';
        
    }

    ?>
    <div class="column">
    <table class="main_sect">
        <tr>
            <td valign="top">
                <table class="inner_sect front" style="width:100%;">
                    <th style="text-align:center;padding-top:0;" colspan="2"><b id="card_title">KAD IDENTITI PEKERJA</b></th>
                    <tr>
                        <td colspan="2">
                            <img id="card_image" src="<?php echo wcwh_render_attachment( $value['attach_id'],['print'=>1] ); ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 33%;" valign="top">Nama<span style="float:right;">:</span></td>
                        <td style="width: 65%;" valign="top"><span class=""><?php echo $value['name']?></span></td>
                    </tr>
                    <tr>
                        <td valign="top">FAS/IBS<span style="float:right;">:</span></td>
                        <td valign="top"><span class="nowrap"><?php echo $value['ibs']?></span></td>
                    </tr>
                    <tr>
                        <td valign="top">ID<span style="float:right;">:</span></td>
                        <td valign="top"><span class="nowrap"><?php echo trim( ltrim( substr( $value['uid'], -6 ), "0" ) ); ?></span></td>
                    </tr>
                    <tr>
                        <td valign="top">Pasport<span style="float:right;">:</span></td>
                        <td valign="top"><span class="nowrap"><?php echo $value['passport']?></span></td>
                    </tr>
                    <tr>
                        <td valign="top">Fasa<span style="float:right;">:</span></td>
                        <td valign="top"><span class="nowrap"><?php echo $value['phase']?></span></td>
                    </tr>
                    <tr>
                        <td valign="top">Pekerjaan<span style="float:right;">:</span></td>
                        <td valign="top"><span class="nowrap"><?php echo $value['job_name']?></span></td>
                    </tr>
                </table>
            </td>
            <td valign="top">
                <table class="inner_sect back" style="width:100%;">
                    <th style="text-align:right;padding-top:0;" colspan="2">
                        <b id="card_title"><?php echo $wh_code; ?></b>
                    </th>
                    <tr>
                        <td style="text-align:left;padding-top:2px;" colspan="2">
                            <?php $qrcode = apply_filters( 'qrcode_img_data', $value['serial'], "M", 4.4, 2 ); ?>
                            <center>
                                <span style="font-size:9px;"><?php echo $value['serial']?></span>
                            </center> 
                            <img id="qrcode" src="<?php echo $qrcode ?>" >
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align:left;padding-top:2px;"><span>Tarikh Dikeluarkan :</span></td>
                        <td style="text-align:left;padding-top:2px;"><span><?php echo current_time( "Y.m.d" ); ?></span></td>
                    </tr>
                    <tr>
                        <td class="desc" colspan="2">
                            <br>
                            <p><b class="desc">Peringatan:-</b></p>
                            <ol>
                                <li>Sila pastikan kad anda dijaga dan disimpan dengan baik.</li>
                                <li>Wajib membawa kad semasa hari gaji.</li>
                                <li>Anda tidak <strong>DIBENARKAN</strong> untuk mengambil gaji sekiranya anda gagal untuk menunjukkan kad anda.</li>
                                <li>Caj RM10.00 akan dikenakan sebagai denda atau ganti rugi untuk bagi yang kehilangan kad atau tidak dikembali kepada pejabat.</li>
                            </ol>
                        </td>
                    </tr>
                </table>
            </td>
        <tr>
    </table>
      </div>
<?php
$curr = $key+1;
if(($curr)%3 == 0 && $key!=0)
{
    echo '</div>';
}
if(($curr)%6 == 0 && $key!=0)
{
    echo '<footer></footer>';
}
}
?>
<?php do_action( 'wcwh_get_template', 'template/doc-footer.php' ); ?>
<script>
    window.print();
</script>