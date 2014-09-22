<?php

namespace Netgen\Bundle\MoreBundle\Helper;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\Core\MVC\ConfigResolverInterface;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\Relation;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\Search\SearchHit;
use Netgen\Bundle\MoreBundle\API\Repository\Values\Content\Query\Criterion\Field;

class LayoutHelper
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * @var \Netgen\Bundle\MoreBundle\Helper\PathHelper
     */
    protected $pathHelper;

    /**
     * @var \eZ\Publish\Core\MVC\ConfigResolverInterface
     */
    protected $configResolver;

    /**
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param \Netgen\Bundle\MoreBundle\Helper\PathHelper $pathHelper
     * @param \eZ\Publish\Core\MVC\ConfigResolverInterface $configResolver
     */
    public function __construct( Repository $repository, PathHelper $pathHelper, ConfigResolverInterface $configResolver )
    {
        $this->repository = $repository;
        $this->pathHelper = $pathHelper;
        $this->configResolver = $configResolver;
    }

    /**
     * Returns the layout based on location ID and URI
     *
     * @param int $locationId
     * @param string $uri The page URI WITHOUT siteaccess part and WITH view parameters
     *                    For example: It can be extracted from current request object with
     *                    $this->request->attributes->get( 'semanticPathinfo' ) . $this->request->attributes->get( 'viewParametersString' )
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function getLayout( $locationId, $uri )
    {
        $uri = "/" . trim( $uri, "/" );

        $urlPrefixLayouts = $this->getLayoutsByUriPrefix( $uri );
        if ( !empty( $urlPrefixLayouts ) )
        {
            return $urlPrefixLayouts[0];
        }

        try
        {
            $location = $this->repository->getLocationService()->loadLocation( $locationId );
        }
        catch ( NotFoundException $e )
        {
            return false;
        }

        $path = $this->pathHelper->getPath( $location->id );
        $path = array_reverse( $path );

        foreach ( $path as $pathItem )
        {
            $relations = $this->repository->getContentService()->loadReverseRelations(
                $this->repository->getContentService()->loadContentInfo( $pathItem['contentId'] )
            );

            /** @var \eZ\Publish\API\Repository\Values\Content\ContentInfo[] $layoutContentInfos */
            $layoutContentInfos = array();
            foreach ( $relations as $relation )
            {
                if ( $relation->type !== Relation::FIELD
                     || $relation->sourceFieldDefinitionIdentifier !== 'apply_layout_to_objects' )
                {
                    continue;
                }

                $layoutContentInfos[] = $relation->getSourceContentInfo();
            }

            foreach ( $layoutContentInfos as $layoutContentInfo )
            {
                $layoutContent = $this->repository->getContentService()->loadContent( $layoutContentInfo->id );
                if ( $layoutContent instanceof Content && $this->validateLayoutContent( $location, $pathItem, $layoutContent ) )
                {
                    return $layoutContent;
                }
            }
        }

        return false;
    }

    /**
     * Validates a layout according to data inside of it
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $originalLocation
     * @param array $pathItem
     * @param \eZ\Publish\API\Repository\Values\Content\Content $layoutContent
     *
     * @return bool
     */
    protected function validateLayoutContent( Location $originalLocation, array $pathItem, Content $layoutContent )
    {
        $enhancedSelectionFieldType = $this->repository->getFieldTypeService()->getFieldType( 'sckenhancedselection' );
        $originalContentType = $this->repository->getContentTypeService()->loadContentType(
            $originalLocation->getContentInfo()->contentTypeId
        );

        $applyLayoutTo = $layoutContent->getFieldValue( 'apply_layout_to' );
        if ( $originalLocation->id != $pathItem['locationId'] )
        {
            if ( !$enhancedSelectionFieldType->isEmptyValue( $applyLayoutTo ) && $applyLayoutTo->identifiers[0] == 'node' )
            {
                return false;
            }
        }
        else
        {
            if ( !$enhancedSelectionFieldType->isEmptyValue( $applyLayoutTo ) && $applyLayoutTo->identifiers[0] == 'children' )
            {
                return false;
            }
        }

        $classFilterType = $layoutContent->getFieldValue( 'class_filter_type' );
        if ( !$enhancedSelectionFieldType->isEmptyValue( $classFilterType ) && $classFilterType->identifiers[0] == 'include' )
        {
            $allowedClasses = $layoutContent->getFieldValue( 'class_filter_array' );
            if ( in_array( $originalContentType->identifier, $allowedClasses->identifiers ) )
            {
                return true;
            }

            return false;
        }

        if ( !$enhancedSelectionFieldType->isEmptyValue( $classFilterType ) && $classFilterType->identifiers[0] == 'exclude' )
        {
            $forbiddenClasses = $layoutContent->getFieldValue( 'class_filter_array' );
            if ( !in_array( $originalContentType->identifier, $forbiddenClasses->identifiers ) )
            {
                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Returns all layouts that have an URI which is a prefix of provided URI
     *
     * @param string $uri
     *
     * @return array
     */
    protected function getLayoutsByUriPrefix( $uri )
    {
        $query = new Query();
        $query->filter = new Query\Criterion\LogicalAnd(
            array(
                new Query\Criterion\ContentTypeIdentifier( "ng_layout" ),
                new Query\Criterion\ParentLocationId(
                    (int)$this->configResolver->getParameter( 'SpecialNodes.SidebarsParentNode', 'ngmore' )
                ),
                new Field( "apply_layout_to_uri", Field::REVERSE_LIKE, $uri )
            )
        );

        //TODO: Sort by length descending
        // $query->sortClauses = array(
            // new Query\SortClause\Field( "ng_layout", "apply_layout_to_uri", Query::SORT_DESC )
        // );

        $searchResult = $this->repository->getSearchService()->findContent( $query );
        if ( $searchResult->totalCount < 1 )
        {
            return array();
        }

        $layouts = array_map(
            function ( SearchHit $searchHit )
            {
                return $searchHit->valueObject;
            },
            $searchResult->searchHits
        );

        return $layouts;
    }
}