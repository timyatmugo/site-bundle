<?php

declare(strict_types=1);

namespace Netgen\Bundle\SiteBundle\Core\MVC\Symfony\Matcher;

use function is_array;

abstract class MultipleValued
{
    /**
     * Values to test against.
     *
     * @var mixed[]
     */
    protected array $values = [];

    /**
     * Registers the matching configuration for the matcher.
     * $matchingConfig can have single (string|int...) or multiple values (array).
     *
     * @throws \InvalidArgumentException Should be thrown if $matchingConfig is not valid
     */
    public function setMatchingConfig($matchingConfig): void
    {
        $this->values = !is_array($matchingConfig) ? [$matchingConfig] : $matchingConfig;
    }
}
