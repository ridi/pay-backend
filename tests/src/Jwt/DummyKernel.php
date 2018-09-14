<?php
declare(strict_types=1);

namespace RidiPay\Tests\Jwt;

use RidiPay\Kernel;
use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Routing\RouteCollectionBuilder;

class DummyKernel extends Kernel
{
    /**
     * @param RouteCollectionBuilder $routes
     * @throws FileLoaderLoadException
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->import(DummyController::class, '/', 'annotation');
    }
}
