@extends('layouts.app')
@section('title', 'Acesso de '.$member->user->name)

@section('content')
    <x-page-header :title="$member->user->name" :description="$member->user->email" />

    @if ($isSelf || $locked)
        <x-alert tone="info" class="mb-6">
            {{ $isSelf ? 'Você não pode alterar o seu próprio acesso. Peça a outro titular.' : 'Somente o titular pode alterar o acesso de quem tem papel de Titular ou Gestão.' }}
        </x-alert>
    @endif

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('team.update', $member) }}" class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PUT')
            @if ($errors->any())
                <x-alert>Revise os campos destacados.</x-alert>
            @endif

            @include('team._roles', ['grantable' => $grantable, 'selected' => $member->roleList(), 'disabled' => $isSelf || $locked])

            <fieldset @if ($errors->has('units') || $errors->has('units.*')) aria-describedby="units-err" @endif>
                <legend class="mb-1 text-sm font-medium text-ink">Unidades em que pode trabalhar</legend>
                <p class="mb-2 text-xs text-muted">Nenhuma marcada = todas as unidades da clínica.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($units as $unit)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-surface-2 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                            <input type="checkbox" name="units[]" value="{{ $unit->id }}" class="size-4" @checked(in_array($unit->id, old('units', $restricted), true)) @disabled($isSelf || $locked)>
                            <span>{{ $unit->name }}</span>
                        </label>
                    @endforeach
                </div>
                @if ($errors->has('units') || $errors->has('units.*'))
                    <p id="units-err" class="mt-1.5 text-xs font-medium text-danger">{{ $errors->first('units') ?: $errors->first('units.*') }}</p>
                @endif
            </fieldset>

            <fieldset>
                <legend class="mb-2 text-sm font-medium text-ink">Situação</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (['active' => 'Ativo — pode entrar nesta clínica', 'disabled' => 'Desativado — perde o acesso na hora'] as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-surface-2 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                            <input type="radio" name="status" value="{{ $value }}" class="size-4" @checked(old('status', $member->status === 'disabled' ? 'disabled' : 'active') === $value) @disabled($isSelf || $locked)>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex flex-col gap-3 sm:flex-row">
                @unless ($isSelf || $locked)
                    <x-button>Salvar acesso</x-button>
                @endunless
                <a href="{{ route('team.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-line px-4 text-sm font-semibold text-ink hover:bg-surface-2">Voltar à equipe</a>
            </div>
        </form>
    </x-card>
@endsection
