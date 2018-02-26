<?php

namespace Netgen\Bundle\MoreBundle\Menu\Factory\LocationFactory;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Search\SearchHit;
use eZ\Publish\Core\FieldType\Url\Value as UrlValue;
use InvalidArgumentException;
use Knp\Menu\ItemInterface;
use Netgen\EzPlatformSiteApi\API\FilterService;
use Netgen\EzPlatformSiteApi\API\LoadService;
use Netgen\EzPlatformSiteApi\API\Values\Content;
use Netgen\EzPlatformSiteApi\API\Values\Location;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

class MenuItemExtension implements ExtensionInterface
{
    /**
     * @var \Netgen\EzPlatformSiteApi\API\LoadService
     */
    protected $loadService;

    /**
     * @var \Netgen\EzPlatformSiteApi\API\FilterService
     */
    protected $filterService;

    /**
     * @var \Symfony\Component\Routing\Generator\UrlGeneratorInterface
     */
    protected $urlGenerator;

    /**
     * @var \Psr\Log\NullLogger
     */
    protected $logger;

    public function __construct(
        LoadService $loadService,
        FilterService $filterService,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger = null
    ) {
        $this->loadService = $loadService;
        $this->filterService = $filterService;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger ?: new NullLogger();
    }

    public function matches(Location $location)
    {
        return $location->contentInfo->contentTypeIdentifier === 'ng_menu_item';
    }

    public function buildItem(ItemInterface $item, Location $location)
    {
        $item->setName($location->content->name);
        $item->setLabel($location->content->name);

        $this->buildItemFromContent($item, $location->content);

        if (!empty($item->getUri()) && $location->content->getField('target_blank')->value->bool) {
            $item->setLinkAttribute('target', '_blank');
            $item->setLinkAttribute('rel', 'noopener noreferrer');
        }

        $this->buildChildItems($item, $location->content);
    }

    protected function buildItemFromContent(ItemInterface $item, Content $content)
    {
        if (!$content->getField('item_url')->isEmpty()) {
            $this->buildItemFromUrl($item, $content->getField('item_url')->value, $content);

            return;
        }

        if ($content->getField('item_object')->isEmpty()) {
            return;
        }

        try {
            $relatedContent = $this->loadService->loadContent(
                $content->getField('item_object')->value->destinationContentId
            );
        } catch (Throwable $t) {
            $this->logger->error($t->getMessage());

            return;
        }

        if (!$relatedContent->contentInfo->published) {
            $this->logger->error(sprintf('Menu item (#%s) has a related object (#%s) that is not published.', $content->id, $relatedContent->id));

            return;
        }

        if ($relatedContent->mainLocation->invisible) {
            $this->logger->error(sprintf('Menu item (#%s) has a related object (#%s) that is not visible.', $content->id, $relatedContent->id));

            return;
        }

        $this->buildItemFromRelatedContent($item, $content, $relatedContent);
    }

    protected function buildItemFromUrl(ItemInterface $item, UrlValue $urlValue, Content $content)
    {
        $uri = $urlValue->link;

        if (stripos($urlValue->link, 'http') !== 0) {
            try {
                $uri = $this->urlGenerator->generate(
                    'ez_legacy',
                    array(
                        'module_uri' => $urlValue->link,
                    )
                );
            } catch (InvalidArgumentException $e) {
                // Do nothing
            }
        }

        $item->setUri($uri);
        $item->setName($uri);

        if (!empty($urlValue->text)) {
            $item->setLinkAttribute('title', $urlValue->text);

            if (!$content->getField('use_menu_item_name')->value->bool) {
                $item->setLabel($urlValue->text);
            }
        }
    }

    protected function buildItemFromRelatedContent(ItemInterface $item, Content $content, Content $relatedContent)
    {
        $item->setUri($this->urlGenerator->generate($relatedContent));
        $item->setName($relatedContent->mainLocationId);
        $item->setExtra('ezlocation', $relatedContent->mainLocation);
        $item->setAttribute('id', 'menu-item-location-id-' . $relatedContent->mainLocationId);
        $item->setLinkAttribute('title', $item->getLabel());

        if (!$content->getField('use_menu_item_name')->value->bool) {
            $item->setLabel($relatedContent->name);
        }
    }

    protected function buildChildItems(ItemInterface $item, Content $content)
    {
        $childLocations = array();

        if (!$content->getField('parent_node')->isEmpty()) {
            /** @var \eZ\Publish\Core\FieldType\Relation\Value $fieldValue */
            $fieldValue = $content->getField('parent_node')->value;

            try {
                $destinationContent = $this->loadService->loadContent($fieldValue->destinationContentId);
            } catch (Throwable $t) {
                $this->logger->error($t->getMessage());

                return;
            }

            if (!$destinationContent->contentInfo->published) {
                $this->logger->error(sprintf('Menu item (#%s) has a related object (#%s) that is not published.', $content->id, $destinationContent->id));

                return;
            }

            if ($destinationContent->mainLocation->invisible) {
                $this->logger->error(sprintf('Menu item (#%s) has a related object (#%s) that is not visible.', $content->id, $destinationContent->id));

                return;
            }

            $parentLocation = $destinationContent->mainLocation;

            if ($content->getField('item_url')->isEmpty() && $content->getField('item_object')->isEmpty()) {
                $item->setName($parentLocation->id);
            }

            $criteria = array(
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\ParentLocationId($parentLocation->id),
            );

            if (!$content->getField('class_filter')->isEmpty() && !$content->getField('class_filter_type')->isEmpty()) {
                /** @var \Netgen\Bundle\ContentTypeListBundle\Core\FieldType\ContentTypeList\Value $contentTypeFilter */
                $contentTypeFilter = $content->getField('class_filter')->value;

                /** @var \Netgen\Bundle\EnhancedSelectionBundle\Core\FieldType\EnhancedSelection\Value $filterType */
                $filterType = $content->getField('class_filter_type')->value;

                if ($filterType->identifiers[0] === 'include') {
                    $criteria[] = new Criterion\ContentTypeIdentifier($contentTypeFilter->identifiers);
                } elseif ($filterType->identifiers[0] === 'exclude') {
                    $criteria[] = new Criterion\LogicalNot(
                        new Criterion\ContentTypeIdentifier($contentTypeFilter->identifiers)
                    );
                }
            }

            $query = new LocationQuery();
            $query->filter = new Criterion\LogicalAnd($criteria);
            $query->sortClauses = $parentLocation->innerLocation->getSortClauses();

            if (!$content->getField('limit')->isEmpty()) {
                /** @var \eZ\Publish\Core\FieldType\Integer\Value $limit */
                $limit = $content->getField('limit')->value;
                if ($limit->value > 0) {
                    $query->limit = $limit->value;
                }
            }

            $searchResult = $this->filterService->filterLocations($query);

            $childLocations = array_map(
                function (SearchHit $searchHit) {
                    return $searchHit->valueObject;
                },
                $searchResult->searchHits
            );
        } elseif (!$content->getField('menu_items')->isEmpty()) {
            foreach ($content->getField('menu_items')->value->destinationLocationIds as $locationId) {
                if (empty($locationId)) {
                    $this->logger->error(sprintf('Empty location ID in RelationList field "%s" for content #%s', 'menu_items', $content->id));

                    continue;
                }

                try {
                    $childLocations[] = $this->loadService->loadLocation($locationId);
                } catch (Throwable $t) {
                    $this->logger->error($t->getMessage());

                    continue;
                }
            }
        }

        foreach ($childLocations as $location) {
            $item->addChild(null, array('ezlocation' => $location));
        }
    }
}
