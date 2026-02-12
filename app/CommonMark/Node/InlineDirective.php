<?php

namespace App\CommonMark\Node;

use League\CommonMark\Node\Inline\AbstractInline;

class InlineDirective extends AbstractInline
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

    /**
     * @return array<string, string>
     */
    public function getDirectiveAttributes(): array
    {
        return $this->attributes;
    }
}
