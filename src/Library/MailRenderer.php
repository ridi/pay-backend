<?php
declare(strict_types=1);

namespace RidiPay\Library;

use RidiPay\Kernel;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

class MailRenderer
{
    private const MAIL_TEMPLATE_DIR = __DIR__ . '/../../resources/mail_templates';

    /** @var \Twig_Environment */
    private $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader([self::MAIL_TEMPLATE_DIR]);
        $this->twig = new Environment($loader, [
            'debug' => Kernel::isLocal()
        ]);

        $this->addGlobalVariables();
    }

    private function addGlobalVariables(): void
    {
        $ridi_pay_url = getenv('RIDI_PAY_URL', true);

        $this->twig->addGlobal('RIDI_PAY_URL', $ridi_pay_url);
        $this->twig->addGlobal('RIDI_PAY_SETTINGS_URL', $ridi_pay_url . '/settings');
    }

    /**
     * @param string $template_file_name
     * @param array $data
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function render(string $template_file_name, array $data = []): string
    {
        return $this->twig->render($template_file_name, $data);
    }
}
