<?php

namespace App\CommonMark\Processor;

use App\CommonMark\Node\ContainerDirective;
use App\CommonMark\Node\GridItem;
use App\CommonMark\Node\LeafDirective;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\NodeIterator;

class GridItemProcessor
{
    private const FRACTION_TO_SPAN = [
        '1/1' => 12,
        'full' => 12,
        '5/6' => 10,
        '3/4' => 9,
        '2/3' => 8,
        '1/2' => 6,
        '1/3' => 4,
        '1/4' => 3,
        '1/6' => 2,
    ];

    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (! $node instanceof ContainerDirective || $node->getName() !== 'section') {
                continue;
            }

            $this->wrapChildrenInGridItems($node);
        }
    }

    /**
     * Parse a col label like "1/2 lg:2/3" into CSS custom property declarations.
     */
    private function parseColStyle(string $label): string
    {
        $tokens = preg_split('/\s+/', trim($label));
        $props = [];

        foreach ($tokens as $token) {
            if (str_contains($token, ':')) {
                [$prefix, $fraction] = explode(':', $token, 2);
                $span = self::FRACTION_TO_SPAN[$fraction] ?? 12;
                $props[] = "--{$prefix}-span:{$span}";
            } else {
                $span = self::FRACTION_TO_SPAN[$token] ?? 12;
                $props[] = "--col-span:{$span}";
            }
        }

        return implode('; ', $props);
    }

    private function wrapChildrenInGridItems(ContainerDirective $section): void
    {
        // Collect all children first to avoid modification during iteration
        $children = [];
        $child = $section->firstChild();
        while ($child !== null) {
            $children[] = $child;
            $child = $child->next();
        }

        /** @var GridItem|null $currentGroup */
        $currentGroup = null;

        foreach ($children as $child) {
            // ::col[fraction] starts a new grid item with the specified width
            if ($child instanceof LeafDirective && $child->getName() === 'col') {
                $colStyle = $this->parseColStyle($child->getLabel());
                $class = $child->getDirectiveAttributes()['class'] ?? '';
                $currentGroup = new GridItem($colStyle, $class);
                $section->appendChild($currentGroup);
                $child->detach();

                continue;
            }

            // --- (ThematicBreak) starts a new full-width grid item
            if ($child instanceof ThematicBreak) {
                $currentGroup = null;
                $child->detach();

                continue;
            }

            if ($child instanceof AbstractBlock) {
                $child->detach();

                if ($currentGroup === null) {
                    $currentGroup = new GridItem;
                    $section->appendChild($currentGroup);
                }

                $currentGroup->appendChild($child);
            }
        }
    }
}
