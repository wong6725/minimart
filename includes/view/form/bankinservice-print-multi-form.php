<?php
if ( !defined("ABSPATH") ) exit;

?>

<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
    <div class='form-rows-group'>
        <h5>Print Option</h5>
        <div class="form-row">
            <div class="col form-group">
                <?php
                    $options = [ '0'=>'Include', '1'=>'Exclude' ];

                    wcwh_form_field( 'InExclude', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Include/Exclude Doc No.', 'required'=>true, 
                            'attrs'=>[], 'class'=>['select2'], 
                            'options'=>$options
                        ], 
                        ($datas['InExclude'])? $datas['InExclude'] : 0, $view 
                    ); 
                ?>
            </div>
            <div class="col form-group">
                <?php
                    $filter = ['wh_code'=>$this->warehouse['code'], 'doc_type'=>'bank_in'];
                    if( $args['seller'] ) $filter['seller'] = $args['seller'];
                    $filter['status'] = [1,6];
                    $docNo = apply_filters( 'wcwh_get_doc_header', $filter, [], false);
                  
                    $options = options_data( $docNo, 'doc_id', ['doc_id','docno']);   
                    wcwh_form_field( 'docID[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Doc No. (Include Default: all)','description'=>'Show Ready & Posted Doc Only', 'required'=>false, 'attrs'=>[], 'class'=>['select2', 'modalSelect'],
                            'options'=> $options, 'multiple'=>1 
                        ], 
                        $datas['docID'], $view 
                    ); 
                ?>
            </div>
            <div class="col form-group">
                <?php 
                    $options = [ 'all'=>'All','0'=>'Trashed', '1'=>'Ready', '6'=>'Posted' ];

                    wcwh_form_field( 'status[]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'Status', 'required'=>false, 
                            'attrs'=>[], 'class'=>['select2', 'modalSelect'], 'multiple'=>1, 
                            'options'=>$options
                        ], 
                        ($datas['status'])? $datas['status'] : '', $view 
                    ); 
                ?>
            </div>
        </div>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $options = [ 'default'=>'Default A4', 'receipt'=>'Receipt Paper' ];

                wcwh_form_field( 'paper_size', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Paper Size', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    ( $args['print_opt']['paper_size'] )? $args['print_opt']['paper_size'] : 'default', $view 
                ); 
            ?>
            </div>
            <div class="col form-group">
            <?php
                $options = [ '1'=>'HTML', '0'=>'PDF' ];

                wcwh_form_field( 'html', 
                    [ 'id'=>'', 'type'=>'select', 'label'=>'Print as', 'required'=>false, 
                        'attrs'=>[], 'class'=>[], 
                        'options'=>$options
                    ], 
                    ( $args['print_opt']['html'] )? $args['print_opt']['html'] : '1', $view 
                );
            ?>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="id" value="{id}" />
    <input type="hidden" name="action" value="{action}" />
    <input type="hidden" name="type" value="bank_in" />
    <input type="hidden" name="section" value="<?php echo $args['section']; ?>" />
    <input type="hidden" name="dbname" value="mnmart_ubb" />
</form>