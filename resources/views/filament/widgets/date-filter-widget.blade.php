<x-filament-widgets::widget>
    <div class="flex justify-end items-center bg-transparent p-0">
        <span class="text-sm font-medium text-black p-4">Custom</span>

        {{ $this->form }}

        <button type="button" wire:click="resetToLast30Days" class="text-sm text-primary-600 font-semibold hover:underline p-4">
            Show last 30 days
        </button>
    </div>
</x-filament-widgets::widget>
