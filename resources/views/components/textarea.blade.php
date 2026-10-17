@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 3, 'required' => false])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $error = $errors->first($name);
    $describedBy = trim(($hint && ! $error ? "{$id}-hint " : '').($error ? "{$id}-err" : ''));
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-sm font-semibold text-ink">
        {{ $label }}@if ($required)<span aria-hidden="true" class="text-muted"> *</span>@endif
    </label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['class', 'id'])->merge(['class' => 'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-base text-ink shadow-[0_1px_2px_rgb(13_27_33/4%)] placeholder:text-muted/80 transition hover:border-brand/40 focus:border-brand focus:outline-none focus:ring-4 focus:ring-ring/25 '.($error ? 'border-danger' : 'border-line')]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-err" class="text-xs font-medium text-danger">{{ $error }}</p>
    @endif
</div>
