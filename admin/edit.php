<?php

namespace modules\users\admin;

use m\module;
use m\i18n;
use m\registry;
use m\view;
use m\form;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_groups;

class edit extends module {

    public function _init()
    {
        if (!isset($this->view->{'user_' . $this->name . '_form'})) {
            return false;
        }

        $item = new users(!empty($this->get->edit) ? $this->get->edit : null);

        if (!empty($item->profile)) {
            view::set('page_title', '<h1><i class="fa fa-list-alt"></i> *Edit a user* ' . ('`' . $item->name . '`') . '</h1>');
            registry::set('title', i18n::get('Edit a user'));
        }
        else {
            view::set('page_title', '<h1><i class="fa fa-list-alt"></i> *Add new user*</h1>');
            registry::set('title', i18n::get('Add new user'));
        }

//        if (empty($item->profile)) {
//            $item->profile = $item->generate_profile();
//        }

        if (empty($item->password) && !empty($this->post->password)) {
            $this->post->password = $item->__md5code($this->post->password);
        }

        if (empty($item->site)) {
            $item->site = $this->site->id;
        }

        new form(
            $item,
            [
                'group' => [
                    'field_name' => i18n::get('Group'),
                    'related' => users_groups::get_options_arr(),
                    'required' => 1,
                ],
                'first_name' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('First name'),
                    'required' => 1,
                ],
                'last_name' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Last name'),
                ],
                'email' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Email'),
                ],
                'phone' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Phone'),
                ],
                'login' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Login'),
                    'required' => 1,
                ],
                'password' => [
                    'type' => 'password',
                    'field_name' => i18n::get('Password'),
                ],
                'confirmed' => [
                    'type' => 'tinyint',
                    'field_name' => i18n::get('Confirmed'),
                ],
            ],
            [
                'form' => $this->view->{'user_' . $this->name . '_form'},
                'varchar' => $this->view->edit_row_varchar,
                'password' => $this->view->edit_row_password,
                'text' => $this->view->edit_row_text,
                'related' => $this->view->edit_row_related,
                'hidden' => $this->view->edit_row_hidden,
                'tinyint' => $this->view->edit_row_tinyint,
                'saved' => $this->view->edit_row_saved,
                'error' => $this->view->edit_row_error,
            ]
        );
    }
}