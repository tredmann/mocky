@props([
    'wire' => '',
    'contentType' => 'application/json',
    'contentTypeWire' => null,
])

<div
    x-data="codeEditor($wire.{{ $wire }}, '{{ $contentType }}', '{{ $wire }}')"
    @if($contentTypeWire)
        x-effect="updateLanguage($wire.{{ $contentTypeWire }})"
    @endif
    class="code-editor-wrapper"
    wire:ignore
>
    <div x-ref="editor" class="code-editor"></div>
    <div class="flex items-center justify-between mt-1">
        <span x-show="hasError" x-cloak class="text-xs text-red-500">Syntax error</span>
        <span x-show="!hasError"></span>
        <button type="button" @click="format()" class="text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 cursor-pointer">
            Format (Shift+Alt+F)
        </button>
    </div>
</div>
