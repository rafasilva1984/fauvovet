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

    /** Tom de avatar (0–7, ver .fv-tone-* no CSS) por espécie, para reconhecer o grupo de relance. */
    public const TONES = [
        'canine' => 2, 'feline' => 1, 'bird' => 5, 'reptile' => 0, 'rodent' => 6, 'rabbit' => 3, 'ferret' => 6,
        'small_mammal' => 6, 'fish' => 4, 'amphibian' => 0, 'equine' => 7, 'bovine' => 7, 'small_ruminant' => 7,
        'swine' => 3, 'wild' => 5, 'other' => 7,
    ];

    public static function tone(string $code): int
    {
        return self::TONES[$code] ?? 7;
    }

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
