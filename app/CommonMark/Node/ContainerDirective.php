<?php

namespace App\CommonMark\Node;

use League\CommonMark\Node\Block\AbstractBlock;

class ContainerDirective extends AbstractBlock
{
    /** @var array<string, string> */
    private array $attributes;

    /**
     * @param  array<string, string>  $attributes
     */
    public function __construct(
        private string $name,
        array $attributes = [],
        private string $label = '',
        private int $fenceLength = 3,
    ) {
        parent::__construct();
        $this->attributes = $attributes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getFenceLength(): int
    {
        return $this->fenceLength;
    }

    /**
     * @return array<string, string>
     */
    public function getDirectiveAttributes(): array
    {
        return $this->attributes;
    }
}
