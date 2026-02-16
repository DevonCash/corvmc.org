<?php

namespace App\CommonMark\Processor;

use App\CommonMark\Node\ContainerDirective;
use App\CommonMark\Node\LeafDirective;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Node\Node;

class SectionProcessor
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();

        // Collect top-level children to avoid modification during iteration
        $children = [];
        $child = $document->firstChild();
        while ($child !== null) {
            $children[] = $child;
            $child = $child->next();
        }

        // Find indices of ::section leaf directives
        $sectionIndices = [];
        foreach ($children as $i => $child) {
            if ($child instanceof LeafDirective && $child->getName() === 'section') {
                $sectionIndices[] = $i;
            }
        }

        if ($sectionIndices === []) {
            return;
        }

        // Process each ::section leaf directive
        // Work backwards so earlier indices remain valid
        foreach (array_reverse($sectionIndices) as $idx) {
            /** @var LeafDirective $leaf */
            $leaf = $children[$idx];

            // Determine the range of siblings that belong to this section:
            // from $idx+1 up to (but not including) the next ::section leaf or end
            $nextSectionIdx = null;
            for ($j = $idx + 1; $j < count($children); $j++) {
                $child = $children[$j];
                if ($child instanceof LeafDirective && $child->getName() === 'section') {
                    $nextSectionIdx = $j;
                    break;
                }
            }

            $endIdx = $nextSectionIdx ?? count($children);

            // Create a ContainerDirective with the same name, attributes, and label
            $container = new ContainerDirective(
                name: 'section',
                attributes: $leaf->getDirectiveAttributes(),
                label: $leaf->getLabel(),
            );

            // Move siblings into the container
            for ($j = $idx + 1; $j < $endIdx; $j++) {
                $sibling = $children[$j];
                $sibling->detach();
                $container->appendChild($sibling);
            }

            // Replace the leaf directive with the container
            $leaf->replaceWith($container);
        }
    }
}
