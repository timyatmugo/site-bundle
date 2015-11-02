<?php

namespace Netgen\Bundle\MoreBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class NetgenMoreExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load( array $configs, ContainerBuilder $container )
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration( $configuration, $configs );

        $loader = new Loader\YamlFileLoader( $container, new FileLocator( __DIR__ . '/../Resources/config' ) );

        $loader->load( 'parameters.yml' );
        $loader->load( 'field_types.yml' );
        $loader->load( 'pagerfanta.yml' );
        $loader->load( 'templating.yml' );
        $loader->load( 'kernel.yml' );
        $loader->load( 'image.yml' );
        $loader->load( 'menu.yml' );
        $loader->load( 'event_listeners.yml' );
        $loader->load( 'matchers.yml' );
        $loader->load( 'services.yml' );

        if ( $container->hasParameter( 'ezpublish.persistence.legacy.search.gateway.sort_clause_handler.common.field.class' ) )
        {
            $loader->load( 'search.yml' );
        }
        else
        {
            $loader->load( 'search_new_namespaces.yml' );
        }
    }
}
