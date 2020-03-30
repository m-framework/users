<?php

namespace modules\users\models;

use m\model;
use m\registry;

class visitors_history extends model
{
    public $_table = 'visitors_history';
    protected $_sort = ['id' => 'DESC'];

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'visitor' => 'int',
        'referrer' => 'varchar',
        'request_uri' => 'varchar',
        'related_model' => 'varchar',
        'related_id' => 'int',
        'date' => 'timestamp',
    ];

    public function _before_save()
    {
        $this->referrer = str_replace(['http://' . registry::get('site')->host, 'https://' . registry::get('site')->host], '', $this->referrer);
        return true;
    }

    public static function set_history(array $history_data)
    {
        if (empty($history_data))
            return false;

        $history_data['referrer'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        $history_data['request_uri'] = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;

        $history_item = new visitors_history();
        $history_item->import((array)$history_data);
        return $history_item->save();
    }

    public function _autoload__visitor()
    {
        $this->_visitor = new visitors($this->visitor);
        return $this->_visitor;
    }
}