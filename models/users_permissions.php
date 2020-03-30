<?php

namespace modules\users\models;

use m\model;
use modules\pages\models\pages;

class users_permissions extends model
{
    public $_table = 'users_permissions';

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'profile' => 'int',
        'page' => 'int',
        'group' => 'int',
        'module' => 'varchar',
        'permission' => 'tinyint',
        'language' => 'int',
    ];

    public function _autoload_name()
    {
        if (!empty($this->profile)) {
            $this->name = users_info::call_static()->s([], ['profile' => $this->profile])->obj()->name;
            return $this->name;
        }
        else if (!empty($this->group)) {
            $this->name = users_groups::call_static()->s([], ['id' => $this->group])->obj()->name;
            return $this->name;
        }
    }

    public function _autoload_user_name()
    {
        if (!empty($this->profile)) {
            $this->user_name = users_info::call_static()->s([], ['profile' => $this->profile])->obj()->name;
            return $this->user_name;
        }
    }

    public function _autoload_group_name()
    {
        if (!empty($this->group)) {
            $this->group_name = users_groups::call_static()->s([], ['id' => $this->group])->obj()->name;
            return $this->group_name;
        }
    }

    public function _autoload_page_name()
    {
        if (!empty($this->page)) {
            $this->page_name = pages::call_static()->s([], ['id' => $this->page])->obj()->name;
            return $this->page_name;
        }
    }

    public function _autoload_page_path()
    {
        if (!empty($this->page)) {
            $this->page_path = pages::call_static()->s([], ['id' => $this->page])->obj()->get_path();
            return $this->page_path;
        }
    }


}