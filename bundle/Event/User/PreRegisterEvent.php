<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Event\User;

use Ibexa\Contracts\Core\Repository\Values\User\UserCreateStruct;

final class PreRegisterEvent extends UserEvent
{
    private UserCreateStruct $userCreateStruct;

    public function __construct(UserCreateStruct $userCreateStruct) 
    {
        $this->userCreateStruct = $userCreateStruct;
    }

    public function setUserCreateStruct(UserCreateStruct $userCreateStruct): void
    {
        $this->userCreateStruct = $userCreateStruct;
    }

    public function getUserCreateStruct(): UserCreateStruct
    {
        return $this->userCreateStruct;
    }
}
