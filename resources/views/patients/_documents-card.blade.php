{{-- Documentos emitidos para o paciente. --}}
<x-card>
    <div class="mb-3 flex items-center justify-between gap-2">
        <h3 class="flex items-center gap-2 font-bold"><x-icon name="list" :size="18" class="text-link" /> Documentos</h3>
        @if ($fvCan('clinical:write') && $patient->status === 'active')
            <a href="{{ route('documents.create', $patient) }}" class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-sm font-semibold text-link hover:bg-tint"><x-icon name="plus" :size="16" /> Emitir</a>
        @endif
    </div>
    @include('documents._list', ['documents' => $clinical['documents']])
</x-card>
