<?php

namespace Oneup\FlysystemBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class OneupFlysystemExtension extends Extension
{
    private $adapterFactories;
    private $cacheFactories;

    public function load(array $configs, ContainerBuilder $container)
    {
        list($adapterFactories, $cacheFactories) = $this->getFactories($configs);

        $configuration = new Configuration($adapterFactories, $cacheFactories);
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('adapters.xml');
        $loader->load('flysystem.xml');
        $loader->load('cache.xml');
        $loader->load('plugins.xml');

        $adapters = array();
        $filesystems = array();
        $caches = array();

        foreach ($config['adapters'] as $name => $adapter) {
            $adapters[$name] = $this->createAdapter($name, $adapter, $container, $adapterFactories);
        }

        foreach ($config['cache'] as $name => $cache) {
            $caches[$name] = $this->createCache($name, $cache, $container, $cacheFactories);
        }

        foreach ($config['filesystems'] as $name => $filesystem) {
            $filesystems[$name] = $this->createFilesystem($name, $filesystem, $container, $adapters, $caches);
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        list($adapterFactories, $cacheFactories) = $this->getFactories();

        return new Configuration($adapterFactories, $cacheFactories);
    }

    private function createCache($name, array $config, ContainerBuilder $container, array $factories)
    {
        foreach ($config as $key => $adapter) {
            if (array_key_exists($key, $factories)) {
                $id = sprintf('oneup_flysystem.%s_cache', $name);
                $factories[$key]->create($container, $id, $adapter);

                return $id;
            }
        }

        throw new \LogicException(sprintf('The cache \'%s\' is not configured.', $name));
    }

    private function createAdapter($name, array $config, ContainerBuilder $container, array $factories)
    {
        foreach ($config as $key => $adapter) {
            if (array_key_exists($key, $factories)) {
                $id = sprintf('oneup_flysystem.%s_adapter', $name);
                $factories[$key]->create($container, $id, $adapter);

                return $id;
            }
        }

        throw new \LogicException(sprintf('The adapter \'%s\' is not configured.', $name));
    }

    private function createFilesystem($name, array $config, ContainerBuilder $container, array $adapters, array $caches)
    {
        if (!array_key_exists($config['adapter'], $adapters)) {
            throw new \LogicException(sprintf('The adapter \'%s\' is not defined.', $config['adapter']));
        }

        $adapter = $adapters[$config['adapter']];
        $id = sprintf('oneup_flysystem.%s_filesystem', $name);

        $cache = null;
        if (array_key_exists($config['cache'], $caches)) {
            $cache = $caches[$config['cache']];

            $container
                ->setDefinition($adapter . '_cached', new DefinitionDecorator('oneup_flysystem.adapter.cached'))
                ->replaceArgument(0, new Reference($adapter))
                ->replaceArgument(1, new Reference($cache));
        }

        $tagParams = array('key' => $name);

        if ($config['mount']) {
            $tagParams['mount'] = $config['mount'];
        }

        $options = [];
        if (array_key_exists('visibility', $config)) {
            $options['visibility'] = $config['visibility'];
        }

        $container
            ->setDefinition($id, new DefinitionDecorator('oneup_flysystem.filesystem'))
            ->replaceArgument(0, new Reference($cache ? $adapter . '_cached' : $adapter))
            ->replaceArgument(1, $options)
            ->addTag('oneup_flysystem.filesystem', $tagParams);


        if (!empty($config['alias'])) {
            $container->getDefinition($id)->setPublic(false);
            $container->setAlias($config['alias'], $id);
        }

        // Attach Plugins
        $defFilesystem = $container->getDefinition($id);

        if (isset($config['plugins']) && is_array($config['plugins'])) {
            foreach ($config['plugins'] as $pluginId) {
                $defFilesystem->addMethodCall('addPlugin', array(new Reference($pluginId)));
            }
        }

        return new Reference($id);
    }

    private function getFactories(&$configs)
    {
        // load bundled factories
        $container = new ContainerBuilder();
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('factories.xml');

        return array(
            $this->getAdapterFactories($container, $configs),
            $this->getCacheFactories($container, $configs),
        );
    }

    private function getAdapterFactories(ContainerBuilder $container, &$configs)
    {
        if (null !== $this->adapterFactories) {
            return $this->adapterFactories;
        }

        $factories = array();
        $services = $container->findTaggedServiceIds('oneup_flysystem.adapter_factory');

        foreach (array_keys($services) as $id) {
            $factory = $container->get($id);
            $factories[str_replace('-', '_', $factory->getKey())] = $factory;
        }

        // load external factory
        foreach ($configs[0]['adapters'] as $adapter => $options) {
            $key = array_keys($options)[0];
            if (!array_key_exists($key, $factories) && isset($options[$key]['factory'])) {
                $factory = $options[$key]['factory'];

                if (false !== strpos($factory, '@')) {
                    $factoryService = $container->get(substr($factory, 1));
                } else {
                    $definition = new Definition($factory);
                    $factoryId = sprintf('oneup_flysystem.adapter_factory.%s', $key);
                    $container->setDefinition($factoryId, $definition);
                    $factoryService = $container->get($factoryId);
                }

                unset($configs[0]['adapters'][$adapter][$key]['factory']);

                $factories[str_replace('-', '_', $factoryService->getKey())] = $factoryService;
            }
        }

        return $this->adapterFactories = $factories;
    }

    private function getCacheFactories(ContainerBuilder $container, &$configs)
    {
        if (null !== $this->cacheFactories) {
            return $this->cacheFactories;
        }

        $factories = array();
        $services = $container->findTaggedServiceIds('oneup_flysystem.cache_factory');

        foreach (array_keys($services) as $id) {
            $factory = $container->get($id);
            $factories[str_replace('-', '_', $factory->getKey())] = $factory;
        }

        // load external factory
        foreach ($configs[0]['cache'] as $cache => $options) {
            $key = array_keys($options)[0];
            if (!array_key_exists($key, $factories) && isset($options[$key]['factory'])) {
                $factory = $options[$key]['factory'];

                if (false !== strpos($factory, '@')) {
                    $factoryService = $container->get(substr($factory, 1));
                } else {
                    $definition = new Definition($factory);
                    $factoryId = sprintf('oneup_flysystem.cache_factory.%s', $key);
                    $container->setDefinition($factoryId, $definition);
                    $factoryService = $container->get($factoryId);
                }

                unset($configs[0]['cache'][$cache][$key]['factory']);

                $factories[str_replace('-', '_', $factoryService->getKey())] = $factoryService;
            }
        }

        return $this->cacheFactories = $factories;
    }
}
