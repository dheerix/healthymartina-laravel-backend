<?php

namespace Tests\Unit\Api\V1\Calendars;

use App\Http\Controllers\Api\V1\Calendars\CalendarPdfController;
use App\Models\Receta;
use ReflectionClass;
use Tests\TestCase;

class CalendarPdfControllerTest extends TestCase
{
    public function test_scale_recipe_ingredients_keeps_subrecipes_in_the_exported_list(): void
    {
        $controller = new CalendarPdfController();
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
        $this->assertSame('Harina', $scaled[0]['ingrediente']);
        $this->assertSame('<a target="_blank" href="/receta/huevo-cocido">Huevo cocido</a>', $scaled[1]['ingrediente']);
        $this->assertSame('subrecipe', $scaled[1]['type']);
        $this->assertSame('/receta/huevo-cocido', $scaled[1]['sub-url']);
    }
}
