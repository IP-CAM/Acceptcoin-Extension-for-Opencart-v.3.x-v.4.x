<?php

class AcceptCoin
{
	public const PREFIX                 = "ACOC";
	public const PROJECT_ID_SYMBOLS_NUM = 6;

	private const DOMAIN = "https://dev7.itlab-studio.com";
//	private const DOMAIN = "https://acceptcoin.io";

	public const ACCEPTCOIN_PROCESSED_AMOUNT_CODE = "acc_processed_amount";
	public const ACCEPTCOIN_PROCESSED_AMOUNT_TITLE = "Paid";

	public const TYPE_NEW = "NEW";
	public const TYPE_FROZEN_DUE_AML = "FROZEN_DUE_AML";


	/**
	 * @param string $projectId
	 * @param string $amount
	 * @param string $projectSecret
	 * @param array $order_info
	 * @param string $returnUrlSuccess
	 * @param string $returnUrlFailed
	 * @return mixed
	 * @throws Exception
	 */
	public static function createPayment(
		string $projectId,
		string $projectSecret,
		string $amount,
		array  $order_info,
		string $returnUrlSuccess,
		string $returnUrlFailed
	)
	{
		if (!$projectId || !$projectSecret) {
			throw new Exception("Missing Acceptcoin configuration");
		}

		require_once DIR_SYSTEM . 'library/acceptcoin/JWT.php';

		$acceptcoinRequestLink = self::DOMAIN . "/api/iframe-invoices";
		$callbackUrl = HTTPS_SERVER . 'index.php?route=extension/payment/acceptcoin/callback';

		$referenceId = self::PREFIX . "-" . substr($projectId, 0, self::PROJECT_ID_SYMBOLS_NUM) . "-" . $order_info['order_id'];

		$requestData = [
			"amount"      => $amount,
			"referenceId" => $referenceId,
			"callBackUrl" => $callbackUrl
		];

		if ($returnUrlSuccess) {
			$requestData ["returnUrlSuccess"] = $returnUrlSuccess;
		}

		if ($returnUrlFailed) {
			$requestData ["returnUrlFail"] = $returnUrlFailed;
		}

		$headers = [
			"Accept: application/json",
			"Content-Type: application/json",
			"Authorization: JWS-AUTH-TOKEN " . JWT::createToken($projectId, $projectSecret)
		];

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $acceptcoinRequestLink);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_HEADER, false);

		$response = curl_exec($curl);
		curl_close($curl);

		$responseData = json_decode($response, true);

		if (!$responseData) {
			throw new Exception('Acceptcoin payment method is not available at this moment.');
		}

		if (!isset($responseData['link'])) {
			throw new Exception('Acceptcoin payment method is not available at this moment.');
		}

		return $responseData['link'];
	}

	/**
	 * @param string $recipient
	 * @param string $type
	 * @param $config
	 * @param array $emailContent
	 * @return void
	 * @throws Exception
	 */
	public static function sendMessage(
		string $recipient,
		string $type,
			   $config,
		array  $emailContent
	)
	{
		$messageData = self::getEmailBody($type, $emailContent);

		if (!$messageData) {
			return;
		}

		$mail = new Mail($config->get('config_mail_engine'));
		$mail->parameter = $config->get('config_mail_parameter');
		$mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $config->get('config_mail_smtp_username');
		$mail->smtp_password = $config->get('config_mail_smtp_password');
		$mail->smtp_port = $config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $config->get('config_mail_smtp_timeout');

		$mail->setTo($recipient);
		$mail->setFrom($config->get('config_email'));
		$mail->setSender($config->get('config_name'));
		$mail->setReplyTo($config->get('config_email'));
		$mail->setSubject($messageData['subject']);
		$mail->setHtml($messageData['body']);
		$mail->send();
	}

	/**
	 * @param string $type
	 * @param array $emailContent
	 * @return string[]|null
	 */
	private static function getEmailBody(string $type, array $vars): ?array
	{
		switch ($type) {
			case self::TYPE_FROZEN_DUE_AML:
			{
				return [
					"subject" => "Dirty coins were identified through AML checks",
					"body"    => '<div
                    style="display:block;width:100%;table-layout:fixed;font-size:13px;line-height:1.4;background:#135A30; text-align: center">
                    <div style="padding:10px 15px;display:inline-block;text-align: left">
                        <div style="padding:10px 15px;display:inline-block;">
                            <div style="width:100px;padding:0;vertical-align:middle">
                                <a
                                    href="https://acceptcoin.io"
                                    style="display:inline-block;vertical-align:middle;color:#333"
                                    target="_blank"
                                >
                                    <img
                                        height="32"
                                        src="https://acceptcoin.io/assets/images/logo-white.png"
                                        style="display:inline-block;vertical-align:middle;border:none"
                                        alt="Acceptcoin"
                                    >
                                </a>
                            </div>
                        </div>
                        <div
                            style="margin:0 auto;padding:35px 15px;background:#fff;font-size:13px;color:#333333;line-height:1.5;text-align: left;">
                            <p style="margin:0 0 10px">
                                Dear '. $vars['name'] .' ' . $vars['lastname'] .'
                            </p>
                            <p style="margin:0 0 10px;background-color:#F69E55;padding:15px;border-radius:25px">
                                <span
                                    style="width:20px;height:20px;background:#EA7B29;border-radius:50%;color:#ffffff;margin-right:5px;font-size:14px;text-align:center;display:inline-block;">!</span>
                                Your transaction '. $vars['transactionId'] .' from '. $vars['date'] .' was blocked
                            </p>
                            <p><b>To confirm the origin of funds, we ask that you fully answer the following questions:</b></p>
                            <p>
                                1. Through which platform did the funds come?
                                If possible, please provide screenshots from the wallet/sender platform\'s withdrawal history, as well as
                                links to both transactions on the explorer.
                            </p>
                            <p>
                            2. For what service were the funds received?
                                What was the transaction amount, as well as the date and time it was received?
                            </p>
                            <p>
                            3. Through which contact person does your client communicate with the sender of the funds?
                                If possible, please provide screenshots of your correspondence with the sender, where we can see
                                confirmation of the transfer of funds.
                            </p>

                            <p>Additionally, we ask that you provide the following materials:</p>
                            <ul>
                                <li>Photo of one of your documents (passport, ID card, or driver\'s license).</li>
                                <li>A selfie with this document and a sheet of paper on which today\'s date and signature will be
                                    handwritten.
                                </li>
                            </ul>

                            <p>Please carefully write down the answers to these questions and email to <a
                                href="">support@acceptcoin.io</a>

                            <p style="margin:0 0 10px;background-color: #6FA5D3;padding:15px;border-radius:25px">
                                <span
                                    style="width:20px;height:20px;background:#1890ff;border-radius:50%;color:#ffffff;margin-right:5px;font-size:14px;text-align:center;display:inline-block;">i</span>
                            Please, don’t answer this mail, send your answer only to
                                support@acceptcoin.io</p>
                            <p>
                            We appreciate you choosing us!
                            </p>
                            <div>
                                If you have any questions, please contact acceptcoin.io administration or write to us.
                            </div>
                        </div>
                    </div>
                </div>'
				];
			}
			case self::TYPE_NEW: {
				return [
					'subject' => "Payment created for " . $vars['vendorName'],
					'body' => '
                    <div style="display:block;width:100%;table-layout:fixed;font-size:13px;line-height:1.4;background:#135A30; text-align: center">
                    <div style="padding:10px 15px;display:inline-block;text-align: left">
                        <div style="padding:10px 15px;display:inline-block;">
                            <div style="width:100px;padding:0;vertical-align:middle">
                                <a
                                    href="https://acceptcoin.io"
                                    style="display:inline-block;vertical-align:middle;color:#333"
                                    target="_blank"
                                >
                                    <img
                                        height="32"
                                        src="https://acceptcoin.io/assets/images/logo-white.png"
                                        style="display:inline-block;vertical-align:middle;border:none"
                                        alt="Acceptcoin"
                                    >
                                </a>
                            </div>
                        </div>

                        <div
                            style="table-layout:fixed;margin:0;padding:35px 15px;background:#fff;font-size:13px;color:#333333;line-height:1.5;">
                            <p style="margin:0 0 10px">
                                Hello ' . $vars['name'] . ' ' . $vars['lastname'] . '
                            </p>
                            <p>
                                Want to complete your payment for ' . $vars['amount'] . ' ' . $vars['currency'] . '?
                            </p>
                            <p>
                                To finish up, go back to payment page or use the button below.
                            </p>

                            <p style="width:fit-content;margin:0 auto 10px;padding:20px">
                                <a style="text-decoration:none;background-color:#016E3B;width:fit-content;border-radius:5px;padding:17px 80px;color:#fff"
                                   href="' . $vars['link'] . '" target="_blank">
                                    Pay
                                </a>
                            </p>
                            <p>
                                We appreciate you choosing us!
                            </p>
                            <div>
                                If you have any questions, please contact acceptcoin.io administration or write to us.
                            </div>
                        </div>
                    </div>
                </div>'
				];
			}
			default:
			{
				return null;
			}
		}
	}
}
