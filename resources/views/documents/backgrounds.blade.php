@extends('layouts.app')
@section('title', 'Fundos de documento')

@section('content')
    <x-page-header title="Fundos de documento" icon="palette" eyebrow="Configurações"
        description="Envie o papel timbrado e o receituário que a clínica já usa (PDF, Word, JPG ou PNG). Assim que enviados, viram o fundo padrão: receituário nas receitas, papel timbrado nos demais documentos." />
    @if ($errors->any())<x-alert class="mb-4">{{ $errors->first() }}</x-alert>@endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach (\App\Models\DocumentBackground::KINDS as $kind => $label)
            @php($bg = $current[$kind] ?? null)
            <x-card class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold">{{ $label }}</h2>
                    @if ($bg)<x-badge tone="success" :dot="true">Em uso</x-badge>@else<x-badge tone="neutral">Não enviado</x-badge>@endif
                </div>
                <p class="text-sm text-muted">
                    @if ($kind === 'prescription')
                        Fundo padrão das <strong class="text-ink">receitas</strong>. Se não houver receituário, as receitas usam o papel timbrado.
                    @else
                        Fundo padrão de <strong class="text-ink">atestados, orientações, encaminhamentos e termos</strong>. Sem papel timbrado, o documento usa o receituário (se houver) ou o cabeçalho padrão do FauvoVet.
                    @endif
                </p>

                @if ($bg)
                    <div class="rounded-2xl bg-surface-2 p-3 text-sm">
                        <p class="font-semibold break-words">{{ $bg->source_name }}</p>
                        <p class="text-xs text-muted">{{ $bg->sizeLabel() }} · enviado em {{ $bg->uploaded_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
                        @if ($bg->conversion_notes)
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-warning">
                                @foreach (explode("\n", $bg->conversion_notes) as $note)<li>{{ $note }}</li>@endforeach
                            </ul>
                        @endif
                        <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                            <a href="{{ route('document-backgrounds.preview', $bg) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-semibold text-link underline underline-offset-4">Ver prévia (PDF)<span class="sr-only"> do {{ mb_strtolower($label) }}</span></a>
                            <a href="{{ route('document-backgrounds.original', $bg) }}" class="inline-flex min-h-11 items-center font-semibold text-link underline underline-offset-4">Baixar original<span class="sr-only"> do {{ mb_strtolower($label) }}</span></a>
                        </p>
                    </div>

                    <form method="POST" action="{{ route('document-backgrounds.update', $bg) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-semibold">Margens do texto sobre o fundo (mm)</p>
                        <div class="grid grid-cols-3 gap-3">
                            <x-field name="margin_top" :id="'mt-'.$kind" label="Superior" type="number" inputmode="numeric" min="5" max="150" :value="$bg->margin_top" required />
                            <x-field name="margin_bottom" :id="'mb-'.$kind" label="Inferior" type="number" inputmode="numeric" min="5" max="150" :value="$bg->margin_bottom" required />
                            <x-field name="margin_side" :id="'ms-'.$kind" label="Laterais" type="number" inputmode="numeric" min="5" max="60" :value="$bg->margin_side" required />
                        </div>
                        <p class="text-xs text-muted">Aumente a superior se o texto encostar no logotipo; a inferior, se encostar no rodapé. Confira na prévia.</p>
                        <x-button variant="secondary">Salvar margens</x-button>
                    </form>
                @endif

                <form method="POST" action="{{ route('document-backgrounds.store') }}" enctype="multipart/form-data" class="flex flex-col gap-3 border-t border-line pt-4" novalidate>
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <div class="flex flex-col gap-1.5">
                        <label for="fundo-{{ $kind }}" class="text-sm font-semibold">{{ $bg ? 'Substituir '.mb_strtolower($label) : 'Enviar '.mb_strtolower($label) }}</label>
                        <input id="fundo-{{ $kind }}" type="file" name="file" required accept=".pdf,.docx,.jpg,.jpeg,.png" aria-describedby="fundo-{{ $kind }}-dica"
                            class="block w-full rounded-xl border border-line bg-surface text-base sm:text-sm text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:bg-tint file:px-4 file:font-semibold file:text-link">
                        <p id="fundo-{{ $kind }}-dica" class="text-xs text-muted">PDF (usamos a 1ª página), Word DOCX (cabeçalho, rodapé e imagens de fundo) ou imagem JPG/PNG da folha inteira. Até 10 MB.</p>
                    </div>
                    <x-button :variant="$bg ? 'secondary' : 'primary'">{{ $bg ? 'Enviar e substituir' : 'Enviar '.mb_strtolower($label) }}</x-button>
                </form>

                @if ($bg)
                    <details>
                        <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-muted hover:text-ink">Parar de usar o {{ mb_strtolower($label) }}</summary>
                        <form method="POST" action="{{ route('document-backgrounds.retire', $bg) }}" class="mt-2 flex flex-col gap-2">
                            @csrf
                            <p class="text-sm text-muted">Os próximos documentos deixam de usar este fundo. Os já emitidos não mudam.</p>
                            <x-button variant="secondary">Remover do uso</x-button>
                        </form>
                    </details>
                @endif
            </x-card>
        @endforeach
    </div>

    <x-card class="mt-6 text-sm">
        <h2 class="mb-2 font-bold">Como funciona</h2>
        <ul class="list-disc space-y-1 pl-5 text-muted">
            <li>O texto do documento é impresso <strong class="text-ink">por cima</strong> do fundo, em todas as páginas, no tamanho A4. O cabeçalho padrão do FauvoVet sai do PDF.</li>
            <li>Cada <a class="font-semibold text-link underline underline-offset-4" href="{{ route('document-templates.index') }}">modelo de documento</a> tem o campo <strong class="text-ink">Fundo</strong> (automático, papel timbrado, receituário ou sem fundo); na emissão dá para trocar.</li>
            <li>O <strong class="text-ink">original</strong> fica guardado sem alteração. Word é convertido sem o Microsoft Word: posições podem variar — confira a prévia; se ficar diferente, envie o mesmo timbre em PDF.</li>
            <li>Substituir ou remover um fundo <strong class="text-ink">não altera</strong> documentos já emitidos.</li>
        </ul>
    </x-card>
@endsection
