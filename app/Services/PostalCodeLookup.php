<?php

namespace App\Services;

use App\Support\BrazilianStates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta de CEP no serviço público ViaCEP, feita pelo SERVIDOR (o navegador só fala com o FauvoVet: a CSP
 * continua bloqueando conexões externas). Só o CEP sai do sistema — nenhum dado de tutor ou paciente.
 * Resultado em cache por 30 dias; falha ou indisponibilidade → null (o usuário preenche à mão).
 */
final class PostalCodeLookup
{
    /** @return array{postal_code: string, street: string, district: string, city: string, state: string}|null */
    public function find(string $cep): ?array
    {
        if (! preg_match('/^\d{8}$/', $cep)) {
            return null;
        }

        return Cache::remember("cep:{$cep}", now()->addDays(30), function () use ($cep) {
            try {
                $res = Http::timeout(4)->connectTimeout(3)->acceptJson()->get("https://viacep.com.br/ws/{$cep}/json/");
                $data = $res->successful() ? $res->json() : null;
            } catch (Throwable $e) {
                Log::warning('cep_lookup_failed', ['error' => class_basename($e)]);

                return null; // não grava em cache para tentar de novo depois
            }
            if (! is_array($data) || ($data['erro'] ?? false) || ! in_array($data['uf'] ?? '', BrazilianStates::codes(), true)) {
                return null;
            }

            return [
                'postal_code' => $cep,
                'street' => mb_substr((string) ($data['logradouro'] ?? ''), 0, 160),
                'district' => mb_substr((string) ($data['bairro'] ?? ''), 0, 80),
                'city' => mb_substr((string) ($data['localidade'] ?? ''), 0, 80),
                'state' => (string) $data['uf'],
            ];
        });
    }
}
