<?php

namespace App\CommonMark\Node;

use League\CommonMark\Node\Block\AbstractBlock;

class GridItem extends AbstractBlock
{
    public function __construct(
        private string $colStyle = '--col-span:12',
        private string $class = '',
    ) {
        parent::__construct();
    }

    public function getColStyle(): string
    {
        return $this->colStyle;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}
