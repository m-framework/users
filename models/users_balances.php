<?php

namespace modules\users\models;

use m\config;
use m\model;

/**
 * This is the model class for table "users_balances".
 *
 * @property integer $id
 * @property integer $site
 * @property integer $user
 * @property integer $type
 * @property float $amount
 * @property integer $currency
 */
class users_balances extends model
{
    public $_table = 'users_balances';

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'user' => 'int', // profile
        'type' => 'tinyint', // 1 - balance, 2 - bonus, etc.
        'amount' => 'float',
        'currency' => 'int',
    ];

    public function _autoload_history()
    {
        return $this->history = users_balances_history::call_static()
            ->s([], ['site' => $this->site, 'balance' => $this->id], [99999])
            ->all('object');
    }

    public function set($amount, $comment = null)
    {
        $this->amount += floatval($amount);
        $this->save();

        users_balances_history::call_static()->i([
            'site' => $this->site,
            'balance' => $this->id,
            'amount' => floatval($amount),
            'date' => date('Y-m-d H:i:s'),
            'comment' => $comment
        ]);

        return $this;
    }

    public function _autoload_beautiful_amount()
    {
        return $this->beautiful_amount = (empty($this->amount) ? '0' : $this->amount)
            . ' ' . config::get('currencies')[$this->currency];
    }
}
