<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\Actions\CreateMembershipSubscriptionAction;
use App\Filament\Resources\Users\Actions\ModifyMembershipAmountAction;
use App\Filament\Resources\Users\Actions\ResumeMembershipAction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class MyMembership extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected string $view = 'filament.pages.my-membership';

    protected static string|\UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Membership';

    protected static ?string $slug = 'membership';

    protected function getHeaderActions(): array
    {
        return [
            CreateMembershipSubscriptionAction::make()
                ->visible(fn () => ! User::me()?->isSustainingMember()),
        ];
    }

    public function getBillingPortalUrl(): ?string
    {
        $user = User::me();

        if (! $user?->stripe_id) {
            return null;
        }

        $returnUrl = route('filament.member.pages.membership');

        return $user->billingPortalUrl($returnUrl);
    }

    public function modifyMembershipAmountAction(): Action
    {
        return ModifyMembershipAmountAction::make();
    }

    public function resumeMembershipAction(): Action
    {
        return ResumeMembershipAction::make();
    }
}
