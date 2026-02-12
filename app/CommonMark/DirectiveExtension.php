<?php

namespace App\CommonMark;

use App\CommonMark\Node\ContainerDirective;
use App\CommonMark\Node\GridItem;
use App\CommonMark\Node\InlineDirective;
use App\CommonMark\Node\LeafDirective;
use App\CommonMark\Parser\ContainerDirectiveStartParser;
use App\CommonMark\Parser\InlineDirectiveParser;
use App\CommonMark\Parser\LeafDirectiveStartParser;
use App\CommonMark\Processor\GridItemProcessor;
use App\CommonMark\Renderer\DirectiveRenderer;
use App\CommonMark\Renderer\GridItemRenderer;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

class DirectiveExtension implements ExtensionInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Container directives must be checked before leaf directives (higher priority)
        $environment->addBlockStartParser(new ContainerDirectiveStartParser(), 200);
        $environment->addBlockStartParser(new LeafDirectiveStartParser(), 199);

        $environment->addInlineParser(new InlineDirectiveParser(), 100);

        $environment->addEventListener(DocumentParsedEvent::class, new GridItemProcessor());

        $renderer = new DirectiveRenderer();
        $environment->addRenderer(ContainerDirective::class, $renderer);
        $environment->addRenderer(LeafDirective::class, $renderer);
        $environment->addRenderer(InlineDirective::class, $renderer);
        $environment->addRenderer(GridItem::class, new GridItemRenderer());
    }
}
