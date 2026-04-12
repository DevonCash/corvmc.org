<?php

namespace App\Filament\Staff\Resources\Base\Cards;

use App\Filament\Staff\Resources\Charges\ChargeResource;
use CorvMC\Finance\Models\Charge;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Model;

class ChargeCard
{
    /**
     * Create a standardized charge/payment card section
     */
    public static function make(string $relationship = 'charge', ?string $label = null): Section
    {
        return Section::make($label ?? 'Payment')
            ->icon('tabler-credit-card')
            ->compact()
            ->headerActions([
                \Filament\Actions\Action::make('view_charge')
                    ->hiddenLabel()
                    ->icon('tabler-arrow-right')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn(?Model $record) => $record?->{$relationship} ? ChargeResource::getUrl('view', ['record' => $record->{$relationship}]) : null)
                    ->openUrlInNewTab(),
            ])
            ->schema([
                Flex::make([
                    IconEntry::make("{$relationship}.status")
                        ->hiddenLabel()
                        ->size(IconSize::TwoExtraLarge)
                        ->grow(false),
                    Grid::make(1)
                        ->gap(0)
                        ->schema([
                            TextEntry::make("{$relationship}.net_amount")
                                ->hiddenLabel()
                                ->weight(FontWeight::Bold)
                                ->size(TextSize::Large)
                                ->state(fn(?Model $record) => (string) $record?->{$relationship}?->net_amount),
                            TextEntry::make('cost_breakdown')
                                ->hiddenLabel()
                                ->color('gray')
                                ->state(fn(?Model $record) => self::getCostBreakdown($record?->{$relationship})),
                            TextEntry::make("{$relationship}.payment_method")
                                ->hiddenLabel()
                                ->icon('tabler-credit-card')
                                ->iconColor('gray')
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->payment_method !== null),
                            TextEntry::make("{$relationship}.paid_at")
                                ->hiddenLabel()
                                ->icon('tabler-clock-check')
                                ->iconColor('success')
                                ->dateTime('M j, Y g:i A')
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->paid_at !== null),
                        ]),
                ])->verticallyAlignCenter(),
            ]);
    }

    /**
     * Create a compact charge reference (for inline use)
     */
    public static function makeCompact(string $relationship = 'charge'): Flex
    {
        return Flex::make([
            TextEntry::make("{$relationship}.status")
                ->hiddenLabel()
                ->badge()
                ->grow(false),
            TextEntry::make("{$relationship}.net_amount")
                ->hiddenLabel()
                ->weight('bold')
                ->state(fn(?Model $record) => (string) $record?->{$relationship}?->net_amount),
        ])->verticallyAlignCenter();
    }

    /**
     * Get cost breakdown text
     */
    private static function getCostBreakdown(?Charge $charge): ?string
    {
        if (!$charge) {
            return null;
        }

        $parts = [];
        
        // Show gross amount if different from net
        if ($charge->amount->getAmount() !== $charge->net_amount->getAmount()) {
            $parts[] = (string) $charge->amount . ' gross';
        }

        // Show credits applied
        if ($charge->credits_applied && count($charge->credits_applied) > 0) {
            foreach ($charge->credits_applied as $type => $blocks) {
                $hours = $blocks * 0.5;
                $parts[] = "{$hours} hrs credit";
            }
        }

        return implode(' − ', $parts);
    }
}