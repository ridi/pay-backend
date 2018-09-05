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
 * @Target({"CLASS", "METHOD"})
 */
class JwtAuth
{
}
