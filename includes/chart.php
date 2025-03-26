<?php
if ( !defined("ABSPATH") )
    exit;
    
if ( !class_exists( "WCWH_Chart" ) )
{

class WCWH_Chart
{
    public $className = "Chart";

    protected $chart_args = [
        'legend_pos'        => 'top',
        'title'             => '',
        'unit'              => '',
        'BeginAtZero'       => true,
        'line_fill'         => false,
        'line_stepped'      => false,
        'mixed_chart_type'  => [ 0, 'bar' ],
        'x_axis_title'      => '',
        'y_axis_title'      => '',
        'line_tension'      => 0,
        'colors_num'        => 1,
        'horizontal_bar'    => false,
        'labels_sort'       => true,
        'interaction_mode'  => 'point',
        'tooltip'    => '',
        'legend_callback'=>false,
    ];

    public function __construct()
    {

    }

    public function __destruct()
    {
        
    }

    public function includes()
    {
        if( ! class_exists( 'WCWH_Colors' ) ) include_once( WCWH_DIR . "/includes/colors.php" ); 
    }
    /*
    $in_Dates_data = [
            [
                'label' => 'seller a item a',
                'data' => [
                    //Order can be not arranged but the x[0]( 2021/02/21 ) must be the label of  y[0]( 20 )
                    'x' => [ '2021/02/21', '2021/03/12', '2021/04/01', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12', '2021/01/02', '2020/09/31' ],
                    'y' => [ 20, 50, 20, 30, 43, 28, 25, 10, 80 ]
                ]
            ],
            [
                'label' => 'seller a item b',
                'data' => [
                    'x' => [ '2021/02/21', '2021/03/15', '2021/04/02', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12', '2021/01/02' ],
                    'y' => [ 22, 33, 22, 45, 32, 48, 75, 25 ]
                ]
            ],
            [
                'label' => 'seller a item c',
                'data' => [
                    'x' => [ '2021/02/21', '2021/03/15', '2021/04/02', '2021/05/06', '2021/06/07', '2021/07/05', '2021/08/12' ],
                    'y' => [ 25, 28, 12, 55, 22, 48, 75 ]
                ]
            ],
        ];
    */
    public function chart_data_conversion( $datas = [], $type='', $chart = [] )
    {
        if( ! $datas || ! $chart ) return [];

        //$start = round(microtime(true) * 1000);

        //Decode the special chars for the datas passed in likes &amp;
        foreach ( $datas as $i => $data)
            foreach ($data as $key => $value)
                $datas[$i][$key] = htmlspecialchars_decode( $datas[$i][$key] );

        //Set chart type
        $chartType = $type;
        if( $type == 'mixed' ) $chartType = 'line';
        //pd($datas);
        $groups = $chart['group'];
        $data_group = [];
        $num = 0;
        $regrouped_datas = [];
        $flag_using_const_data_group = false;

        //Check if the user pass data group as an array
        if( is_array( $groups ) )
        {
            $flag_using_const_data_group = true;
        }
        else
        {
            $data_group = explode( ",", $groups );

            $data_group_detail1 = ''; 
            $data_group_detail2 = ''; 
            $group_details_1 = [];
            $group_details_2 = []; 
            $chart_data_group_name = [];

            $data_group_detail1 = $data_group[0];
            $group_details_1 = explode( "-", $data_group_detail1 );

            if( !is_null( $data_group[1] ) )
            {
                $data_group_detail2 = $data_group[1];
                $group_details_2 = explode( "-", $data_group_detail2 );
            }

            foreach ( $datas as $i => $data) {

                $str_Group_Detail_1 = [];
                $str_Group_Detail_2 = [];
                
                if( count( $data_group ) == 2 )
                {
                    $data['chart_data_group_name'] = $data[ $group_details_1[0] ];

                    if( count( $group_details_1 ) > 1 )
                    {
                        foreach ($group_details_1 as $key => $value) {
                            if( $data[ $value ] ) $str_Group_Detail_1[] = $data[ $value ]; 
                        }
                        $chart_data_group_name = implode( " - ", $str_Group_Detail_1 );
                        $data['chart_data_group_name'] = $chart_data_group_name;
                    }

                    if( count( $group_details_2 ) > 1 )
                    {
                        foreach ($group_details_2 as $key => $value) {
                            if( $data[ $value ] ) $str_Group_Detail_2[] = $data[ $value ]; 
                        }
                        $chart_data_group_name = implode( " - ", $str_Group_Detail_2 );
                        $data['chart_data_group_name'] .= ', ' . $chart_data_group_name;
                    }
                    else
                        $data['chart_data_group_name'] .= ', ' . $data[ $group_details_2[0] ]; 

                    $regrouped_datas[ $data[ $group_details_1[0] ] ][ $data[ $group_details_2[0] ] ][$i] = $data;
                }
                else if( count( $data_group ) == 1 )
                {
                    $data['chart_data_group_name'] = $data[ $group_details_1[0] ];

                    if( count( $group_details_1 ) > 1 )
                    {
                        foreach ($group_details_1 as $key => $value) {
                            if( $data[ $value ] ) $str_Group_Detail_1[] = $data[ $value ];
                        }
                        $chart_data_group_name = implode( " - ", $str_Group_Detail_1 );
                        $data['chart_data_group_name'] = $chart_data_group_name;
                    }

                    $regrouped_datas[ $data[ $group_details_1[0] ] ][$i] = $data;
                }
            }
        }

        $chart_data = [];

        switch( $chartType )
        {
            case 'line':
            case 'bar':
                if( $flag_using_const_data_group && ( strcmp( $chart['y'], '~chart_group' ) == 0 || strcmp( $chart['x'], '~chart_group' ) == 0 ) )
                {
                    foreach ($groups as $group_key => $group_name)
                    {

                        $label = '';
                        $x = [];
                        $y = [];

                        foreach ( $datas as $key => $val)
                        {
                            if( empty( $label ) )$label = $group_name;

                            $arrAxisX = explode( '-', $chart['x'] );
                            //pd($arrAxisX);
                            if( count( $arrAxisX ) > 1 )
                            {
                               
                                $tempAxisX = [];
                                foreach ($arrAxisX as $k => $v) {
                                    $tempAxisX[] = $val[ $v ];

                                }

                                $strAxisX = implode( ' - ', $tempAxisX );

                                $x[] = $strAxisX;
                            }
                            else if( strcmp( $chart['x'], '~chart_group' ) == 0 ){
                                $x[] = $val[ $group_key ];
                                
                            }
                            else
                               
                                $x[] = $val[ $chart['x'] ];

                            $arrAxisY = explode( '-', $chart['y'] );
                            if( count( $arrAxisY ) > 1 )
                            {
                                $tempAxisY = [];
                                foreach ($arrAxisY as $k => $v) {
                                    $tempAxisY[] = $val[ $v ];
                                }

                                $strAxisY = implode( ' - ', $tempAxisY );

                                $y[] = $strAxisY;
                            }
                            else if( strcmp( $chart['y'], '~chart_group' ) == 0 ){
                                $y[] = $val[ $group_key ];
                                
                            }
                            else
                                
                               $y[] = $val[ $chart['y'] ];
                        }

                        array_push( $chart_data, [ 'label' => $label, 'data' => [ 'x' => $x, 'y' => $y ] ] );
                    }
                }
                
                
                else if( count( $data_group ) == 2 && $flag_using_const_data_group == false )
                {
                    foreach( $regrouped_datas as $i => $datas )
                    {
                        foreach( $datas as $data => $data_info )
                        {
                            $label = '';
                            $x = [];
                            $y = [];

                            foreach ($data_info as $key => $val) 
                            {
                                if( empty( $label ) )$label = $val[ 'chart_data_group_name' ];

                                $arrAxisX = explode( '-', $chart['x'] );
                                if( count( $arrAxisX ) > 1 )
                                {
                                    $tempAxisX = [];
                                    foreach ($arrAxisX as $k => $v) {
                                        $tempAxisX[] = $val[ $v ];
                                    }

                                    $strAxisX = implode( ' - ', $tempAxisX );

                                    $x[] = $strAxisX;
                                }
                                else $x[] = $val[ $chart['x'] ];

                                $arrAxisY = explode( '-', $chart['y'] );
                                if( count( $arrAxisY ) > 1 )
                                {
                                    $tempAxisY = [];
                                    foreach ($arrAxisY as $k => $v) {
                                        $tempAxisY[] = $val[ $v ];
                                    }

                                    $strAxisY = implode( ' - ', $tempAxisY );

                                    $y[] = $strAxisY;
                                }
                                else $y[] = $val[ $chart['y'] ];
                            }

                            array_push( $chart_data, [ 'label' => $label, 'data' => [ 'x' => $x, 'y' => $y ] ] );
                        }
                    }
                }
                else if( count( $data_group ) == 1 && $flag_using_const_data_group == false )
                {
                    foreach( $regrouped_datas as $i => $data )
                    {
                        $label = '';
                        $x = [];
                        $y = [];

                        foreach ($data as $key => $val) 
                        {
                            if( empty( $label ) )$label = $val[ 'chart_data_group_name' ];

                            $arrAxisX = explode( '-', $chart['x'] );
                            if( count( $arrAxisX ) > 1 )
                            {
                                $tempAxisX = [];
                                foreach ($arrAxisX as $k => $v) {
                                    $tempAxisX[] = $val[ $v ];
                                }

                                $strAxisX = implode( ' - ', $tempAxisX );

                                $x[] = $strAxisX;
                            }
                            else $x[] = $val[ $chart['x'] ];
                            
                            $arrAxisY = explode( '-', $chart['y'] );
                            if( count( $arrAxisY ) > 1 )
                            {
                                $tempAxisY = [];
                                foreach ($arrAxisY as $k => $v) {
                                    $tempAxisY[] = $val[ $v ];
                                }

                                $strAxisY = implode( ' - ', $tempAxisY );

                                $y[] = $strAxisY;
                            }
                            else $y[] = $val[ $chart['y'] ];
                        }

                        array_push( $chart_data, [ 'label' => $label, 'data' => [ 'x' => $x, 'y' => $y ] ] );
                    }
                }
                break;
            case 'pie':
            case 'doughnut':
            case 'polarArea':
                if( $chart['x']==""||$chart['x']=='~chart_group')
                {
                    if(is_array($chart['y']))
            {
                // foreach ($chart['y'] as $group_key => $group_name)
                //     {
                //         $labels = [];
                //         $y = [];

                //     foreach( $regrouped_datas as $i => $data )
                //     {
                //         foreach ($data as $key => $val) 
                //         {
                //             $labels[] = $val[ 'chart_data_group_name' ];
                //             $y[] = $val[ $group_key];
                //         }
                //     }
                // $chart_data[] = [ 'labels' => $labels, 'data' => [ 'y' => $y ] ]; 
                        
                //     }
                        
                foreach ( $regrouped_datas as $i => $data)
                    {
                        //array_push($labels,$i."-".$group_name);
                        $y = [];

                    foreach( $chart['y'] as $group_key => $group_name)
                    {
                        foreach ($data as $key => $val) 
                        {
                            $labels[] = $val[ 'chart_data_group_name' ]."-".$group_name;
                            $y[] = $val[ $group_key];
                        }
                    }
                $chart_data[] = [ 'labels' => $labels, 'data' => [ 'y' => $y ] ]; 
                        
                    }

                

            } else{
                $labels = [];
                $y = [];
                foreach( $regrouped_datas as $i => $data )
                {
                    foreach ($data as $key => $val) 
                    {
                        $labels[] = $val[ 'chart_data_group_name' ];
                        $y[] = $val[ $chart['y'] ];
                    }
                }
                $chart_data[] = [ 'labels' => $labels, 'data' => [ 'y' => $y ] ]; 

            }
                break;

                }else{
                    break;
                }
                
                
            default:
                break;
        }

        //$end = round(microtime(true) * 1000);

        //echo $end-$start;
        //pd($chart_data);
        return $chart_data;
    }
    
    public function chart_generator( $datas = [], $type = '', $args = [] )
    {
        if( ! $datas ) return [];

        //Set chart type
        $chartType = $type;
        if( $type == 'mixed' ) $chartType = 'line';

        //Convert and set chartData
        $chartData = [
            'labels' => [],
            'datasets' => [],
        ];

        $chartArgs = [];

        $labels_axis = 'x';

        $horizontal_bar = is_null( $args['horizontal_bar'] ) ? $this->chart_args['horizontal_bar'] : $args['horizontal_bar'];

        if( $horizontal_bar )//----horizontal bar chart at y axis, set indexAxis to y;
        {
            $labels_axis = 'y';
            $chartArgs['options']['indexAxis'] = 'y';
        }

        $not_labels_axis = $labels_axis == 'x' ? 'y' : 'x';

        $labels_sort = is_null( $args['labels_sort'] ) ? $this->chart_args['labels_sort'] : $args['labels_sort'];

        $chart_flag = !is_null( $datas[0]['data']['x'] ) ? true : false;//-----pos sales by item category/ sort by amount(RM)

        //Sort and get the rearranged labels
        $rearranged_labels = [];
        if( $chart_flag )
        {
            foreach( $datas as $dataset )
            {
                for( $i=0; $i<count($dataset['data'][$labels_axis]); $i++ )
                {
                    $rearranged_labels[] = $dataset['data'][$labels_axis][$i];      
                }
            }
            $rearranged_labels = array_unique( $rearranged_labels );
            if( $labels_sort )
                sort($rearranged_labels);
        }
        else
        {
            //$rearranged_labels = $datas[0]['labels']; //Set the labels for the pie chart( consider all pie chart generated has only one dataset )  
            for($i=0;$i<count($datas);$i++)
            {
               $rearranged_labels= $datas[$i]['labels'];
            }
            
            $rearranged_labels = array_unique( $rearranged_labels);
            
            
        }

        $chartData['labels'] = $rearranged_labels;
        //---------jeff------//
        $temp_chartData = [];
        $mixed = is_null( $args['mixed'] ) ? '' : $args['mixed'];
        $mixed_Data = is_null( $args['mixed_Data'] ) ? '' : $args['mixed_Data'];
        //------jeff----//

        //Sort and get dataset with label and rearranged datas
        foreach ( $datas as $dataset ) 
        {
            if( $chart_flag )
            {
                $rearranged_data = [];
                $tempArray = [];
                $label = $dataset['label'];

                for( $i=0; $i<count($dataset['data'][$labels_axis]); $i++ )
                    $tempArray[$dataset['data'][$labels_axis][$i]] = $dataset['data'][$not_labels_axis][$i];

                if( $labels_sort )
                    ksort( $tempArray );

                $labels_axis_keys = array_keys( $tempArray );

                for( $i=0; $i<count($dataset['data']['x']); $i++ )  
                    array_push( $rearranged_data, [ $labels_axis => strval( $labels_axis_keys[$i] ), $not_labels_axis => $tempArray[$labels_axis_keys[$i]] ] );
                    $a=labels_axis_keys[$i];
                array_push( $chartData['datasets'], [ 'label' => $label, 'data' => $rearranged_data] );

                
                //-------jeff----//
                
                    if($mixed_Data)
                    {
                        $mixed = ($mixed)? $mixed : $chartType;
                        if(in_array($label,$mixed_Data))
                        {
                             array_push( $temp_chartData, [ 'label' => $label.' '.$mixed, 'data' => $rearranged_data, 'type' => $mixed] );
                        }

                    }
                    else
                    {
                        array_push( $temp_chartData, [ 'label' => $label.' '.$mixed, 'data' => $rearranged_data, 'type' => $mixed ] );
                    }
                
                //---jeff---//
            }
            else
            {
                array_push( $chartData['datasets'], [ 'label' => $dataset['labels'], 'data' => $dataset['data']['y'] ] );   
            }
        }

        //-----jeff-------///
        if($mixed)
        {
            for($i=0; $i<count($temp_chartData); $i++)
            {
                array_push( $chartData['datasets'], $temp_chartData[$i] );
            }
        }
        //------------------jeff----------//////

        //Convert and set chart arguments
        $chartArgs['options']['interaction_mode'] = is_null( $args['interaction_mode'] ) ? $this->chart_args['interaction_mode'] : $args['interaction_mode'];
        $chartArgs['options']['plugin-legend-pos'] = is_null( $args['legend_pos'] ) ? $this->chart_args['legend_pos'] : $args['legend_pos'];
        $chartArgs['options']['plugin-title'] = is_null( $args['title'] ) ? [ true, $this->chart_args['title'] ] : [ true, $args['title'] ];
        $chartArgs['options']['XValue'] = is_null( $args['x_axis_title'] ) ? $this->chart_args['x_axis_title'] : $args['x_axis_title'];
        $chartArgs['options']['YValue'] = is_null( $args['y_axis_title'] ) ? $this->chart_args['y_axis_title'] : $args['y_axis_title'];
        $chartArgs['options']['legend_callback'] = is_null( $args['legend_callback'] ) ? $this->chart_args['legend_callback'] : $args['legend_callback'];
        $chartArgs['count'] = ( $args['count'] ) ? $args['count']: 0;
        //-----jeff---//
        $chartArgs['options']['x_axis_stacked'] = is_null( $args['x_axis_stacked'] ) ? false :  $args['x_axis_stacked'];
        //---eff---//
        $chartArgs['options']['y_axis_stacked'] = is_null( $args['y_axis_stacked'] ) ? false :  $args['y_axis_stacked'];
        $chartArgs['options']['Unit'] = is_null( $args['unit'] ) ? $this->chart_args['unit'] : $args['unit'];
        $chartArgs['options']['BeginAtZero'] = is_null( $args['BeginAtZero'] ) ? $this->chart_args['BeginAtZero'] : $args['BeginAtZero'];

        if( $type == 'mixed' )
        {
            $chartArgs['dataType'] = is_null( $args['mixed_chart_type'] ) ? $this->chart_args['mixed_chart_type'] : $args['mixed_chart_type'];
            $chartArgs['stackGroup'] = is_null( $args['stackGroup'] )? '':$args['stackGroup'] ;

        }elseif($type=='bar')
        {
            $chartArgs['stackGroup'] = is_null( $args['stackGroup'] )? '':$args['stackGroup'] ;
        }
            

        if( $chartType == 'line' )
        {
            $chartArgs['fill'] = is_null( $args['line_fill'] ) ? $this->chart_args['line_fill'] : $args['line_fill'];
            $chartArgs['stepped'] = is_null( $args['line_stepped'] ) ? $this->chart_args['line_stepped'] : $args['line_stepped'];
            $chartArgs['lineTension'] = is_null( $args['line_tension'] ) ? $this->chart_args['line_tension'] : $args['line_tension'];
        }

        $this->includes();
        $Inst = new WCWH_Colors();
       
        $chartArgs['colors'] = is_null( $args['colors_num'] ) ? $Inst->get_colors_list( 'RGBA', $this->chart_args['colors_num'], [ 'get_type' => 'color_code_only' ] ) : $Inst->get_colors_list( 'RGBA', $args['colors_num'], [ 'get_type' => 'color_code_only' ] );

        $chartArgs['options']['tooltip'] = is_null( $args['tooltip'] ) ? $this->chart_args['tooltip'] : $args['tooltip'];

        //Set chart information
        $chartInfo = [
            'chartData' => $chartData,
            'chartType' => $chartType,
            'chartArgs' => $chartArgs
        ];

        return $chartInfo;
    }
}

}