<?php

namespace Opencart\Catalog\Model\Extension\AcceptCoin\Payment;

use Opencart\System\Engine\Model;

class AcceptCoin extends Model
{
    /**
     * @return array
     */
    public function getMethod(): array
    {
        $this->load->language('extension/acceptcoin/payment/acceptcoin');

        return [
            'code'       => 'acceptcoin',
            'title'      => $this->language->get('text_title'),
            'sort_order' => $this->config->get('payment_acceptcoin_sort_order')
        ];

    }
}