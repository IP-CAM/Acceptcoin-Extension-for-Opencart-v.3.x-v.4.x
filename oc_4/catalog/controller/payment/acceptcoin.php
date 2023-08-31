<?php

namespace Opencart\Catalog\Controller\Extension\AcceptCoin\Payment;

use Exception;
use Opencart\extension\acceptcoin\system\library\ACUtils;
use Opencart\System\Engine\Controller;
use Opencart\System\Library\Log;
use Opencart\System\Library\Signature;
use Throwable;

class AcceptCoin extends Controller
{
    private const STABLE_CURRENCY = "USD";

    /**
     * @return string
     */
    public function index(): string
    {
        $this->load->language('extension/acceptcoin/payment/acceptcoin');

        if (!$this->config->get('payment_acceptcoin_project_id') ||
            !$this->config->get('payment_acceptcoin_project_secret')) {

            $data['error'] = $this->language->get('settings_error');

            return $this->load->view('extension/acceptcoin/payment/acceptcoin', $data);
        }

        return $this->load->view('extension/acceptcoin/payment/acceptcoin');
    }

    /**
     * @return void
     */
    public function createOrder()
    {
        $this->load->language('extension/acceptcoin/payment/acceptcoin');
        $this->response->addHeader('Content-Type: application/json');

        $projectId = $this->config->get("payment_acceptcoin_project_id");
        $projectSecret = $this->config->get("payment_acceptcoin_project_secret");
        $returnUrlSuccess = $this->config->get("payment_acceptcoin_redirect_url_success");
        $returnUrlFailed = $this->config->get("payment_acceptcoin_redirect_url_fail");

        if (!isset($this->session->data['order_id'])) {
            $data['error'] = $this->language->get('missing_order_error');
            $this->response->setOutput(json_encode($data));
            return;
        }
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        if (!$order_info) {
            $data['error'] = $this->language->get('missing_order_error');
            $this->response->setOutput(json_encode($data));
            return;
        }

        $amount = (string)$this->currency->convert(
            $order_info['total'] * $order_info['currency_value'],
            $order_info['currency_code'],
            self::STABLE_CURRENCY
        );

        try {
            require_once DIR_EXTENSION . 'acceptcoin/system/library/AcceptCoin.php';

            $iframeLink = \Opencart\System\Library\AcceptCoin::createPayment(
                $projectId,
                $projectSecret,
                $amount,
                $order_info,
                $returnUrlSuccess,
                $returnUrlFailed
            );

            $data['iframeLink'] = $iframeLink;

            $this->model_checkout_order->addHistory(
                $this->session->data['order_id'],
                $this->config->get("payment_acceptcoin_order_status_pending")
            );

            \Opencart\System\Library\AcceptCoin::sendMessage(
                $order_info['email'],
                \Opencart\System\Library\AcceptCoin::TYPE_NEW, $this->config,
                [
                    "name"       => $order_info['payment_firstname'],
                    "lastname"   => $order_info['payment_lastname'],
                    "vendorName" => $this->config->get('config_name'),
                    "amount"     => $order_info['total'] * $order_info['currency_value'],
                    "currency"   => $order_info['currency_code'],
                    "link"       => $iframeLink
                ]
            );
        } catch (Throwable $exception) {
            $data['error'] = $exception->getMessage();
            $this->response->setOutput(json_encode($data));
            return;
        }

        $this->clearCart();

        $this->response->setOutput(json_encode($data));
    }

    /**
     * @return void
     * @throws Exception
     */
    public function callback()
    {
        $body = file_get_contents("php://input");
        $response = json_decode($body, true);

        $this->load->language('extension/payment/acceptcoin');

        if (!isset($response['data'])) {
            throw new Exception($this->language->get('missing_data_error'), 400);
        }

        if (!is_array($response['data'])) {
            $response['data'] = json_decode($response['data'], true);
        }

        if (!isset($response['data']['referenceId'])) {
            throw new Exception($this->language->get('missing_data_error'), 400);
        }

        require_once DIR_EXTENSION . 'acceptcoin/system/library/AcceptCoin.php';
        require_once DIR_EXTENSION . 'acceptcoin/system/library/Signature.php';
        require_once DIR_EXTENSION . 'acceptcoin/system/library/ACUtils.php';

        if (!Signature::checkSignature(
            json_encode($response['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $response['signature'],
            $this->config->get("payment_acceptcoin_project_secret")
        )) {
            throw new Exception($this->language->get('invalid_signature_error'), 400);
        }

        $referenceArray = explode('-', $response['data']['referenceId']);

        if (!isset($referenceArray[2])) {
            throw new Exception($this->language->get('not_processing_order_error'), 400);
        }

        $orderId = $referenceArray[2];

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($orderId);

        if (!$order_info || $order_info['order_status_id'] != $this->config->get("payment_acceptcoin_order_status_pending")) {
            throw new Exception($this->language->get('not_processing_order_error'), 400);
        }

        $responseStatus = strtolower($response['data']['status']['value']);

        try {
            if ($response['data']['status']['value'] === "FROZEN_DUE_AML") {
                $responseStatus = "fail";

                $emailContent = [
                    "name"          => $order_info['payment_firstname'],
                    "lastname"      => $order_info['payment_lastname'],
                    "transactionId" => $response['data']['id'],
                    "date"          => date("Y-m-d H:i:s", $response['data']['createdAt'])
                ];

                \Opencart\System\Library\AcceptCoin::sendMessage($order_info['email'], $response['data']['status']['value'], $this->config, $emailContent);
            }

            $this->model_checkout_order->addHistory($orderId, $this->config->get("payment_acceptcoin_order_status_$responseStatus"), $this->language->get('status_changed'), true);

            if ($response['data']['status']['value'] === "PROCESSED") {
                $this->insertOrderTotal(
                    $orderId,
                    \Opencart\System\Library\AcceptCoin::ACCEPTCOIN_PROCESSED_AMOUNT_CODE,
                    \Opencart\System\Library\AcceptCoin::ACCEPTCOIN_PROCESSED_AMOUNT_TITLE,
                    $this->currency->convert(
                        ACUtils::getProcessedAmount($response['data']),
                        self::STABLE_CURRENCY,
                        $order_info['currency_code']
                    ) / $order_info['currency_value']
                );
            }
        } catch (Throwable $exception) {
            throw new Exception($exception->getMessage(), 400);
        }
    }

    /**
     * @return void
     */
    private function clearCart()
    {
        $this->cart->clear();

        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['shipping_address']);
        unset($this->session->data['guest']);
        unset($this->session->data['comment']);
        unset($this->session->data['order_id']);
        unset($this->session->data['coupon']);
        unset($this->session->data['reward']);
        unset($this->session->data['voucher']);
        unset($this->session->data['vouchers']);
        unset($this->session->data['totals']);
    }

    /**
     * @return void
     */
    private function insertOrderTotal($orderId, $code, $title, $value, $sort = 0)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "order_total SET order_id = '" . (int)$orderId . "', code = '" . $this->db->escape($code) .  "', extension = '" . $this->db->escape("acceptcoin") .  "', title = '" . $this->db->escape($title) . "', `value` = '" . (float)$value . "', sort_order = '" . (int)$sort . "'");
    }

}