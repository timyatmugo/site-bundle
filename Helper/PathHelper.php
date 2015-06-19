<?php

namespace Netgen\Bundle\MoreBundle\Helper;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use eZ\Publish\Core\Helper\TranslationHelper;
use Symfony\Component\Routing\RouterInterface;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;

class PathHelper
{
    /**
     * @var \eZ\Publish\API\Repository\LocationService
     */
    protected $locationService;

    /**
     * @var \eZ\Publish\Core\MVC\ConfigResolverInterface
     */
    protected $configResolver;

    /**
     * @var \eZ\Publish\Core\Helper\TranslationHelper
     */
    protected $translationHelper;

    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    protected $router;

    /**
     * @var array
     */
    protected $pathArray;

    public function __construct(
        LocationService $locationService,
        ConfigResolverInterface $configResolver,
        TranslationHelper $translationHelper,
        RouterInterface $router
    )
    {
        $this->locationService = $locationService;
        $this->configResolver = $configResolver;
        $this->translationHelper = $translationHelper;
        $this->router = $router;
    }

    /**
     * Returns the path array for location ID
     *
     * @param mixed $locationId
     * @return array
     */
    public function getPath( $locationId )
    {
        if ( $this->pathArray !== null )
        {
            return $this->pathArray;
        }

        $this->pathArray = array();
        $path = $this->locationService->loadLocation( $locationId )->path;

        // The root location can be defined at site access level
        $rootLocationId = $this->configResolver->getParameter( 'content.tree_root.location_id' );

        $isRootLocation = false;

        // Shift of location "1" from path as it is not a fully valid location and not readable by most users
        array_shift( $path );

        $location = null;
        for ( $i = 0; $i < count( $path ); $i++ )
        {
            // if root location hasn't been found yet
            if ( !$isRootLocation )
            {
                // If we reach the root location, we begin to add item to the path array from it
                if ( $path[$i] == $rootLocationId )
                {
                    try
                    {
                        $location = $this->locationService->loadLocation( $path[$i] );
                    }
                    catch ( UnauthorizedException $e )
                    {
                        return array();
                    }

                    $isRootLocation = true;
                    $this->pathArray[] = array(
                        'text' => $this->translationHelper->getTranslatedContentNameByContentInfo( $location->contentInfo ),
                        'url' => $location->id != $locationId ? $this->router->generate( $location ) : false,
                        'locationId' => $location->id,
                        'contentId' => $location->contentId,
                        'contentTypeId' => $location->contentInfo->contentTypeId
                    );
                }
            }
            // The root location has already been reached, so we can add items to the path array
            else
            {
                try
                {
                    $location = $this->locationService->loadLocation( $path[$i] );
                }
                catch ( UnauthorizedException $e )
                {
                    return array();
                }

                $this->pathArray[] = array(
                    'text' => $this->translationHelper->getTranslatedContentNameByContentInfo( $location->contentInfo ),
                    'url' => $location->id != $locationId ? $this->router->generate( $location ) : false,
                    'locationId' => $location->id,
                    'contentId' => $location->contentId,
                    'contentTypeId' => $location->contentInfo->contentTypeId
                );
            }
        }

        return $this->pathArray;
    }
}
