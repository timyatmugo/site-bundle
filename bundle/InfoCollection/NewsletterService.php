<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\InfoCollection;

use Ibexa\Contracts\ContentForms\Data\Content\FieldData;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\FieldType\Checkbox\Value as CheckboxValue;
use MailerLiteApi\MailerLite;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_filter;
use function array_unique;
use function count;
use function ctype_digit;
use function explode;
use function preg_match;
use function reset;

final class NewsletterService
{
    public const ALREADY_ACTIVE = 'already_active';

    public const NEW_UNCONFIRMED = 'new';

    public const PREVIOUS_UNCONFIRMED = 'unconfirmed';

    public const UNSUBSCRIBED = 'unsubscribed';
    private MailerInterface $mailer;
    private MailerLite $mailerLite;
    private string $mailerLiteApiKey;
    private TranslatorInterface $translator;
    private string $newsletterSenderEmail;
    private string $newsletterRecipientEmail;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        MailerLite $mailerLite,
        string $mailerLiteApiKey,
        TranslatorInterface $translator,
        string $newsletterSenderEmail,
        string $newsletterRecipientEmail,
        LoggerInterface $logger = null
    ) {

        $this->mailer = $mailer;
        $this->mailerLite = $mailerLite;
        $this->mailerLiteApiKey = $mailerLiteApiKey;
        $this->translator = $translator;
        $this->newsletterSenderEmail = $newsletterRecipientEmail;
        $this->newsletterRecipientEmail = $newsletterSenderEmail;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param \Ibexa\Contracts\ContentForms\Data\Content\FieldData[] $fields
     */
    public function subscribeToNewsletters(Content $content, array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->isFieldValidNewsletterOptIn($field)) {
                $this->subscribeToNewsletter($content, $fields, $field);
            }
        }
    }

    /**
     * @param \Ibexa\Contracts\ContentForms\Data\Content\FieldData[] $fields
     */
    private function subscribeToNewsletter(Content $content, array $fields, FieldData $field): void
    {
        if ($this->mailerLiteApiKey === '') {
            $this->logger->warning(
                'MailerLite API key is not configured, newsletter integration is disabled',
            );

            return;
        }

        $identifier = $this->extractOptInIdentifier($field);

        $subscriberData = $this->extractSubscriberData($fields);
        $mailerLiteGroupIds = $this->extractMailerLiteGroupIds($content, $identifier);

        $status = [];

        foreach ($mailerLiteGroupIds as $mailerLiteGroupId) {
            $mailerLiteResponse = $this->addSubscriberToGroup((int) $mailerLiteGroupId, $subscriberData);

            if (isset($mailerLiteResponse->error)) {
                throw new RuntimeException('MailerLite error');
            }

            $subscriberId = $mailerLiteResponse->id;

            if ($mailerLiteResponse->type === 'unconfirmed' && $mailerLiteResponse->sent === 1) {
                $status[] = self::PREVIOUS_UNCONFIRMED;
            } elseif ($mailerLiteResponse->type === 'active') {
                $status[] = self::ALREADY_ACTIVE;
            } elseif ($mailerLiteResponse->type === 'unsubscribed') {
                $status[] = self::UNSUBSCRIBED;
            } else {
                $status[] = self::NEW_UNCONFIRMED;
            }

            $currentStatus = array_unique($status);
            $currentStatus = reset($currentStatus);

            if ($currentStatus === self::UNSUBSCRIBED) {
                $this->sendUnsubscribedWarningMail($subscriberId);
            } elseif ($currentStatus === self::PREVIOUS_UNCONFIRMED) {
                $this->sendPreviouslyUnconfirmedMail($subscriberId);
            }
        }
    }

    private function isFieldValidNewsletterOptIn(FieldData $field): bool
    {
        $identifier = $this->extractOptInIdentifier($field);

        if ($identifier === '') {
            return false;
        }

        $value = $field->value;

        if (!$value instanceof CheckboxValue) {
            return false;
        }

        return $value->bool;
    }

    private function extractOptInIdentifier(FieldData $field): string
    {
        $identifier = $field->fieldDefinition->identifier;
        $success = preg_match('/^newsletter_(?<identifier>.*?)_consent/', $identifier, $matches);

        if ($success === false) {
            throw new RuntimeException('Error matching opt-in identifier');
        }

        return $matches['identifier'] ?? '';
    }

    /**
     * @param array<string, \Ibexa\Contracts\ContentForms\Data\Content\FieldData|null> $fields
     *
     * @return array<string, mixed>
     */
    private function extractSubscriberData(array $fields): array
    {
        //nullsafe for php7
        $email = $fields['sender_email'] ?? null;
        $name = $fields['sender_first_name'] ?? null;
        $last_name = $fields['sender_last_name'] ?? null;
        $company = $fields['sender_company'] ?? null;
        if ($email) {
            $email = $email->value->email;
        }
        if ($name) {
            $name = $name->value->text;
        }
        if ($last_name) {
            $last_name = $last_name->value->text;
        }
        if ($company) {
            $company = $company->value->text;
        }
        return [
            'email' => $email,
            'fields' => [
                'name' => $name ?? '',
                'last_name' => $last_name ?? '',
                'company' => $company ?? '',
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function extractMailerLiteGroupIds(Content $content, string $identifier): array
    {
        $groupIdsFieldIdentifier = 'newsletter_' . $identifier . '_group_ids';
        $mailerLiteGroupIds = $content->getFieldValue($groupIdsFieldIdentifier)->text;
        $mailerLiteGroupIds = count($mailerLiteGroupIds) > 0 ? explode(' ', $mailerLiteGroupIds) : [];

        return array_filter(
            $mailerLiteGroupIds,
            static fn ($mailerLiteGroupId): bool => ctype_digit($mailerLiteGroupId),
        );
    }

    private function sendUnsubscribedWarningMail(int $subscriberId): void
    {
        $message = new Email();

        $subject = $this->translator->trans('newsletter.unsubscribed_person_subscribed.subject');
        $body = $this->translator->trans('newsletter.unsubscribed_person_subscribed.body');
        $body .= "\nhttps://app.mailerlite.com/subscribers/single/" . $subscriberId;

        $message->addFrom(Address::create($this->newsletterSenderEmail));
        $message->addTo(Address::create($this->newsletterRecipientEmail));
        $message->subject($subject);
        $message->text($body);

        $this->mailer->send($message);
    }

    private function sendPreviouslyUnconfirmedMail(int $subscriberId): void
    {
        $message = new Email();

        $subject = $this->translator->trans('newsletter.previously_unconfirmed.subject');
        $body = $this->translator->trans('newsletter.previously_unconfirmed.body');
        $body .= "\nhttps://app.mailerlite.com/subscribers/single/" . $subscriberId;

        $message->addFrom(Address::create($this->newsletterSenderEmail));
        $message->addTo(Address::create($this->newsletterRecipientEmail));
        $message->subject($subject);
        $message->text($body);

        $this->mailer->send($message);
    }

    /**
     * @param array<string, mixed> $subscriberData
     */
    private function addSubscriberToGroup(int $groupId, array $subscriberData)
    {
        return $this->mailerLite->groups()->addSubscriber($groupId, $subscriberData);
    }
}
