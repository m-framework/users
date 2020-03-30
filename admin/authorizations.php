<?php

namespace modules\users\admin;

use m\module;
use m\view;
use modules\admin\admin\overview_data;

class authorizations extends module {

    public function _init()
    {
        view::set('content', overview_data::items(
            'modules\users\models\users_authorizations',
            [],
            [],
            $this->view->authorizations_overview,
            $this->view->authorizations_item
        ));
    }
}
