<?php

namespace modules\users\models;

use m\model;

class users_groups extends model
{
    public $_table = 'users_groups';

    protected $fields = [
        'id' => 'int',
        'name' => 'varchar',
    ];

    public static function get_options_arr()
    {
        return static::call_static()->s(['id AS value', 'name'], [], [1000])->all();
    }
}