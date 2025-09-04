<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\UserSubscriptionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Spatie\Permission\Models\Role;

class AdminUserControlForm
{
    public static function configure($schema)
    {
        return $schema
            ->schema([
                Section::make('User Permissions & Roles')
                    ->description('Manage user roles and permissions within the system.')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->options(Role::all()->pluck('name', 'id'))
                            ->preload()
                            ->searchable()
                            ->helperText('Assign roles to grant specific permissions and access levels.'),
                    ]),

                Section::make('Membership Administration')
                    ->description('Administrative tools for managing user subscriptions and membership status.')
                    ->schema([
                        Toggle::make('sustaining_member_role_override')
                            ->label('Force Sustaining Member Status')
                            ->helperText('Override automatic sustaining member detection based on transactions')
                            ->default(function ($record) {
                                return $record?->hasRole('sustaining member') ?? false;
                            })
                            ->afterStateUpdated(function ($state, $record) {
                                if ($record) {
                                    $service = app(UserSubscriptionService::class);
                                    if ($state) {
                                        $service->grantSustainingMemberStatus($record);
                                    } else {
                                        $service->revokeSustainingMemberStatus($record);
                                    }
                                }
                            })
                            ->live(),
                        Action::make('create_admin_subscription')
                            ->label('Create Admin Subscription')
                            ->icon('heroicon-o-plus')
                            ->color('warning')
                            ->schema([
                                Select::make('price_id')
                                    ->label('Subscription Plan')
                                    ->options([
                                        'price_sustaining_10' => 'Sustaining Member - $10/month',
                                        'price_sustaining_25' => 'Sustaining Member Plus - $25/month',
                                        'price_sustaining_50' => 'Sustaining Member Premium - $50/month',
                                    ])
                                    ->required(),
                            ])
                            ->action(function (array $data, $record) {
                                try {
                                    $subscription = $record->newSubscription('default', $data['price_id'])->create();
                                    \Filament\Notifications\Notification::make()
                                        ->title('Subscription created successfully')
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Failed to create subscription')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                            ->visible(function ($record) {
                                return ! $record?->subscription('default')?->active() && $record?->stripe_id;
                            }),
                        TextEntry::make('stripe_subscriptions')
                            ->label('All Subscriptions')
                            ->formatStateUsing(function ($record) {
                                if (! $record->subscriptions()->count()) {
                                    return 'No subscriptions found';
                                }

                                return $record->subscriptions()
                                    ->get()
                                    ->map(function ($subscription) {
                                        $price = $subscription->items->first()?->price ?? null;
                                        $status = ucfirst($subscription->stripe_status);
                                        $amount = $price ? '$' . ($price->unit_amount / 100) : 'Unknown';

                                        return sprintf(
                                            '%s - %s (%s/month)',
                                            $subscription->type,
                                            $status,
                                            $amount
                                        );
                                    })
                                    ->implode('<br>');
                            })
                            ->html()
                            ->placeholder('No subscriptions found'),
                        TextEntry::make('customer_portal_admin')
                            ->label('Stripe Customer Portal')
                            ->formatStateUsing(function ($record) {
                                if ($record->stripe_id) {
                                    return '<a href="#" onclick="openCustomerPortal(\'' . $record->id . '\')" class="text-primary-600 hover:underline">Open Customer Portal</a>';
                                }

                                return 'No Stripe customer ID';
                            })
                            ->html(),
                    ]),

                Section::make('Staff Profile Management')
                    ->description('Administrative tools for managing staff profiles.')
                    ->visible(fn($record) => User::me()?->can('manage staff profiles'))
                    ->headerActions([
                        Action::make('create_staff_profile')
                            ->label('Add Staff Profile')
                            ->icon('heroicon-o-plus')
                            ->color('primary')
                            ->visible(fn($record) => ! $record?->staffProfile)
                            ->action(function ($livewire) {
                                $record = $livewire->getRecord();
                                if ($record && ! $record->staffProfile) {
                                    $record->staffProfile()->create([
                                        'name' => $record->name,
                                        'email' => $record->email,
                                        'type' => 'staff',
                                        'is_active' => false,
                                        'sort_order' => 0,
                                    ]);
                                    $record->refresh();
                                    $livewire->form->fill($record->toArray());
                                }
                            }),
                    ]),
            ]);
    }
}
