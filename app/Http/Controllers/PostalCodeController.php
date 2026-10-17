<?php

namespace App\Http\Controllers;

use App\Services\PostalCodeLookup;
use Illuminate\Http\JsonResponse;

/** GET /cep/{cep} → endereço (JSON) para preencher formulários. Só autenticado; limite de requisições. */
final class PostalCodeController extends Controller
{
    public function __invoke(string $cep, PostalCodeLookup $lookup): JsonResponse
    {
        $address = $lookup->find($cep);

        return $address
            ? response()->json($address)
            : response()->json(['message' => 'CEP não encontrado. Preencha o endereço manualmente.'], 404);
    }
}
