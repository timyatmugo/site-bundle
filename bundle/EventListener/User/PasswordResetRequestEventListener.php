<?php

declare(strict_types=1);

namespace Netgen\Bundle\MoreBundle\EventListener\User;

use eZ\Publish\API\Repository\Values\User\User;
use Netgen\Bundle\MoreBundle\Event\NetgenMoreEvents;
use Netgen\Bundle\MoreBundle\Event\User\PasswordResetRequestEvent;
use Netgen\Bundle\MoreBundle\EventListener\UserEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordResetRequestEventListener extends UserEventListener implements EventSubscriberInterface
{
    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            NetgenMoreEvents::USER_PASSWORD_RESET_REQUEST => 'onPasswordResetRequest',
        ];
    }

    /**
     * Listens for the start of forgot password procedure.
     * Event contains information about the submitted email and the user, if found.
     */
    public function onPasswordResetRequest(PasswordResetRequestEvent $event): void
    {
        $user = $event->getUser();
        $email = $event->getEmail();

        if (!$user instanceof User) {
            $this->mailHelper
                ->sendMail(
                    $email,
                    'ngmore.user.forgot_password.not_registered.subject',
                    $this->configResolver->getParameter('template.user.mail.forgot_password_not_registered', 'ngmore')
                );

            return;
        }

        if (!$user->enabled) {
            if ($this->ngUserSettingRepository->isUserActivated($user->id)) {
                $this->mailHelper
                    ->sendMail(
                        [$user->email => $this->getUserName($user)],
                        'ngmore.user.forgot_password.disabled.subject',
                        $this->configResolver->getParameter('template.user.mail.forgot_password_disabled', 'ngmore'),
                        [
                            'user' => $user,
                        ]
                    );

                return;
            }

            $this->mailHelper
                ->sendMail(
                    [$user->email => $this->getUserName($user)],
                    'ngmore.user.forgot_password.not_active.subject',
                    $this->configResolver->getParameter('template.user.mail.forgot_password_not_active', 'ngmore'),
                    [
                        'user' => $user,
                    ]
                );

            return;
        }

        $accountKey = $this->ezUserAccountKeyRepository->create($user->id);

        $this->mailHelper
            ->sendMail(
                [$user->email => $this->getUserName($user)],
                'ngmore.user.forgot_password.subject',
                $this->configResolver->getParameter('template.user.mail.forgot_password', 'ngmore'),
                [
                    'user' => $user,
                    'hash' => $accountKey->getHash(),
                ]
            );
    }
}