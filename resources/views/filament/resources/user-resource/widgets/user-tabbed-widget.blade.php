<x-filament::widget>
    <div x-data="{ tab: 'all' }">
        <x-filament::tabs>
            {{-- Tab 1 --}}
            <x-filament::tabs.item
                name="all"
                label="All Users"
                x-on:click="tab = 'all'"
                :active="tab === 'all'"
            />

            {{-- Tab 2 --}}
            <x-filament::tabs.item
                name="pending"
                label="Pending Approval"
                x-on:click="tab = 'pending'"
                :active="tab === 'pending'"
            />
        </x-filament::tabs>

        <div class="p-4">
            <div x-show="tab === 'all'" x-cloak>
                <p>Showing all users...</p>
            </div>

            <div x-show="tab === 'pending'" x-cloak>
                <p>Showing pending users...</p>
            </div>
        </div>
    </div>
</x-filament::widget>
