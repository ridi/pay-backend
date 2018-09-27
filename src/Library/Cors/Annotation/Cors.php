<?php
declare(strict_types=1);

namespace RidiPay\Library\Cors\Annotation;

/**
 * Annotation class for @Cors().
 *
 * Annotated classes or methods with annotation @Cors use CorsMiddleware.
 * @see CorsMiddleware
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class Cors
{
    /** @var string[] */
    private $methods;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->setMethods($data);
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @param array $data
     */
    private function setMethods(array $data): void
    {
        $this->methods = $data['methods'];
    }
}
