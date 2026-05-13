<?php declare(strict_types = 1);

use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\Extend;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\NotHaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespacesExactly;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $src = ClassSet::fromDir(__DIR__ . '/src');

    $config->add(
        $src,

        // All concrete event classes must implement the marker interface.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespacesExactly('ShipMonk\SortedLinkedList\Event'))
            ->andThat(new NotHaveNameMatching('ListEventInterface'))
            ->should(new Implement('ShipMonk\SortedLinkedList\Event\ListEventInterface'))
            ->because('all event value objects must be tagged with ListEventInterface'),

        // All exception classes (except the base) must extend the base exception.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespacesExactly('ShipMonk\SortedLinkedList\Exception'))
            ->andThat(new NotHaveNameMatching('SortedLinkedListException'))
            ->should(new Extend('ShipMonk\SortedLinkedList\Exception\SortedLinkedListException'))
            ->because('every library exception must extend SortedLinkedListException so callers can catch all errors with one clause'),

        // Core namespaces must not import from the optional Symfony bridge.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespacesExactly(
                'ShipMonk\SortedLinkedList\Event',
                'ShipMonk\SortedLinkedList\EventListener',
                'ShipMonk\SortedLinkedList\Exception',
            ))
            ->should(new NotDependsOnTheseNamespaces(['ShipMonk\SortedLinkedList\Bridge']))
            ->because('the core library must not depend on the optional Symfony bridge'),
    );
};
