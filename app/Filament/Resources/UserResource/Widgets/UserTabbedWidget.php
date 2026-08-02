<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\Widget;

class UserTabbedWidget extends Widget
{
    protected static string $view = 'filament.resources.user-resource.widgets.user-tabbed-widget';

    protected int | string | array $columnSpan = 'full'; // Full width
}
