<?php

namespace App\Filament\Member\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class DirectoryCluster extends Cluster
{
    protected static ?string $slug = 'directory';

    protected static ?string $navigationLabel = 'Directory';

    protected static string|BackedEnum|null $navigationIcon = 'tabler-users';

    protected static ?string $clusterBreadcrumb = 'Directory';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
