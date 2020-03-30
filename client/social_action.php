<?php

namespace modules\users\client;

use m\configurator;
use m\core;
use m\module;
use m\registry;
use m\view;
use m\config;
use m\i18n;
use m\valid;
use m\m_mail;
use libraries\social_auth\social_auth;
use modules\users\models\users_socials;

class social_action extends module {

    public static $_name = '*Social\'s network interaction*';

    public function _init()
    {
        $social_api = config::get('social_api');

        //TODO: get settings from module options (from DB);

        if (!empty($this->route['1']) && !empty($social_api[$this->route['1']])) {

            if (empty($this->route['2']) || $this->route['2'] == 'in' || $this->route['2'] == 'bind') {

                $_SESSION['redirect_url'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] :
                    'http' . (configurator::is_https() ? 's' : '') . '://' . $this->domain->host . '/' .$this->language . '/';

                $adapter = social_auth::_get_adapter([
                    $this->route['1'] => $social_api[$this->route['1']]
                ]);

//                core::out($adapter);

                $auth_url = $adapter->getAuthUrl();

//                core::out($auth_url);

                if (!empty($this->route['2']) && $this->route['2'] == 'bind') {
                    $_SESSION['bind'] = 1;
                }

                if (!empty($auth_url)) {
                    core::redirect($auth_url);
                }
            }
            else if (!empty($this->route['2']) && $this->route['2'] == 'response') {

                unset($_SESSION['oauth_user']);

                $adapter = social_auth::_get_adapter([
                    $this->route['1'] => $social_api[$this->route['1']]
                ]);

                $social_auth = new social_auth($adapter);

                if ($social_auth->authenticate()) {

                    $user_social = users_socials::call_static()
                        ->s([], ['provider' => $social_auth->getProvider(), 'social_id' => $social_auth->getSocialId()])
                        ->obj();

                    if (empty($user_social) || empty($user_social->id))
                        $user_social = new users_socials();

                    if (!empty($user_social->profile) && !empty($_SESSION['bind']) && $user_social->destroy()) {

                        $redirect_url = $_SESSION['redirect_url'];

                        unset($_SESSION['redirect_url']);
                        unset($_SESSION['bind']);

                        core::redirect($redirect_url);
                    }
                    else if (empty($user_social->profile) && !empty($this->user->profile) && !empty($_SESSION['bind'])) {
                        unset($_SESSION['bind']);
                        $user_social->profile = $this->user->profile;
                    }

                    $user_social->save([
                        'provider' => $social_auth->getProvider(),
                        'social_id' => $social_auth->getSocialId(),
                        'name' => $social_auth->getName(),
                        'email' => $social_auth->getEmail(),
                        'social_page' => $social_auth->getSocialPage(),
                        'gender' => $social_auth->getGender(),
                        'avatar' => $social_auth->getAvatar(),
                        'date' => date('Y-m-d H:i:s'),
                    ]);

                    $user_error = $user_social->error();

                    if (empty($user_error) && !empty($user_social->id)) {

                        $_SESSION['oauth_user'] = md5($user_social->id . 'm-framework');
                    }

//                    $redirect_url = $_SESSION['redirect_url'];
//                    unset($_SESSION['redirect_url']);

                    core::redirect('/login');
                }

                core::redirect('/login');
            }
        }

        die;
    }
}