<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Event\User;

use Ibexa\Contracts\Core\Repository\Values\User\User;

final class ActivationRequestEvent extends UserEvent
{
    private string $email;
    private ?User $user;

    public function __construct(string $email, ?User $user = null) 
    {
        $this->email = $email;
        $this->user = $user;
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
