<?php

namespace Oneup\FlysystemBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OneupFlysystemExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('factories.xml');
        $loader->load('adapters.xml');
        $loader->load('flysystem.xml');
        $loader->load('cache.xml');
        $loader->load('plugins.xml');
    }
}
