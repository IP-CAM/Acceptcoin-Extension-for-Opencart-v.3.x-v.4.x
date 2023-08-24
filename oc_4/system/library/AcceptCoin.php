<?php

namespace Opencart\System\Library;

use Exception;

class AcceptCoin
{
    public const PREFIX                 = "ACOC";
    public const PROJECT_ID_SYMBOLS_NUM = 6;
    private const DOMAIN = "https://dev7.itlab-studio.com";

    public const ACCEPTCOIN_PROCESSED_AMOUNT_CODE = "acc_processed_amount";
    public const ACCEPTCOIN_PROCESSED_AMOUNT_TITLE = "Paid";

//    private const DOMAIN = "https://acceptcoin.io";

    /**
     * @param string $projectId
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
        array $order_info,
        string $returnUrlSuccess,
        string $returnUrlFailed
    )
    {
        if (!$projectId || !$projectSecret) {
            throw new Exception("Missing Acceptcoin configuration");
        }

        require_once DIR_EXTENSION . 'acceptcoin/system/library/JWT.php';

        $acceptcoinRequestLink = self::DOMAIN . "/api/iframe-invoices";

        $callbackUrl = HTTP_SERVER . 'index.php?route=extension/acceptcoin/payment/acceptcoin|callback';

        $referenceId = self::PREFIX . "-" . substr($projectId, 0, self::PROJECT_ID_SYMBOLS_NUM) . "-" . $order_info['order_id'];

        $requestData = [
            "amount"      => $order_info['total'],
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
        array $emailContent
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
        $mail->setReplyTo($config->get('config_email'));
        $mail->setSender($config->get('config_name'));
        $mail->setSubject($messageData['subject']);
        $mail->setHtml($messageData['body']);
        $mail->send();
    }

    /**
     * @param string $type
     * @param array $emailContent
     * @return string[]|null
     */
    private static function getEmailBody(string $type, array $emailContent): ?array
    {
        switch ($type) {
            case "FROZEN_DUE_AML":
            {
                return [
                    "subject" => "Dirty coins were identified through AML checks",
                    "body"    => "
                       <div>
                          <p>
                            Hello!
                          </p>
                          <p>" . $emailContent['name'] . " " . $emailContent['lastname'] . ". Your transaction for " . $emailContent['amount'] . " " . $emailContent['currency'] . " was blocked.
                            Transaction ID " . $emailContent['referenceId'] . ".
                          </p>
                          <p><b>To confirm the origin of funds, we ask that you fully answer the following questions:</b></p>
                          <ol>
                            <li>Through which platform did the funds come to you? If possible, please provide screenshots from the wallet/sender platform's withdrawal history, as well as links to both transactions on the explorer.</li>
                            <li>For what service did you receive the funds? - What was the transaction amount, as well as the date and time it was recieved?</li>
                            <li>Through which contact person did you communicate with the sender of the funds? If possible, please provide screenshots of your correspondence with the sender, where we can see confirmation of the transfer of funds.</li>
                          </ol>

                          <br>
                          <p>Additionally, we ask that you provide the following materials:</p>
                          <ul>
                            <li>Photo of one of your documents (passport, ID card or driver's license).</li>
                            <li>A selfie with this document and a sheet of paper on which today's date and signature will be handwritten.</li>
                          </ul>
                          <p><b>Please carefully write down the answers to these questions and email to support@acceptcoin.io</b></p>
                          <hr>
                          NOTE: <i>Please, don’t answer this mail, send your answer only to support@acceptcoin.io</i>.
                          </div>"
                ];
            }
            default:
            {
                return null;
            }
        }
    }


}
