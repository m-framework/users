<?php

namespace modules\users\admin;

use m\module;
use m\view;
use modules\admin\admin\overview_data;

class groups extends module {

    public function _init()
    {
        view::set_css($this->module_path . '/css/groups_overview.css');

        view::set('content', overview_data::items(
            'modules\users\models\users_groups',
            [],
            [],
            $this->view->groups_overview,
            $this->view->groups_item
        ));
    }
}
