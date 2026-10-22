@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'hint' => null, 'placeholder' => null])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $key = str_replace(['[', ']'], ['.', ''], $name); // "rows[0][route]" → "rows.0.route" (old/erros)
    $error = $errors->first($key);
    $current = (string) old($key, $value);
    $describedBy = trim(($hint && ! $error ? "{$id}-hint " : '').($error ? "{$id}-err" : ''));
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-sm font-semibold text-ink">
        {{ $label }}@if ($required)<span aria-hidden="true" class="text-muted"> *</span>@endif
    </label>
    <select id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['class', 'id'])->merge(['class' => 'min-h-11 w-full rounded-xl border bg-surface px-3 text-base text-ink shadow-[0_1px_2px_rgb(13_27_33/4%)] transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-4 focus:ring-ring/25 '.($error ? 'border-danger' : 'border-line')]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-err" class="text-xs font-medium text-danger">{{ $error }}</p>
    @endif
</div>
