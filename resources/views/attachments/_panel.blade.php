{{-- Anexos ($attachments) do paciente ($patient) e, opcionalmente, de um atendimento ($encounter). --}}
@php
    $maxMb = intdiv((int) config('fauvovet.attachments.max_kb'), 1024);
    $fid = 'anexo-'.($encounter?->id ?? $patient->id);
@endphp
<x-card>
    <h3 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="copy" :size="18" class="text-link" /> Anexos</h3>
    @if ($attachments->isEmpty())
        <p class="text-sm text-muted">Nenhum anexo.</p>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($attachments as $att)
                <li class="rounded-xl bg-surface-2 p-2">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @if ($att->isImage())
                            <a href="{{ route('attachments.download', $att) }}" target="_blank" rel="noopener" class="shrink-0">
                                <img src="{{ route('attachments.download', $att) }}" alt="Miniatura de {{ $att->title }}" loading="lazy" width="48" height="48" class="size-12 rounded-lg object-cover ring-1 ring-line">
                            </a>
                        @else
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-surface text-xs font-bold uppercase text-link ring-1 ring-line">{{ $att->extension }}</span>
                        @endif
                        <span class="min-w-0 flex-1 basis-40">
                            <span class="block truncate text-sm font-semibold">{{ $att->title }}</span>
                            <span class="block truncate text-xs text-muted">{{ \App\Models\Attachment::CATEGORIES[$att->category] }} · {{ $att->sizeLabel() }} · {{ $att->created_at->timezone('America/Sao_Paulo')->format('d/m/Y') }} · {{ $att->uploader?->name }}</span>
                        </span>
                        <span class="ml-auto flex shrink-0 gap-1">
                            @if ($att->isImage())
                                <a href="{{ route('attachments.download', $att) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg px-2 text-xs font-semibold text-link hover:bg-tint">Abrir<span class="sr-only"> {{ $att->title }}</span></a>
                            @endif
                            <a href="{{ route('attachments.download', ['attachment' => $att, 'baixar' => 1]) }}" class="inline-flex min-h-11 items-center rounded-lg px-2 text-xs font-semibold text-link hover:bg-tint">Baixar<span class="sr-only"> {{ $att->title }}</span></a>
                        </span>
                    </div>
                    @if ($att->description)<p class="mt-1.5 px-1 text-xs text-muted">{{ $att->description }}</p>@endif
                    @if ($fvCan('clinical:write'))
                        <details class="mt-1 px-1 text-xs">
                            <summary class="inline-flex min-h-8 cursor-pointer items-center text-muted hover:text-ink">Remover da ficha<span class="sr-only"> {{ $att->title }}</span></summary>
                            <form method="POST" action="{{ route('attachments.remove', $att) }}" class="mt-2 flex flex-wrap items-end gap-2" novalidate>
                                @csrf
                                <label for="motivo-{{ $att->id }}" class="sr-only">Motivo da remoção de {{ $att->title }}</label>
                                <input id="motivo-{{ $att->id }}" name="reason" required maxlength="200" placeholder="Motivo (ex.: enviado no paciente errado)" class="min-h-10 flex-1 rounded-lg border border-line bg-surface px-2 text-base sm:text-sm">
                                <x-button variant="secondary" class="min-h-10 text-xs">Remover</x-button>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($fvCan('clinical:write') && $patient->status === 'active')
        <form method="POST" action="{{ route('attachments.store', $patient) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 border-t border-line pt-4" novalidate>
            @csrf
            @if ($encounter)<input type="hidden" name="encounter_id" value="{{ $encounter->id }}">@endif
            @if ($errors->has('file'))<x-alert>{{ $errors->first('file') }}</x-alert>@endif
            <div class="flex flex-col gap-1.5">
                <label for="{{ $fid }}" class="text-sm font-semibold text-ink">Arquivo <span aria-hidden="true" class="text-muted">*</span></label>
                <input id="{{ $fid }}" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,application/pdf,image/jpeg,image/png,image/webp" aria-describedby="{{ $fid }}-hint"
                    class="block w-full rounded-xl border border-line bg-surface text-base sm:text-sm text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:bg-tint file:px-4 file:font-semibold file:text-link">
                <p id="{{ $fid }}-hint" class="text-xs text-muted">PDF, JPG, PNG, WebP ou Word (DOCX), até {{ $maxMb }} MB.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-select name="category" :id="$fid.'-cat'" label="Categoria" :options="\App\Models\Attachment::CATEGORIES" value="exam" />
                <x-field name="title" :id="$fid.'-title'" label="Título" placeholder="Ex.: Hemograma" />
            </div>
            <x-button variant="secondary">Enviar anexo</x-button>
        </form>
    @endif
</x-card>
