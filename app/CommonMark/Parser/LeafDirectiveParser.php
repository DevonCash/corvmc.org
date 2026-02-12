<?php

namespace App\CommonMark\Parser;

use App\CommonMark\AttributeParser;
use App\CommonMark\Node\LeafDirective;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

class LeafDirectiveParser extends AbstractBlockContinueParser
{
    private LeafDirective $block;

    public function __construct(string $name, string $attrString, string $label)
    {
        $this->block = new LeafDirective(
            $name,
            AttributeParser::parse($attrString),
            $label,
        );
    }

    public function getBlock(): LeafDirective
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        // Leaf directives are single-line — return none() so the next line is still
        // available for block start parsing (finished() would consume the line)
        return BlockContinue::none();
    }
}
