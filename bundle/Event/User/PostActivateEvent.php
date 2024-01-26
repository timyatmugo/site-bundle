<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Event\User;

use Ibexa\Contracts\Core\Repository\Values\User\User;

final class PostActivateEvent extends UserEvent
{
    private User $user;
    public function __construct(User $user) 
    {
        $this->user = $user;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
