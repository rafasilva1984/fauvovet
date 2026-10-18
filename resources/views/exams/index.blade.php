@extends('layouts.app')
@section('title', 'Exames')

@section('content')
    @php
        $tabs = ['abertos' => 'Em aberto'] + \App\Models\ExamRequest::STATUS;
        $open = collect($counts)->except(['reviewed', 'cancelled'])->sum();
    @endphp
    <x-page-header title="Exames" icon="flask" eyebrow="Clínica" description="Pedidos de exame da clínica por situação: do pedido à revisão do veterinário." />

    <nav aria-label="Situação dos exames" class="mb-4 flex flex-wrap gap-1 rounded-2xl border border-line bg-surface p-1 shadow-card">
        @foreach ($tabs as $key => $label)
            @php
                $n = $key === 'abertos' ? $open : ($counts[$key] ?? 0);
            @endphp
            <a href="{{ route('exams.index', ['situacao' => $key]) }}" @if ($key === $status) aria-current="page" @endif
                class="inline-flex min-h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold {{ $key === $status ? 'bg-brand fv-gradient text-on-brand shadow-glow' : 'hover:bg-surface-2' }}">
                {{ $label }} <span class="tabular-nums {{ $key === $status ? '' : 'text-muted' }}">{{ $n }}</span>
            </a>
        @endforeach
    </nav>

    @if ($list->isEmpty())
        <x-empty-state title="Nenhum exame nesta situação" icon="flask">Peça exames pela ficha do paciente (bloco “Exames”).</x-empty-state>
    @else
        <x-card class="p-1.5">
            <ul class="divide-y divide-line">
                @foreach ($list as $e)
                    @php
                        $days = (int) $e->requested_at->diffInDays(now());
                    @endphp
                    <li>
                        <a href="{{ route('exams.show', $e) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl px-3.5 py-3 transition hover:bg-tint">
                            <x-avatar :name="$e->patient->name" icon="paw" :tone="\App\Support\Species::tone($e->patient->species)" />
                            <span class="min-w-0 flex-1 basis-48">
                                <span class="block font-semibold">{{ $e->patient->name }} · {{ $e->type->name }}</span>
                                <span class="block truncate text-sm text-muted">Pedido em {{ $e->requested_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}{{ $e->isOpen() ? ' · há '.$days.' '.($days === 1 ? 'dia' : 'dias') : '' }}{{ $e->lab_name ? ' · '.$e->lab_name : '' }}</span>
                            </span>
                            @if ($e->out_of_range_count)<x-badge tone="danger">{{ $e->out_of_range_count }} fora da faixa</x-badge>@endif
                            <x-badge :tone="\App\Models\ExamRequest::TONE[$e->status]" :dot="true">{{ \App\Models\ExamRequest::STATUS[$e->status] }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
