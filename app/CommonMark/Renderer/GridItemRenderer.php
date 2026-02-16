<?php

namespace App\CommonMark\Renderer;

use App\CommonMark\Node\GridItem;
use Illuminate\Support\HtmlString;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class GridItemRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        /** @var GridItem $node */
        $html = view('components.blocks.grid-item', [
            'colStyle' => $node->getColStyle(),
            'content' => new HtmlString($childRenderer->renderNodes($node->children())),
        ])->render();

        if ($node->getClass() !== '') {
            $html = preg_replace('/class="([^"]*)"/', 'class="$1 ' . e($node->getClass()) . '"', $html, 1);
        }

        return $html;
    }
}
