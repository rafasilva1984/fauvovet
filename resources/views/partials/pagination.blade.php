{{-- Paginação simples e acessível (anterior/próxima), sem dependências. --}}
@if ($paginator->hasPages())
    <nav aria-label="Paginação" class="flex items-center justify-between gap-3 text-sm">
        @if ($paginator->onFirstPage())
            <span class="inline-flex min-h-11 items-center rounded-xl border border-line px-4 text-muted opacity-60" aria-disabled="true">Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex min-h-11 items-center rounded-xl border border-line px-4 font-semibold hover:bg-surface-2">Anterior</a>
        @endif
        <span class="text-muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex min-h-11 items-center rounded-xl border border-line px-4 font-semibold hover:bg-surface-2">Próxima</a>
        @else
            <span class="inline-flex min-h-11 items-center rounded-xl border border-line px-4 text-muted opacity-60" aria-disabled="true">Próxima</span>
        @endif
    </nav>
@endif
