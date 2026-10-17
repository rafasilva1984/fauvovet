{{-- Lista de documentos emitidos ($documents). --}}
@if ($documents->isEmpty())
    <p class="text-sm text-muted">Nenhum documento emitido.</p>
@else
    <ul class="flex flex-col gap-1.5">
        @foreach ($documents as $doc)
            <li>
                <a href="{{ route('documents.show', $doc) }}" class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm transition hover:bg-tint">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-tint text-link"><x-icon name="list" :size="16" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold {{ $doc->isVoided() ? 'text-muted line-through' : '' }}">{{ $doc->title }}</span>
                        <span class="block text-xs text-muted">{{ $doc->issued_at->timezone('America/Sao_Paulo')->format('d/m/Y') }} · {{ $doc->issuer?->name }}</span>
                    </span>
                    @if ($doc->isVoided())<x-badge tone="danger">Anulado</x-badge>@endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
