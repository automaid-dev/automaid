{{-- resources/views/vendor/filament/pages/auth/login.blade.php --}}
@push('styles')
<style>
    /* Hide default Filament top logos */
    .fi-logo,
    .filament-auth-logo {
        display: none !important;
    }
</style>
@endpush

<x-filament-panels::page.simple>

    {{-- Remove top header --}}
    <x-slot name="header"></x-slot>

    {{-- Remove default auth logo slot --}}
    <x-slot name="authLogo"></x-slot>

    {{-- Custom Login Logo with optional slogan --}}
    <div class="flex flex-col items-center mb-6">
        <img
            src="{{ asset('assets/images/LOGO_BARU_30_JAN_2026.svg') }}"
            alt="Login Logo"
            style="height: 200px;"
        >
        <!-- <span class="text-gray-600 mt-2 text-sm">Welcome to Automaid</span> -->
    </div>

    {{-- Optional registration link --}}
    @if (filament()->hasRegistration())
        <x-slot name="subheading">
            {{ __('filament-panels::pages/auth/login.actions.register.before') }}
            {{ $this->registerAction }}
        </x-slot>
    @endif

    {{-- Hook before form --}}
    {{ \Filament\Support\Facades\FilamentView::renderHook(
        \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
        scopes: $this->getRenderHookScopes()
    ) }}

    {{-- Login Form (Livewire handled) --}}
    <x-filament-panels::form id="form" wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{-- Hook after form --}}
    {{ \Filament\Support\Facades\FilamentView::renderHook(
        \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
        scopes: $this->getRenderHookScopes()
    ) }}

</x-filament-panels::page.simple>
