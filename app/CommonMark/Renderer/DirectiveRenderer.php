<?php

namespace App\CommonMark\Renderer;

use App\CommonMark\Node\ContainerDirective;
use App\CommonMark\Node\InlineDirective;
use App\CommonMark\Node\LeafDirective;
use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class DirectiveRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        $attrs = match (true) {
            $node instanceof ContainerDirective => $node->getDirectiveAttributes(),
            $node instanceof LeafDirective => $node->getDirectiveAttributes(),
            $node instanceof InlineDirective => $node->getDirectiveAttributes(),
            default => [],
        };

        $name = match (true) {
            $node instanceof ContainerDirective => $node->getName(),
            $node instanceof LeafDirective => $node->getName(),
            $node instanceof InlineDirective => $node->getName(),
            default => 'unknown',
        };

        $attrs['label'] = match (true) {
            $node instanceof ContainerDirective => $node->getLabel(),
            $node instanceof LeafDirective => $node->getLabel(),
            $node instanceof InlineDirective => $node->getLabel(),
            default => '',
        };

        if ($node instanceof ContainerDirective) {
            $attrs['content'] = new HtmlString($childRenderer->renderNodes($node->children()));
        }

        $viewName = "components.blocks.{$name}";

        if (! view()->exists($viewName)) {
            // Fallback: render as a div with the directive name as a class
            $inner = $node instanceof ContainerDirective
                ? $childRenderer->renderNodes($node->children())
                : ($attrs['label'] ?? '');

            return '<div class="directive-' . e($name) . '">' . $inner . '</div>';
        }

        return view($viewName, $attrs)->render();
    }
}
