<?php

namespace modules\users\models;

use m\functions;
use m\model;
use m\registry;
use m\config;

class users_info extends model
{
    public $_table = 'users_info';
    public $_sort = ['profile' => 'DESC'];
    public $__id = 'profile';

    protected $fields = [
        'profile' => 'int',
        'site' => 'int',
        'first_name' => 'varchar',
        'middle_name' => 'varchar',
        'last_name' => 'varchar',
        'avatar' => 'varchar',
        'cover' => 'varchar',
        'city' => 'varchar',
        'email' => 'varchar',
        'phone' => 'varchar',
        'phone_2' => 'varchar',
        'phone_3' => 'varchar',
        'phone_4' => 'varchar',
        'website' => 'varchar',
        'gender' => 'tinyint',
        'description' => 'varchar',
        'page' => 'varchar',
        'date' => 'timestamp',
    ];

    public function _autoload_name()
    {
        return $this->name = trim($this->first_name . ' ' . $this->last_name);
    }

    public function _override_avatar()
    {
        return $this->avatar = empty($this->avatar) || !is_file(config::get('root_path') . $this->avatar) ?
            registry::get('template_dir') . 'img/no-avatar.png' : $this->avatar; 
    }

    public static function get_users_options_arr()
    {
        $arr = [];

        $users = static::call_static()->s(['profile', 'first_name', 'last_name'], [], [10000])->all();

        if (!empty($users)) {
            foreach ($users as $user) {
                $arr[] = [
                    'value' => $user['profile'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name'])
                ];
            }
        }

        return $arr;
    }

    public function _autoload_beauty_date()
    {
        if (empty($this->date)) {
            return '';
        }
        $time = strtotime($this->date);
        $this->beauty_date = date('Y', $time) !== date('Y') ? strftime('%e %b %Y %H:%M', $time)
            : strftime('%e %b %H:%M', $time);
        return $this->beauty_date;
    }

    public function get_beautiful_phone($n = null)
    {
        $phone_k = empty($n) ? 'phone' : 'phone_' . $n;

        if (empty($this->{$phone_k})) {
            return '';
        }

        $phone = $this->{$phone_k};

        $cleaned_phone = functions::clear_phone($this->{$phone_k});

        if (!empty($cleaned_phone) && $cleaned_phone !== $phone) {
            $phone = $this->{$phone_k} = $cleaned_phone;
            $this->save();
        }

        // Ukraine
        if (substr($phone, 0, 2) == '38') {
            return '+' . substr($phone, 0, 2) . ' (' . substr($phone, 2, 3) . ') ' . substr($phone, 5, 3) . '-' . substr($phone, 8, 2) . '-' . substr($phone, 10, 2);
        }
        // KZ, RU
        else if (substr($phone, 0, 1) == '7') {
            return '+' . substr($phone, 0, 1) . ' (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7, 2) . '-' . substr($phone, 9, 2);
        }
    }
}