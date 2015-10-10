<?php

namespace Netgen\Bundle\MoreBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\Core\MVC\Symfony\View\ContentView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use eZ\Publish\Core\FieldType\Relation\Value as RelationValue;
use eZ\Publish\Core\FieldType\Url\Value as UrlValue;
use eZ\Publish\Core\Pagination\Pagerfanta\LocationSearchAdapter;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use Pagerfanta\Pagerfanta;

class FullViewController extends Controller
{
    /**
     * Action for viewing content with ng_category content type identifier
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \eZ\Publish\Core\MVC\Symfony\View\ContentView $view
     *
     * @return \Symfony\Component\HttpFoundation\Response|\eZ\Publish\Core\MVC\Symfony\View\ContentView
     */
    public function viewNgCategoryContent( Request $request, ContentView $view )
    {
        $content = $view->getContent();
        $location = $view->getLocation();
        if ( !$location instanceof Location )
        {
            $location = $this->getRepository()->getLocationService()->loadLocation(
                $content->contentInfo->mainLocationId
            );
        }

        $response = $this->checkCategoryRedirect( $location );
        if ( $response instanceof Response )
        {
            return $response;
        }

        $fieldHelper = $this->container->get( 'ezpublish.field_helper' );
        $translationHelper = $this->container->get( 'ezpublish.translation_helper' );

        $criteria = array(
            new Criterion\Subtree( $location->pathString ),
            new Criterion\Visibility( Criterion\Visibility::VISIBLE ),
            new Criterion\LogicalNot( new Criterion\LocationId( $location->id ) )
        );

        $fetchSubtreeValue = $translationHelper->getTranslatedField( $content, 'fetch_subtree' )->value;
        if ( !$fetchSubtreeValue->bool )
        {
            $criteria[] = new Criterion\Location\Depth( Criterion\Operator::EQ, $location->depth + 1 );
        }

        if ( !$fieldHelper->isFieldEmpty( $content, 'children_class_filter_include' ) )
        {
            $contentTypeFilter = $translationHelper->getTranslatedField( $content, 'children_class_filter_include' )->value;
            $criteria[] = new Criterion\ContentTypeIdentifier(
                array_map(
                    'trim',
                    explode( ',', $contentTypeFilter )
                )
            );
        }
        else if ( $this->getConfigResolver()->hasParameter( 'ChildrenNodeList.ExcludedClasses', 'content' ) )
        {
            $excludedContentTypes = $this->getConfigResolver()->getParameter( 'ChildrenNodeList.ExcludedClasses', 'content' );
            if ( !empty( $excludedContentTypes ) )
            {
                $criteria[] = new Criterion\LogicalNot(
                    new Criterion\ContentTypeIdentifier( $excludedContentTypes )
                );
            }
        }

        $query = new LocationQuery();
        $query->filter = new Criterion\LogicalAnd( $criteria );

        $query->sortClauses = array(
            $this->container->get( 'ngmore.helper.sort_clause_helper' )->getSortClauseBySortField(
                $location->sortField,
                $location->sortOrder
            )
        );

        $pager = new Pagerfanta(
            new LocationSearchAdapter(
                $query,
                $this->getRepository()->getSearchService()
            )
        );

        $pager->setNormalizeOutOfRangePages( true );

        /** @var \eZ\Publish\Core\FieldType\Integer\Value $pageLimitValue */
        $pageLimitValue = $translationHelper->getTranslatedField( $content, 'page_limit' )->value;

        $defaultLimit = 12;
        if ( isset( $params['childrenLimit'] ) )
        {
            $childrenLimit = (int)$params['childrenLimit'];
            if ( $childrenLimit > 0 )
            {
                $defaultLimit = $childrenLimit;
            }
        }

        $pager->setMaxPerPage( $pageLimitValue->value > 0 ? (int)$pageLimitValue->value : $defaultLimit );

        $currentPage = (int)$request->get( 'page', 1 );
        $pager->setCurrentPage( $currentPage > 0 ? $currentPage : 1 );

        $view->addParameters(
            array(
                'pager' => $pager
            )
        );

        return $view;
    }

    /**
     * Action for viewing content with ng_landing_page content type identifier
     *
     * @param \eZ\Publish\Core\MVC\Symfony\View\ContentView $view
     *
     * @return \Symfony\Component\HttpFoundation\Response|\eZ\Publish\Core\MVC\Symfony\View\ContentView
     */
    public function viewNgLandingPageContent( ContentView $view )
    {
        $location = $view->getLocation();
        if ( $location instanceof Location )
        {
            $response = $this->checkCategoryRedirect( $location );
            if ( $response instanceof Response )
            {
                return $response;
            }
        }

        return $view;
    }

    /**
     * Action for viewing content with ng_category_page content type identifier
     *
     * @param \eZ\Publish\Core\MVC\Symfony\View\ContentView $view
     *
     * @return \Symfony\Component\HttpFoundation\Response|\eZ\Publish\Core\MVC\Symfony\View\ContentView
     */
    public function viewNgCategoryPageContent( ContentView $view )
    {
        $location = $view->getLocation();
        if ( $location instanceof Location )
        {
            $response = $this->checkCategoryRedirect( $location );
            if ( $response instanceof Response )
            {
                return $response;
            }
        }

        return $view;
    }

    /**
     * Checks if content at location defined by it's ID contains
     * valid category redirect value and returns a redirect response if it does
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Location $location
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function checkCategoryRedirect( Location $location )
    {
        $contentService = $this->getRepository()->getContentService();
        $content = $contentService->loadContent( $location->contentId );

        $fieldHelper = $this->container->get( 'ezpublish.field_helper' );
        $translationHelper = $this->container->get( 'ezpublish.translation_helper' );

        $internalRedirectValue = $translationHelper->getTranslatedField( $content, 'internal_redirect' )->value;
        $externalRedirectValue = $translationHelper->getTranslatedField( $content, 'external_redirect' )->value;
        if ( $internalRedirectValue instanceof RelationValue && !$fieldHelper->isFieldEmpty( $content, 'internal_redirect' ) )
        {
            $internalRedirectContentInfo = $contentService->loadContentInfo( $internalRedirectValue->destinationContentId );
            if ( $internalRedirectContentInfo->mainLocationId != $location->id )
            {
                return new RedirectResponse(
                    $this->container->get( 'router' )->generate(
                        'ez_urlalias',
                        array(
                            'locationId' => $internalRedirectContentInfo->mainLocationId
                        )
                    ),
                    RedirectResponse::HTTP_MOVED_PERMANENTLY
                );
            }
        }
        else if ( $externalRedirectValue instanceof UrlValue && !$fieldHelper->isFieldEmpty( $content, 'external_redirect' ) )
        {
            if ( stripos( $externalRedirectValue->link, 'http' ) === 0 )
            {
                return new RedirectResponse( $externalRedirectValue->link, RedirectResponse::HTTP_MOVED_PERMANENTLY );
            }

            return new RedirectResponse(
                $this->container->get( 'router' )->generate(
                    'ez_urlalias',
                    array(
                        'locationId' => $this->getConfigResolver()->getParameter( 'content.tree_root.location_id' )
                    )
                ) . trim( $externalRedirectValue->link, '/' ),
                RedirectResponse::HTTP_MOVED_PERMANENTLY
            );
        }
    }
}
