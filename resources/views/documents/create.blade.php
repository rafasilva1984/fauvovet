@extends('layouts.app')
@section('title', 'Novo documento — '.$patient->name)

@section('content')
    <x-page-header icon="list" eyebrow="Documentos" :title="'Novo documento para '.$patient->name" :description="$encounter ? 'Vinculado ao atendimento de '.$encounter->started_at->timezone('America/Sao_Paulo')->format('d/m/Y') : 'Sem atendimento vinculado: os campos do atendimento ficam em branco.'" />

    @if ($needsCrmv)
        <x-alert tone="warning" class="mb-6">Seu CRMV não está cadastrado. Receitas, atestados e encaminhamentos exigem o CRMV: <a class="font-semibold underline underline-offset-4" href="{{ route('profile.edit') }}">abrir Meu perfil</a>.</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <nav aria-label="Modelos" class="flex flex-col gap-2">
            <h2 class="mb-1 text-sm font-bold uppercase tracking-[0.12em] text-muted">1. Escolha o modelo</h2>
            @forelse ($templates as $t)
                @php($current = $template && $template->id === $t->id)
                <a href="{{ route('documents.create', array_filter(['patient' => $patient, 'modelo' => $t->id, 'atendimento' => $encounter?->id])) }}" @if ($current) aria-current="true" @endif
                    class="flex min-h-12 items-center gap-3 rounded-2xl border px-3 py-2 text-sm transition {{ $current ? 'border-transparent bg-brand fv-gradient text-on-brand shadow-glow' : 'border-line bg-surface shadow-card hover:border-brand/40 hover:bg-tint' }}">
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ $t->name }}</span>
                        <span class="block text-xs {{ $current ? 'opacity-90' : 'text-muted' }}">{{ \App\Models\DocumentTemplate::CATEGORIES[$t->category] }}</span>
                    </span>
                </a>
            @empty
                <p class="text-sm text-muted">Nenhum modelo disponível para o seu papel.</p>
            @endforelse
        </nav>

        <section aria-labelledby="revisar">
            <h2 id="revisar" class="mb-3 text-sm font-bold uppercase tracking-[0.12em] text-muted">2. Revise e emita</h2>
            @if (! $template)
                <x-empty-state title="Escolha um modelo à esquerda" icon="list">O texto vem preenchido com os dados do paciente{{ $encounter ? ' e do atendimento' : '' }}; você ajusta antes de emitir.</x-empty-state>
            @else
                <x-card>
                    <form method="POST" action="{{ route('documents.store', $patient) }}" class="flex flex-col gap-4" novalidate>
                        @csrf
                        <input type="hidden" name="template_id" value="{{ $template->id }}">
                        @if ($encounter)<input type="hidden" name="encounter_id" value="{{ $encounter->id }}">@endif
                        @if ($errors->any())<x-alert>{{ $errors->first('body') ?: $errors->first() }}</x-alert>@endif
                        <x-field name="title" label="Título" :value="$template->title" required />
                        <x-textarea name="body" label="Texto do documento" :value="$prefilled" rows="16" hint="Substitua os trechos entre [colchetes]. O cabeçalho da clínica, os dados do paciente e do tutor e a assinatura entram automaticamente no PDF." />
                        @if ($errors->has('keep_brackets'))
                            <label class="flex min-h-11 items-center gap-3 rounded-xl border border-warning/30 bg-warning-bg px-3 text-sm text-warning">
                                <input type="checkbox" name="keep_brackets" value="1" class="size-4">
                                <span>Os colchetes fazem parte do texto: emitir mesmo assim.</span>
                            </label>
                        @endif
                        <div class="flex flex-col gap-3 rounded-2xl bg-tint p-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-muted">Depois de emitido, o documento <strong class="text-ink">não pode ser editado</strong>; se necessário, anule e emita outro.</p>
                            <x-button class="shrink-0"><x-icon name="check-circle" :size="18" /> Emitir PDF</x-button>
                        </div>
                    </form>
                </x-card>
            @endif
        </section>
    </div>
@endsection
