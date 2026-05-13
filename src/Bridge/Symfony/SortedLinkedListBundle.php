<?php declare(strict_types = 1);

namespace ShipMonk\SortedLinkedList\Bridge\Symfony;

use Override;
use ShipMonk\SortedLinkedList\EventListener\ListenerChain;
use ShipMonk\SortedLinkedList\EventListener\ListEventListenerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * @api
 */
final class SortedLinkedListBundle extends AbstractBundle
{

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function loadExtension(
        array $config,
        ContainerConfigurator $configurator,
        ContainerBuilder $container,
    ): void
    {
        $services = $configurator->services();

        // Collect all user-defined listeners (tagged sorted_linked_list.listener) into a chain.
        $services->set(ListenerChain::class)
            ->arg('$listeners', tagged_iterator('sorted_linked_list.listener'));

        $services->set(SortedLinkedListFactory::class)
            ->autowire()
            ->arg('$eventListener', service(ListenerChain::class));

        // Tag all ListEventListenerInterface implementations for collection into the chain.
        $container->registerForAutoconfiguration(ListEventListenerInterface::class)
            ->addTag('sorted_linked_list.listener');
    }

}
