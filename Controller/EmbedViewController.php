<?php

namespace Netgen\Bundle\MoreBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use Psr\Log\LoggerInterface;

class EmbedViewController extends Controller
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct( LoggerInterface $logger = null )
    {
        $this->logger = $logger;
    }

    /**
     * Action for viewing embedded content with image content type identifier
     *
     * @param mixed $contentId
     * @param string $viewType
     * @param boolean $layout
     * @param array $params
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function embedImageContent( $contentId, $viewType, $layout = false, array $params = array() )
    {
        $fieldHelper = $this->container->get( 'ezpublish.field_helper' );
        $translationHelper = $this->container->get( 'ezpublish.translation_helper' );
        $imageVariationService = $this->container->get( 'ezpublish.fieldtype.ezimage.variation_service' );

        $targetLink = !empty( $params['objectParameters']['href'] ) ? trim( $params['objectParameters']['href'] ) : null;
        if ( !empty( $targetLink ) )
        {
            if ( !empty( $params['objectParameters']['link_direct_download'] ) )
            {
                if ( stripos( $targetLink, 'eznode://' ) === 0 )
                {
                    $locationId = (int)substr( $targetLink, 9 );

                    try
                    {
                        $location = $this->getRepository()->getLocationService()->loadLocation( $locationId );
                        $content = $this->getRepository()->getContentService()->loadContent( $location->contentId );
                    }
                    catch ( NotFoundException $e )
                    {
                        $targetLink = null;
                        if ( $this->logger )
                        {
                            $this->logger->error(
                                'Tried to generate link to non existing location #' . $locationId
                            );
                        }
                    }
                    catch ( UnauthorizedException $e )
                    {
                        $targetLink = null;
                        if ( $this->logger )
                        {
                            $this->logger->error(
                                'Tried to generate link to location #' . $locationId . ' without read rights'
                            );
                        }
                    }
                }
                else if ( stripos( $targetLink, 'ezobject://' ) === 0 )
                {
                    $linkedContentId = (int)substr( $targetLink, 11 );

                    try
                    {
                        $content = $this->getRepository()->getContentService()->loadContent( $linkedContentId );
                    }
                    catch ( NotFoundException $e )
                    {
                        $targetLink = null;
                        if ( $this->logger )
                        {
                            $this->logger->error(
                                'Tried to generate link to non existing content #' . $linkedContentId
                            );
                        }
                    }
                    catch ( UnauthorizedException $e )
                    {
                        $targetLink = null;
                        if ( $this->logger )
                        {
                            $this->logger->error(
                                'Tried to generate link to content #' . $contentId . ' without read rights'
                            );
                        }
                    }
                }

                if ( !empty( $content ) )
                {
                    if ( isset( $content->fields['file'] ) && !$fieldHelper->isFieldEmpty( $content, 'file' ) )
                    {
                        /** @var \eZ\Publish\Core\FieldType\BinaryFile\Value $fieldValue */
                        $fieldValue = $translationHelper->getTranslatedField( $content, 'file' )->value;
                        $targetLink = $fieldValue->uri;
                    }
                    else if ( isset( $content->fields['image'] ) && !$fieldHelper->isFieldEmpty( $content, 'image' ) )
                    {
                        $imageVariation = $imageVariationService->getVariation( $content->getField( 'image' ), $content->versionInfo, 'original' );
                        $targetLink = $imageVariation->uri;
                    }
                }
            }

            if ( stripos( $targetLink, 'eznode://' ) === 0 )
            {
                $targetLink = $this->container->get( 'router' )
                    ->generate(
                        'ez_urlalias',
                        array(
                            'locationId' => substr( $targetLink, 9 )
                        )
                    );
            }
            else if ( stripos( $targetLink, 'ezobject://' ) === 0 )
            {
                $targetLink = $this->container->get( 'router' )
                    ->generate(
                        'ez_urlalias',
                        array(
                            'contentId' => substr( $targetLink, 11 )
                        )
                    );
            }
        }

        return $this->get( 'ez_content' )->embedContent(
            $contentId,
            $viewType,
            $layout,
            $params + array(
                'link_href' => $targetLink
            )
        );
    }
}