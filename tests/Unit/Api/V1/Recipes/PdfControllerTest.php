<?php

namespace Tests\Unit\Api\V1\Recipes;

use App\Http\Controllers\Api\V1\Recipes\PdfController;
use App\Models\Receta;
use ReflectionClass;
use Tests\TestCase;

class PdfControllerTest extends TestCase
{
    public function test_scale_recipe_ingredients_keeps_subrecipes_in_standalone_export(): void
    {
        $controller = new PdfController();
        $method = (new ReflectionClass($controller))->getMethod('scaleRecipeIngredients');
        $method->setAccessible(true);

        $recipe = new class extends Receta {
            public array $ingredients = [];
            public bool|int|null $calledWith = null;

            public function getIngredientes($solo_ingredientes = false)
            {
                $this->calledWith = $solo_ingredientes;
                return $this->ingredients;
            }

            public function getPorciones($active = true)
            {
                return ['cantidad' => 2];
            }
        };

        $recipe->ingredients = [
            [
                'ingrediente' => 'Harina',
                'cantidad' => 2,
                'medida' => 'tz',
            ],
            [
                'ingrediente' => '<a target="_blank" href="/receta/huevo-cocido">Huevo cocido</a>',
                'cantidad' => 1,
                'medida' => 'pieza',
                'type' => 'subrecipe',
                'sub-url' => '/receta/huevo-cocido',
            ],
        ];

        $scaled = $method->invoke($controller, $recipe, 4);

        $this->assertFalse($recipe->calledWith);
        $this->assertCount(2, $scaled);
        $this->assertSame('2', $scaled[1]['cantidad']);
        $this->assertSame('subrecipe', $scaled[1]['type']);
        $this->assertSame('/receta/huevo-cocido', $scaled[1]['sub-url']);
    }
}
