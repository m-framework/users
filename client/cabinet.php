<?php

namespace modules\users\client;

use libraries\photo_resize\photo_resize;
use m\configurator;
use m\core;
use m\form;
use m\_form;
use m\module;
use m\registry;
use m\view;
use m\config;
use m\i18n;
use m\valid;
use m\m_mail;
use libraries\social_auth\social_auth;
use modules\shop\client\order;
use modules\shop\models\shop_orders;
use modules\shop\models\shop_products;
use modules\shop\models\shop_wishlist;
use modules\users\models\users_socials;
use modules\users\models\visitors_history;

class cabinet extends module {

    public static $_name = '*Personal cabinet*';

    public function _init()
    {
        if (empty($this->user->profile)) {
            core::redirect('/login');
            return false;
        }

        if ($this->alias == 'cabinet') {
            return view::set('content', $this->view->cabinet_dashboard->prepare());
        }

        if ($this->alias == 'settings') {
            return $this->settings();
        }

        if (in_array($this->alias, ['orders', 'wishlist', 'looked'])) {
            return  $this->data_list();
        }

        if (!empty($this->get->cabinet) && $this->get->cabinet == 'orders' && !empty($this->get->decline)) {
            $this->decline_order();
        }

        if (!empty($this->get->cabinet) && $this->get->cabinet == 'orders' && !empty($this->get->edit)) {
            $this->edit_order();
        }

        if (!empty($this->get->cabinet) && $this->get->cabinet == 'orders' && !empty($this->get->pay)) {
            $this->pay_order();
        }
    }

    private function data_list()
    {
        switch ($this->alias) {
            case 'orders':
                $items = shop_orders::call_static()->s([], [
                    [['site' => registry::get('site')->id], ['site' => null]],
                    'user' => $this->user->profile
                ], [1000])->all('object');
                $title = '*My orders*';
                $item_view = $this->view->cabinet_order_item;
                i18n::init('/m-framework/modules/shop/client/i18n/');
                break;
            case 'wishlist':
                $items = shop_wishlist::call_static()->select(
                    ['shop_products.*','shop_wishlist.date'],
                    ['shop_products' => [
                        'id' => 'product'
                    ]],
                    [
                        [['site' => registry::get('site')->id], ['site' => null]],
                        'user' => $this->user->profile
                    ],
                    ['shop_wishlist.product'],
                    ['shop_wishlist.date' => 'DESC'],
                    [1000]
                )->all('object');
                $title = '*My wishlist*';
                $item_view = $this->view->cabinet_wishlist_item;
                break;
            case 'looked':
                $items = shop_products::call_static()->select(
                    ['shop_products.*','visitors_history.date'],
                    ['visitors_history' => [
                        'related_id' => 'id'
                    ]],
                    [
                        [['shop_products.site' => registry::get('site')->id], ['shop_products.site' => null]],
                        'visitor' => $this->visitor->id,
                        'visitors_history.related_model' => 'shop_products'
                    ],
                    [],
                    ['visitors_history.date' => 'DESC'],
                    [1000]
                )->all('object');
                $title = '*Looked products*';
                $item_view = $this->view->cabinet_looked_item;
                break;
            default:
                core::redirect('/cabinet');
        }

        $items_rows = '';

        if (!empty($items) && isset($item_view) && !empty($title)) {
            foreach ($items as $item) {
                if ($this->alias == 'orders') {
                    $item->options_buttons = empty($item->status) && empty($item->paid)
                        ? $this->view->cabinet_order_options_buttons->prepare([
                        'id' => $item->id,
                    ]) : '';
                }
                $items_rows .= $item_view->prepare($item);
            }
            registry::set('title', $title);
        }

        $this->css = [
            '/css/cabinet_data_list.css',
        ];

        $errors = '';

        if (!empty($_SESSION['orders_error'])) {
            $errors .= $this->view->cabinet_error->prepare([
                'text' => $_SESSION['orders_error'],
            ]);
            unset($_SESSION['orders_error']);
        }

        return view::set('content', $this->view->cabinet_data_rows->prepare([
            'errors' => $errors,
            'title' => $title,
            'items_rows' => $items_rows,
        ]));
    }

    private function settings()
    {
        if (!empty($_POST) && !empty($_FILES['users_3_avatar']) && !empty($_FILES['users_3_avatar']['tmp_name'])) {

            $out_path = config::get('data_path') . $this->site->id . date('/Y/m/d/') . md5(microtime()) . '.jpg';

            $resize = new photo_resize([
                'width' => 100,
                'height' => 100,
                'file_path' => $_FILES['users_3_avatar']['tmp_name'],
                'out_path' => config::get('root_path') . $out_path,
            ]);
            $resize->save();

            $this->user->info->avatar = $out_path;
            $this->user->info->save();

            $_SESSION['users_3_saved'] = 1;

            core::redirect(config::get('previous'));
        }


        registry::set('title', i18n::get('Profile settings'));

        registry::set('breadcrumbs', [
            '/cabinet' => '*Personal cabinet*',
            '/cabinet/settings' => '*Settings*',
        ]);

        $this->css = [
            '/css/cabinet_settings.css',
        ];

        view::set('basic_settings', new form(
            $this->user,
            [
                'first_name' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('First name'),
                    'required' => 1,
                ],
                'last_name' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Last name'),
                    'required' => 1,
                ],
                'email' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Email'),
                    'validate' => 'email',
                ],
                'phone' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Phone'),
                    'required' => 1,
                ],
                'site' => [
                    'type' => 'hidden',
                ],
            ],
            [
                'form' => $this->view->cabinet_basic_settings,
                'varchar' => $this->view->cabinet_settings_varchar,
                'hidden' => $this->view->cabinet_settings_hidden,
                'saved' => $this->view->cabinet_saved,
                'error' => $this->view->cabinet_error,
            ],
            true
        ));

        view::set('password_settings', new form(
            $this->user,
            [
                'password_new' => [
                    'type' => 'password',
                    'field_name' => i18n::get('New password'),
                    'validate' => 'same_password',
                ],
                'password_new_confirm' => [
                    'type' => 'password',
                    'field_name' => i18n::get('Confirm password'),
                    'validate' => 'same_password',
                ],
                'site' => [
                    'type' => 'hidden',
                ],
            ],
            [
                'form' => $this->view->cabinet_password_settings,
                'password' => $this->view->cabinet_settings_password,
                'hidden' => $this->view->cabinet_settings_hidden,
                'saved' => $this->view->cabinet_saved,
                'error' => $this->view->cabinet_error,
            ],
            true
        ));

        view::set('avatar_settings', new form(
            $this->user,
            [
                'avatar' => [
                    'type' => 'file',
                    'field_name' => i18n::get('Choose a photo'),
                ],
                'site' => [
                    'type' => 'hidden',
                ],
            ],
            [
                'form' => $this->view->cabinet_avatar_settings,
                'file' => $this->view->cabinet_settings_file,
                'hidden' => $this->view->cabinet_settings_hidden,
                'saved' => $this->view->cabinet_saved,
                'error' => $this->view->cabinet_error,
            ],
            true
        ));

        $social = [];
        $social_records = users_socials::call_static()->s([], ['profile' => $this->user->profile], [100])->all();

        if (!empty($social_records)) {
            foreach ($social_records as $social_record) {
                $social[$social_record['provider']] = 1;
            }
        }

        view::set('social_settings', new form(
            $this->user,
            [
                'facebook' => [
                    'type' => 'social',
                    'field_name' => 'Facebook',
                    'options' => [
                        'social' => 'fb',
                        'active_class' => empty($social['facebook']) ? 'btn-grey' : '',
                        'social_icon' => 'fa-facebook',
                    ],
                ],
                'Instagram' => [
                    'type' => 'social',
                    'field_name' => 'Instagram',
                    'options' => [
                        'social' => 'in',
                        'active_class' => empty($social['instagram']) ? 'btn-grey' : '',
                        'social_icon' => 'fa-instagram',
                    ],
                ],
                'linkedin' => [
                    'type' => 'social',
                    'field_name' => 'Linkedin',
                    'options' => [
                        'social' => 'li',
                        'active_class' => empty($social['linkedin']) ? 'btn-grey' : '',
                        'social_icon' => 'fa-linkedin',
                    ],
                ],
                'google' => [
                    'type' => 'social',
                    'field_name' => 'Google',
                    'options' => [
                        'social' => 'go',
                        'active_class' => empty($social['google']) ? 'btn-grey' : '',
                        'social_icon' => 'fa-google',
                    ],
                ],
                'site' => [
                    'type' => 'hidden',
                ],
            ],
            [
                'form' => $this->view->cabinet_social_settings,
                'social' => $this->view->cabinet_settings_social,
                'hidden' => $this->view->cabinet_settings_hidden,
                'saved' => $this->view->cabinet_saved,
                'error' => $this->view->cabinet_error,
            ],
            true
        ));

        view::set('content', $this->view->cabinet_settings_form->prepare([]));
    }

    private function decline_order()
    {
        $order = new shop_orders($this->get->decline);

        if (!empty($order->status)) {
            $_SESSION['orders_error'] = i18n::get('You can decline only orders in status "New"');
            core::redirect('/cabinet/orders');
        }

        if (!empty($order->paid)) {
            $_SESSION['orders_error'] = i18n::get('An order') . ' ' . $this->get->pay . ' ' .
                i18n::get('is already paid') . ' ' . i18n::get('so you can\'t decline it');

            core::redirect('/cabinet/orders');
        }

        if ($order->user == $this->user->profile) {
            $order->save([
                'status' => 10,// Declined by customer
            ]);
        }
        core::redirect($this->previous);
    }

    private function edit_order()
    {
        $order = new shop_orders($this->get->edit);

        if (!empty($order->status)) {
            $_SESSION['orders_error'] = i18n::get('You can edit only orders in status "New"');
            core::redirect('/cabinet/orders');
        }

        if (!empty($order->paid)) {
            $_SESSION['orders_error'] = i18n::get('An order') . ' ' . $this->get->pay . ' ' .
                i18n::get('is already paid') . ' ' . i18n::get('so you can\'t edit it');

            core::redirect('/cabinet/orders');
        }

        $order_module = new order();

        if (!empty($this->post->payment_system) && !empty($this->post->delivery_system)) {
            $order_module->make_order();
            core::redirect('/cabinet/orders');
        }

        return $order_module->get_order_form($this->get->edit);
    }

    private function pay_order()
    {
        $order = new shop_orders($this->get->pay);


        if (!empty($order->status)) {
            $_SESSION['orders_error'] = i18n::get('You can try to pay only orders in status "New"');
            core::redirect('/cabinet/orders');
        }

        if (!empty($order->paid)) {
            $_SESSION['orders_error'] = i18n::get('An order') . ' ' . $this->get->pay . ' ' . i18n::get('is already paid');
            core::redirect('/cabinet/orders');
        }


        if ((int)$order->payment_system == 3) {
            core::redirect('/cabinet/orders');
        }

        $payment_lib = (new payment_systems($order->payment_system))->get_library();

        if (!empty($payment_lib) && method_exists($payment_lib, 'get_payment_redirect_form')) {

            core::out($payment_lib->get_payment_redirect_form([
                'order_id' => $order->id,
                'amount' => $order->total, // 0.1,
                'currency' => 'UAH' // TODO: check a currency code via payment library
            ]));
        }

        core::redirect('/cabinet/orders');
    }
}