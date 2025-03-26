<?php
if (!defined("ABSPATH"))
    exit;

if (!class_exists("WCWH_Colors")) 
{
    class WCWH_Colors
    {
        public $className = "Colors";

        protected $colors_code_collection = [
            'RGBA' => [
                'red' => 'rgba( 255, 0, 0, 0.5 )',
                'blue' => 'rgba( 0, 0, 255, 0.5 )',
                'gold' => 'rgba( 255, 215, 0, 0.5 )',
                'purple' => 'rgba( 128, 0, 128, 0.5 )',
                'violet' => 'rgba( 238, 130, 238, 0.5 )',
                'brown' => 'rgba( 139, 69, 19, 0.5 )',
                'green' => 'rgba( 0, 255, 0, 0.5 )',
                'orange' => 'rgba( 255, 140, 0, 0.5 )',
                'light_blue' => 'rgba( 173,216,230, 0.5 )',
                'crimson' => 'rgba( 220, 20, 60, 0.5 )',
                'dark_blue' => 'rgba( 0, 0, 139, 0.5 )',
                'dark_violet' => 'rgba( 148, 0, 211, 0.5 )',
                'pink' => 'rgba( 255, 192, 203, 0.5 )',
                'aqua_marine' => 'rgba( 127, 255, 212, 0.5 )',
                'aqua' => 'rgba(0,255,255,0.5)',
                'yellow' => 'rgba( 255, 255, 0, 0.5 )',
                'dark_green' => 'rgba( 0, 128, 0, 0.5 )',
                'sky_blue' => 'rgba( 0, 191, 255, 0.5 )',
                'dark_pink' => 'rgba( 255, 20, 147, 0.5 )',
                'light_brown' => 'rgba( 210, 105, 30, 0.5 )',
                'light_green' => 'rgba( 144, 238, 144, 0.5 )',
                'yellow_green' => 'rgba( 154, 205, 50, 0.5 )',
                'salmon' => 'rgba( 250, 128, 114, 0.5 )',
                'turquoise' => 'rgba(64,224,208,0.5)',
                'slate_blue' => 'rgba(106,90,205,0.5)',
                'medium_purple' => 'rgba(147,112,219,0.5)',
                'hot_pink' => 'rgba(255,105,180,0.5)',
                'antique_white' => 'rgba(250,235,215,0.5)',
                'misty_rose' => 'rgba(255,228,225,0.5)',
                'tan' => 'rgba(210,180,140,0.5)',
                'wheat' => 'rgba(245,222,179,0.5)',
                'slate gray' => 'rgba(112,128,144,0.5)',
                'light_sea_green' => 'rgba(32,178,170,0.5)',
                'khaki' => 'rgba(240,230,140,0.5)',
                'firebrick' => 'rgba(178,34,34,0.5)',
                'dark_khaki' => 'rgba(189,183,107,0.5)',
                'spring_green' => 'rgba(0,255,127,0.5)',
                'dark_olive_green' => 'rgba(85,107,47,0.5)',
                'midnight_blue' => 'rgba(25,25,112,0.5)',
                'pale_violet_red' => 'rgba(219,112,147,0.5)',
                'indigo' => 'rgba(75,0,130,0.5)',
                'papaya_whip' => 'rgba(255,239,213,0.5)',
                'rosy_brown' => 'rgba(188,143,143,0.5)',
                'royal_blue' => 'rgba(65,105,225,0.5)',
                'blue_violet' => 'rgba(138,43,226,0.5)'
            ],
            'HEX' => [
                'red' => '#FF0000',
                'blue' => '#0000FF',
                'gold' => '#FFD700',
                'purple' => '#800080',
                'violet' => '#EE82EE',
                'brown' => '#8B4513',
                'green' => '#00FF00',
                'orange' => '#FF8C00',
                'light_blue' => '#ADD8E6',
                'crimson' => '#DC143C',
                'dark_blue' => '#00008B',
                'dark_violet' => '#9400D3',
                'pink' => '#FFC0CB',
                'aqua_marine' => '#7FFFD4',
                'aqua' => '#00FFFF',
                'yellow' => '#FFFF00',
                'dark_green' => '#008000',
                'sky_blue' => '#00BFFF',
                'dark_pink' => '#FF1493',
                'light_brown' => '#D2691E',
                'light_green' => '#90EE90',
                'yellow_green' => '#9ACD32',
                'salmon' => '#FA8072',
                'turquoise' => '#40E0D0',
                'slate_blue' => '#6A5ACD',
                'medium_purple' => '#9370DB',
                'hot_pink' => '#FF69B4',
                'antique_white' => '#FAEBD7',
                'misty_rose' => '#FFE4E1',
                'tan' => '#D2B48C',
                'wheat' => '#F5DEB3',
                'slate_gray' => '#708090',
                'light_sea_green' => '#20B2AA',
                'khaki' => '#F0E68C',
                'firebrick' => '#B22222',
                'dark_khaki' => '#BDB76B',
                'spring_green' => '#00FF7F',
                'dark_olive_green' => '#556B2F',
                'midnight_blue' => '#191970',
                'pale_violet_red' => '#DB7093',
                'indigo' => '#4B0082',
                'papaya_whip' => '#FFEFD5',
                'rosy_brown' => '#BC8F8F',
                'royal_blue' => '#4169E1',
                'blue_violet' => '#8A2BE2'
            ],
        ];

        public function __construct()
        {

        }

        public function __destruct()
        {
            
        }

        public function get_colors_list($type = '', $count = 0, $args = [])
        {
            if (!$type || !$count)
                return [];

            $colors_group_by_type[] = $this->colors_code_collection[$type];

            $color_group = [];
            $count_num = 0;

            foreach ($colors_group_by_type as $color_type => $colors) {
                foreach ($colors as $name => $code) {
                    if ($count_num < $count) {
                        if ($args['get_type'] == 'color_code_only')
                            $color_group[] = $code;
                        else
                            $color_group[] = [$name => $code];
                        $count_num++;
                    } else
                        break;
                }
            }

            return $color_group;
        }

        public function get_color($type = '', $name = '')
        {
            if (!$type || !$name)
                return '';

            $color_selected = $this->$colors_code_collection[$type][$name];

            return $color_selected;
        }
    }
}