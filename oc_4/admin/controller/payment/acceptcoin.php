<?php

namespace Opencart\Admin\Controller\Extension\AcceptCoin\Payment;

use Opencart\System\Engine\Config;
use Opencart\System\Engine\Controller;

class AcceptCoin extends Controller
{
    private $error = [];

    private const STATUSES = [
        "PENDING"   => 'Pending',
        "PROCESSED" => 'Processing',
        "FAILED"    => 'Failed'
    ];

    public function index(): void
    {
        $_config = new Config();
        $_config->addPath(DIR_EXTENSION . 'acceptcoin/system/config/');
        $_config->load('acceptcoin');

        $config_settings = $_config->get('acceptcoin_settings');

        $this->load->language('extension/acceptcoin/payment/acceptcoin');
        $this->document->setTitle($this->language->get('heading_title'));

        # settings translations
        $this->setTranslationData($data);

        # admin actions
        $this->setActions($data);

        # breadcrumbs
        $this->setBreadcrumbs($data);
        # settings filling
        $this->fillSettings($data);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/acceptcoin/payment/acceptcoin', $data));
    }

    public function install(): void
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/acceptcoin/payment/acceptcoin');

        $this->model_setting_setting->editValue('config', 'config_session_samesite', 'Lax');

        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('acceptcoin_order_add_history');
        $this->model_setting_event->deleteEventByCode('acceptcoin_order_delete_order');
        $this->model_setting_event->deleteEventByCode('acceptcoin_customer_delete_customer');

        if (VERSION >= '4.0.1.0') {
            $this->model_setting_event->addEvent([
                'code'    => 'acceptcoin_header', 'description' => '',
                'trigger' => 'catalog/controller/common/header/before',
                'action'  => 'extension/acceptcoin/payment/acceptcoin|header_before',
                'status'  => true, 'sort_order' => 1
            ]);
            $this->model_setting_event->addEvent([
                'code'        => 'acceptcoin_extension_get_extensions_by_type',
                'description' => '',
                'trigger'     => 'catalog/model/setting/extension/getExtensionsByType/after',
                'action'      => 'extension/acceptcoin/payment/acceptcoin|extension_get_extensions_by_type_after',
                'status'      => true, 'sort_order' => 2
            ]);
            $this->model_setting_event->addEvent([
                'code'        => 'acceptcoin_extension_get_extension_by_code',
                'description' => '',
                'trigger'     => 'catalog/model/setting/extension/getExtensionByCode/after',
                'action'      => 'extension/acceptcoin/payment/acceptcoin|extension_get_extension_by_code_after',
                'status'      => true, 'sort_order' => 3
            ]);
        } else {
            $this->model_setting_event->addEvent('acceptcoin_header', '', 'catalog/controller/common/header/before', 'extension/acceptcoin/payment/acceptcoin|header_before', true, 1);
            $this->model_setting_event->addEvent('acceptcoin_extension_get_extensions_by_type', '', 'catalog/model/setting/extension/getExtensionsByType/after', 'extension/acceptcoin/payment/acceptcoin|extension_get_extensions_by_type_after', true, 2);
            $this->model_setting_event->addEvent('acceptcoin_extension_get_extension_by_code', '', 'catalog/model/setting/extension/getExtensionByCode/after', 'extension/acceptcoin/payment/acceptcoin|extension_get_extension_by_code_after', true, 3);
        }
    }

    public function uninstall(): void
    {
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('acceptcoin_header');
        $this->model_setting_event->deleteEventByCode('acceptcoin_extension_get_extensions_by_type');
        $this->model_setting_event->deleteEventByCode('acceptcoin_extension_get_extension_by_code');
    }

    /**
     * @return void
     */
    public function save(): void
    {
        $this->load->language('extension/acceptcoin/payment/acceptcoin');

        $this->load->model('extension/acceptcoin/payment/acceptcoin');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_acceptcoin', $this->request->post);

            $data['success'] = $this->language->get('success_save');
        }

        $data['error'] = $this->error;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    /**
     * @return bool
     */
    public function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/acceptcoin/payment/acceptcoin')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_acceptcoin_project_id']) {
            $this->error['project_id'] = $this->language->get('acc_project_id_error');
        }

        if (!$this->request->post['payment_acceptcoin_project_secret']) {
            $this->error['acc_project_secret_error'] = $this->language->get('acc_project_secret_error');
        }

        return !$this->error;
    }

    /**
     * @param $data
     * @return void
     */
    private function setTranslationData(&$data)
    {
        $data['success_save'] = $this->language->get('success_save');

        $data['text_button_save'] = $this->language->get('text_button_save');
        $data['text_button_cancel'] = $this->language->get('text_button_cancel');
        $data['text_home'] = $this->language->get('text_home');
        $data['text_extension'] = $this->language->get('text_extension');
        $data['heading_title'] = $this->language->get('heading_title');
        $data['error_permission'] = $this->language->get('error_permission');

        $data['help_button_create_acc_acceptcoin'] = $this->language->get('help_button_create_acc_acceptcoin');
        $data['acc_module_version'] = $this->language->get('acc_module_version');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['acc_status'] = $this->language->get('acc_status');
    }

    /**
     * @param $data
     * @return void
     */
    private function setActions(&$data)
    {
        $data['save'] = $this->url->link('extension/acceptcoin/payment/acceptcoin|save', 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
    }

    /**
     * @param $data
     * @return void
     */
    private function setBreadcrumbs(&$data)
    {
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/acceptcoin', 'user_token=' . $this->session->data['user_token'], true),
        ];
    }

    /**
     * @param $data
     * @return void
     */
    private function fillSettings(&$data)
    {
        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_acceptcoin_order_status_pending'])) {
            $data['payment_acceptcoin_order_status_pending'] = $this->request->post['payment_acceptcoin_order_status_pending'];
        } else {
            $data['payment_acceptcoin_order_status_pending'] = !empty($this->config->get('payment_acceptcoin_order_status_pending')) ? $this->config->get('payment_acceptcoin_order_status_pending') : $this->findOrderStatus(self::STATUSES['PENDING'], "name");
        }

        if (isset($this->request->post['payment_acceptcoin_order_status_processed'])) {
            $data['payment_acceptcoin_order_status_processed'] = $this->request->post['payment_acceptcoin_order_status_processed'];
        } else {
            $data['payment_acceptcoin_order_status_processed'] = !empty($this->config->get('payment_acceptcoin_order_status_processed')) ? $this->config->get('payment_acceptcoin_order_status_processed') : $this->findOrderStatus(self::STATUSES['PROCESSED'], "name");
        }

        if (isset($this->request->post['payment_acceptcoin_order_status_fail'])) {
            $data['payment_acceptcoin_order_status_fail'] = $this->request->post['payment_acceptcoin_order_status_fail'];
        } else {
            $data['payment_acceptcoin_order_status_fail'] = !empty($this->config->get('payment_acceptcoin_order_status_fail')) ? $this->config->get('payment_acceptcoin_order_status_fail') : $this->findOrderStatus(self::STATUSES['FAILED'], "name");
        }

        if (isset($this->request->post['payment_acceptcoin_project_secret'])) {
            $data['payment_acceptcoin_project_secret'] = $this->request->post['payment_acceptcoin_project_secret'];
        } else {
            $data['payment_acceptcoin_project_secret'] = $this->config->get('payment_acceptcoin_project_secret') ?? "";
        }

        if (isset($this->request->post['payment_acceptcoin_project_id'])) {
            $data['payment_acceptcoin_project_id'] = $this->request->post['payment_acceptcoin_project_id'];
        } else {
            $data['payment_acceptcoin_project_id'] = $this->config->get('payment_acceptcoin_project_id') ?? "";
        }

        if (isset($this->request->post['payment_acceptcoin_sort_order'])) {
            $data['payment_acceptcoin_sort_order'] = $this->request->post['payment_acceptcoin_sort_order'];
        } else {
            $data['payment_acceptcoin_sort_order'] = $this->config->get('payment_acceptcoin_sort_order');
        }

        if (isset($this->request->post['payment_acceptcoin_status'])) {
            $data['payment_acceptcoin_status'] = $this->request->post['payment_acceptcoin_status'];
        } else {
            $data['payment_acceptcoin_status'] = $this->config->get('payment_acceptcoin_status');
        }

        if (isset($this->request->post['payment_acceptcoin_redirect_url_success'])) {
            $data['payment_acceptcoin_redirect_url_success'] = $this->request->post['payment_acceptcoin_redirect_url_success'];
        } else {
            $data['payment_acceptcoin_redirect_url_success'] = $this->config->get('payment_acceptcoin_redirect_url_success') ?? "";
        }

        if (isset($this->request->post['payment_acceptcoin_redirect_url_fail'])) {
            $data['payment_acceptcoin_redirect_url_fail'] = $this->request->post['payment_acceptcoin_redirect_url_fail'];
        } else {
            $data['payment_acceptcoin_redirect_url_fail'] = $this->config->get('payment_acceptcoin_redirect_url_fail') ?? "";
        }
    }

    /**
     * @param string $statusName
     * @param string $column
     * @return mixed
     */
    private function findOrderStatus(string $statusName, string $column)
    {
        return $this->model_localisation_order_status->getOrderStatuses()[array_search($statusName, array_column($this->model_localisation_order_status->getOrderStatuses(), $column))]['order_status_id'];
    }
}
