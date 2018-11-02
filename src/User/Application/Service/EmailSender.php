<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ridibooks\Crm\Client;
use Ridibooks\Crm\Notification\Payload\Email;
use RidiPay\Library\EmailAddressConstant;
use RidiPay\Library\SentryHelper;

class EmailSender
{
    /**
     * @param string $recipient
     * @param string $title
     * @param string $body
     */
    public static function send(string $recipient, string $title, string $body): void
    {
        $email = new Email(
            EmailAddressConstant::NOREPLY_ADDRESS,
            [$recipient],
            $title,
            $body
        );
        $client = Client::createWithDefaultRetry();
        $response = $client->sendEmail($email);

        if ($response->getStatusCode() !== 200) {
            SentryHelper::captureMessage('Email 발송 실패', [], [], true);
        }
    }
}
