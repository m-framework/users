<?php

namespace modules\users\models;

use m\model;

class editors extends model
{
    public $_table = 'editors';

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'profile' => 'int',
        'page' => 'int',
    ];
}