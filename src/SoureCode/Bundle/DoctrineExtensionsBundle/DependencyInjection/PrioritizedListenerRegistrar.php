<?php

declare(strict_types=1);

namespace SoureCode\Bundle\DoctrineExtensionsBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Centralizes the `doctrine.event_listener` re-tagging that every behavior
 * bundle (Authorable, Timestampable, Versionable, …) used to copy-paste
 * verbatim. Each bundle defines its own services.php without a tag, then
 * calls this registrar from `loadExtension()` so listener priorities are
 * declared once per bundle in a single place.
 */
final class PrioritizedListenerRegistrar
{
    private function __construct()
    {
    }

    /**
     * Re-tags a service definition with the supplied Doctrine event
     * listener bindings. Existing `doctrine.event_listener` tags on the
     * definition are removed first.
     *
     * @param array<string, int> $events Map of Doctrine event constant (use
     *                                   `Doctrine\ORM\Events::*` /
     *                                   `Doctrine\ORM\Tools\ToolEvents::*`)
     *                                   to listener priority.
     */
    public static function register(
        ContainerBuilder $builder,
        string $serviceId,
        array $events,
    ): void {
        $definition = $builder->getDefinition($serviceId);
        $definition->clearTag('doctrine.event_listener');

        foreach ($events as $event => $priority) {
            $definition->addTag('doctrine.event_listener', [
                'event' => $event,
                'priority' => $priority,
            ]);
        }
    }
}
