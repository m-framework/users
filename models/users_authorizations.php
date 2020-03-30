<?php

namespace modules\users\models;

use m\model;
use m\registry;

class users_authorizations extends model
{
    public $_table = 'users_authorizations';
    public $__id = 'profile';

    protected $fields = [
        'profile' => 'int',
        'authorize' => 'varchar',
        'ip' => 'varbinary',
        'expire' => 'int',
        'date' => 'timestamp',
    ];

    public function _before_save()
    {
        $this->ip = inet_pton(registry::get('ip'));
        return true;
    }

    public function get_ip()
    {
        return !empty($this->ip)
        && filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6) == false ? inet_ntop($this->ip)
            : $this->ip;
    }

    public function _autoload_user_name()
    {
        if (!empty($this->profile)) {
            $this->user_name = users_info::call_static()->s([], ['profile' => $this->profile])->obj()->name;
            return $this->user_name;
        }
    }

    public function _autoload_ip_address()
    {
        $this->ip_address = $this->get_ip();
        return $this->ip_address;
    }
}
