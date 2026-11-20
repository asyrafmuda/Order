{{-- A labelled input. Expects: name, label; optional: type, value, hint, required, placeholder, autocomplete, inputmode. --}}
@php($id = 'f-'.$name)
<div class="space-y-1.5 {{ $class ?? '' }}">
    <label for="{{ $id }}" class="block text-xs font-semibold text-ink-300">
        {{ $label }}@if ($required ?? false)<span class="ml-0.5 text-danger-400">*</span>@endif
    </label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type ?? 'text' }}"
        value="{{ ($type ?? 'text') === 'password' ? '' : old($name, $value ?? '') }}"
        @if ($required ?? false) required @endif
        @isset($placeholder) placeholder="{{ $placeholder }}" @endisset
        @isset($autocomplete) autocomplete="{{ $autocomplete }}" @endisset
        @isset($inputmode) inputmode="{{ $inputmode }}" @endisset
        @class([
            'h-11 w-full rounded-lg border bg-ink-900 px-3 text-sm text-ink-100 placeholder:text-ink-500 focus:border-flame-400 focus:outline-none',
            'border-danger-500' => $errors->has($name),
            'border-ink-600' => ! $errors->has($name),
        ])
    >
    @error($name)
        <p class="text-xs font-medium text-danger-400">{{ $message }}</p>
    @else
        @isset($hint)<p class="text-xs text-ink-400">{{ $hint }}</p>@endisset
    @enderror
</div>
