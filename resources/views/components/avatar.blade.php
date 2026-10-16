{{-- Avatar com iniciais (ou ícone) em um dos 8 tons AA. Tom estável por nome, ou definido por $tone. --}}
@props(['name' => '', 'size' => 'md', 'icon' => null, 'tone' => null])
@php
    $words = preg_split('/\s+/', trim(preg_replace('/\(.*?\)/', '', (string) $name))) ?: [];
    $initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    $t = $tone ?? (crc32(mb_strtolower((string) $name)) % 8);
    $sizes = ['sm' => 'size-8 text-xs rounded-xl', 'md' => 'size-11 text-sm rounded-2xl', 'lg' => 'size-14 text-lg rounded-2xl', 'xl' => 'size-16 text-xl rounded-[1.25rem]'];
@endphp
<span aria-hidden="true" {{ $attributes->merge(['class' => 'fv-tone fv-tone-'.$t.' inline-flex shrink-0 items-center justify-center font-bold '.$sizes[$size]]) }}>
    @if ($icon)<x-icon :name="$icon" :size="['sm' => 16, 'md' => 20, 'lg' => 26, 'xl' => 30][$size]" />@else{{ $initials }}@endif
</span>
