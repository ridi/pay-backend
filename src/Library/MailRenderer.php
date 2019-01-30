<?php
declare(strict_types=1);

namespace RidiPay\Library;

use RidiPay\Kernel;

class MailRenderer
{
    private const MAIL_TEMPLATE_DIR = __DIR__ . '/../../resources/mail_templates';

    /** @var \Twig_Environment */
    private $twig;

    public function __construct()
    {
        $loader = new \Twig_Loader_Filesystem([self::MAIL_TEMPLATE_DIR]);
        $this->twig = new \Twig_Environment($loader, [
            'debug' => Kernel::isLocal()
        ]);

        $this->addGlobalVariables();
    }

    private function addGlobalVariables(): void
    {
        $ridi_pay_url = getenv('RIDI_PAY_URL');

        $this->twig->addGlobal('RIDI_PAY_URL', $ridi_pay_url);
        $this->twig->addGlobal('RIDI_PAY_SETTINGS_URL', $ridi_pay_url . '/settings');
    }

    /**
     * @param string $template_file_name
     * @param array $data
     * @return string
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function render(string $template_file_name, array $data = []): string
    {
        return $this->twig->render($template_file_name, $data);
    }
}
