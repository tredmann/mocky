@props([
    'sidebar' => false,
])

@if($sidebar)
    <div class="flex flex-col">
        <flux:sidebar.brand name="{{ config('app.name') }}" {{ $attributes }}>
            <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            </x-slot>
        </flux:sidebar.brand>
        @if(config('app.version') !== 'dev')
            <span class="px-3 text-xs text-zinc-400">{{ config('app.version') }}</span>
        @endif
    </div>
@else
    <flux:brand name="{{ config('app.name') }}" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
