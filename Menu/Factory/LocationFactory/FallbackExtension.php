<?php

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

    /**
     * @param \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function matches(Location $location)
    {
        return true;
    }

    public function buildItem(ItemInterface $item, Location $location)
    {
        $item
            ->setUri($this->urlGenerator->generate($location))
            ->setName($location->id)
            ->setLabel($location->content->name)
            ->setAttribute('id', 'menu-item-location-id-' . $location->id);
    }
}