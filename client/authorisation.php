<?php

namespace modules\users\client;

use libraries\helper\url;
use libraries\simple_mail\simple_mail;
use m\core;
use m\events;
use m\functions;
use m\module;
use m\registry;
use m\view;
use m\config;
use m\i18n;
use m\valid;
use m\m_mail;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_info;
use modules\users\models\users_authorizations;
use modules\users\models\users_socials;
use libraries\photo_resize\photo_resize;
use modules\captcha\client\captcha;

class authorisation extends module
{
    private $errors = [];
    private $success = [];

    protected $css = [
        '/css/authorisation.css',
    ];

    public static $_name = '*Users authorisation*';

    public function _init()
    {
//        core::out(functions::__md5code('c34m2h'));

        if (!empty($this->alias) && $this->alias == 'out') {
            $this->logout();
            return true;
        }

        if (!empty($this->request->login) && !empty($this->request->password)) {
            $this->login();
        }

        if (!empty($this->route) && !empty($this->route['1']) && $this->route['1'] == 'reset') { //  && (!empty($_POST) || !empty($this->get->confirm))
            $this->reset();
        }

        if ($this->alias == 'registration' && !empty($_POST)) {
            $this->registation();
        }

        if (!empty($_SESSION['oauth_user'])) {
            $this->social_login();
        }

        if (!empty($this->user->profile) && isset($this->view->personal)) {

            registry::set('title', i18n::get('Authorisation'));

            view::set('content', $this->view->personal->prepare([
                "main_domain" => $this->domain->host,
                "language" => $this->language,
                "profile" => $this->user->profile,
                "email" => !empty($this->user->info) && !empty($this->user->info->email) ? $this->user->info->email : '',
                "first_name" => $this->user->info->first_name,
                "last_name" => $this->user->info->last_name,
                "name" => $this->user->info->name,
                "nickname" => @$this->user->info->nickname,
                "last_visit" => @$this->user->last_visit,
                "route" => $this->_route,
                "errors" => implode("\n", $this->errors),
                'title' => registry::get('title'),
            ]));

        } else if (!empty($this->page) && !empty($this->page->type) && $this->page->type == 'authorisation'
            && isset($this->view->reset_form) && !empty($this->route['1']) && $this->route['1'] == 'reset') {

            $captcha = captcha::get_captcha();

            $email = '';
            if (!empty($this->post->email)) {
                $email = $this->post->email;
            }
            else if (!empty($this->user->login)) {
                $email = $this->user->login;
            }

            registry::set('title', i18n::get('Password reset'));

            view::set('content', $this->view->reset_form->prepare([
                'main_domain' => $this->domain->host,
                'language' => $this->language,
                'errors' => implode("\n", $this->errors),
                'success' => implode("\n", $this->success),
                'captcha_unique_id' => $captcha->captcha_unique_id,
                'captcha_image' => $captcha->captcha_image,
                'title' => registry::get('title'),
                'email' => $email,
            ]));

            registry::set('breadcrumbs',[
                '/login' => '*Authorisation*',
                '' => '*Password reset*'
            ]);

        } else if (!empty($this->page) && !empty($this->page->type) && $this->page->type == 'authorisation'
            && isset($this->view->registration_form) && !empty($this->alias) && $this->alias == 'registration') {

            registry::set('title', i18n::get('*Registration*'));

            $captcha = captcha::get_captcha();

            view::set('content', $this->view->registration_form->prepare([
                'main_domain' => $this->domain->host,
                'language' => $this->language,
                'errors' => implode("\n", $this->errors),
                'success' => implode("\n", $this->success),
                'captcha_unique_id' => empty($captcha) ? null : $captcha->captcha_unique_id,
                'captcha_image' => empty($captcha) ? null : $captcha->captcha_image,
                'title' => registry::get('title'),
                'first_name' => empty($this->post->first_name) ? '' : $this->post->first_name,
                'last_name' => empty($this->post->last_name) ? '' : $this->post->last_name,
                'email' => empty($this->post->email) ? '' : $this->post->email,
            ]));

            registry::set('breadcrumbs',[
                '/login' => '*Authorisation*',
                '' => '*Registration*'
            ]);

        } else if (!empty($this->page) && !empty($this->page->type)
            && isset($this->view->authorisation_form)) {
            // && $this->page->type == 'authorisation'

            registry::set('title', i18n::get('Authorisation'));

            view::set('content', $this->view->authorisation_form->prepare([
                'main_domain' => $this->domain->host,
                'language' => $this->language,
                'errors' => implode("\n", $this->errors),
                'title' => registry::get('title'),
            ]));

        }

        return true;
    }

    private function login()
    {
        $this->simpleString = "__qwertyuiop[]asdfghjkl;'zxcvbnm,./`1234567890-==-0987654321`][poiuytrewq';lkjhgfdsa/.,";
        $this->simpleString .= "mnbvcxz__".date("Y");

//        if (strstr($this->simpleString, $this->request->password)) {
//            $this->errors[] = "*auth pass simple*";
//            return false;
//        }

        $login = $this->user->login($this->request->login, $this->request->password);

        if (is_array($login) && !empty($login['error'])) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => $login['error'],
            ]);
            return false;
        }
        else if (is_array($login) && !empty($login['attempt']) && isset($this->view->attempt)) {
            $this->view->attempt = i18n::get('attempt in auth');
            $this->errors[] = $this->view->attempt->prepare(['minutes' => $login['attempt']]);
            return false;
        }

        if (!empty($login['user'])) {
            events::call('notification_user_authorisation', $login['user']);

            $redirect_path = $this->previous;

            if (config::get('login_redirect')) {
                $redirect_path = url::to(config::get('login_redirect'));
            }

            $this->redirect($redirect_path);
        }
    }

    private function social_login()
    {
        $oauth_user = users_socials::call_static()
            ->s([], ["MD5(CONCAT(id,'m-framework'))='" . $_SESSION['oauth_user'] . "'"])->obj();

        unset($_SESSION['oauth_user']);

        $user_authorization = new users_authorizations(empty($oauth_user->profile) ? null : $oauth_user->profile);

        if (!empty($oauth_user->profile)) {

            $user = users::call_static()->s([], ['profile' => $oauth_user->profile, 'confirmed' => 1])->obj();
			
            if (!empty($user->confirmed) && !empty($oauth_user->avatar)) {
                $user->info->avatar = $this->save_social_avatar($oauth_user->avatar);
                $user->info->save();
            }
        }
        else if (!empty($oauth_user->email)) {

            $user = users::call_static()->s([], ['login' => trim($oauth_user->email)])->obj();

            if (empty($user->profile)) {

                $user = new users();

                $password = $user->generate_string(8);
                $profile = $user->generate_profile();

                $user->save([
                    'site' => $this->site->id,
                    'profile' => $profile,
                    'login' => $oauth_user->email,
                    'password' => $user->__md5code($password),
                    'confirmed' => '1',
                    'group' => '1',
                    'last_visit' => date('Y-m-d H:i:s'),
                    'online' => '1',
                ]);

                if ($user->error()) {
                    return false;
                }

                $name_arr = !empty($oauth_user->name) ? explode(' ', $oauth_user->name) : [];

                users_info::call_static()->save([
                    'profile' => $profile,
					'site' => $this->site->id,
                    'first_name' => empty($name_arr['0']) ? '' : $name_arr['0'],
                    'last_name' => empty($name_arr['1']) ? '' : $name_arr['1'],
                    'email' => empty($oauth_user->email) ? '' : $oauth_user->email,
                    'avatar' => empty($oauth_user->avatar) ? '' : $this->save_social_avatar($oauth_user->avatar),
                ]);

                $oauth_user->save(['profile' => $profile]);
            }
        }
        else if (empty($oauth_user->profile) && empty($oauth_user->email)) {

            $user = new users();

            $login = $user->generate_string(8);
            $profile = $user->generate_profile();

            $insert = $user->save([
                'site' => $this->site->id,
                'profile' => $profile,
                'login' => $login,
                'password' => null,
                'confirmed' => '1',
                'group' => '1',
                'last_visit' => date('Y-m-d H:i:s'),
                'online' => '1',
            ]);

            if (!$insert) {
                $this->error = $user->error();
                return false;
            }

            $name_arr = !empty($oauth_user->name) ? explode(' ', $oauth_user->name) : [];

            users_info::call_static()->save([
                'profile' => $profile,
                'site' => $this->site->id,
                'first_name' => empty($name_arr['0']) ? '' : $name_arr['0'],
                'last_name' => empty($name_arr['1']) ? '' : $name_arr['1'],
                'email' => empty($oauth_user->email) ? '' : $oauth_user->email,
                'avatar' => empty($oauth_user->avatar) ? '' : $this->save_social_avatar($oauth_user->avatar),
            ]);

            $oauth_user->save(['profile' => $profile]);
        }

        if (!isset($user) || empty($user->profile)) {
            $this->redirect('/' . $this->_route);
            return false;
        }

        $expire = $user->generate_expire();
        $authorize = $user->generate_string('16');

        $user_authorization->save([
            'profile' => $user->profile,
            'expire' => $expire,
            'authorize' => $authorize,
        ]);

        $user->save([
            'last_visit' => date('Y-m-d H:i:s'),
            'online' => '1',
        ]);

        unset($_COOKIE['authorize']);
        setcookie('authorize', $authorize, $expire, '/', $this->domain->host);
        setcookie('authorize', $authorize, $expire, '/', $this->site->host);

        if (!empty($_SESSION['redirect_url'])) {
            $redirect_url = $_SESSION['redirect_url'];
            unset($_SESSION['redirect_url']);
            $this->redirect($redirect_url);
        }

        $this->redirect('/' . $this->_route);

        return true;
    }

    private function logout()
    {
        if (!empty($this->user->profile))
            $this->user->out();

        setcookie('authorize', null, time()-6000, '/', $this->site->host);
        setcookie('authorize', null, time()-6000, '/', $this->domain->host);

        $this->redirect($this->previous);
    }

    private function save_social_avatar($url)
    {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$avatar_file = curl_exec($ch);
		curl_close($ch);

        //$avatar_file = file_get_contents($url);

        if (empty($avatar_file)) {
            return '';
        }

        $ext = pathinfo($url, PATHINFO_EXTENSION);

        if (empty($ext)) {
            $ext = 'jpg';
        }

        $file_name = md5(time() . rand(1,10)) . '.' . $ext;

        $tmp_path = config::get('root_path') . config::get('tmp_path') . $file_name;

        file_put_contents($tmp_path, $avatar_file);

        $out_path = config::get('data_path') . $this->site->id . date('/Y/m/d/') . $file_name;

        $resize = new photo_resize([
            'width' => 100,
            'height' => 100,
            'file_path' => $tmp_path,
            'out_path' => config::get('root_path') . $out_path
        ]);

        if ($resize->save()) {

			if (is_file($tmp_path)) {
				unlink($tmp_path);
			}
			
            return $out_path;
        }

        return '';
    }

    private function registation()
    {
        if (empty($this->request->email) || !valid::email($this->request->email)) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Email is not valid*'
            ]);
            return false;
        }

        $user = users::call_static()->s([], ['login' => $this->request->email])->obj();
        $user_info = users_info::call_static()->s([], ['email' => $this->request->email])->obj();

        if (!empty($user->profile) || !empty($user_info->profile)) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Email is busy*'
            ]);
            return false;
        }

        if (!captcha::verify()) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Wrong captcha code*'
            ]);
            return false;
        }

        $user = new users();

        $password = $user->generate_string(8);

        $post_arr = $this->post;
        $post_arr->site = $this->site->id;
        $post_arr->login = $this->post->email;
        $post_arr->password = $user->__md5code($password);
        $post_arr->confirmed = 1;
        $post_arr->group = 1;
        $post_arr->online = 1;
        $post_arr->last_visit = date('Y-m-d H:i:s');

        $user->import($post_arr);
        $user->save();

        if ($save_error = $user->error()) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => $save_error
            ]);
            return false;
        }

        $this->view->msg_text = i18n::get('mail_user_register');

        $message = $this->view->mail_user_register->prepare([
            'msg_text' => $this->view->msg_text->prepare([
                'domain' => $this->site->host,
                'first_name' => !empty($this->post->first_name) ? $this->post->first_name : i18n::get('customer'),
                'password' => $password,
            ]),
        ]);

        if (!simple_mail::send(
            $this->conf->admin_mail,
            $this->post->email,
            i18n::get('You was successfully registered') . ' ' . i18n::get('on') . ' ' . $this->site->host,
            i18n::lang_replace($message)
        )) {
            return $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Cant send this mail from server*'
            ]);
        }

        $user->login_by_hash($user->get_login_hash());

        registry::set('user', $user);

//        events::call('notification_successfully_registered', $user);
//        events::call('notification_successfully_registered_admin', $user);

        return $this->success[] = $this->view->div_success->prepare([
            'text' => '*You was successfully registered*! *Password sent to your mailbox*.'
        ]);
    }

    private function reset()
    {
        if (!empty($this->get->confirm)) {
            $user = users::call_static()
                ->s(
                    [],
                    ['new_password' => ['not' => null], "MD5(new_password)='" . $this->get->confirm . "'"]
                )
                ->obj();

            if (empty($user) || empty($user->login)) {
                $this->errors[] = $this->view->div_error->prepare([
                    'text' => '*Wrong confirmation code*'
                ]);
//                return false;
            }
            else {

                $user->save([
                    'password' => $user->new_password,
                    'new_password' => null,
                ]);

                $user_login = $this->user->login_by_hash($user->get_login_hash());

//                core::out([$user_login]);

                $this->redirect('/login');
            }

        }

        if (empty($this->request->email)) {
            return false;
        }
        else if (!valid::email($this->request->email)) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Email is not valid*'
            ]);
            return false;
        }

        $user = users::call_static()->s([], ['login' => $this->request->email])->obj();

        if (empty($user) || empty($user->profile)) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*We can\'t find that user*'
            ]);
        }

        if (!captcha::verify()) {
            $this->errors[] = $this->view->div_error->prepare([
                'text' => '*Wrong captcha code*'
            ]);
        }

        if (empty($this->errors)) {

            $new_password = $user->generate_string(10);

            $user->new_password = $user->__md5code($new_password);
            $user->save();

            $subject = i18n::get('Reset password on site') . ' ' . $this->domain->host;

            $this->view->mail_renewal_password =
                stripslashes(htmlspecialchars_decode(i18n::get('mail_renewal_password')));

            $message = $this->view->mail_renewal_password->prepare([
                'domain' => $this->site->host,
                'confirm_link' => 'https://' . $this->site->host . '/login/reset/confirm/' . md5($user->new_password),
                'first_name' => !empty($user->info) && !empty($user->info->first_name) ? $user->info->first_name :
                    i18n::get('customer'),
                'new_password' => $new_password,
            ]);

            if (simple_mail::send($this->conf->admin_mail, $user->login, $subject, i18n::lang_replace($message))) {
                view::set('password_restore', $this->view->reset_password_success->prepare());
                return true;
            }
            else {
                $this->errors[] = $this->view->div_error->prepare([
                    'text' => '*Cant send this mail from server*'
                ]);
            }
        }
    }
}
