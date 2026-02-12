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
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (! $node instanceof ContainerDirective || $node->getName() !== 'section') {
                continue;
            }

            $this->wrapChildrenInGridItems($node);
        }
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

        // --- (ThematicBreak) is the only column separator.
        // All content between --- markers is grouped into a single grid item.

        /** @var GridItem|null $currentGroup */
        $currentGroup = null;

        foreach ($children as $child) {
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
