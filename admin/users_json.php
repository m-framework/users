<?php

namespace modules\users\admin;

use m\module;
use m\core;
use m\registry;
use m\i18n;
use modules\users\models\users;
use modules\users\models\users_info;
use modules\users\models\visitors;

class users_json extends module {

    public function _init()
    {
        $arr = [
            'data' => [[],[]],
            'data_captions' => [],
            'lines_captions' => [
                i18n::get('New users'),
                i18n::get('Users authorisations'),
            ],
            'colors' => [
                '#1a6590',
                '#e71776',
            ]
        ];

        registry::set('is_ajax', true);

        $last_date = users_info::call_static()->s(['date'], ['site' => $this->site->id, ], [1], ['date' => 'DESC'])->one();

        $from_date = strtotime('-7 days', empty($last_date) ? time() : strtotime(substr($last_date, 0, 10)));
        
        $to_date = date('Y-m-d 00:00:00', empty($last_date) ? time() : strtotime('+ 7 days', strtotime($last_date)));
                
        /**
         * Get new users
         */
        $items = users_info::call_static()
            ->select(
                ['date'],
                ['users' => ['profile' => 'profile']],
                ['users_info.site' => $this->site->id, 'users_info.date' => ['between' => [date('Y-m-d 00:00:00', $from_date), $to_date]], 'users.confirmed' => 1],
                [],
                ['date' => 'DESC'],
                [100000]
            )
            ->all();
            
        /**
        if (!empty($items) && is_array($items))
            foreach ($items as $item) {
                $date = date('d.m', strtotime(substr($item['date'], 0, 10)));
                $_date = date('Y-m-d', strtotime(substr($item['date'], 0, 10)));
                $date_pos = array_search($date, $arr['data_captions']);
                if ($date_pos == false) {
                    $arr['data']['0'][$_date] = 1;
                    $arr['data_captions'][$_date] = $date;
                }
                else {
                    $arr['data']['0'][$date_pos] += 1;
                }
            }

        /**
         * Get users authorisations
         */
        $items2 = users::call_static()
            ->s(
                ['last_visit'],
                ['site' => $this->site->id, 'last_visit' => ['between' => [date('Y-m-d 00:00:00', $from_date), $to_date]], 'confirmed' => 1],
                [100000],
                ['last_visit' => 'DESC']
            )
            ->all();
        
        //core::out($this->db_logs);
        
        if (!empty($items2) && is_array($items2))
            foreach ($items2 as $item) {
                $date = date('d.m', strtotime(substr($item['last_visit'], 0, 10)));
                $_date = date('Y-m-d', strtotime(substr($item['last_visit'], 0, 10)));

                if (!isset($arr['data']['1'][$_date])) {
                    $arr['data']['1'][$_date] = 1;
                    $arr['data_captions'][$_date] = $date;
                }
                else {
                    $arr['data']['1'][$_date] += 1;
                }
            }


        for ($day = $from_date; $day <= strtotime($to_date); $day = $day+86400) {
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
