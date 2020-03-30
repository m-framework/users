<?php

namespace modules\users\admin;

use m\module;
use m\core;
use modules\users\models\visitors;

class delete_visitor extends module {

    public function _init()
    {
        $item = new visitors(!empty($this->get->delete_visitor) ? $this->get->delete_visitor : null);

        if (!empty($item->date)) {
            $item->destroy();
        }

        core::redirect('/' . $this->conf->admin_panel_alias . '/users/visitors');
    }
}
