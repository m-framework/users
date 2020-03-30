<?php

namespace modules\users\admin;

use m\module;
use m\view;
use modules\admin\admin\overview_data;

class social extends module {

    public function _init()
    {
        view::set('content', overview_data::items(
            'modules\users\models\users_socials',
            [],
            [],
            $this->view->socials_overview,
            $this->view->socials_item
        ));
    }
}
