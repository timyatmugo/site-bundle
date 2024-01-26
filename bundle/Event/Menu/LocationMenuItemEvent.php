<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Event\Menu;

use Knp\Menu\ItemInterface;
use Netgen\IbexaSiteApi\API\Values\Location;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is triggered when a menu item is build using the location menu factory.
 */
final class LocationMenuItemEvent extends Event
{
    private ItemInterface $item;
    private Location $location;

    public function __construct(ItemInterface $item, Location $location) 
    {
        $this->item = $item;
        $this->location = $location;
    }

    /**
     * Returns the item which was built.
     */
    public function getItem(): ItemInterface
    {
        return $this->item;
    }

    /**
     * Returns the Ibexa location for which the menu item was built.
     */
    public function getLocation(): Location
    {
        return $this->location;
    }
}
