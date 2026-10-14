<?php

namespace App\Support;

class ShoppingListAggregator
{
    private const VOLUME_UNITS = [
        'teaspoon'     => 1.0,
        'teaspoons'    => 1.0,
        'tsp'          => 1.0,
        'cdta'         => 1.0,
        'cdtas'        => 1.0,
        'cucharadita'  => 1.0,
        'cucharaditas' => 1.0,
        'tablespoon'   => 3.0,
        'tablespoons'  => 3.0,
        'tbsp'         => 3.0,
        'cda'          => 3.0,
        'cdas'         => 3.0,
        'cucharada'    => 3.0,
        'cucharadas'   => 3.0,
        'cup'          => 48.0,
        'cups'         => 48.0,
        'tz'           => 48.0,
        'tzs'          => 48.0,
        'taza'         => 48.0,
        'tazas'        => 48.0,
    ];

    private const WEIGHT_UNITS = [
        'gram'       => 1.0,
        'grams'      => 1.0,
        'g'          => 1.0,
        'gramo'      => 1.0,
        'gramos'     => 1.0,
        'kilogram'   => 1000.0,
        'kilograms'  => 1000.0,
        'kg'         => 1000.0,
        'kilo'       => 1000.0,
        'kilos'      => 1000.0,
        'kilogramo'  => 1000.0,
        'kilogramos' => 1000.0,
    ];

    /**
     * Aggregate every scheduled occurrence represented by an ingredient and
     * its repeat entries, converting compatible volume/weight units and
     * normalizing ratios to standard cooking fractions.
     */
    public static function aggregateIngredient(array $ingredient): array
    {
        $occurrences = [$ingredient, ...array_values($ingredient['repeat'] ?? [])];
        $hasToTaste = false;
        $volumeTeaspoons = 0.0;
        $hasVolume = false;
        $weightGrams = 0.0;
        $hasWeight = false;
        $otherTotals = [];
        $unitOrder = [];

        foreach ($occurrences as $occurrence) {
            if (self::isToTaste($occurrence)) {
                $hasToTaste = true;
                continue;
            }

            $quantity = self::scaledQuantity($occurrence);
            if ($quantity === null) {
                continue;
            }

            $volumeFactor = self::volumeFactor($occurrence);
            if ($volumeFactor !== null) {
                if (!in_array('__volume__', $unitOrder, true)) {
                    $unitOrder[] = '__volume__';
                }
                $volumeTeaspoons += $quantity * $volumeFactor;
                $hasVolume = true;
                continue;
            }

            $weightFactor = self::weightFactor($occurrence);
            if ($weightFactor !== null) {
                if (!in_array('__weight__', $unitOrder, true)) {
                    $unitOrder[] = '__weight__';
                }
                $weightGrams += $quantity * $weightFactor;
                $hasWeight = true;
                continue;
            }

            $unitKey = self::unitKey($occurrence);
            if (!in_array($unitKey, $unitOrder, true)) {
                $unitOrder[] = $unitKey;
            }
            if (!isset($otherTotals[$unitKey])) {
                $otherTotals[$unitKey] = [
                    'quantity' => 0.0,
                    'singular' => trim((string) ($occurrence['medida'] ?? '')),
                    'plural'   => trim((string) ($occurrence['medida_plural'] ?? ($occurrence['medida'] ?? ''))),
                    'is_tsp'   => false,
                ];
            }
            $otherTotals[$unitKey]['quantity'] += $quantity;
        }

        $finalTotals = [];

        foreach ($unitOrder as $key) {
            if ($key === '__volume__' && $hasVolume && $volumeTeaspoons > 0) {
                if ($volumeTeaspoons < 3.0) {
                    $finalTotals[] = [
                        'quantity' => $volumeTeaspoons,
                        'singular' => 'cdta',
                        'plural'   => 'cdtas',
                        'is_tsp'   => true,
                    ];
                } elseif ($volumeTeaspoons < 12.0) {
                    $finalTotals[] = [
                        'quantity' => $volumeTeaspoons / 3.0,
                        'singular' => 'cda',
                        'plural'   => 'cdas',
                        'is_tsp'   => false,
                    ];
                } else {
                    $finalTotals[] = [
                        'quantity' => $volumeTeaspoons / 48.0,
                        'singular' => 'tz',
                        'plural'   => 'tzs',
                        'is_tsp'   => false,
                    ];
                }
            } elseif ($key === '__weight__' && $hasWeight && $weightGrams > 0) {
                if ($weightGrams < 1000.0) {
                    $finalTotals[] = [
                        'quantity' => round($weightGrams),
                        'singular' => 'g',
                        'plural'   => 'g',
                        'is_tsp'   => false,
                    ];
                } else {
                    $finalTotals[] = [
                        'quantity' => $weightGrams / 1000.0,
                        'singular' => 'kg',
                        'plural'   => 'kg',
                        'is_tsp'   => false,
                    ];
                }
            } elseif (isset($otherTotals[$key])) {
                $finalTotals[] = $otherTotals[$key];
            }
        }

        $ingredient['repeat'] = [];

        if (count($finalTotals) === 1 && !$hasToTaste) {
            $total = reset($finalTotals);
            if (abs($total['quantity'] - round($total['quantity'])) < 1e-4) {
                $ingredient['cantidad'] = (int) round($total['quantity']);
            } else {
                $ingredient['cantidad'] = self::formatFraction((float) $total['quantity'], $total['is_tsp'] ?? false);
            }
            $ingredient['medida'] = self::unitForQuantity($total);
            $ingredient['medida_plural'] = $total['plural'];
            return $ingredient;
        }

        $parts = [];
        foreach ($finalTotals as $total) {
            $unit = self::unitForQuantity($total);
            $parts[] = trim(self::formatFraction((float) $total['quantity'], $total['is_tsp'] ?? false) . ' ' . $unit);
        }
        if ($hasToTaste) {
            $parts[] = 'al gusto';
        }

        $ingredient['cantidad'] = implode(' + ', $parts);
        $ingredient['medida'] = '';
        $ingredient['medida_plural'] = '';

        return $ingredient;
    }

    private static function scaledQuantity(array $ingredient): ?float
    {
        if (!isset($ingredient['cantidad']) || !is_numeric($ingredient['cantidad'])) {
            return null;
        }

        $quantity = (float) $ingredient['cantidad'];
        $scheduledServings = $ingredient['get_servings'] ?? null;
        if (!is_numeric($scheduledServings)) {
            return $quantity;
        }

        $basePortion = $ingredient['porcion'] ?? null;
        if (!empty($ingredient['subrecipe']) && is_array($ingredient['subrecipe'])) {
            $basePortion = $ingredient['subrecipe']['porcion'] ?? $basePortion;
        }

        if (!is_numeric($basePortion) || (float) $basePortion <= 0) {
            return $quantity;
        }

        return $quantity * (float) $scheduledServings / (float) $basePortion;
    }

    private static function isToTaste(array $ingredient): bool
    {
        return (int) ($ingredient['tipo_medida_id'] ?? 0) === 4
            || mb_strtolower(trim((string) ($ingredient['medida'] ?? ''))) === 'al gusto';
    }

    private static function volumeFactor(array $ingredient): ?float
    {
        $english = mb_strtolower(trim((string) ($ingredient['medida_english'] ?? '')));
        $display = mb_strtolower(trim((string) ($ingredient['medida'] ?? '')));

        if (isset(self::VOLUME_UNITS[$english])) {
            return self::VOLUME_UNITS[$english];
        }
        if (isset(self::VOLUME_UNITS[$display])) {
            return self::VOLUME_UNITS[$display];
        }

        return null;
    }

    private static function weightFactor(array $ingredient): ?float
    {
        $english = mb_strtolower(trim((string) ($ingredient['medida_english'] ?? '')));
        $display = mb_strtolower(trim((string) ($ingredient['medida'] ?? '')));

        if (isset(self::WEIGHT_UNITS[$english])) {
            return self::WEIGHT_UNITS[$english];
        }
        if (isset(self::WEIGHT_UNITS[$display])) {
            return self::WEIGHT_UNITS[$display];
        }

        return null;
    }

    private static function unitKey(array $ingredient): string
    {
        $english = mb_strtolower(trim((string) ($ingredient['medida_english'] ?? '')));
        $display = mb_strtolower(trim((string) ($ingredient['medida'] ?? '')));

        return $english !== '' && $english !== 'na' ? $english : $display;
    }

    private static function unitForQuantity(array $total): string
    {
        return (float) $total['quantity'] > 1.0
            ? ($total['plural'] ?: $total['singular'])
            : $total['singular'];
    }

    public static function formatFraction(float $value, bool $isTsp = false): string
    {
        $sign = $value < 0 ? '-' : '';
        $absolute = abs($value);
        $whole = (int) floor($absolute);
        $decimal = $absolute - $whole;

        if ($decimal < 0.01) {
            return $sign . (string) $whole;
        }

        $fractions = [
            ['num' => 1, 'den' => 16, 'val' => 1.0 / 16.0],
            ['num' => 1, 'den' => 8,  'val' => 1.0 / 8.0],
            ['num' => 1, 'den' => 4,  'val' => 1.0 / 4.0],
            ['num' => 1, 'den' => 3,  'val' => 1.0 / 3.0],
            ['num' => 3, 'den' => 8,  'val' => 3.0 / 8.0],
            ['num' => 1, 'den' => 2,  'val' => 1.0 / 2.0],
            ['num' => 5, 'den' => 8,  'val' => 5.0 / 8.0],
            ['num' => 2, 'den' => 3,  'val' => 2.0 / 3.0],
            ['num' => 3, 'den' => 4,  'val' => 3.0 / 4.0],
            ['num' => 7, 'den' => 8,  'val' => 7.0 / 8.0],
            ['num' => 1, 'den' => 1,  'val' => 1.0],
        ];

        if ($isTsp) {
            array_unshift($fractions, ['num' => 1, 'den' => 32, 'val' => 1.0 / 32.0]);
        }

        $best = null;
        $minDiff = 10000.0;
        foreach ($fractions as $cand) {
            $diff = abs($decimal - $cand['val']);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $best = $cand;
            }
        }

        if (!$best || $best['val'] >= 0.99) {
            return $sign . (string) ($whole + 1);
        }

        $fracStr = $best['num'] . '/' . $best['den'];
        return $sign . ($whole > 0 ? "{$whole} {$fracStr}" : $fracStr);
    }
}
