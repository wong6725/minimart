<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>

    <div class='form-rows-group'>
        <h5>Filter</h5>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'from_date', 
                    [ 'id'=>'from_date', 'type'=>'text', 'label'=>'From Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ],  '', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                wcwh_form_field( 'to_date', 
                    [ 'id'=>'to_date', 'type'=>'text', 'label'=>'To Date', 'required'=>false, 'attrs'=>[ 'data-dd-format="Y-m-d"' ], 'class'=>['doc_date', 'picker'] ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>
		
		<div class="form-row">
            <div class="col form-group">
                <?php
					$note_actions = ['2'=>'Debit Note', '1'=>'Credit Note'];
                    wcwh_form_field( 'note_action[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Credit/Debit Note', 'required'=>false, 'attrs'=>[], 'class'=>['select2','modalSelect'],
                            'options'=> $note_actions,'multiple'=>1
                        ], 
	                    isset( $this->filters['note_action'] )? $this->filters['note_action'] : '', $view 
                    ); 
				?>
            </div>
            <div class="col form-group scheme_ref client_code">
            <?php 
                $options = options_data( apply_filters( 'wcwh_get_supplier', [ 'status'=>1 ], [], false, [] ), 'code', [ 'code', 'name' ] );
                
                wcwh_form_field( 'supplier_code', 
                    [ 'id'=>'supplier_code', 'type'=>'select', 'label'=>'Supplier', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    '', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php 
                $options = [ 1=>'Ready', 6=>'Posted', 9=>'Completed'];
                
                wcwh_form_field( 'status', 
                    [ 'id'=>'supplier_code', 'type'=>'select', 'label'=>'Status', 'required'=>false, 'attrs'=>[], 'class'=>['select2'],
                        'options'=> $options
                    ], 
                    '', $view 
                ); 
            ?>
            </div>
        </div>
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