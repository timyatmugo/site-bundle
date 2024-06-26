<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Helper;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

use function array_key_first;
use function count;
use function in_array;
use function is_array;
use function is_string;

final class MailHelper
{
    private MailerInterface $mailer;
    private Environment $twig;
    private TranslatorInterface $translator;
    private ConfigResolverInterface $configResolver;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        TranslatorInterface $translator,
        ConfigResolverInterface $configResolver,
        LoggerInterface $logger = null
    ) {

        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->translator = $translator;
        $this->configResolver = $configResolver;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Sends an mail.
     *
     * Receivers can be:
     * a string: info@netgen.io
     * or:
     * array( 'info@netgen.io' => 'Netgen Site' ) or
     * array( 'info@netgen.io', 'example@netgen.io' ) or
     * array( 'info@netgen.io' => 'Netgen Site', 'example@netgen.io' => 'Example' )
     *
     * Sender can be:
     * a string: info@netgen.io
     * an array: array( 'info@netgen.io' => 'Netgen Site' )
     *
     * @param string|string[] $receivers
     * @param array<string, mixed> $parameters
     *  @param string|string[]|null $sender
     */
    public function sendMail($receivers, string $subject, string $template, array $parameters = [], $sender = null): void
    {
        try {
            $senderAddress = $this->createSenderAddress($sender);
        } catch (InvalidArgumentException $e) {
            $this->logger->error($e->getMessage());

            return;
        }

        $email = (new Email())
            ->from($senderAddress)
            ->sender($senderAddress)
            ->to(...$this->createReceiverAddresses($receivers))
            ->subject($this->translator->trans($subject, [], 'ngsite_mail'))
            ->html($this->twig->render($template, $parameters));

        $this->mailer->send($email);
    }

    /**
     * Creates a sender address from provided value.
     * If sender is not provided (if it is null), it attempts to get the sender from the parameters:
     * ngsite.default.mail.sender_email
     * ngsite.default.mail.sender_name (optional).
     *
     * @param string|string[]|null $sender
     *
     * @throws \InvalidArgumentException If sender was not provided
     */
    private function createSenderAddress($sender): Address
    {
        if (!in_array($sender, [null, '', []], true)) {
            if ((is_array($sender) && count($sender) === 1 && !isset($sender[0])) || is_string($sender)) {
                if (is_array($sender)) {
                    return new Address(array_key_first($sender), $sender[array_key_first($sender)]);
                }

                return new Address($sender);
            }

            throw new InvalidArgumentException(
                "Parameter 'sender' has to be either a string, or an associative array with one element (e.g. array( 'info@example.com' => 'Example name' ))",
            );
        }

        if ($this->configResolver->hasParameter('mail.sender_email', 'ngsite')) {
            $name = $this->configResolver->hasParameter('mail.sender_name', 'ngsite') ?
                $this->configResolver->getParameter('mail.sender_name', 'ngsite') :
                '';

            return new Address(
                $this->configResolver->getParameter('mail.sender_email', 'ngsite'),
                $name,
            );
        }

        throw new InvalidArgumentException("Parameter 'sender' has not been provided, nor it has been configured via parameters ('ngsite.default.mail.sender_email')!");
    }

    /**
     * @param string|string[] $addresses
     *
     * @return iterable<\Symfony\Component\Mime\Address>
     */
    private function createReceiverAddresses($addresses): iterable
    {
        foreach ((array) $addresses as $key => $value) {
            if (is_string($key)) {
                yield new Address($key, $value);

                continue;
            }

            yield new Address($value);
        }
    }
}
