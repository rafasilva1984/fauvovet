@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 3])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $error = $errors->first($name);
    $describedBy = trim(($hint && ! $error ? "{$id}-hint " : '').($error ? "{$id}-err" : ''));
@endphp
<div {{ $attributes->only('class')->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label for="{{ $id }}" class="text-sm font-medium text-ink">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['class', 'id'])->merge(['class' => 'w-full rounded-xl border bg-surface px-3.5 py-2.5 text-base text-ink placeholder:text-muted transition focus:border-brand focus:outline-none focus:ring-3 focus:ring-ring/40 '.($error ? 'border-danger' : 'border-line')]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint && ! $error)
        <p id="{{ $id }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-err" class="text-xs font-medium text-danger">{{ $error }}</p>
    @endif
</div>
