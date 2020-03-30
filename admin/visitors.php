<?php

namespace modules\users\admin;

use m\i18n;
use m\module;
use m\view;
use m\core;
use m\config;
use m\registry;
use modules\admin\admin\overview_data;

class visitors extends module {

    public function _init()
    {
        if (!config::get('allow_visitors')) {
            return view::set('content', $this->view->div_notice->prepare([
                'text' => i18n::get('Visitors are disallowed in config file. Check parameter `allow_visitors`')
            ]));
        }

        if (!empty($this->get->visitors) && $this->get->visitors == 'history' && !empty($this->get->id)) {
            return $this->history();
        }
		
		config::set('per_page', 200);

        view::set('content', overview_data::items(
            'modules\users\models\visitors',
            [],
            ['site' => $this->site->id],
            $this->view->visitors_overview,
            $this->view->visitors_item,
            [
                'sort' => ['id' => 'DESC']
            ]
        ));
    }

    private function history()
    {
        if ($this->get->visitors !== 'history' || empty($this->get->id)) {
            core::redirect('/' . $this->conf->admin_panel_alias . '/visitors');
        }

        config::set('per_page', 200);

        view::set('page_title', '<h1><i class="fa fa-clock-o"></i> *Visitor history*</h1>');
        registry::set('title', '*Visitor history*');

        registry::set('breadcrumbs', [
            '/' . $this->conf->admin_panel_alias . '/users' => '*Users*',
            '/' . $this->conf->admin_panel_alias . '/users/visitors' => '*Visitors*',
            '' => '*Visitor history*',
        ]);

        view::set('content', overview_data::items(
            'modules\users\models\visitors_history',
            [],
            ['visitor' => $this->get->id, 'site' => $this->site->id],
            $this->view->visitors_history_overview,
            $this->view->visitors_history_item
        ));

        return true;
    }
}
