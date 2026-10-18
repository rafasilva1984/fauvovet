@extends('layouts.app')
@section('title', 'Configurações')

@section('content')
    <x-page-header title="Configurações" eyebrow="Clínica" icon="settings" description="Ajustes que valem para toda a clínica. Você vê aqui só o que o seu papel permite alterar." />

    <div class="flex flex-col gap-8">
        @foreach ($sections as $label => $items)
            <section aria-labelledby="cfg-{{ $loop->index }}">
                <h2 id="cfg-{{ $loop->index }}" class="mb-3 text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ $label }}</h2>
                <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ route($item['route']) }}" class="group flex h-full items-start gap-4 rounded-2xl border border-line bg-surface p-4 shadow-card transition hover:border-brand/40 hover:bg-tint">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-tint text-link transition group-hover:bg-surface"><x-icon :name="$item['icon']" :size="20" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $item['label'] }}</span>
                                    <span class="mt-0.5 block text-sm text-muted">{{ $item['hint'] }}</span>
                                </span>
                                <x-icon name="chevron-right" :size="18" class="mt-1 shrink-0 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
