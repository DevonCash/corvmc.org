<?php

namespace App\CommonMark\Parser;

use App\CommonMark\AttributeParser;
use App\CommonMark\Node\ContainerDirective;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

class ContainerDirectiveParser extends AbstractBlockContinueParser
{
    private ContainerDirective $block;

    public function __construct(string $name, string $attrString, string $label, int $fenceLength)
    {
        $this->block = new ContainerDirective(
            $name,
            AttributeParser::parse($attrString),
            $label,
            $fenceLength,
        );
    }

    public function getBlock(): ContainerDirective
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        $line = $cursor->getLine();

        // Check for closing fence: same or more colons, nothing else on the line
        if (preg_match('/^\s*(:{3,})\s*$/', $line, $matches)) {
            if (strlen($matches[1]) >= $this->block->getFenceLength()) {
                return BlockContinue::finished();
            }
        }

        return BlockContinue::at($cursor);
    }
}
