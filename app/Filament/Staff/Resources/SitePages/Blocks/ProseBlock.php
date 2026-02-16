<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\MarkdownEditor;
use Illuminate\Support\Str;

class ProseBlock
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            MarkdownEditor::make('content')
                ->required(),
        ];
    }

    public static function previewLabel(array $data): string
    {
        return Str::limit(strip_tags($data['content'] ?? ''), 40);
    }
}
