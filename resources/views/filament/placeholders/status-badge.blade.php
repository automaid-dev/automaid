@php
    $status = strtolower($getRecord()->status);

    $color = match ($status) {
        'active' => 'success',
        'pending' => 'warning',
        'cancelled' => 'danger',
        'inactive' => 'gray',
        default => 'gray',
    };
@endphp

<div class="text-right">
    <div class="inline-block">
        <x-filament::badge :color="$color">
            {{ ucfirst($status) }}
        </x-filament::badge>
    </div>
</div>
