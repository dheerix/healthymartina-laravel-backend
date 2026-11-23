<?php

namespace Tests\Unit\Support;

use App\Support\NutritionPreferenceSupport;
use Tests\TestCase;

class NutritionPreferenceSupportTest extends TestCase
{
    public function test_all_configured_nutrients_are_available_as_recipe_preferences(): void
    {
        $options = NutritionPreferenceSupport::getRecipeNutritionOptions();
        $optionIds = array_column($options, 'id');
        $configuredIds = array_values(array_map(
            fn (array $item) => $item['id'],
            config('constants.nutritients')
        ));

        $this->assertEqualsCanonicalizing($configuredIds, $optionIds);
        $this->assertContains(104, $optionIds); // Magnesium
        $this->assertContains(180, $optionIds); // Cholesterol
        $this->assertContains(124, $optionIds); // Vitamin A
    }

    public function test_only_basic_nutrients_are_selected_by_default(): void
    {
        $options = NutritionPreferenceSupport::getRecipeNutritionOptions();

        $this->assertEqualsCanonicalizing(
            NutritionPreferenceSupport::RECIPE_DEFAULT_IDS,
            NutritionPreferenceSupport::getSelectedIdsFromInfo($options)
        );
    }

    public function test_customized_preferences_can_select_non_basic_nutrients(): void
    {
        $stored = NutritionPreferenceSupport::normalizeNutritionInfo();
        foreach ($stored as &$item) {
            $item['mostrar'] = (int) $item['id'] === 104 ? 1 : 0;
        }
        unset($item);
        $stored = NutritionPreferenceSupport::markRecipePreferencesCustomized($stored);

        $options = NutritionPreferenceSupport::getRecipeNutritionOptions($stored);

        $this->assertSame([104], NutritionPreferenceSupport::getSelectedIdsFromInfo($options));
    }
}
