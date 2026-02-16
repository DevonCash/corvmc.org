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

        // Extract class from attrs — it gets injected into the outermost element after rendering
        $class = $attrs['class'] ?? '';
        unset($attrs['class']);

        if ($node instanceof ContainerDirective) {
            $attrs['content'] = new HtmlString($childRenderer->renderNodes($node->children()));
        }

        $viewName = "components.blocks.{$name}";

        if (! view()->exists($viewName)) {
            // Fallback: render as a div with the directive name as a class
            $inner = $node instanceof ContainerDirective
                ? $childRenderer->renderNodes($node->children())
                : ($attrs['label'] ?? '');

            return '<div class="directive-' . e($name) . ' ' . e($class) . '">' . $inner . '</div>';
        }

        $html = view($viewName, $attrs)->render();

        if ($class !== '') {
            $html = $this->injectClass($html, $class);
        }

        return $html;
    }

    /**
     * Inject classes into the first HTML element's class attribute,
     * or add a class attribute if none exists.
     */
    private function injectClass(string $html, string $class): string
    {
        $escapedClass = e($class);

        // Append to existing class attribute on the first element
        $result = preg_replace('/class="([^"]*)"/', 'class="$1 ' . $escapedClass . '"', $html, 1, $count);

        if ($count > 0) {
            return $result;
        }

        // No class attribute found — add one to the first opening tag
        return preg_replace('/^(\s*<[\w-]+)/', '$1 class="' . $escapedClass . '"', $html, 1);
    }
}
