<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Topic;

use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Contracts\Core\Repository\Values\Content\Search\SearchHit;
use Ibexa\Contracts\Core\Repository\Values\ValueObject;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Netgen\IbexaSiteApi\API\FindService;
use Netgen\IbexaSiteApi\API\LoadService;
use Netgen\IbexaSiteApi\API\Values\Location;
use Netgen\TagsBundle\API\Repository\Values\Content\Query\Criterion\TagId;
use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_map;
use function count;

final class UrlGenerator
{
    private FindService $findService;
    private LoadService $loadService;
    private ConfigResolverInterface $configResolver;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        FindService $findService,
        LoadService $loadService,
        ConfigResolverInterface $configResolver,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->findService = $findService;
        $this->loadService = $loadService;
        $this->configResolver = $configResolver;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Returns the path for the topic specified by provided tag.
     *
     * @param array<string, mixed> $parameters
     */
    public function generate(Tag $tag, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate(
            '',
            [RouteObjectInterface::ROUTE_OBJECT => $this->getTopicValueObject($tag)] + $parameters,
            $referenceType,
        );
    }

    /**
     * If exists, returns the location of the content with ng_topic identifier connected to provided tag.
     *
     * Otherwise, the tag itself is returned.
     */
    private function getTopicValueObject(Tag $tag)
    {
        $rootLocation = $this->loadService->loadLocation(
            $this->configResolver->getParameter('content.tree_root.location_id'),
        );

        $query = new LocationQuery();
        $query->limit = 1;

        $query->filter = new Criterion\LogicalAnd(
            [
                new Criterion\Subtree($rootLocation->pathString),
                new Criterion\LogicalNot(new Criterion\LocationId($rootLocation->id)),
                new Criterion\Location\IsMainLocation(Criterion\Location\IsMainLocation::MAIN),
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\ContentTypeIdentifier(['ng_topic']),
                new TagId($tag->id),
            ],
        );

        /** @var \Netgen\IbexaSiteApi\API\Values\Location[] $locations */
        $locations = array_map(
            static fn (SearchHit $searchHit): ValueObject => $searchHit->valueObject,
            $this->findService->findLocations($query)->searchHits,
        );

        if (count($locations) > 0) {
            return $locations[0];
        }

        return $tag;
    }
}
