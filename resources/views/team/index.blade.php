@extends('layouts.app')
@section('title', 'Equipe')

@section('content')
    <x-page-header title="Equipe" icon="shield" eyebrow="Clínica" description="Pessoas com acesso a esta clínica, seus papéis e convites pendentes." />

    @if (session('invite_link'))
        <x-card class="mb-6 border-brand/40" x-data="copyLink">
            <h2 class="text-lg font-bold tracking-tight [overflow-wrap:anywhere]">Link de convite para {{ session('invite_email') }}</h2>
            <p class="mt-1 text-sm text-muted">Este link aparece <strong>só agora</strong>. Envie para a pessoa por um canal de sua confiança (WhatsApp, e-mail). Ele vale por {{ \App\Models\Invitation::TTL_DAYS }} dias e funciona uma única vez.</p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <label for="invite-link" class="sr-only">Link de convite</label>
                <input id="invite-link" x-ref="link" type="text" readonly size="1" value="{{ session('invite_link') }}" class="min-h-11 w-full rounded-xl border border-line bg-surface-2 px-3.5 font-mono text-base sm:text-sm text-ink">
                <x-button type="button" variant="primary" class="shrink-0" x-on:click="copy">
                    <x-icon name="copy" /> <span x-text="label">Copiar link</span>
                </x-button>
            </div>
            <p class="mt-3 text-sm"><a class="font-semibold text-link underline underline-offset-4" href="https://wa.me/?text={{ rawurlencode('Convite para a equipe no FauvoVet: '.session('invite_link')) }}" target="_blank" rel="noopener noreferrer">Enviar pelo WhatsApp</a></p>
        </x-card>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-6">
            <section aria-labelledby="membros-titulo">
                <h2 id="membros-titulo" class="mb-3 text-lg font-bold tracking-tight">Membros</h2>
                @if ($members->isEmpty())
                    <x-empty-state title="Nenhum membro" icon="users" />
                @else
                    <x-card class="p-1.5">
                        <ul class="divide-y divide-line">
                            @foreach ($members as $m)
                                <li class="flex flex-col gap-2 rounded-2xl px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <x-avatar :name="$m->user->name" />
                                        <div class="min-w-0">
                                        <p class="truncate font-semibold text-ink">{{ $m->user->name }}@if ($m->user_id === $selfId) <span class="text-sm font-normal text-muted">(você)</span>@endif</p>
                                        <p class="truncate text-sm text-muted">{{ $m->user->email }}</p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1.5 sm:justify-end">
                                        @foreach ($m->roleList() as $role)
                                            <x-badge tone="tint">{{ \App\Tenancy\Roles::label($role) }}</x-badge>
                                        @endforeach
                                        @if ($m->status !== 'active')
                                            <x-badge :tone="$m->status === 'disabled' ? 'danger' : 'warning'" :dot="true">{{ $m->status === 'disabled' ? 'Desativado' : 'Convidado' }}</x-badge>
                                        @endif
                                        @if ($fvCan('members:manage') && $m->user_id !== $selfId && ($isOwner || ! array_intersect($m->roleList(), \App\Tenancy\Roles::ADMIN_ROLES)))
                                            <a href="{{ route('team.edit', $m) }}" class="ml-1 inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-link underline underline-offset-4">Editar<span class="sr-only"> acesso de {{ $m->user->name }}</span></a>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </section>

            @if ($fvCan('members:manage'))
                <section aria-labelledby="convites-titulo">
                    <h2 id="convites-titulo" class="mb-3 text-lg font-bold tracking-tight">Convites pendentes</h2>
                    @if ($invitations->isEmpty())
                        <p class="text-sm text-muted">Nenhum convite pendente.</p>
                    @else
                        <x-card class="p-1.5">
                            <ul class="divide-y divide-line">
                                @foreach ($invitations as $inv)
                                    <li class="flex flex-col gap-2 rounded-2xl px-3.5 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-warning-bg text-warning"><x-icon name="mail" /></span>
                                            <div class="min-w-0">
                                            <p class="truncate font-medium">{{ $inv->email }}</p>
                                            <p class="text-sm text-muted">{{ collect($inv->roleList())->map(fn ($r) => \App\Tenancy\Roles::label($r))->join(', ') }} · expira em {{ $inv->expires_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · por {{ $inv->inviter->name }}</p>
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('team.invite.revoke', $inv) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="secondary">Cancelar<span class="sr-only"> convite de {{ $inv->email }}</span></x-button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </x-card>
                        <p class="mt-2 text-xs text-muted">Perdeu o link? Convide o mesmo e-mail de novo: um link novo é gerado e o anterior deixa de funcionar.</p>
                    @endif
                </section>
            @endif
        </div>

        @if ($fvCan('members:manage'))
            <section aria-labelledby="convidar-titulo" id="convidar">
                <x-card>
                    <h2 id="convidar-titulo" class="text-lg font-bold tracking-tight">Convidar pessoa</h2>
                    <p class="mb-4 mt-1 text-sm text-muted">Geramos um link de uso único para você enviar. Quem já tem conta no FauvoVet entra com ela; quem não tem cria a senha ao abrir o link.</p>
                    <form method="POST" action="{{ route('team.invite') }}" class="flex flex-col gap-4" novalidate>
                        @csrf
                        <x-field name="email" label="E-mail da pessoa" type="email" autocomplete="off" inputmode="email" required />
                        @include('team._roles', ['grantable' => $grantable, 'selected' => []])
                        <x-button><x-icon name="mail" /> Gerar link de convite</x-button>
                    </form>
                </x-card>
            </section>
        @endif
    </div>
@endsection
