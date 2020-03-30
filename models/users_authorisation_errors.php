<?php

namespace modules\users\models;

use m\model;

class users_authorisation_errors extends model
{
    public $_table = 'users_authorisation_errors';

    public $id;
    public $site;
    public $ip;
    public $attempt;
    public $expire;

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'ip' => 'varchar',
        'attempt' => 'int',
        'expire' => 'int',
    ];
}