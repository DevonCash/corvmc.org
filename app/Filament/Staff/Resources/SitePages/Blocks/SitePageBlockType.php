<?php

namespace App\Filament\Staff\Resources\SitePages\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Hidden;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\HtmlString;

enum SitePageBlockType: string implements HasColor, HasLabel
{
    case SectionStart = 'section_start';
    case Header = 'header';
    case Prose = 'prose';
    case Card = 'card';
    case Alert = 'alert';
    case Stat = 'stat';
    case Step = 'step';
    case Button = 'button';

    public function getLabel(): string
    {
        return match ($this) {
            self::SectionStart => 'Segment',
            self::Header => 'Header',
            self::Prose => 'Prose',
            self::Card => 'Card',
            self::Alert => 'Alert',
            self::Stat => 'Stat',
            self::Step => 'Step',
            self::Button => 'Button',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SectionStart => 'gray',
            self::Header => 'primary',
            self::Prose => 'gray',
            self::Card => 'success',
            self::Alert => 'warning',
            self::Stat, self::Step => 'info',
            self::Button => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SectionStart => 'tabler-layout-rows',
            self::Header => 'tabler-heading',
            self::Prose => 'tabler-align-left',
            self::Card => 'tabler-id',
            self::Alert => 'tabler-alert-triangle',
            self::Stat => 'tabler-chart-bar',
            self::Step => 'tabler-list-numbers',
            self::Button => 'tabler-click',
        };
    }

    public function block(): Block
    {
        $blockClass = match ($this) {
            self::SectionStart => SectionStartBlock::class,
            self::Header => HeaderBlock::class,
            self::Prose => ProseBlock::class,
            self::Card => CardBlock::class,
            self::Alert => AlertBlock::class,
            self::Stat => StatBlock::class,
            self::Step => StepBlock::class,
            self::Button => ButtonBlock::class,
        };

        $hiddenFields = match ($this) {
            self::SectionStart => [],
            self::Card, self::Stat => [
                Hidden::make('col_span')->default('auto'),
                Hidden::make('color')->default('base'),
            ],
            self::Button => [
                Hidden::make('col_span')->default('full'),
                Hidden::make('variant')->default('solid'),
                Hidden::make('color')->default('primary'),
            ],
            default => [
                Hidden::make('col_span')->default('auto'),
            ],
        };

        return Block::make($this->value)
            ->label(fn (?array $state): HtmlString => $this->blockLabel($state))
            ->icon($this->icon())
            ->schema([...$blockClass::schema(), ...$hiddenFields]);
    }

    public function previewLabel(array $data): string
    {
        $blockClass = match ($this) {
            self::SectionStart => SectionStartBlock::class,
            self::Header => HeaderBlock::class,
            self::Prose => ProseBlock::class,
            self::Card => CardBlock::class,
            self::Alert => AlertBlock::class,
            self::Stat => StatBlock::class,
            self::Step => StepBlock::class,
            self::Button => ButtonBlock::class,
        };

        return $blockClass::previewLabel($data);
    }

    private function blockLabel(?array $state): HtmlString
    {
        $color = $this->getColor();
        $classes = "bg-{$color}-100 text-{$color}-700 dark:bg-{$color}-500/20 dark:text-{$color}-400";
        $badge = '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium '.$classes.'">'.$this->getLabel().'</span>';

        $detail = e($this->previewLabel($state ?? []));

        return new HtmlString($badge.($detail ? " {$detail}" : ''));
    }

    /**
     * @return array<int, Block>
     */
    public static function blocks(): array
    {
        return array_map(
            fn (self $type) => $type->block(),
            self::cases(),
        );
    }
}
