<?php

namespace App\CommonMark\Renderer;

use App\CommonMark\Node\GridItem;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

class GridItemRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        return '<div class="prose max-w-none">' . $childRenderer->renderNodes($node->children()) . '</div>';
    }
}
