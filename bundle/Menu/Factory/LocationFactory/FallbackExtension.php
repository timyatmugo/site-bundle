<?php

declare(strict_types=1);

namespace Netgen\Bundle\MoreBundle\Menu\Factory\LocationFactory;

use Knp\Menu\ItemInterface;
use Netgen\EzPlatformSiteApi\API\Values\Location;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FallbackExtension implements ExtensionInterface
{
    /**
     * @var \Symfony\Component\Routing\Generator\UrlGeneratorInterface
     */
    protected $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function matches(Location $location): bool
    {
        return true;
    }

    public function buildItem(ItemInterface $item, Location $location): void
    {
        $item
            ->setUri($this->urlGenerator->generate($location))
            ->setAttribute('id', 'menu-item-location-id-' . $location->id);
    }
}