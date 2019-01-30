<?php
declare(strict_types=1);

namespace RidiPay\Tests\PHPStan;

use Doctrine\Common\Annotations\Reader;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

class AnnotationReaderDynamicReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
    /**
     * @return string
     */
    public function getClass(): string
    {
        return Reader::class;
    }

    /**
     * @param MethodReflection $method_reflection
     * @return bool
     */
    public function isMethodSupported(MethodReflection $method_reflection): bool
    {
        return $method_reflection->getName() === 'getMethodAnnotation';
    }

    /**
     * @param MethodReflection $method_reflection
     * @param MethodCall $method_call
     * @param Scope $scope
     * @return Type
     */
    public function getTypeFromMethodCall(
        MethodReflection $method_reflection,
        MethodCall $method_call,
        Scope $scope
    ): Type {
        return $scope->getType($method_call->args[0]->value);
    }
}