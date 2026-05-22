<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Factory for the `listener_priorities` config tree every behavior bundle
 * exposes. Pair with {@see PrioritizedListenerRegistrar} on the runtime
 * side so a bundle declares its events in one place — the configure
 * helper — and the registrar applies them.
 *
 * Use {@see ArrayNodeDefinition::append()} to attach the built node:
 *
 *     $definition->rootNode()->children()
 *         ->append(ListenerPrioritiesConfigBuilder::build([
 *             Events::prePersist  => 0,
 *             Events::onFlush     => 0,
 *         ]))
 *     ->end();
 */
final class ListenerPrioritiesConfigBuilder
{
    private function __construct()
    {
    }

    /**
     * @param array<string, int> $events  event-key => default priority
     */
    public static function build(array $events): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('listener_priorities');

        /** @var ArrayNodeDefinition $node */
        $node = $treeBuilder->getRootNode();
        $node
            ->addDefaultsIfNotSet()
            ->info('Doctrine event listener priorities. Higher numbers run first.');

        $children = $node->children();

        foreach ($events as $key => $default) {
            $children->integerNode($key)->defaultValue($default)->end();
        }

        $children->end();

        return $node;
    }
}
