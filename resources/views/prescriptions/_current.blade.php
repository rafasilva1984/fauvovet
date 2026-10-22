{{-- Medicamentos em uso ($current): itens de receitas válidas com duração em curso ou uso contínuo. --}}
@if ($current->isEmpty())
    <p class="text-sm text-muted">Nenhum medicamento em uso pelas receitas emitidas.</p>
@else
    <ul class="flex flex-col gap-2" data-testid="rx-current">
        @foreach ($current as $it)
            <li class="rounded-xl border border-line px-3 py-2 text-sm">
                <a href="{{ route('documents.show', $it->document_id) }}" class="font-semibold text-link underline underline-offset-4">{{ $it->name }}</a>
                <span class="block text-muted">{{ $it->dose_text }} · {{ mb_strtolower($it->route) }} · {{ mb_strtolower($it->frequency) }}</span>
                <span class="block text-xs text-muted">Desde {{ $it->starts_on->format('d/m/Y') }} · {{ $it->continuous ? 'uso contínuo' : ($it->ends_on ? 'até '.$it->ends_on->format('d/m/Y') : '') }}@if ($it->controlled) · controle especial @endif</span>
            </li>
        @endforeach
    </ul>
@endif
