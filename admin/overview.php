<?php

namespace modules\users\admin;

use m\module;
use m\view;
use m\i18n;
use m\config;
use modules\admin\admin\overview_data;
use modules\pages\models\pages;

class overview extends module {

    public function _init()
    {
        view::set('content', overview_data::items(
            'modules\users\models\users',
            [],
            [[['site' => $this->site->id], ['site' => null]]],
            $this->view->overview,
            $this->view->overview_item
        ));
    }
}
