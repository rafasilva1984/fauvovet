@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'required' => false])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $error = $errors->first($name);
    $describedBy = trim(($hint && ! $error ? "{$id}-hint " : '').($error ? "{$id}-err" : ''));
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-sm font-medium text-ink">
        {{ $label }}@if ($required)<span aria-hidden="true" class="text-muted"> *</span>@endif
    </label>
    <div class="relative">
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($required) required @endif
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['class', 'id'])->merge(['class' => 'min-h-11 w-full rounded-xl border bg-surface px-3.5 text-base text-ink placeholder:text-muted transition focus:border-brand focus:outline-none focus:ring-3 focus:ring-ring/40 '.($error ? 'border-danger' : 'border-line').(isset($trailing) ? ' pr-12' : '')]) }} />
        @isset($trailing)
            <div class="absolute inset-y-0 right-1 flex items-center">{{ $trailing }}</div>
        @endisset
    </div>
    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-err" class="text-xs font-medium text-danger">{{ $error }}</p>
    @endif
</div>
