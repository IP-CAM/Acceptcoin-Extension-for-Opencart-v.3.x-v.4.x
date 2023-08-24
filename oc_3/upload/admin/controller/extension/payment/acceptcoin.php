<?php

class ControllerExtensionPaymentAcceptCoin extends Controller
{
	private $error = array();

	private const STATUSES_ID = [
		"PENDING"   => 1,
		"PROCESSED" => 15,
		"FAILED"    => 10
	];

	public function index()
	{
		$this->language->load('extension/payment/acceptcoin');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_acceptcoin', $this->request->post);
			$this->session->data['success'] = $this->language->get('success_save');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}

		# settings translations
		$this->setTranslationData($data);

		# admin actions
		$this->setActions($data);

		# error messages
		$this->setErrors($data);

		# breadcrumbs
		$this->setBreadcrumbs($data);

		# settings filling
		$this->fillSettings($data);

		#template components

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		#template components end

		$this->response->setOutput($this->load->view('extension/payment/acceptcoin', $data));
	}

	public function validate()
	{
		if (!$this->user->hasPermission('modify', 'extension/payment/acceptcoin')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_acceptcoin_project_id']) {
			$this->error['acc_project_id_error'] = $this->language->get('acc_project_id_error');
		}

		if (!$this->request->post['payment_acceptcoin_project_secret']) {
			$this->error['acc_project_secret_error'] = $this->language->get('acc_project_secret_error');
		}

		return !$this->error;
	}

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

	private function setActions(&$data)
	{
		$data['action'] = $this->url->link('extension/payment/acceptcoin', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
	}

	private function setErrors(&$data)
	{
		if (isset($this->error['acc_project_id_error'])) {
			$data['acc_project_id_error'] = $this->error['acc_project_id_error'];
		} else {
			$data['acc_project_id_error'] = '';
		}

		if (isset($this->error['acc_project_secret_error'])) {
			$data['acc_project_secret_error'] = $this->error['acc_project_secret_error'];
		} else {
			$data['acc_project_secret_error'] = '';
		}
	}

	private function setBreadcrumbs(&$data)
	{
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/acceptcoin', 'user_token=' . $this->session->data['user_token'], true),
		);
	}

	private function fillSettings(&$data)
	{
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_acceptcoin_order_status_pending'])) {
			$data['payment_acceptcoin_order_status_pending'] = $this->request->post['payment_acceptcoin_order_status_pending'];
		} else {
			$data['payment_acceptcoin_order_status_pending'] = $this->config->get('payment_acceptcoin_order_status_pending') ?? self::STATUSES_ID['PENDING'];
		}

		if (isset($this->request->post['payment_acceptcoin_order_status_processed'])) {
			$data['payment_acceptcoin_order_status_processed'] = $this->request->post['payment_acceptcoin_order_status_processed'];
		} else {
			$data['payment_acceptcoin_order_status_processed'] = $this->config->get('payment_acceptcoin_order_status_processed') ?? self::STATUSES_ID['PROCESSED'];
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

		if (isset($this->request->post['payment_acceptcoin_order_status_fail'])) {
			$data['payment_acceptcoin_order_status_fail'] = $this->request->post['payment_acceptcoin_order_status_fail'];
		} else {
			$data['payment_acceptcoin_order_status_fail'] = $this->config->get('payment_acceptcoin_order_status_fail') ?? self::STATUSES_ID['FAILED'];
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

		if (isset($this->request->post['payment_acceptcoin_status'])) {
			$data['payment_acceptcoin_status'] = $this->request->post['payment_acceptcoin_status'];
		} else {
			$data['payment_acceptcoin_status'] = $this->config->get('payment_acceptcoin_status');
		}

		if (isset($this->request->post['payment_acceptcoin_sort_order'])) {
			$data['payment_acceptcoin_sort_order'] = $this->request->post['payment_acceptcoin_sort_order'];
		} else {
			$data['payment_acceptcoin_sort_order'] = $this->config->get('payment_acceptcoin_sort_order');
		}
	}
}
