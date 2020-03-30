<?php

namespace modules\users\models;

use m\model;

class users_socials extends model
{
    public $_table = 'users_socials';

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'profile' => 'int',
        'provider' => 'varchar',
        'social_id' => 'varchar',
        'name' => 'varchar',
        'email' => 'varchar',
        'social_page' => 'varchar',
        'sex' => 'int',
        'avatar' => 'text',
        'date' => 'timestamp',
    ];

    public function _autoload_user_info()
    {
        if (empty($this->profile)) {
            return false;
        }

        $this->user_info = users_info::call_static()->s([], ['profile' => $this->profile])->obj();
        return $this->user_info;
    }

    public function _autoload_user_name()
    {
        if (empty($this->profile)) {
            return '';
        }

        $this->user_name = $this->user_info->name;
        return $this->user_name;
    }

    public function _autoload_user_avatar()
    {
        if (empty($this->profile)) {
            return '';
        }

        $this->user_avatar = $this->user_info->avatar;
        return $this->user_avatar;
    }
}