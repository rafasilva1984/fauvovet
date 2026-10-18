{{-- Peso com gráfico. --}}
<x-card>
    <h3 class="mb-3 flex items-center gap-2 font-bold"><x-icon name="heart" :size="18" class="text-link" /> Peso</h3>
    @include('patients._weight-chart', ['weights' => $clinical['weights']])
</x-card>
