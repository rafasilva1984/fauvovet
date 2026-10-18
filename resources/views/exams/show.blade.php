@extends('layouts.app')
@section('title', $exam->type->name.' · '.$exam->patient->name)

@section('content')
    @php
        $tz = 'America/Sao_Paulo';
        $S = \App\Models\ExamRequest::STATUS;
        $timeline = [
            'requested' => $exam->requested_at,
            'collected' => $exam->collected_at,
            'in_analysis' => $exam->sent_at,
            'received' => $exam->received_at,
            'reviewed' => $exam->reviewed_at,
        ];
    @endphp
    <x-page-header :title="$exam->type->name" icon="flask" eyebrow="Exame"
        :description="$exam->patient->name.' · pedido por '.($exam->requester?->name ?? '—').' em '.$exam->requested_at->timezone($tz)->format('d/m/Y H:i')">
        <a href="{{ route('patients.show', $exam->patient) }}" class="inline-flex min-h-11 items-center rounded-xl border border-line bg-surface px-4 text-sm font-semibold shadow-card hover:bg-tint">Ficha de {{ $exam->patient->name }}</a>
    </x-page-header>
    @if ($errors->any())<x-alert class="mb-4">{{ $errors->first() }}</x-alert>@endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <x-card>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-bold">Situação</h2>
                    <x-badge :tone="\App\Models\ExamRequest::TONE[$exam->status]" :dot="true">{{ $S[$exam->status] }}</x-badge>
                </div>
                <ol class="grid grid-cols-1 gap-2 sm:grid-cols-5" aria-label="Etapas do exame">
                    @foreach ($timeline as $step => $at)
                        <li class="rounded-xl border p-2.5 text-sm {{ $at ? 'border-brand/40 bg-tint' : 'border-line text-muted' }}">
                            <span class="block font-semibold">{{ $S[$step] }}</span>
                            <span class="block text-xs tabular-nums">{{ $at ? $at->timezone($tz)->format('d/m/Y H:i') : '—' }}</span>
                        </li>
                    @endforeach
                </ol>
                @if ($exam->status === 'cancelled')
                    <p class="mt-3 text-sm text-danger">Cancelado em {{ $exam->cancelled_at?->timezone($tz)->format('d/m/Y H:i') }}: {{ $exam->cancel_reason }}</p>
                @endif
                @if ($exam->lab_name || $exam->request_notes)
                    <p class="mt-3 text-sm text-muted">{{ $exam->lab_name ? 'Laboratório: '.$exam->lab_name.'. ' : '' }}{{ $exam->request_notes }}</p>
                @endif
                @if ($fvCan('clinical:write') && $exam->nextSteps() !== [])
                    <form method="POST" action="{{ route('exams.advance', $exam) }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
                        @csrf
                        <x-select name="status" id="exame-etapa" label="Avançar para" :options="collect($exam->nextSteps())->mapWithKeys(fn ($s) => [$s => $S[$s]])->all()" />
                        <x-button variant="secondary">Atualizar situação</x-button>
                    </form>
                @endif
            </x-card>

            @include('exams._values')

            <x-card>
                <h2 class="mb-3 font-bold">Resultado em texto e laudo</h2>
                @if ($exam->result_text)
                    <div class="whitespace-pre-line rounded-xl bg-surface-2 p-3 text-sm">{{ $exam->result_text }}</div>
                @endif
                @if ($exam->resultAttachment)
                    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                        <x-icon name="copy" :size="16" class="text-link" /> {{ $exam->resultAttachment->title }} ({{ strtoupper($exam->resultAttachment->extension) }}, {{ $exam->resultAttachment->sizeLabel() }})
                        <a href="{{ route('attachments.download', ['attachment' => $exam->resultAttachment, 'baixar' => 1]) }}" class="font-semibold text-link underline underline-offset-4">Baixar laudo</a>
                        @if ($exam->resultAttachment->text_status === 'indexed')<x-badge tone="success">Texto pesquisável</x-badge>@elseif ($exam->resultAttachment->text_status)<x-badge tone="neutral">Sem texto pesquisável</x-badge>@endif
                    </p>
                @endif
                @if (! $exam->result_text && ! $exam->resultAttachment)
                    <p class="text-sm text-muted">Ainda sem resultado.</p>
                @endif
                @if ($fvCan('clinical:write') && $exam->isOpen())
                    <form method="POST" action="{{ route('exams.result', $exam) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 border-t border-line pt-4" novalidate>
                        @csrf
                        <x-textarea name="result_text" id="exame-resultado" label="Resultado (texto)" :value="$exam->result_text" rows="5" hint="Valores e observações do laudo. Pode deixar em branco se enviar o arquivo." />
                        <div class="flex flex-col gap-1.5">
                            <label for="exame-laudo" class="text-sm font-semibold">Laudo (PDF, imagem ou DOCX)</label>
                            <input id="exame-laudo" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.docx" class="block w-full rounded-xl border border-line bg-surface text-base sm:text-sm text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:bg-tint file:px-4 file:font-semibold file:text-link">
                        </div>
                        <x-button variant="secondary">Registrar resultado</x-button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-card>
                <h2 class="mb-3 font-bold">Revisão do veterinário</h2>
                @if ($exam->status === 'reviewed')
                    <div class="whitespace-pre-line text-sm">{{ $exam->interpretation }}</div>
                    <p class="mt-3 text-xs text-muted">Revisado e assinado por {{ $exam->reviewer?->name }}{{ $exam->reviewer?->crmv ? ' · '.$exam->reviewer->crmv : '' }} em {{ $exam->reviewed_at?->timezone($tz)->format('d/m/Y H:i') }}. Somente leitura.</p>
                @elseif ($exam->status === 'received' && $fvCan('clinical:sign'))
                    <form method="POST" action="{{ route('exams.review', $exam) }}" class="flex flex-col gap-3" novalidate>
                        @csrf
                        <x-textarea name="interpretation" id="exame-interpretacao" label="Interpretação" rows="5" required />
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="size-4"> Confirmo a revisão (o exame não muda mais)</label>
                        <x-button>Revisar e assinar</x-button>
                    </form>
                @elseif ($exam->status === 'received')
                    <p class="text-sm text-muted">Aguardando revisão de um médico-veterinário.</p>
                @else
                    <p class="text-sm text-muted">A revisão fica disponível quando o resultado for recebido.</p>
                @endif
            </x-card>
            @if ($fvCan('clinical:write') && $exam->isOpen())
                <x-card>
                    <details>
                        <summary class="inline-flex min-h-11 cursor-pointer items-center text-sm font-semibold text-muted hover:text-ink">Cancelar exame</summary>
                        <form method="POST" action="{{ route('exams.cancel', $exam) }}" class="mt-2 flex flex-col gap-3" novalidate>
                            @csrf
                            <x-field name="reason" id="exame-motivo" label="Motivo" required placeholder="Ex.: tutor desistiu" />
                            <x-button variant="secondary">Cancelar exame</x-button>
                        </form>
                    </details>
                </x-card>
            @endif
        </div>
    </div>
@endsection
