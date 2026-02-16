<?php

return [
    'default_collection' => null,

    'collections' => [
        'pages' => [
            'disk' => 'pages',
            'sheet_class' => App\Sheets\Page::class,
            'content_parser' => App\Sheets\DirectiveMarkdownParser::class,
            'extension' => 'md',
        ],
    ],
];
