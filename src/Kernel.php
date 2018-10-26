<?php
declare(strict_types=1);

namespace RidiPay;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,yaml,yml}';

    private const PRODUCTION_ENVIRONMENTS = ['prod', 'staging'];
    private const DEVELOPMENT_ENVIRONMENTS = ['dev', 'test', 'phpunit'];

    public function getCacheDir()
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir()
    {
        return $this->getProjectDir() . '/var/log';
    }

    public function registerBundles()
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        $container->addResource(new FileResource($this->getProjectDir() . '/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', true);
        $conf_dir = $this->getProjectDir() . '/config';

        $loader->load($conf_dir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($conf_dir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($conf_dir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($conf_dir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $routes->import($this->getProjectDir() . '/src/Controller/', '/', 'annotation');
    }

    /**
     * @return bool
     */
    public static function isDev(): bool
    {
        return in_array(getenv('APP_ENV'), self::DEVELOPMENT_ENVIRONMENTS);
    }
}
