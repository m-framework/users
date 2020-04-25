<?php

namespace modules\users\models;

use libraries\simple_mail\simple_mail;
use m\core;
use
    m\model,
    m\config,
    m\registry,
    m\cache,
    m\logs,
    m\i18n,
    m\m_mail;

use modules\users\models\users_balances;

/**
 * This is the model class for table "users".
 *
 * @property integer $profile
 * @property integer $site
 * @property string $login
 * @property string $password
 * @property integer $confirmed
 * @property integer $online
 * @property string $last_visit
 * @property string $last_ip
 * @property integer $group
 * @property string $new_password
 * @property users_balances $balance
 */
class users extends model
{
    public $__id = 'profile';
    public $_sort = ['profile' => 'DESC'];
    public $_table = 'users';

    protected $fields = [
        'profile' => 'int',
        'site' => 'int',
        'login' => 'varchar',
        'password' => 'varchar',
        'confirmed' => 'tinyint',
        'online' => 'tinyint',
        'last_visit' => 'timestamp',
        'last_ip' => 'varbinary',
        'group' => 'int',
        'new_password' => 'varchar',
        'admin' => 'tinyint',
    ];

    public function init()
    {
        $this->_clear_inactive();

        if (empty($this->profile)) {
            $this->authorize();
        }

        return $this;
    }

    public function _after_save()
    {
        $info_fields = array_keys(users_info::call_static()->get_fields());
        $info = [];
        $vars = get_object_vars($this);

        foreach ($info_fields as $info_field) {
            if (isset($vars[$info_field]) && $info_field !== 'profile') {
                $info[$info_field] = $vars[$info_field];
            }
        }

        if (empty($info)) {
            return true;
        }

        $info['profile'] = $this->profile;

        $users_info = new users_info($this->profile);
        $users_info->save($info);

//        \m\core::out(registry::get('db_logs'));

        // TODO: send mail with new password

        return true;
    }

    public function _before_save()
    {
//        if (empty($this->profile)) {
//            $this->profile = $this->generate_profile();
//        }

        $this->last_ip = inet_pton(registry::get('ip'));

        if (!empty($this->password_new) && !empty($this->password_new_confirm)
            && $this->password_new == $this->password_new_confirm) {
            $this->password = $this->__md5code($this->password_new);
            unset($this->password_new);
            unset($this->password_new_confirm);
        }

        return true;
    }

    public function _before_destroy()
    {
        //TODO: delete all user files & photos

        users_info::call_static()->s([], ['profile' => $this->profile])->obj()->destroy();
        users_socials::call_static()->s([], ['profile' => $this->profile])->obj()->destroy();
        users_authorizations::call_static()->s([], ['profile' => $this->profile])->obj()->destroy();
        users_permissions::call_static()->s([], ['profile' => $this->profile])->obj()->destroy();

        /**
         * Update all client's visitors
         */
        visitors::call_static()->u(['user' => null], ['user' => $this->profile]);

        if (class_exists('modules\shop\models\shop_orders')) {
            $orders = \modules\shop\models\shop_orders::call_static()->s([], ['user' => $this->profile])->all('object');
            if (!empty($orders)) {
                foreach ($orders as $order) {
                    $order->destroy();
                }
            }
        }

        if (class_exists('modules\comments\models\comments')) {
            $comments = \modules\comments\models\comments::call_static()->s([], ['author' => $this->profile])->all('object');
            if (!empty($comments)) {
                foreach ($comments as $comment) {
                    $comment->destroy();
                }
            }
        }

        if (class_exists('modules\files\models\files')) {
            $files = \modules\files\models\files::call_static()->s([], ['author' => $this->profile])->all('object');
            if (!empty($files)) {
                foreach ($files as $file) {
                    $file->destroy();
                }
            }
        }

        if (class_exists('modules\notifications\models\notifications_rules')) {
            \modules\notifications\models\notifications_rules::call_static()->d(['user' => $this->profile]);
        }

        return true;
    }


    private function _clear_inactive()
    {
        // TODO: some stuff
        return true;
    }

    private function authorize()
    {
        if (empty($_COOKIE['authorize'])) {
            unset($this->db);
            return false;
        }

        $cookie_authorize = addslashes(htmlspecialchars(trim($_COOKIE['authorize'])));

        $this->select(
                ['users' => '*'], // TODO: can be ['users' => ''] or ['users' => '*']
                ['users_authorizations' => ['profile' => 'profile']], // INNER JOIN `users_authorizations` ON `users_authorizations`.`profile` = `users`.`profile`
                [
                    ['users.online' => 1],
                    ['users_authorizations.expire' => ['>', time()]],
                    ['users_authorizations.authorize' => $cookie_authorize],
                ],
                [],
                ['users.profile' => 'DESC'],
                []
            )
            ->obj();

        $domain = registry::get('domain');
        $site = registry::get('site');

        if (!empty($this->profile)) {

            $_expire = $this->generate_expire();

            setcookie('authorize', $cookie_authorize, $_expire, '/', "." . $domain->host);
            if ($domain->host !== $site->host) {
                setcookie('authorize', $cookie_authorize, $_expire, '/', '.' . $site->host);
            }

            if (!empty($this->profile) && !empty($_expire))
                users_authorizations::call_static()->u(
                    ['expire' => $_expire],
                    ['profile' => $this->profile]
                );

            return true;
        }
        else{
            setcookie('authorize','',time()-86400,'/','.' . $domain->host);
            if ($domain->host !== $site->host) {
                setcookie('authorize', '', time()-86400, '/', '.' . $site->host);
            }

            return false;
        }
    }

    public function out()
    {
        if (empty($this->profile))
            return false;

        $user_authorize = new users_authorizations($this->profile);
        $user_authorize->destroy();

        $this->u(['online' => '0', 'last_visit' => date("Y-m-d H:i:s")], [$this->__id => $this->profile]);

        $count_online_users = $this->s([], ['online' => '1'])->one();
        cache::set('count_online_users', !empty($count_online_users) ? $count_online_users : '0');

        if ($this->error())
            return $this->error;
        else
            return true;
    }

    public function is_admin()
    {
        if (empty($this->profile)) {
            return false;
        }

        $site = registry::get('site');

        $admin_group = empty($site->admin_group) ? 3 : (int)$site->admin_group;

        if ((!empty($this->group) && (int)$this->group == (int)$admin_group) || (int)$site->admin==(int)$this->profile){
            return true;
        }

        return false;
    }

    public function has_permission($module, $page = null)
    {
        if (empty($this->profile)) {
            return null;
        }

        if ($this->is_admin()) {
            return true;
        }

        $permission = null;

        $permissions_cond = [ // TODO: language?
            [['site' => registry::get('site')->id], ['site' => null]]
        ];

        if (empty($module)) {
            $permissions_cond['module'] = null;
        }
        else {
//            $permissions_cond[] = "module IS NULL OR module='" . $module . "'";
            $permissions_cond[] = [['module' => null], ['module' => $module]];
        }

        if (empty($page)) {
            $permissions_cond['page'] = null;
        }
        else {
//            $permissions_cond[] = "page IS NULL OR page='" . $page . "'";
            $permissions_cond[] = [['page' => null], ['page' => $page]];
        }

        if (!empty($this->group)) {

            $permissions_cond['group'] = $this->group;

            $permission = users_permissions::call_static()->s(['permission'], $permissions_cond)->one();
        }

        if (empty($permission) && !empty($this->profile)) {
            $permissions_cond['group'] = null;
            $permissions_cond['profile'] = $this->profile;

            $permission = users_permissions::call_static()->s(['permission'], $permissions_cond)->one();
        }

        return !empty($permission);
    }

    public function get_last_ip()
    {
        return !empty($this->last_ip)
        && filter_var($this->last_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6) == false
            ? inet_ntop($this->last_ip) : $this->last_ip;
    }


//    private function busyProfile($profile)
//    {
//        return $this->s(array('email'), array('profile'=>$profile))->one();
//    }

    public function generate_profile()
    {
        return $this->s(['profile+1'], [], ['1'], ['profile' => 'DESC'])->one();
    }

    public function update($arr)
    {
        $user_arr = [];
        $info_arr = [];

        foreach ($arr as $k=>$v) {
            switch ($k) {
                case 'login':
                case 'password':
                case 'confirmed':
                case 'online':
                case 'last_visit':
                case 'last_ip':
                case 'admin':
                case 'editor':
                case 'type':
                    $user_arr[$k] = $v; // WTF ?
                    break;
                default:
                    $info_arr[$k] = $v; // WTF ?
            }
        }

        $user_info = new users_info($this->profile);
        $users = new users($this->profile);

        return ($users->save($user_arr) &&  empty($users->error) && $user_info->save($info_arr)) ? true : false;
    }

    public function _autoload_info()
    {
        if (!empty($this->info) && !empty($this->info->profile) && $this->info->profile == $this->profile)
            return $this->info;

        $info = users_info::call_static()->s([], ['profile' => $this->profile])->obj();

        if (!empty($info))
            $this->info = $info;

        return $this->info;
    }

    public function register($email, array $options)
    {
        if (empty($email)) {
            return false;
        }

        $existed_user = $this->s(['profile'], ['email' => $email])->one();

        if (!empty($existed_user)) {
            return true;
        }

        $user_profile = $this->generate_profile();
        $password = $this->generate_string('10');
        $domain = config::get('domain');

        $this->save([
            'profile' => $user_profile,
            'site' => registry::get('site')->id,
            'login' => $email,
            'password' => $this->__md5code($password),
        ]);

        $info_arr = [
            'site' => registry::get('site')->id,
            'profile' => $user_profile,
            'email' => $email
        ];

        if (!empty($options['name'])) {
            $name_arr = explode(' ', trim($options['name']));
            $info_arr['first_name'] = $name_arr['0'];
            $info_arr['last_name'] = !empty($name_arr['1']) ? $name_arr['0'] : '';
        }

        if (!empty($options['first_name'])) {
            $info_arr['first_name'] = $options['first_name'];
        }

        if (!empty($options['last_name'])) {
            $info_arr['last_name'] = $options['last_name'];
        }

        if (!empty($options['middle_name'])) {
            $info_arr['middle_name'] = $options['middle_name'];
        }

        if (!empty($options['email'])) {
            $info_arr['email'] = $options['email'];
        }

        if (!empty($options['phone'])) {
            $info_arr['phone'] = $options['phone'];
        }

        users_info::call_static()->save($info_arr);

        $subject = i18n::get('mail from site')." ".registry::get('site')->host;

        // ' . $this->language . '/
        $link = 'https://' . registry::get('site')->host . '/registration/confirm/' . md5($user_profile.$email);

        $var = [
            '~domain~' => registry::get('site')->host,
            '~host~' => registry::get('site')->host,
            '~first_name~' => !empty($info_arr['first_name']) ? $info_arr['first_name'] : i18n::get('guest'),
            '~password~' => $password,
            '~link~' => '<a href="' . $link . '">' . $link . '</a>'
        ];

        $mail_registration = stripslashes(htmlspecialchars_decode(i18n::get('mail_registration')));

        $message = str_replace(array_keys($var), array_values($var), $mail_registration);

        return simple_mail::send(config::get('admin_mail'), $email, $subject, $message);
    }

    public function get_login_hash()
    {
        return md5($this->profile . $this->login);
    }

    public function login_by_hash($hash)
    {
        $response = [];
        $domain = registry::get('domain');
        $site = registry::get('site');
        $ip = registry::get('ip');

        $this->s([], ["MD5(CONCAT(profile,login))='" . $hash . "'"])->obj();

        if (empty($this->profile)) {

            $response['error'] = "*wrong password or login*";

            users_authorisation_errors::call_static()->save([
                "attempt"   => 1,
                "expire"    => time() + config::get('authorisation_error_expire') * 60,
                "site"      => $site->id,
                "ip"        => $ip,
            ]);

            return $response;
        }

        users_authorisation_errors::call_static()->d(['ip' => $ip, 'site' => $site->id], []);

        $user_authorize = new users_authorizations($this->profile);

        $expire = $this->generate_expire();
        $authorize = $this->generate_string('16');

        $authorize_arr = [
            'profile' => $this->profile,
            'expire' => $expire,
            'authorize' => $authorize,
            'ip' => $ip,
        ];

        /**
         * We can't to use save() on tables without auto_increment field
         * TODO: check is it true
         */
        if (empty($user_authorize->authorize)) {
            $user_authorize->i($authorize_arr);
        }
        else {
            $user_authorize->u($authorize_arr, ['profile' => $this->profile]);
        }

        $this->online = '1';
        $this->last_visit = date("Y-m-d H:i:s");
        unset($this->type);
        unset($this->password);
        $this->save();

        if ($error = $this->error()) {
            $response['error'] = $error;
            return $response;
        }

        setcookie('authorize', $authorize, $expire, '/', '.' . $domain->host);
        if ($domain->host !== $site->host) {
            setcookie('authorize', $authorize, $expire, '/', '.' . $site->host);
        }

        return $this;
    }

    public function login($login, $password)
    {
        $response = [];
        $domain = registry::get('domain');
        $site = registry::get('site');
        $ip = registry::get('ip');

        $simpleString = "__qwertyuiop[]asdfghjkl;'zxcvbnm,./`1234567890-==-0987654321`][poiuytrewq';lkjhgfdsa/.,";
        $simpleString .= "mnbvcxz__".date("Y");

        if (strstr($simpleString, $password))
            return $response['error'] = "*auth pass simple*";

        users_authorisation_errors::call_static()->d(["`expire`<'" . time() . "'"], []);

        $auth_errors = users_authorisation_errors::call_static()
            ->s([], [[['site' => $site->id], ['site' => null]], 'ip' => $ip, 'expire>' . time()])
            ->obj();

        if (!empty($auth_errors->attempt) && $auth_errors->attempt >= 3) {

            $att_min = round(($auth_errors->expire - time())/60);

            if ($att_min == 0)
                $att_min = 1;

            $response['attempt'] = $att_min;

            return $response;
        }

//        $this->s([], ['login' => $login, 'password' => $this->__md5code($password), 'confirmed' => '1'])->obj();

        //find profiles in users_info by email, phone, phone_2, phone_3, phone_4
        $profiles_info = users_info::call_static()
            ->s(['profile'],
                [
                    [
                        ['email' => $login],
                        ['phone' => $login],
                        ['phone_2' => $login],
                        ['phone_3' => $login],
                        ['phone_4' => $login],
                    ],
                ],
                [100])
            ->all();
        $profiles = [];

        if (!empty($profiles_info)) {
            foreach ($profiles_info as $profile_info) {
                $profiles[] = $profile_info['profile'];
            }
        }

        $this->select(
            ['users.*'],
            [],
//            ['users_info' => ['profile' => 'profile']],
            [
                'users.profile' => $profiles,
                'users.password' => $this->__md5code($password),
                'users.confirmed' => '1'],
            [],
            [],
            [1]
        )->obj();

//        core::out(registry::get('db_logs'));

        if (empty($this->profile)) {

            $response['error'] = "*wrong password or login*";

            users_authorisation_errors::call_static()->save([
                "attempt"   => !empty($auth_errors->attempt) ? $auth_errors->attempt+1 : 1,
                "expire"    => time() + config::get('authorisation_error_expire') * 60,
                "site"      => $site->id,
                "ip"        => $ip,
            ]);

            return $response;
        }
//        core::out([$this->profile]);

        users_authorisation_errors::call_static()->d(['ip' => $ip, 'site' => $site->id]);

        $user_authorize = new users_authorizations($this->profile);

        $expire = $this->generate_expire();
        $authorize = $this->generate_string('16');

        $authorize_arr = [
            'profile' => $this->profile,
            'expire' => $expire,
            'authorize' => $authorize,
            'ip' => $ip,
        ];

        /**
         * We can't to use save() on tables without auto_increment field
         */
        if (empty($user_authorize->profile)) {
            $user_authorize->i($authorize_arr);
        }
        else {
            $user_authorize->u($authorize_arr, ['profile' => $this->profile]);
        }

        $this->online = '1';
        $this->last_visit = date("Y-m-d H:i:s");
        $this->last_ip = config::get('ip');
        unset($this->type);
        unset($this->password);
        $this->save();

        if ($error = $this->error()) {
            $response['error'] = $error;
            return $response;
        }

        setcookie('authorize', $authorize, $expire, '/', ".".$domain->host);
        if ($domain->host !== $site->host) {
            setcookie('authorize', $authorize, $expire, '/', '.' . $site->host);
        }

        $response['user'] = $this;

        return $response;
    }

    public function generate_expire()
    {
        return config::get('authorize_expire') && (int)config::get('authorize_expire') > 0 ?
            time()+60*(int)config::get('authorize_expire') :
            time()+60*30;
    }

    public function _autoload_name()
    {
        return $this->name = $this->info->_autoload_name();
    }

    public function _autoload_email()
    {
        return $this->email = $this->info->email;
    }

    public function _autoload_phone()
    {
        return $this->phone = $this->info->phone;
    }

    public function _autoload_first_name()
    {
        return $this->first_name = $this->info->first_name;
    }

    public function _autoload_last_name()
    {
        return $this->last_name = $this->info->last_name;
    }

    public function _autoload_middle_name()
    {
        return $this->middle_name = $this->info->middle_name;
    }

    public function _autoload_group_name()
    {
        return $this->group_name = empty($this->group) ? '' : users_groups::call_static()->s(['name'], ['id' => $this->group])
            ->one();
    }

    public function _autoload_avatar()
    {
        return $this->avatar = $this->info->avatar;
    }

    public function _autoload_balance()
    {
        $this->balance = users_balances::call_static()
            ->s([], ['site' => $this->site, 'user' => $this->profile, 'type' => 1])
            ->obj();

        if (empty($this->balance) || empty($this->balance->id)) {

            $currencies = array_keys(config::get('currencies'));

            $this->balance = new users_balances();
            $this->balance->import([
                'site' => $this->site,
                'user' => $this->profile,
                'type' => 1,
                'amount' => '0',
                'currency' => empty($currencies['0']) ? 1 : $currencies['0'],
            ]);
            $this->balance->save();
        }

        return $this->balance;
    }
}