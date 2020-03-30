<?php

namespace modules\users\admin;

use m\functions;
use m\module;
use m\view;
use m\i18n;
use m\registry;
use m\core;
use m\form;
use modules\admin\admin\overview_data;
use modules\pages\models\pages;
use modules\users\models\users_info;
use modules\users\models\users_permissions;
use modules\users\models\users_groups;

class permissions extends module {

    public function _init()
    {
//        \m\core::out($this->get);

        if ($this->alias == 'add' || (!empty($this->get->permissions) && $this->get->permissions == 'edit' && !empty($this->get->id))) {
            return $this->edit();
        }
        else if (!empty($this->get->permissions) && $this->get->permissions == 'delete' && !empty($this->get->id)) {
            return $this->delete();
        }

        view::set('content', overview_data::items(
            'modules\users\models\users_permissions',
            [],
            [],
            $this->view->permissions_overview,
            $this->view->permissions_item
        ));
    }

    private function edit()
    {
        if (!isset($this->view->{'permission_edit_form'})) {
            return false;
        }
        $item = new users_permissions(!empty($this->get->id) ? $this->get->id : null);

        if (!empty($item->id)) {
            view::set('page_title', '<h1><i class="fa fa-drivers-license-o"></i> *Edit permissions* `' . $item->name . '`</h1>');
            registry::set('title', i18n::get('Edit permissions'));

            registry::set('breadcrumbs', [
                '/' . $this->conf->admin_panel_alias . '/users' => '*Users*',
                '/' . $this->conf->admin_panel_alias . '/users/permissions' => '*Users permissions*',
                '' => '*Edit permissions*',
            ]);
        }
        else {
            view::set('page_title', '<h1><i class="fa fa-drivers-license-o"></i> *Add permissions*</h1>');
            registry::set('title', i18n::get('Add permissions'));

            registry::set('breadcrumbs', [
                '/' . $this->conf->admin_panel_alias . '/users' => '*Users*',
                '/' . $this->conf->admin_panel_alias . '/users/permissions' => '*Users permissions*',
                '/' . $this->conf->admin_panel_alias . '/users/permissions/add' => '*Add permissions*',
            ]);
        }

        if (empty($item->site)) {
            $item->site = $this->site->id;
        }

        new form(
            $item,
            [
                'profile' => [
                    'type' => 'autocomplete',
                    'field_name' => i18n::get('User'),
                    'options' => [
                        'model' => 'users_info',
                    ],
                ],
                'page' => [
                    'field_name' => i18n::get('Page'),
                    'related' => pages::call_static()->s(['id as value', 'name'],[],10000)->all(),
                ],
                'group' => [
                    'field_name' => i18n::get('Group'),
                    'related' => users_groups::get_options_arr(),
                ],
                'module' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Module'),
                ],
                'permission' => [
                    'type' => 'tinyint',
                    'field_name' => i18n::get('Permitted'),
                ],
            ],
            [
                'form' => $this->view->{'permission_edit_form'},
                'varchar' => $this->view->edit_row_varchar,
                'related' => $this->view->edit_row_related,
                'hidden' => $this->view->edit_row_hidden,
                'tinyint' => $this->view->edit_row_tinyint,
                'autocomplete' => $this->view->edit_row_autocomplete,
                'saved' => $this->view->edit_row_saved,
                'error' => $this->view->edit_row_error,
            ]
        );
    }

    private function delete()
    {
        $item = new users_permissions(!empty($this->get->id) ? $this->get->id : null);

        if (!empty($item->id) && !empty($this->user->profile) && $this->user->is_admin() && $item->destroy()) {
            core::redirect('/' . $this->conf->admin_panel_alias . '/users/permissions');
        }

        return false;
    }

    public function _ajax_suggestions()
    {
        if (empty($this->post->fields) || empty($this->post->fragment)) {
            core::out(['error' => 'empty important data']);
        }

        $fields_conditions = [];
        $fields = explode(',', $this->post->fields);
        foreach ($fields as $field)
        {
            if (!empty($field)) {

                if ($field == 'id' || $field == 'code') {
                    $fields_conditions[] = [$field => $this->post->fragment];
                }
                else {
                    $fields_conditions[] = [$field . " LIKE '%" . $this->post->fragment . "%'"];
                }
            }
        }

        $cond = [$fields_conditions];

        if (!empty($this->post->additional_conditions)) {
            foreach ($this->post->additional_conditions as $add_k => $additional_condition) {
                $cond[$add_k] = $additional_condition;
            }
        }

        switch ($this->post->model) {
            case 'users_info':
                $model = users_info::call_static();
        }

        if (empty($model)) {
            core::out(['error' => 'empty model']);
        }

        $suggestions = $districts = $regions = [];

        $users = $model->s([], $cond, [100])->all('object');

        if(!empty($users))
        foreach ($users as $user) {

            $suggestions[] = [
                'info' => $user->email . ', ' . $user->phone . ', ' . functions::beautiful_date($user->date),
                'label' => $user->name,
                'id' => $user->profile,
            ];
        }

        if (empty($suggestions)) {
            core::out(registry::get('db_logs'));
        }
        else {
            core::out($suggestions);
        }
    }
}
