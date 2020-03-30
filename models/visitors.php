<?php

namespace modules\users\models;

use m\core;
use m\model;
use m\config;
use m\registry;
use m\logs;

class visitors extends model
{
    public $_table = 'visitors';
    protected $_sort = ['id' => 'DESC'];

    public static $_current_visitor;

    private static $allowed_bots = ['AdsBot-Google','Google-Adwords','facebookexternalhit/','SkypeUriPreview','Wget',
        'Twitterbot/','LinkedInBot','Slackbot','Google-Site-Verification','Sitemap','Outlook','bingbot/','CFNetwork',
        'Googlebot','YandexBot','M-SEO_bot','SiteCheckerBot','Thunderbird/','validator.w3.org','vkShare',
        'archive.org_bot','Google Page Speed Insights','Google Favicon','Google-Structured-Data-Testing-Tool',
        'MxToolbox'];

    private static $disabled_bots = ['spider','ltx71','FunWebProducts','ia_archiver','NETCRAFT',
        'SeznamBot','Baiduspider','Slurp','Dataprovider','DotBot','Uptimebot',
        'zgrab','libwww','Crawler','ips-agent','dfgdfg36fsdfsgh','brokenlinkcheck.com','MJ12bot','AhrefsBot','Mail.RU',
        'Exabot','DuckDuckGo','Go-http-client','SemrushBot','Python/','Nimbostratus-Bot/','Vagabondo/','Scrapy/',
        'BDFetch','ZoomBot','python-','Java/','Uptime/','urllib/','curl/','AhrefsBot/','Wappalyzer','LinkpadBot/',
        '$ua.tools.random()','7Siters/','Indy Library','Barkrowler/','Navicat','SafeDNSBot','CCBot/','alphaseobot'];

    private static $disabled_ips = [
        '23.238.115.114',
    ];

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'user' => 'int',
        'ip' => 'varbinary',
        'user_agent' => 'varchar',
        'date' => 'timestamp',
    ];

    public function _before_save()
    {
        $this->ip = inet_pton(registry::get('ip'));
        $this->user_agent = empty($_SERVER['HTTP_USER_AGENT']) ? null : $_SERVER['HTTP_USER_AGENT'];
        //exit(print(!empty($this->ip)));
        if (empty($this->site) && !empty(registry::get('site')->id)) {
            $this->site = registry::get('site')->id;
        }
        return true;
    }

    public function _before_destroy()
    {
        visitors_history::call_static()->d(['site' => $this->site, 'visitor' => $this->id], [1000000]);
        return true;
    }

    public function get_ip()
    {
		return !empty($this->ip) && filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == false
            && in_array(strlen($this->ip), [4,16]) ? inet_ntop($this->ip) : $this->ip;
    }
	
    public function _autoload_ip_address()
    {
        $this->ip_address = $this->get_ip();
        return $this->ip_address;
    }

    public static function detect_bots()
    {
        if (!empty($_SERVER['argv'])) {
            return true;
        }

//        if (empty($_SERVER['HTTP_USER_AGENT'])) {
//            die ('Indexation for bots will be soon');
//        }

        if (!empty(static::$disabled_ips) && in_array(registry::get('ip'), static::$disabled_ips)) {
            logs::set('Disabled for IP: ' . registry::get('ip'));
            die ('Indexation for bots will be soon');
        }

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            foreach (static::$allowed_bots as $allowed_bot) {
                if ($_SERVER['HTTP_USER_AGENT'] == $allowed_bot) {
                    return true;
                }
            }
        }

        if (!empty($_SERVER['HTTP_USER_AGENT']))
        foreach (static::$disabled_bots as $bot_fragment) {
            if (!(stripos($_SERVER['HTTP_USER_AGENT'], $bot_fragment) === false)) {
                logs::set('Disabled for bot: ' . $bot_fragment);
                die ('Indexation for bots will be soon');
            }
        }

        return true;
    }

    public static function init_visitor(array $visitor_data)
    {
        if (!config::has('allow_visitors')) {
            return false;
        }

        if (
            empty($visitor_data)
            || !empty($_SERVER['argv'])
            || empty($visitor_data['site'])
            || empty($_SERVER['HTTP_USER_AGENT'])
            || empty($_SERVER['REQUEST_URI'])
            || empty($_SERVER['HTTP_HOST'])
            || !(strrpos($_SERVER['HTTP_USER_AGENT'], 'Yandex') === false)
            || !(strrpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot') === false)
        ) {
            return false;
        }

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            foreach (static::$allowed_bots as $bot_fragment) {
                if (!(stripos($_SERVER['HTTP_USER_AGENT'], $bot_fragment) === false)) {
                    return false;
                }
            }
        }

        $visitor = self::detect_visitor_by_session();

        if (empty($visitor))
            $visitor = self::detect_visitor_by_cookie();

        if (empty($visitor))
            $visitor = self::detect_visitor_by_ip_and_agent();

//if($_SERVER['REMOTE_ADDR'] == '37.73.189.151') {
//    core::out([$visitor]);
//}
            
        if (empty($visitor))
            $visitor = new visitors();

        /**
         *
         */
        if (!empty($visitor) && !empty($visitor_data['user']) && (int)$visitor->user !== (int)$visitor_data['user']) {

            $user_visitor = self::detect_visitor_by_user($visitor_data['user']);

            if (!empty($user_visitor->id) && (int)$user_visitor->id !== (int)$visitor->id) {
                /**
                 * If a user creates many of visitor's history before logged on the site
                 */
                $visitors_history = visitors_history::call_static()
                    ->s(['*'], ['visitor' => $visitor->id], [1000])
                    ->all('object');

                if (!empty($visitors_history))
                    foreach ($visitors_history as $history_item) {
                        $history_item->visitor = $user_visitor->id;
                        $history_item->save();
                    }

                $visitor->destroy();
                $visitor = $user_visitor;
            }
            else if (empty($user_visitor->id)) {
                /**
                 * If user wasn't ever logged in before
                 */
                $visitor->user = (int)$visitor_data['user'];
                $visitor->save();
            }
        }

//        \m\core::out(registry::get('db_logs'));

        $import_arr = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        ];

        if (!empty($visitor_data['user']))
            $import_arr['user'] = $visitor_data['user'];

        $visitor->import($import_arr);
        $visitor->save();

        self::$_current_visitor = $visitor;
        setcookie('_visitor', (int)$visitor->id, time() + 158112000, '/', $_SERVER['HTTP_HOST']);
        $_SESSION['_visitor'] = $visitor->id;
        registry::set('visitor', $visitor);
        return $visitor;
    }

    public static function set_history(array $visitor_data = null)
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            return false;
        }

        if (config::has('developers_ips') && is_array(config::get('developers_ips'))
            && in_array(registry::get('ip'), (array)config::get('developers_ips'))) {
            return false;
        }

        return visitors_history::set_history([
            'site' => (int)registry::get('visitor')->site,
            'visitor' => (int)registry::get('visitor')->id,
            'related_model' => !empty($visitor_data['related_model']) ? $visitor_data['related_model'] : null,
            'related_id' => !empty($visitor_data['related_id']) ? $visitor_data['related_id'] : null,
        ]);
    }

    static function detect_visitor_by_session()
    {
        $visitor = null;

        if (!empty(self::$_current_visitor) && !empty(self::$_current_visitor->id))
            return self::$_current_visitor;

        if (empty($_SESSION['_visitor']) || !((int)$_SESSION['_visitor'] > 0))
            return $visitor;

        if (!empty($_SESSION['_visitor']) && !is_integer($_SESSION['_visitor'])) {
            unset($_SESSION['_visitor']);
            return $visitor;
        }

        $visitor = new visitors((int)$_SESSION['_visitor']);

        if (empty($visitor) || empty($visitor->date)) {
            unset($_SESSION['_visitor']);
            return null;
        }

        return $visitor;
    }

    static function detect_visitor_by_ip_and_agent()
    {
        $visitor = null;

        if (!empty(self::$_current_visitor) && !empty(self::$_current_visitor->id))
            return self::$_current_visitor;

            
        $ip = registry::get('ip');
        $agent = empty($_SERVER['HTTP_USER_AGENT']) ? null : $_SERVER['HTTP_USER_AGENT'];

// if($_SERVER['REMOTE_ADDR'] == '37.73.189.151') {
    // config::set('db_logs', true);
// }        

        $visitor = visitors::call_static()->s([], ["inet_ntoa(conv(HEX(ip), 16, 10))='" . $ip . "'", 'user_agent' => $agent, ['date' => ['>' => date('Y-m-d 00:00:00')]]])->obj();

// if($_SERVER['REMOTE_ADDR'] == '37.73.189.151') {
    // core::out($visitor);
// }
        
        if (empty($visitor) || empty($visitor->id)) {
            return null;
        }

        return $visitor;
    }

    static function detect_visitor_by_user($user)
    {
        if (empty($user) || (int)$user <= 0)
            return null;

        $visitor = visitors::call_static()->s([], ['user' => (int)$user])->obj();

        if (!empty($visitor) && !empty($visitor->id) && !empty($visitor->date))
            return $visitor;

        return null;
    }

    static function detect_visitor_by_cookie()
    {
        $visitor = null;

        if (!empty(self::$_current_visitor) && !empty(self::$_current_visitor->id))
            return self::$_current_visitor;

        if (empty($_COOKIE['_visitor']) || !((int)$_COOKIE['_visitor'] > 0)) {
            setcookie('_visitor', null, time() - 158112000, '/', $_SERVER['HTTP_HOST']);
            return $visitor;
        }

        $visitor = new visitors((int)$_COOKIE['_visitor']);

        if (empty($visitor) || empty($visitor->date)) {
            setcookie('_visitor', null, time() - 158112000, '/', $_SERVER['HTTP_HOST']);
            return null;
        }

        return $visitor;
    }

    static function detect_user(array $visitor_data)
    {
        $visitor = self::init_visitor($visitor_data);

        if (empty($visitor) || empty($visitor->user) || !((int)$visitor->user > 0))
            return null;

        $user = new users((int)$visitor->user);

        if (empty($user) || empty($user->profile))
            return null;

        return $user;
    }

    public static function new_visitor(array $data = null)
    {
        return self::init_visitor([
            'site' => registry::get('site') ? (int)registry::get('site')->id : 1,
            'customer_id' => !registry::get('user') ? (int)registry::get('user')->id : null,
            'related_model' => !empty($data) && !empty($data['related_model']) && (int)$data['related_model'] > 0 ?
                (int)$data['related_model'] : null,
            'related_id' => !empty($data) && !empty($data['related_id']) && (int)$data['related_id'] > 0 ?
                (int)$data['related_id'] : null,
        ]);
    }

    static function get_visits()
    {
        return !config::get('allow_visitors') ? '' :
            self::call_static()->count(["date >= '" . date('Y-m-d 00:00:00') . "'"]);
    }

    public function _autoload_user_name()
    {
        if (!empty($this->user)) {
            $this->user_name = users_info::call_static()->s([], ['profile' => $this->user])->obj()->name;
        }
        else {
            $this->user_name = '*Visitor* ' . $this->id;
        }

        return $this->user_name;
    }

    public function visits_count()
    {
        return visitors_history::call_static()->count(['site' => $this->site, 'visitor' => $this->id]);
    }
}
