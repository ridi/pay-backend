<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
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
                $client = new Client(['base_uri' => 'https://dev.ridi.io']);
            } else {
                $client = new Client(['base_uri' => 'https://ridibooks.com']);
            }
            $client->post(
                '/api/notification/email',
                [
                    RequestOptions::HEADERS => ['api-key' => getenv('EMAIL_API_KEY', true)],
                    RequestOptions::JSON => [
                        'to' => [$recipient],
                        'from' => EmailAddressConstant::NOREPLY_ADDRESS,
                        'html' => $body,
                        'subject' => $title,
                    ]
                ]
            );
        } catch (\Throwable $t) {
            // 이메일 발송 실패로 인한 exception 발생 시, API 요청 자체에 영향을 주지 않도록 catch
            SentryHelper::captureException($t);
        }
    }
}
