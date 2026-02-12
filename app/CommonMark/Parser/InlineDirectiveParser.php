<?php

namespace App\CommonMark\Parser;

use App\CommonMark\AttributeParser;
use App\CommonMark\Node\InlineDirective;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

class InlineDirectiveParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // Match single colon followed by a word character (directive name start)
        return InlineParserMatch::regex(':(\w[\w-]*)\[');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // Check that the colon is not preceded by another colon (avoid matching :: or :::)
        $previousChar = $cursor->peek(-1);
        if ($previousChar === ':') {
            return false;
        }

        $fullMatch = $inlineContext->getFullMatch();
        $matches = $inlineContext->getMatches();
        $name = $matches[1];

        // Advance past the matched `:name[`
        $cursor->advanceBy($inlineContext->getFullMatchLength());

        // Read the label (content inside brackets)
        $label = '';
        $depth = 1;
        while (! $cursor->isAtEnd()) {
            $char = $cursor->getCurrentCharacter();
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    $cursor->advanceBy(1); // skip closing ]
                    break;
                }
            }
            $label .= $char;
            $cursor->advanceBy(1);
        }

        if ($depth !== 0) {
            // Unmatched bracket — not a valid directive
            return false;
        }

        // Optionally read attributes {key=value ...}
        $attrString = '';
        if (! $cursor->isAtEnd() && $cursor->getCurrentCharacter() === '{') {
            $cursor->advanceBy(1); // skip {
            while (! $cursor->isAtEnd() && $cursor->getCurrentCharacter() !== '}') {
                $attrString .= $cursor->getCurrentCharacter();
                $cursor->advanceBy(1);
            }
            if (! $cursor->isAtEnd()) {
                $cursor->advanceBy(1); // skip }
            }
        }

        $inlineContext->getContainer()->appendChild(
            new InlineDirective($name, AttributeParser::parse($attrString), $label)
        );

        return true;
    }
}
