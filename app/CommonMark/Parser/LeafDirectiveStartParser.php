<?php

namespace App\CommonMark\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

class LeafDirectiveStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        // Match exactly 2 colons (not 3+) followed by directive name
        $match = $cursor->match('/^::(\w[\w-]*)(?:\[([^\]]*)\])?(?:\{([^}]*)\})?\s*$/');

        if ($match === null) {
            return BlockStart::none();
        }

        preg_match('/^::(\w[\w-]*)(?:\[([^\]]*)\])?(?:\{([^}]*)\})?\s*$/', $match, $parts);

        $name = $parts[1];
        $label = $parts[2] ?? '';
        $attrString = $parts[3] ?? '';

        return BlockStart::of(new LeafDirectiveParser($name, $attrString, $label))->at($cursor);
    }
}
