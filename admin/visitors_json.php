<?php

namespace modules\users\admin;

use m\module;
use m\core;
use m\registry;
use m\i18n;
use modules\users\models\users;
use modules\users\models\users_info;
use modules\users\models\visitors;
use modules\users\models\visitors_history;

class visitors_json extends module {

    public function _init()
    {
        $arr = [
            'data' => [[],[]],
            'data_captions' => [],
            'lines_captions' => [
                i18n::get('New visitors'),
                i18n::get('Pages visits'),
            ],
            'colors' => [
                '#20780e',
                '#0bb6c3',
            ],
        ];

        registry::set('is_ajax', true);

        $last_date = visitors::call_static()->s(['date'], ['site' => $this->site->id, ], [1], ['date' => 'DESC'])->one();

        $from_date = strtotime('-10 days', empty($last_date) ? time() : strtotime(substr($last_date, 0, 10)));

        /**
         * Get visitors
         */
        $visitors = visitors::call_static()
            ->s(['id', 'date'],
                ['site' => $this->site->id, 'date' => ['>' => date('Y-m-d 00:00:00', $from_date)]],
                [10000],
                ['date' => 'DESC'])
            ->all();

        $visitors_ids = [];

        if (!empty($visitors) && is_array($visitors))
            foreach ($visitors as $visitor) {
                $date = date('d.m', strtotime(substr($visitor['date'], 0, 10)));
                $_date = date('Y-m-d', strtotime(substr($visitor['date'], 0, 10)));

                if (!isset($arr['data']['0'][$_date])) {
                    $arr['data']['0'][$_date] = 1;
                    $arr['data_captions'][$_date] = $date;
                }
                else {
                    $arr['data']['0'][$_date] += 1;
                }

                $visitors_ids[] = $visitor['id'];
            }


        /**
         * Get visitors history (pages visit)
         */
        $visitors_history = visitors_history::call_static()
            ->s(['date'],
                ['site' => $this->site->id, 'date' => ['>' => date('Y-m-d 00:00:00', $from_date)], 'visitor' => $visitors_ids],
                [10000],
                ['date' => 'DESC'])
            ->all();

        if (!empty($visitors_history) && is_array($visitors_history))
            foreach ($visitors_history as $visitor_history) {
                $date = date('d.m', strtotime(substr($visitor_history['date'], 0, 10)));
                $_date = date('Y-m-d', strtotime(substr($visitor_history['date'], 0, 10)));

                if (!isset($arr['data']['1'][$_date])) {
                    $arr['data']['1'][$_date] = 1;
                    $arr['data_captions'][$_date] = $date;
                }
                else {
                    $arr['data']['1'][$_date] += 1;
                }
            }


        for ($day = $from_date; $day <= strtotime(date('Y-m-d')); $day = $day+86400) {
            $day_date = date('d.m', $day);
            $date = date('Y-m-d', $day);

            if (!isset($arr['data']['0'][$date])) {
                $arr['data']['0'][$date] = 0;
                $arr['data_captions'][$date] = $day_date;
            }

            if (!isset($arr['data']['1'][$date])) {
                $arr['data']['1'][$date] = 0;
            }
        }

        ksort($arr['data']['0']);
        ksort($arr['data']['1']);
        ksort($arr['data_captions']);

        $arr['data']['0'] = array_values($arr['data']['0']);
        $arr['data']['1'] = array_values($arr['data']['1']);
        $arr['data_captions'] = array_values($arr['data_captions']);

        core::out((object)$arr);
    }
}
