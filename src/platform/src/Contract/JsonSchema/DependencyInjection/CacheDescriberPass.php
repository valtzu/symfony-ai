<?php

namespace Symfony\AI\Platform\Contract\JsonSchema\DependencyInjection;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CacheDescriberPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var Factory $factory */
        $factory = $container->get('ai.platform.json_schema_factory');
        $schemaRegistry = [];

        foreach ($container->findTaggedServiceIds('ai.tool') as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();
            foreach ($tags as $attributes) {
                $schema = $factory->buildParameters($class, $attributes['method']);
                if (null === $schema) {
                    continue;
                }
                $id = 'ai.platform.json_schema.cache.'.hash('xxh3', $class.'::'.$attributes['method']);
                $schemaRegistry[$class.'::'.$attributes['method']] = new Reference($id);
                $container->register($id, 'ArrayObject')->setArguments([$schema]);
            }
        }
        $container->getDefinition('ai.platform.json_schema.describer.cache')
            ->setDecoratedService('ai.platform.json_schema.describer')
            ->replaceArgument(0, new ServiceLocatorArgument($schemaRegistry));
    }
}
