<?php

declare(strict_types=1);

namespace Netgen\Bundle\MoreBundle\Layouts\Block\Plugin;

use Netgen\BlockManager\Block\BlockDefinition\BlockDefinitionHandlerInterface;
use Netgen\BlockManager\Block\BlockDefinition\Handler\Plugin;
use Netgen\BlockManager\Parameters\ParameterBuilderInterface;
use Netgen\BlockManager\Parameters\ParameterType;

class SetContainerPlugin extends Plugin
{
    /**
     * The list of sizes available. Keys should be identifiers, while values
     * should be human readable names of the sizes.
     *
     * @var array
     */
    private $sizes = [];

    public function __construct(array $sizes)
    {
        $this->sizes = $sizes;
    }

    public static function getExtendedHandlers(): array
    {
        return [BlockDefinitionHandlerInterface::class];
    }

    public function buildParameters(ParameterBuilderInterface $builder): void
    {
        $designGroup = [self::GROUP_DESIGN];

        $builder->remove('set_container');

        $builder->add(
            'set_container',
            ParameterType\Compound\BooleanType::class,
            [
                'label' => 'block.plugin.common_params.set_container',
                'translatable' => false,
                'groups' => $designGroup,
            ]
        );

        $builder->get('set_container')->add(
            'set_container:size',
            ParameterType\ChoiceType::class,
            [
                'default_value' => '',
                'label' => 'block.plugin.set_container.size',
                'translatable' => false,
                'options' => array_flip($this->sizes),
                'groups' => $designGroup,
            ]
        );
    }
}