<?php
if ( !defined("ABSPATH") ) exit;

$datas = $args['data'];
$view = $args['view'];

$prefixName = ( $args['prefixName'] )? '_'.$args['prefixName'] : '_permission';
?>

<?php if( ! $args['get_content'] ): ?>
<form id="<?php echo $args['tplName']; ?>" class="needValidate <?php echo $args['new']; ?> <?php echo $args['view']; ?>" 
    action="" method="post" data-token="<?php echo $args['token'] ?>" data-hook="<?php echo $args['hook'] ?>" novalidate 
>
<?php endif; ?>

    <?php if( $args['new'] ): ?>
        <div class="form-row">
            <div class="col form-group">
            <?php 
                $user_args = ( $args['roles'] )? [ 'role__in' => $args['roles'] ] : array();
                $users = get_users( $user_args );

                if( $users )
                {
                    $opts = array();
                    foreach( $users as $i => $user )
                    {
                        $opts[$i] = array( 'id' => $user->ID, 'name' => $user->display_name );
                    }
                    $options = options_data( (array) $opts, 'id', [ 'name' ] );

                    wcwh_form_field( $prefixName.'[ref_id]', 
                        [ 'id'=>'', 'type'=>'select', 'label'=>'User', 'required'=>true, 'attrs'=>[], 'class'=>['select2'],
                            'options'=> $options
                        ], 
                        $datas['ref_id'], $view 
                    ); 

                    wcwh_form_field( $prefixName.'[scheme]', 
                        [ 'id'=>'', 'type'=>'hidden', 'label'=>'', 'required'=>false, 'attrs'=>[] ], 
                        'user', $view 
                    ); 
                }                
            ?>
            </div>
        </div>
    <?php endif; ?>

    <?php 
        if( $args['permission'] ){ 

            //Outlet Permission
            ?>
            <div class='form-rows-group'>
                <div class="form-row">
                    <div class="col form-group flex-row flex-align-center">
                    <?php 
                        wcwh_form_field( '', 
                            [ 'id'=>$section.'_title', 'label'=>'Outlet Permission', 'type'=>'checkbox', 'attrs'=>[ 'data-closest=".form-rows-group"', 'data-find=".inner-row-group"' ], 'class'=>[ 'tick-all' ]  ], 
                            '', $view 
                        ); 
                    ?>
                    </div>
                </div>
                <div class="inner-row-group">
                <?php 
                    $warehouses = apply_filters( 'wcwh_get_warehouse', [ 'status'=>1, 'visible'=>1 ], [], false, [] );//'not_indication'=>1
                    if( $warehouses )
                    {
                        foreach( $warehouses as $wh )
                        {
                            $right = 'access_wcwh_'.$wh['code'];
                            $ques = 'Access '.$wh['name'];
                            ?>
                            <div class="form-row">
                                <div class="col form-group flex-row flex-align-center">
                                <?php 
                                    wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                        [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                        ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                    ); 
                                ?>
                                </div>
                            </div>
                            <?php
                        }
                    }
                ?>
                </div>
            </div>
            <?php

            //Permission
            foreach( $args['permission'] as $section => $infos )
            {
                if( $infos['caps'] || $infos['segments'] )
                {
                    ?>
                    <div class='form-rows-group'>
                        <div class="form-row">
                            <div class="col form-group flex-row flex-align-center">
                            <?php 
                                wcwh_form_field( '', 
                                    [ 'id'=>$section.'_title', 'label'=>$infos['title'], 'type'=>'checkbox', 'attrs'=>[ 'data-closest=".form-rows-group"', 'data-find=".inner-row-group"' ], 'class'=>[ 'tick-all' ]  ], 
                                    '', $view 
                                ); 
                            ?>
                            </div>
                        </div>

                        <div class="inner-row-group">
                        <?php
                        foreach( $infos['caps'] as $right => $ques )
                        {
                        ?>
                            <div class="form-row">
                                <div class="col form-group flex-row flex-align-center">
                                <?php 
                                    wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                        [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                        ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                    ); 
                                ?>
                                </div>
                            </div>
                        <?php
                        }
                        ?>

                        <?php
                        if( $warehouses && $infos['outlet'] )
                        {   
                            ?>
                                <div class="form-row">
                                    <div class="col form-group flex-row flex-align-center">
                                    <?php 
                                        $right = "overide_{$section}_{$section}";
                                        $ques = 'Overide Outlet Permission';

                                        wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                            [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                            ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                        ); 
                                    ?>
                                    </div>
                                </div>
                            <?php
                            foreach( $warehouses as $wh )
                            {
                                $right = "access_{$wh['code']}_{$section}_{$section}";
                                $ques = 'View on '.$wh['name'];
                                ?>
                                    <div class="form-row">
                                        <div class="col form-group flex-row flex-align-center">
                                        <?php 
                                            wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                                [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                                ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                            ); 
                                        ?>
                                        </div>
                                    </div>
                                <?php
                            }
                        }
                        ?>
                        </div>

                        <?php
                        if( $infos['segments'] )
                        {
                        ?>
                            <hr>
                            <div class="segment-row-group">
                            <?php
                            foreach( $infos['segments'] as $segment => $innest )
                            {
                            ?>  
                                <h5><?php echo $innest['title'] ?></h5>
                                <div class="inner-row-group">
                                <?php 
                                    if( $innest['caps'] )
                                    foreach( $innest['caps'] as $right => $desc )
                                    {
                                        ?>
                                            <div class="form-row">
                                                <div class="col form-group flex-row flex-align-center">
                                                <?php 
                                                    wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                                        [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$desc, 'required'=>false, 'attrs'=>[] ], 
                                                        ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                                    ); 
                                                ?>
                                                </div>
                                            </div>
                                        <?php
                                    }

                                    if( $warehouses && $innest['outlet'] )
                                    {   
                                        ?>
                                            <div class="form-row">
                                                <div class="col form-group flex-row flex-align-center">
                                                <?php 
                                                    $right = "overide_{$segment}_{$section}";
                                                    $ques = 'Overide Outlet Permission';

                                                    wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                                        [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                                        ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                                    ); 
                                                ?>
                                                </div>
                                            </div>
                                        <?php
                                        foreach( $warehouses as $wh )
                                        {
                                            $right = "access_{$wh['code']}_{$segment}_{$section}";
                                            $ques = 'View on '.$wh['name'];
                                            ?>
                                                <div class="form-row">
                                                    <div class="col form-group flex-row flex-align-center">
                                                    <?php 
                                                        wcwh_form_field( $prefixName.'[permission]['.$right.']', 
                                                            [ 'id'=>$right, 'type'=>'checkbox', 'label'=>$ques, 'required'=>false, 'attrs'=>[] ], 
                                                            ( $datas['permission'] && in_array( $right, $datas['permission'] )? 1 : 0 ), $view 
                                                        ); 
                                                    ?>
                                                    </div>
                                                </div>
                                            <?php
                                        }
                                    }
                                ?>
                                </div>
                                <hr>
                            <?php
                            }
                            ?>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <?php
                }
            }
        } 
    ?>

    <?php if( $datas['id'] ): ?>
        <input type="hidden" name="<?php echo $prefixName; ?>[id]" value="<?php echo $datas['id']; ?>" />
    <?php endif; ?>

<?php if( ! $args['get_content'] ): ?>
    <input type="hidden" name="action" value="<?php echo $args['action']; ?>" />
</form>
<?php endif; ?>