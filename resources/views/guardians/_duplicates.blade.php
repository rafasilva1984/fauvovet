@if (session('duplicates'))
    <x-alert tone="warning">
        <p class="font-semibold">Possível cadastro repetido nesta clínica:</p>
        <ul class="mt-2 list-disc pl-5">
            @foreach (session('duplicates') as $dup)
                <li>
                    @if (($dup['type'] ?? 'guardian') === 'patient')
                        <a class="font-semibold underline underline-offset-4" href="{{ route('patients.show', $dup['id']) }}">{{ $dup['name'] }}</a> (paciente com mesmo nome e espécie deste responsável)
                    @else
                        <a class="font-semibold underline underline-offset-4" href="{{ route('guardians.show', $dup['id']) }}">{{ $dup['name'] }}</a> (mesmo CPF/CNPJ ou telefone)
                    @endif
                </li>
            @endforeach
        </ul>
        <label class="mt-3 flex min-h-11 items-center gap-3">
            <input type="checkbox" name="confirm_duplicate" value="1" class="size-4">
            <span>Conferi: não é a mesma pessoa/animal. Cadastrar mesmo assim.</span>
        </label>
    </x-alert>
@endif
