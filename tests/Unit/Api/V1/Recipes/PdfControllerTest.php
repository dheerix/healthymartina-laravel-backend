<?php

namespace Tests\Unit\Api\V1\Recipes;

use App\Http\Controllers\Api\V1\Recipes\PdfController;
use App\Models\Receta;
use ReflectionClass;
use Tests\TestCase;

class PdfControllerTest extends TestCase
{
    public function test_recipe_pdf_uses_approved_fractions(): void
    {
        $controller = new PdfController();
        $method = (new ReflectionClass($controller))->getMethod('formatMixedFraction');
        $method->setAccessible(true);

        $this->assertSame('1/3', $method->invoke($controller, 0.30));
        $this->assertSame('1/4', $method->invoke($controller, 0.25));
        $this->assertSame('1/2', $method->invoke($controller, 0.50));
        $this->assertSame('2/3', $method->invoke($controller, 0.625));
        $this->assertSame('1 1/3', $method->invoke($controller, 1.30));
        $this->assertSame('-1/2', $method->invoke($controller, -0.50));
        $this->assertSame('1', $method->invoke($controller, 0.99));
    }

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

    public function test_external_payload_preserves_recipe_yield_measurement(): void
    {
        $controller = new PdfController();
        $method = (new ReflectionClass($controller))->getMethod('buildExternalPayload');
        $method->setAccessible(true);

        $recipe = new class extends Receta {
            public function getIngredientes($solo_ingredientes = false)
            {
                return [];
            }

            public function getPorciones($active = true)
            {
                return [
                    'cantidad' => 1,
                    'nombre' => 'Taza',
                    'nombre_plural' => 'Tazas',
                ];
            }

            public function getInformacionNutrimental($porcentajeOverride = 100, $porcionesOverride = 0, $numeroDivision = 0, $porcionesDivision = 0)
            {
                return ['info' => []];
            }

            public function getInstrucciones()
            {
                return [];
            }

            public function getTipsPlain()
            {
                return [];
            }
        };
        $recipe->id = 42;
        $recipe->titulo = 'Caldo';

        $user = (object) [
            'bname' => 'Healthy Martina',
            'bemail' => 'test@example.com',
            'email' => 'test@example.com',
            'bimage' => '',
            'color' => '#36544e',
        ];

        $payload = $method->invoke($controller, $recipe, 1, [], $user);
        $exportedRecipe = $payload['recipePages'][0]['recipe'];

        $this->assertSame('Taza', $exportedRecipe['porcion_nombre']);
        $this->assertSame('Tazas', $exportedRecipe['porcion_nombre_plural']);
    }
}
