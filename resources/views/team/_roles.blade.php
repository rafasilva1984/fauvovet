{{-- Seleção de papéis. $grantable: papéis que quem está logado pode conceder; $selected: marcados. --}}
@php($selected = old('roles', $selected ?? []))
<fieldset @if ($errors->has('roles')) aria-describedby="roles-err" @endif>
    <legend class="mb-2 text-sm font-medium text-ink">Papéis <span aria-hidden="true" class="text-muted">*</span></legend>
    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ($grantable as $role => $label)
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2 text-sm transition has-[:checked]:border-brand has-[:checked]:bg-surface-2 has-[:focus-visible]:ring-3 has-[:focus-visible]:ring-ring">
                <input type="checkbox" name="roles[]" value="{{ $role }}" class="size-4 accent-[var(--fv-primary)]" @checked(in_array($role, (array) $selected, true)) @disabled($disabled ?? false)>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>
    @if ($errors->has('roles'))
        <p id="roles-err" class="mt-1.5 text-xs font-medium text-danger">{{ $errors->first('roles') }}</p>
    @endif
</fieldset>
