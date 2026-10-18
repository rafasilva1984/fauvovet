@extends('layouts.app')
@section('title', 'Importar modelo')

@section('content')
    <x-page-header title="Importar modelo" icon="copy" eyebrow="Modelos de documento"
        description="Traga um documento que a clínica já usa (Word DOCX ou PDF com texto). O original fica guardado; o texto vira um modelo editável." />
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <x-card>
            <form method="POST" action="{{ route('document-templates.import.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4" novalidate>
                @csrf
                @error('file')<x-alert>{{ $message }}</x-alert>@enderror
                <div class="flex flex-col gap-1.5">
                    <label for="modelo-arquivo" class="text-sm font-semibold text-ink">Arquivo <span aria-hidden="true" class="text-muted">*</span></label>
                    <input id="modelo-arquivo" type="file" name="file" required accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-describedby="modelo-arquivo-dica"
                        class="block w-full rounded-xl border border-line bg-surface text-base sm:text-sm text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:bg-tint file:px-4 file:font-semibold file:text-link">
                    <p id="modelo-arquivo-dica" class="text-xs text-muted">DOCX ou PDF com texto, até 10 MB. PDF digitalizado (foto do papel) não tem texto para converter.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Nome do modelo" required />
                    <x-select name="category" label="Categoria" :options="\App\Models\DocumentTemplate::CATEGORIES" value="consent" required />
                </div>
                <x-button>Importar para revisão</x-button>
            </form>
        </x-card>
        <aside>
            <x-card class="flex flex-col gap-2 text-sm">
                <h2 class="font-bold">O que acontece</h2>
                <ul class="list-disc space-y-1 pl-5 text-muted">
                    <li>O <strong class="text-ink">original</strong> fica guardado sem alteração (dá para baixar depois).</li>
                    <li>O <strong class="text-ink">texto</strong> vira o corpo do modelo. Negrito, tabelas, imagens e timbre <strong class="text-ink">não</strong> são convertidos.</li>
                    <li>Lacunas como “Paciente: ______” viram variáveis (&#123;&#123;paciente.nome&#125;&#125;). As outras viram [completar].</li>
                    <li>O modelo nasce <strong class="text-ink">desativado</strong>: revise e ative para usar.</li>
                </ul>
            </x-card>
        </aside>
    </div>
@endsection
