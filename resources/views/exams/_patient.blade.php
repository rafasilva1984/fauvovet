{{-- Exames na ficha do paciente ($exams, $examTypes) — equipe clínica. --}}
<x-card>
    <h3 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="flask" :size="18" class="text-link" /> Exames</h3>
    @if ($exams->isEmpty())
        <p class="text-sm text-muted">Nenhum exame pedido.</p>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($exams as $e)
                <li>
                    <a href="{{ route('exams.show', $e) }}" class="flex flex-wrap items-center gap-2 rounded-xl bg-surface-2 p-2.5 text-sm hover:bg-tint">
                        <span class="min-w-0 flex-1 font-semibold">{{ $e->type->name }}<span class="block text-xs font-normal text-muted">{{ $e->requested_at->timezone('America/Sao_Paulo')->format('d/m/Y') }}</span></span>
                        <x-badge :tone="\App\Models\ExamRequest::TONE[$e->status]" :dot="true">{{ \App\Models\ExamRequest::STATUS[$e->status] }}</x-badge>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
    @if ($fvCan('clinical:write') && $patient->status === 'active' && $examTypes->isNotEmpty())
        <form method="POST" action="{{ route('exams.store', $patient) }}" class="mt-4 flex flex-col gap-3 border-t border-line pt-4" novalidate>
            @csrf
            <x-select name="exam_type_id" id="exame-tipo" label="Pedir exame" :options="$examTypes->pluck('name', 'id')->all()" placeholder="Escolha o exame" required />
            <x-field name="lab_name" id="exame-lab" label="Laboratório" placeholder="Ex.: na própria clínica" />
            <x-field name="request_notes" id="exame-obs" label="Observações do pedido" placeholder="Ex.: jejum de 8 h" />
            <x-button variant="secondary">Solicitar exame</x-button>
        </form>
    @endif
</x-card>
