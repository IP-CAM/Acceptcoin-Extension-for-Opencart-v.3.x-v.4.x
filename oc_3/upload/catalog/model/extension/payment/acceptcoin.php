<?php

class ModelExtensionPaymentAcceptCoin extends Model
{
	/**
	 * @return array
	 */
	public function getMethod(): array
	{
		$this->load->language('extension/payment/acceptcoin');

		$listItem = '<span><img style="max-width: 30px; margin-right: 10px;" src="https://acceptcoin.io/assets/images/logo50.png"/>'
			. $this->language->get('text_title') . '</span>';

		return [
			'code'       => 'acceptcoin',
			'title'      => $listItem,
			'sort_order' => $this->config->get('payment_acceptcoin_sort_order')
		];

	}
}

