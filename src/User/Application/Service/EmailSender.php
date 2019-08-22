<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ridibooks\Crm\Client;
use Ridibooks\Crm\Notification\Payload\Email;
use RidiPay\Kernel;
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
        try {
            if (Kernel::isDev()) {
                $title = "[Dev] {$title}";
            }

            $email = new Email(
                EmailAddressConstant::NOREPLY_ADDRESS,
                [$recipient],
                $title,
                $body
            );

            if (Kernel::isDev()) {
                $client = Client::createWithDefaultRetry(['base_uri' => 'https://crm-api.dev.ridi.io']);
            } else {
                $client = Client::createWithDefaultRetry();
            }

            $client->sendEmail($email);
        } catch (\Throwable $t) {
            // 이메일 발송 실패로 인한 exception 발생 시, API 요청 자체에 영향을 주지 않도록 catch
            SentryHelper::captureException($t);
        }
    }
}
