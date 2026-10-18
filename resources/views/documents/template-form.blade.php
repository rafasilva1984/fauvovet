@extends('layouts.app')
@section('title', $template->exists ? 'Editar '.$template->name : 'Novo modelo')

@section('content')
    <x-page-header icon="list" eyebrow="Modelos de documento" :title="$template->exists ? 'Editar '.$template->name : 'Novo modelo'" :description="$template->exists ? 'Versão atual: '.$template->version.'. Salvar alterações de texto cria a versão '.($template->version + 1).'.' : null" />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <x-card>
            @if ($template->source_name)
                <x-alert tone="info" class="mb-4">
                    <strong>Importado de {{ $template->source_name }}</strong> em {{ $template->imported_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.
                    O texto foi convertido; formatação, tabelas e imagens do original não. Revise antes de ativar.
                    <a href="{{ route('document-templates.original', $template) }}" class="font-semibold underline underline-offset-4">Baixar original</a>
                    @if (session('import_suggestions'))
                        <span class="mt-2 block">Variáveis sugeridas: {{ implode('; ', session('import_suggestions')) }}.</span>
                    @endif
                </x-alert>
            @endif
            <form method="POST" action="{{ $template->exists ? route('document-templates.update', $template) : route('document-templates.store') }}" class="flex flex-col gap-4" novalidate>
                @csrf
                @if ($template->exists) @method('PUT') @endif
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Nome do modelo" :value="$template->name" required />
                    <x-select name="category" label="Categoria" :options="\App\Models\DocumentTemplate::CATEGORIES" :value="$template->category" required />
                </div>
                <x-field name="title" label="Título no documento" :value="$template->title" required />
                <x-select name="background" id="modelo-fundo" label="Fundo do documento" :options="\App\Models\DocumentBackground::options($backgrounds)" :value="$template->background ?? 'auto'"
                    hint="Papel timbrado e receituário são enviados em Configurações › Fundos de documento. Na emissão ainda dá para trocar." />
                <x-textarea name="body" label="Texto" :value="$template->body" rows="16" hint="Use as variáveis da lista ao lado, exatamente como aparecem. Trechos entre [colchetes] servem de lembrete para completar na hora de emitir." />
                @if ($template->exists)
                    <label class="flex min-h-11 items-center gap-2 text-sm">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" class="size-4" @checked(old('active', $template->active))>
                        <span>Ativo (aparece ao emitir documentos)</span>
                    </label>
                @endif
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-button>Salvar modelo</x-button>
                    <a href="{{ route('document-templates.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Voltar</a>
                </div>
            </form>
        </x-card>
        <aside>
            <x-card>
                <h2 class="mb-2 font-bold">Variáveis disponíveis</h2>
                <p class="mb-3 text-xs text-muted">Só estas são aceitas; qualquer outra é recusada ao salvar.</p>
                <dl class="flex flex-col gap-1.5 text-xs">
                    @foreach (\App\Documents\Placeholders::LIST as $key => $label)
                        <div><dt class="font-mono font-semibold text-link">&#123;&#123;{{ $key }}&#125;&#125;</dt><dd class="text-muted">{{ $label }}</dd></div>
                    @endforeach
                </dl>
            </x-card>
        </aside>
    </div>
@endsection
