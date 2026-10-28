@props(['name'])

<div x-data="richTextEditor(@js((string) $slot))" {{ $attributes }}>
    <div x-ref="editor"></div>
    <input type="hidden" name="{{ $name }}" x-ref="input">
</div>
