<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt\Annotation;

/**
 * Annotation class for @JwtAuth().
 *
 * Annotated classes or methods with annotation @JwtAuth use JwtAuthorizationMiddleware.
 * @see JwtAuthorizationMiddleware
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class JwtAuth
{
    /**
     * JWT payload의 iss로 가능한 값들
     *
     * @var string[]
     */
    private $isses;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->setIsses($data['isses']);
    }

    /**
     * @param string[] $isses
     */
    private function setIsses(array $isses)
    {
        $this->isses = $isses;
    }

    /**
     * @return string[]
     */
    public function getIsses(): array
    {
        return $this->isses;
    }
}
