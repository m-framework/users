<?php

namespace modules\users\admin;

use m\module;
use m\core;
use modules\users\models\users;

class delete extends module {

    public function _init()
    {
        $item = new users(!empty($this->get->delete) ? $this->get->delete : null);

        if (!empty($item->profile) && !empty($this->user->profile) && (int)$item->profile !== (int)$this->user->profile
            && $this->user->is_admin() && $item->destroy()) {
            core::redirect('/' . $this->conf->admin_panel_alias . '/users');
        }
    }
}
