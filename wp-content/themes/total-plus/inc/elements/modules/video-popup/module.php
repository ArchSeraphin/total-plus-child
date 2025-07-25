<?php

namespace TotalPlusElements\Modules\VideoPopup;

use TotalPlusElements\Base\Module_Base;

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

class Module extends Module_Base {

    public function get_name() {
        return 'total-plus-video-popup';
    }

    public function get_widgets() {
        $widgets = [
            'VideoPopup',
        ];
        return $widgets;
    }

}
