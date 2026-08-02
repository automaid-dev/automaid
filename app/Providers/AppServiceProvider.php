<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\UserMenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen('App\Events\RiderPendingAcceptance', 'App\Listeners\RiderPendingAcceptance');
        Event::listen('App\Events\RiderPendingAcceptance', 'App\Listeners\Telegram\RiderPendingAcceptance');

        Event::listen('App\Events\RiderPickupWashOutlet', 'App\Listeners\RiderPickupWashOutlet');
        Event::listen('App\Events\RiderPickupWashOutlet', 'App\Listeners\Telegram\RiderPickupWashOutlet');

        Event::listen('App\Events\MerchantDeliveryWashOutlet', 'App\Listeners\MerchantDeliveryWashOutlet');
        Event::listen('App\Events\MerchantDeliveryWashOutlet', 'App\Listeners\Telegram\MerchantDeliveryWashOutlet');

        Event::listen('App\Events\MerchantOrderDelivered', 'App\Listeners\MerchantOrderDelivered');
        Event::listen('App\Events\MerchantOrderDelivered', 'App\Listeners\Telegram\MerchantOrderDelivered');

        Event::listen('App\Events\MerchantPendingAcceptance', 'App\Listeners\MerchantPendingAcceptance');
        Event::listen('App\Events\MerchantPendingAcceptance', 'App\Listeners\Telegram\MerchantPendingAcceptance');

        Event::listen('App\Events\CustomerDeliveryToCustomer', 'App\Listeners\CustomerDeliveryToCustomer');
        Event::listen('App\Events\CustomerDeliveryToCustomer', 'App\Listeners\Telegram\CustomerDeliveryToCustomer');

        Event::listen('App\Events\CustomerDeliveryWashOutlet', 'App\Listeners\CustomerDeliveryWashOutlet');
        Event::listen('App\Events\CustomerDeliveryWashOutlet', 'App\Listeners\Telegram\CustomerDeliveryWashOutlet');

        Event::listen('App\Events\CustomerNewAnnouncement', 'App\Listeners\CustomerNewAnnouncement');
        // Event::listen('App\Events\CustomerNewAnnouncement', 'App\Listeners\Telegram\CustomerNewAnnouncement');
        
        Event::listen('App\Events\CustomerNewSupportTicket', 'App\Listeners\CustomerNewSupportTicket');
        Event::listen('App\Events\CustomerNewSupportTicket', 'App\Listeners\Telegram\CustomerNewSupportTicket');

        Event::listen('App\Events\CustomerOrderDelivered', 'App\Listeners\CustomerOrderDelivered');
        Event::listen('App\Events\CustomerOrderDelivered', 'App\Listeners\Telegram\CustomerOrderDelivered');

        Event::listen('App\Events\CustomerReadyPickup', 'App\Listeners\CustomerReadyPickup');
        Event::listen('App\Events\CustomerReadyPickup', 'App\Listeners\Telegram\CustomerReadyPickup');

        Event::listen('App\Events\CustomerWashInProgress', 'App\Listeners\CustomerWashInProgress');
        Event::listen('App\Events\CustomerWashInProgress', 'App\Listeners\Telegram\CustomerWashInProgress');

        Event::listen('App\Events\CustomerAdminCancelOrder', 'App\Listeners\CustomerAdminCancelOrder');
        Event::listen('App\Events\RiderAdminCancelOrder', 'App\Listeners\RiderAdminCancelOrder');
        Event::listen('App\Events\MerchantAdminCancelOrder', 'App\Listeners\MerchantAdminCancelOrder');


        if (!app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
