<?php

namespace App\Support;

/**
 * Catálogo de espécies (CLAUDE.md §2.1 B: caninos, felinos, aves, répteis, pequenos mamíferos, exóticos e outros).
 * O código fica no banco; o detalhe livre (`species_detail`) identifica a espécie exata, ex.: "Calopsita".
 */
final class Species
{
    public const LABELS = [
        'canine' => 'Cão',
        'feline' => 'Gato',
        'bird' => 'Ave',
        'reptile' => 'Réptil',
        'rodent' => 'Roedor',
        'rabbit' => 'Coelho',
        'ferret' => 'Furão',
        'small_mammal' => 'Outro pequeno mamífero',
        'fish' => 'Peixe',
        'amphibian' => 'Anfíbio',
        'equine' => 'Equino',
        'bovine' => 'Bovino',
        'small_ruminant' => 'Ovino/caprino',
        'swine' => 'Suíno',
        'wild' => 'Silvestre/exótico',
        'other' => 'Outra',
    ];

    /** Espécies em que o detalhe (qual ave, qual réptil...) é pedido com destaque na interface. */
    public const DETAIL_SUGGESTED = ['bird', 'reptile', 'rodent', 'small_mammal', 'fish', 'amphibian', 'wild', 'other'];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }
}
