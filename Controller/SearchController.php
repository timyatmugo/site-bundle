<?php

namespace Netgen\Bundle\MoreBundle\Controller;

use Netgen\Bundle\EzPlatformSiteApiBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Netgen\EzPlatformSiteApi\Core\Site\Pagination\Pagerfanta\NodeSearchHitAdapter;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use Pagerfanta\Pagerfanta;

class SearchController extends Controller
{
    /**
     * Action for displaying the results of full text search.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function search(Request $request)
    {
        $configResolver = $this->getConfigResolver();

        $searchText = trim($request->get('searchText', ''));
        $contentTypes = $configResolver->getParameter('search.content_types', 'ngmore');

        if (empty($searchText)) {
            return $this->render(
                $configResolver->getParameter('template.search', 'ngmore'),
                array(
                    'search_text' => '',
                    'locations' => array(),
                )
            );
        }

        $criteria = array(
            new Criterion\FullText($searchText),
            new Criterion\Subtree($this->getRootLocation()->pathString),
            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
        );

        if (is_array($contentTypes) && !empty($contentTypes)) {
            $criteria[] = new Criterion\ContentTypeIdentifier($contentTypes);
        }

        $query = new LocationQuery();
        $query->query = new Criterion\LogicalAnd($criteria);

        $pager = new Pagerfanta(
            new NodeSearchHitAdapter(
                $query,
                $this->getSite()->getFindService()
            )
        );

        $pager->setNormalizeOutOfRangePages(true);
        $pager->setMaxPerPage(
            (int)$configResolver->getParameter('search.default_limit', 'ngmore')
        );

        $currentPage = (int)$request->get('page', 1);
        $pager->setCurrentPage($currentPage > 0 ? $currentPage : 1);

        return $this->render(
            $configResolver->getParameter('template.search', 'ngmore'),
            array(
                'search_text' => $searchText,
                'locations' => $pager,
            )
        );
    }
}