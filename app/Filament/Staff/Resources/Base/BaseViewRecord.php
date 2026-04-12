<?php

namespace App\Filament\Staff\Resources\Base;

use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

abstract class BaseViewRecord extends ViewRecord
{
    /**
     * Build the standard 3-column layout with activity sidebar
     */
    protected function buildStandardLayout(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Hero section - full width
                ...$this->getHeroComponents(),

                // Main content - 2 columns
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema($this->getMainContentComponents())
                    ->columnSpan(2),

                // Activity sidebar - 1 column
                $this->buildActivitySection(),
            ]);
    }

    /**
     * Get hero components (full width status bar)
     * Override this to provide custom hero content
     */
    protected function getHeroComponents(): array
    {
        return [];
    }

    /**
     * Get main content components (2-column area)
     * Override this to provide main content cards
     */
    protected function getMainContentComponents(): array
    {
        return [];
    }

    /**
     * Build the activity log sidebar
     */
    protected function buildActivitySection(): Section
    {
        return Section::make('Activity')
            ->icon('tabler-history')
            ->compact()
            ->contained(false)
            ->schema([
                View::make('activity_log')
                    ->view('filament.staff.components.activity-log')
                    ->viewData(fn($record) => ['activity' => Activity::forSubject($record)->with('causer')->latest()->get()]),
            ]);
    }


    /**
     * Helper method to format hours consistently
     */
    protected function formatHours(float $hours): string
    {
        return fmod($hours, 1) === 0.0 ? intval($hours) . ' hrs' : number_format($hours, 1) . ' hrs';
    }

    /**
     * Helper method to format money consistently
     */
    protected function formatMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
