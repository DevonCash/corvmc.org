<?php

namespace App\Sheets;

use App\CommonMark\DirectiveExtension;
use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\Sheets\ContentParser;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Zenstruck\CommonMark\Extension\GitHub\AdmonitionExtension;

class DirectiveMarkdownParser implements ContentParser
{
    public function parse(string $contents): array
    {
        $document = YamlFrontMatter::parse($contents);

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new DirectiveExtension());
        $environment->addExtension(new AdmonitionExtension());

        $converter = new MarkdownConverter($environment);
        $html = $converter->convert($document->body());

        return array_merge(
            $document->matter(),
            ['contents' => new HtmlString($html)]
        );
    }
}
