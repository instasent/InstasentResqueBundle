<?php

declare(strict_types=1);

namespace Instasent\ResqueBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class InstasentResqueExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('instasent_resque.resque.vendor_dir', $config['vendor_dir']);
        $container->setParameter('instasent_resque.resque.class', $config['class']);
        $container->setParameter('instasent_resque.resque.redis.host', $config['redis']['host']);
        $container->setParameter('instasent_resque.resque.redis.port', $config['redis']['port']);
        $container->setParameter('instasent_resque.resque.redis.database', $config['redis']['database']);

        if (!empty($config['prefix'])) {
            $container->setParameter('instasent_resque.prefix', $config['prefix']);
            $container->getDefinition('instasent_resque.resque')
                ->addMethodCall('setPrefix', [$config['prefix']]);
        }

        if (!empty($config['worker']['root_dir'])) {
            $container->setParameter('instasent_resque.worker.root_dir', $config['worker']['root_dir']);
        }

        if (!empty($config['auto_retry'])) {
            if (isset($config['auto_retry'][0])) {
                $container->getDefinition('instasent_resque.resque')
                    ->addMethodCall('setGlobalRetryStrategy', [$config['auto_retry'][0]]);
            } else {
                if (isset($config['auto_retry']['default'])) {
                    $container->getDefinition('instasent_resque.resque')
                        ->addMethodCall('setGlobalRetryStrategy', [$config['auto_retry']['default']]);
                    unset($config['auto_retry']['default']);
                }
                $container->getDefinition('instasent_resque.resque')
                    ->addMethodCall('setJobRetryStrategy', [$config['auto_retry']]);
            }
        }
    }
}
