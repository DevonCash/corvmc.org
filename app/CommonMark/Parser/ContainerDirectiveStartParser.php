<?php

namespace App\CommonMark\Parser;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

class ContainerDirectiveStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented()) {
            return BlockStart::none();
        }

        $match = $cursor->match('/^(:{3,})(\w[\w-]*)(?:\[([^\]]*)\])?(?:\{([^}]*)\})?\s*$/');

        if ($match === null) {
            return BlockStart::none();
        }

        preg_match('/^(:{3,})(\w[\w-]*)(?:\[([^\]]*)\])?(?:\{([^}]*)\})?\s*$/', $match, $parts);

        $colons = strlen($parts[1]);
        $name = $parts[2];
        $label = $parts[3] ?? '';
        $attrString = $parts[4] ?? '';

        return BlockStart::of(new ContainerDirectiveParser($name, $attrString, $label, $colons))->at($cursor);
    }
}
