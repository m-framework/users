<?php

namespace modules\users\models;

use m\model;

/**
 * This is the model class for table "users_balances_history".
 *
 * @property integer $id
 * @property integer $site
 * @property integer $balance
 * @property float $amount
 * @property string $date
 * @property string $comment
 */
class users_balances_history extends model
{
    public $_table = 'users_balances_history';
    protected $_sort = ['id' => 'DESC'];

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'balance' => 'int',
        'amount' => 'float',
        'date' => 'timestamp',
        'comment' => 'varchar',
    ];
}
