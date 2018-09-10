<?php
declare(strict_types=1);

namespace RidiPay\Library\Log;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class StdoutLogger extends Logger
{
    /**
     * @param string $name
     * @param array $handlers
     * @param array $processors
     * @throws \Exception
     */
    public function __construct(string $name, array $handlers = [], array $processors = [])
    {
        parent::__construct($name, $handlers, $processors);

        $this->pushHandler(self::getStdoutStreamHandler());
    }

    /**
     * @return StreamHandler
     * @throws \Exception
     */
    private static function getStdoutStreamHandler(): StreamHandler
    {
        $formatter = new LineFormatter(null, DATE_ATOM);
        $formatter->includeStacktraces();
        $stream = new StreamHandler('php://stdout');
        $stream->setFormatter($formatter);

        return $stream;
    }
}
