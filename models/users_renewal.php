<?php

namespace modules\users\models;

use m\model;

class users_renewal extends model
{
    public $_table = 'users_renewal';
    public $__id = 'profile';

    public $profile;
    public $code;
    public $password;
    public $date;

    protected $fields = [
        'profile' => 'int',
        'code' => 'varchar',
        'password' => 'varchar',
        'status' => 'int',
        'date' => 'timestamp',
    ];

    public function _init()
    {
        return $this;
    }
}