<?php

namespace Tests\Unit\Support;

use App\Support\ShoppingListAggregator;
use PHPUnit\Framework\TestCase;

class ShoppingListAggregatorTest extends TestCase
{
    public function test_it_scales_and_sums_every_scheduled_occurrence(): void
    {
        $ingredient = $this->ingredient(3, 'scoop', 'scoops', 3, 1);
        $ingredient['repeat'] = [
            $this->ingredient(3, 'scoop', 'scoops', 3, 1),
            $this->ingredient(3, 'scoop', 'scoops', 3, 1),
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame(3, $result['cantidad']);
        $this->assertSame('scoops', $result['medida']);
        $this->assertSame([], $result['repeat']);
    }

    public function test_it_preserves_different_units_for_the_same_ingredient(): void
    {
        $ingredient = $this->ingredient(1 / 3, 'pza', 'pzas', 1, 1, 'piece');
        $ingredient['repeat'] = [
            $this->ingredient(1 / 3, 'pza', 'pzas', 1, 1, 'piece'),
            $this->ingredient(75, 'g', 'g', 1, 1, 'gram'),
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame('2/3 pza + 75 g', $result['cantidad']);
        $this->assertSame('', $result['medida']);
    }

    public function test_it_keeps_to_taste_once_alongside_numeric_units(): void
    {
        $ingredient = $this->ingredient(1 / 8, 'cdta', 'cdtas', 1, 1, 'teaspoon');
        $ingredient['repeat'] = [
            $this->ingredient(1 / 2, 'cdta', 'cdtas', 1, 1, 'teaspoon'),
            [
                'cantidad' => 1,
                'medida' => 'al gusto',
                'tipo_medida_id' => 4,
            ],
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame('5/8 cdta + al gusto', $result['cantidad']);
    }

    public function test_it_preserves_to_taste_when_it_is_the_first_occurrence(): void
    {
        $ingredient = [
            'cantidad' => 1,
            'medida' => 'al gusto',
            'tipo_medida_id' => 4,
            'repeat' => [
                $this->ingredient(1 / 2, 'cdta', 'cdtas', 1, 1, 'teaspoon'),
            ],
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame('1/2 cdta + al gusto', $result['cantidad']);
    }

    public function test_it_converts_mixed_volume_units_and_normalizes_ratio(): void
    {
        $ingredient = $this->ingredient(1.5625, 'cdta', 'cdtas', 1, 1, 'teaspoon');
        $ingredient['repeat'] = [
            $this->ingredient(8.9167, 'cda', 'cdas', 1, 1, 'tablespoon'),
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame('5/8', $result['cantidad']);
        $this->assertSame('tz', $result['medida']);
    }

    public function test_it_aggregates_tablespoons_below_cup_threshold(): void
    {
        $ingredient = $this->ingredient(1, 'cda', 'cdas', 1, 1, 'tablespoon');
        $ingredient['repeat'] = [
            $this->ingredient(2, 'cda', 'cdas', 1, 1, 'tablespoon'),
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame(3, $result['cantidad']);
        $this->assertSame('cdas', $result['medida']);
    }

    public function test_it_converts_two_and_one_third_tsp_plus_twenty_three_forty_eighths_cup(): void
    {
        // 2 1/3 cdtas + 23/48 tz
        // 2 1/3 tsp = 7/3 tsp
        // 23/48 cup = 23 tsp
        // Total = 25 1/3 tsp = 0.527778 cup -> snaps to 1/2 tz
        $ingredient = $this->ingredient(7 / 3, 'cdta', 'cdtas', 1, 1, 'teaspoon');
        $ingredient['repeat'] = [
            $this->ingredient(23 / 48, 'tz', 'tzs', 1, 1, 'cup'),
        ];

        $result = ShoppingListAggregator::aggregateIngredient($ingredient);

        $this->assertSame('1/2', $result['cantidad']);
        $this->assertSame('tz', $result['medida']);
    }

    private function ingredient(
        float $quantity,
        string $singular,
        string $plural,
        float $basePortion,
        float $scheduledServings,
        string $english = 'unit'
    ): array {
        return [
            'cantidad' => $quantity,
            'medida' => $singular,
            'medida_plural' => $plural,
            'medida_english' => $english,
            'tipo_medida_id' => 1,
            'porcion' => $basePortion,
            'get_servings' => $scheduledServings,
        ];
    }
}
