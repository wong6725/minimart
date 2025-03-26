<?php
//Steven written
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php
    $filters = $args['filters'];
    $date_from = $filters['from_date'];
    $date_to = $filters['to_date'];
    if( ! $filters['from_date'] || ! $filters['to_date'] )
    {
        $date_from = current_time( 'Y-m-d' );
        $date_to = current_time( 'Y-m-d' );
    }

    $date_from = date( 'Y-m-d', strtotime( $date_from ) );
    $date_to = date( 'Y-m-d', strtotime( $date_to ) );
	
	$def_from = date( 'm/d/Y', strtotime( $date_from ) );
	$def_to = date( 'm/d/Y', strtotime( $date_to ) );
?>
    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'from_date', 
                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'From Date', 'required'=>false, 'class'=>['doc_date', 'picker'],
						'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_from.'"' ]
					],  $date_from, $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_date', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'To Date', 'required'=>false, 'class'=>['doc_date', 'picker'], 
						'attrs'=>[ 'data-dd-format="Y-m-d"', 'data-dd-default-date="'.$def_to.'"' ]
					], $date_to, $view 
                ); 
            ?>
            </div>
        </div>
		
        <div class="form-row">
    		<div class="col form-group">
                <?php
                    $filter = [];
                    if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_customer', $filter ), 'id', [ 'code', 'uid', 'name' ], '', [ 'guest'=>'Guest' ] );
                    
                    wcwh_form_field( 'customer[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'By Customer', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                            'options'=> $options, 'multiple'=>1
                        ], 
                        $filters['customer'], $view 
                    ); 
                ?>
            </div>


            <div class="col form-group">
                <?php
                    $filter = [];
                    if( $filters['seller'] ) $filter['seller'] = $filters['seller'];
                    $options = options_data( apply_filters( 'wcwh_get_item', $filter, [], false, [ 'uom'=>1, 'usage'=>1, 'needTree'=>1, 'treeOrder'=>['breadcrumb_code','asc'] ] ), 'id', [ 'code', '_uom_code', 'name' ], '' );
                        
                    wcwh_form_field( 'product[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'By Item', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                            'options'=> $options, 'multiple'=>1
                        ], 
                        $filters['product'], $view 
                    ); 
                ?>
            </div>
        </div>
        
        <?php
            wcwh_form_field( 'seller', 
                [ 'id'=>'seller', 'type'=>'hidden', 'required'=>false, 'attrs'=>[], 'class'=>[] ], 
                $filters['seller'], $view 
            ); 
        ?>
    </div>

    <div class='form-rows-group'>
        <h5>Export Option</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'xlsx'=>'Excel .xlsx', 'xls'=>'Excel .xls', 'csv'=>'Excel .csv', 'txt'=>'Text File .txt' ];

                wcwh_form_field( 'file_type', 
                    [ 'id'=>'file_type', 'type'=>'select', 'label'=>'File Type', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    'xlsx', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = [ ','=>',', '\t'=>'tab', ';'=>';', '|'=>'|', '||'=>'||' ];

                wcwh_form_field( 'delimiter', 
                    [ 'id'=>'delimiter', 'type'=>'select', 'label'=>'Delimiter', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 'description'=>'Apply for file with .csv or .txt extension',
                        'options'=>$options
                    ], 
                    ',', $view 
                ); 
            ?>
            </div>
            <div class="col form-group flex-row flex-align-center">
            <?php 
                wcwh_form_field( 'header', 
                    [ 'id'=>'header', 'type'=>'checkbox', 'label'=>'First Row Header', 'required'=>false, 'attrs'=>[] ], 
                    '1', $view 
                ); 
            ?>
            </div>
        </div>
    </div>

	<input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
</form>