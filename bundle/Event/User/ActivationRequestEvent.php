<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Event\User;

use Ibexa\Contracts\Core\Repository\Values\User\User;
use Symfony\Contracts\EventDispatcher\Event;

final class ActivationRequestEvent extends Event
{
    public function __construct(private string $email, private ?User $user = null)
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
