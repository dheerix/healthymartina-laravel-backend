<?php
use App\Models\Calendar;
use App\Models\Receta;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Storage;

function float2rat($n, $tolerance = 1.e-6)
{
    $h1 = 1;
    $h2 = 0;
    $k1 = 0;
    $k2 = 1;
    $b = 1 / $n;
    do {
        $b = 1 / $b;
        $a = floor($b);
        $aux = $h1;
        $h1 = $a * $h1 + $h2;
        $h2 = $aux;
        $aux = $k1;
        $k1 = $a * $k1 + $k2;
        $k2 = $aux;
        $b = $b - $a;
    } while (abs($n - $h1 / $k1) > $n * $tolerance);

    return "$h1/$k1";
}

function float2fraction($float, $concat = ' ')
{

    // ensures that the number is float,
    // even when the parameter is a string
    $float = (float) $float;

    if ($float == 0) {
        return $float;
    }

    // when float between -1 and 1
    if ($float > -1 && $float < 0 || $float < 1 && $float > 0) {
        $fraction = float2rat($float);
        return $fraction;
    } else {

        // get the minor integer
        if ($float < 0) {
            $integer = ceil($float);
        } else {
            $integer = floor($float);
        }

        // get the decimal
        $decimal = $float - $integer;

        if ($decimal != 0) {

            $fraction = float2rat(abs($decimal));
            $fraction = $integer . $concat . $fraction;
            return $fraction;
        } else {
            return $float;
        }
    }
}

function decToFraction($float)
{
    // 1/2, 1/4, 1/8, 1/16, 1/3 ,2/3, 3/4, 3/8, 5/8, 7/8, 3/16, 5/16, 7/16,
    // 9/16, 11/16, 13/16, 15/16
    $whole = floor($float);
    $decimal = $float - $whole;
    $leastCommonDenom = 48; // 16 * 3;
    $denominators = array(2, 3, 4, 8, 16, 24, 48);
    $roundedDecimal = round($decimal * $leastCommonDenom) / $leastCommonDenom;
    if ($roundedDecimal == 0) {
        return $whole;
    }

    if ($roundedDecimal == 1) {
        return $whole + 1;
    }

    foreach ($denominators as $d) {
        if ($roundedDecimal * $d == floor($roundedDecimal * $d)) {
            $denom = $d;
            break;
        }
    }
    return ($whole == 0 ? '' : $whole) . " " . ($roundedDecimal * $denom) . "/" . $denom;
}

function decToFractionNew($float)
{
    // 1/2, 1/4, 1/8, 1/16, 1/3 ,2/3, 3/4, 3/8, 5/8, 7/8, 3/16, 5/16, 7/16,
    // 9/16, 11/16, 13/16, 15/16
    $whole = floor($float);
    $decimal = $float - $whole;
    $leastCommonDenom = 48; // 16 * 3;
    $denominators = array(2, 3, 4, 8, 16, 24, 48);
    $roundedDecimal = round($decimal * $leastCommonDenom) / $leastCommonDenom;
    if ($roundedDecimal == 0) {
        return $whole;
    }

    if ($roundedDecimal == 1) {
        return $whole + 1;
    }

    foreach ($denominators as $d) {
        if ($roundedDecimal * $d == floor($roundedDecimal * $d)) {
            $denom = $d;
            break;
        }
    }
    $response = [
        'whole' => $whole == 0 ? '' : $whole,
        'roundedDecimal' => ($roundedDecimal * $denom) . "/" . $denom,
    ];
    return $response;
    //return ($whole == 0 ? '' : $whole) . " " . ($roundedDecimal * $denom) . "/" . $denom;
}

function check_leftover($calendario_id, $meal, $day, $schedule)
{
    static $leftoverCache = [];
    $userId = Auth::user()->id;
    $cacheKey = $userId . ':' . $calendario_id;

    if (!isset($leftoverCache[$cacheKey])) {
        $calendar = Calendar::where('id', $calendario_id)->where('user_id', $userId)->first();
        $leftoverCache[$cacheKey] = [
            'main' => json_decode($calendar->main_leftovers ?? '[]', true) ?: [],
            'side' => json_decode($calendar->sides_leftovers ?? '[]', true) ?: [],
        ];
    }

    $main_leftovers = $leftoverCache[$cacheKey]['main'];
    $sides_leftovers = $leftoverCache[$cacheKey]['side'];
    $return = false;
    if ($schedule == 'main') {
        foreach ($main_leftovers as $key => $value) {
            foreach ($value as $key1 => $value1) {
                if ($key1 == $meal && $day == $key && $value1 == null) {
                    $return = true;
                }
            }
        }
        return $return;
    } else {
        foreach ($sides_leftovers as $key => $value) {
            foreach ($value as $key1 => $value1) {
                if ($key1 == $meal && $day == $key && $value1 == null) {
                    $return = true;
                }
            }
        }
        return $return;
    }

}
function get_servings($calendario_id, $meal, $day, $schedule)
{
    static $servingsCache = [];
    $userId = Auth::user()->id;
    $cacheKey = $userId . ':' . $calendario_id;

    if (!isset($servingsCache[$cacheKey])) {
        $calendar = Calendar::where('id', $calendario_id)->where('user_id', $userId)->first();
        $servingsCache[$cacheKey] = [
            'main' => json_decode($calendar->main_servings ?? '[]', true) ?: [],
            'side' => json_decode($calendar->sides_servings ?? '[]', true) ?: [],
        ];
    }

    $main_servings = $servingsCache[$cacheKey]['main'];
    $sides_servings = $servingsCache[$cacheKey]['side'];
    $return = '';
    if ($schedule == 'main') {
        foreach ($main_servings as $key => $value) {
            foreach ($value as $key1 => $value1) {
                if ($key1 == $meal && $day == $key) {
                    $return = $value1;
                }
            }
        }
        return $return;
    } else {
        foreach ($sides_servings as $key => $value) {
            foreach ($value as $key1 => $value1) {
                if ($key1 == $meal && $day == $key) {
                    $return = $value1;
                }
            }
        }
        return $return;
    }

}
function getRelatedIngrediente($calendario_id, $categoria_id, $use = "list")
{
    static $calendarContextCache = [];
    static $recipesByCalendarCache = [];
    $userId = Auth::user()->id;
    $cacheKey = $userId . ':' . $calendario_id;

    if (!isset($calendarContextCache[$cacheKey])) {
        $calendar = Calendar::where('id', $calendario_id)->where('user_id', $userId)->first();
        $calendarContextCache[$cacheKey] = [
            'main_schedule' => json_decode($calendar->main_schedule ?? '[]', true) ?: [],
            'sides_schedule' => json_decode($calendar->sides_schedule ?? '[]', true) ?: [],
        ];
    }

    $cMains = $calendarContextCache[$cacheKey]['main_schedule'];
    $cSides = $calendarContextCache[$cacheKey]['sides_schedule'];

    if (!isset($recipesByCalendarCache[$cacheKey])) {
        $recipeIds = [];
        foreach ($cMains as $dayMeals) {
            foreach ((array) $dayMeals as $recipeId) {
                if (!empty($recipeId)) {
                    $recipeIds[] = (int) $recipeId;
                }
            }
        }
        foreach ($cSides as $dayMeals) {
            foreach ((array) $dayMeals as $recipeId) {
                if (!empty($recipeId)) {
                    $recipeIds[] = (int) $recipeId;
                }
            }
        }
        $recipeIds = array_values(array_unique(array_filter($recipeIds)));
        $recipesByCalendarCache[$cacheKey] = empty($recipeIds)
            ? []
            : Receta::whereIn('id', $recipeIds)->get()->keyBy('id')->all();
    }
    $recipesById = $recipesByCalendarCache[$cacheKey];

    $cRecpIds = array();
    $mcRecpIgs = array();
    $scRecpIgs = array();
    $cRecp[] = array();
    foreach ($cMains as $key => $cMain) {
        foreach ($cMain as $key1 => $cRecpId) {

            if ($cRecpId) {
                $check_leftover = check_leftover($calendario_id, $key1, $key, 'main');
                if ($check_leftover == true) {
                    $get_servings = get_servings($calendario_id, $key1, $key, 'main');
                    $receta = $recipesById[(int) $cRecpId] ?? null;
                    if (!$receta) {
                        continue;
                    }
                    $porcion = $receta->getPorciones()['cantidad'];
                    $mcRecpIgs[] = $receta->getCategoriaIngredientes($categoria_id, $key1, $key, 'main', $get_servings, $porcion);
                }
            }
        }
    }

    foreach ($cSides as $key => $cSide) {
        foreach ($cSide as $key1 => $scRecpId) {
            if ($scRecpId) {
                $check_leftover = check_leftover($calendario_id, $key1, $key, 'side');
                if ($check_leftover == true) {
                    $get_servings = get_servings($calendario_id, $key1, $key, 'side');
                    $receta = $recipesById[(int) $scRecpId] ?? null;
                    if (!$receta) {
                        continue;
                    }
                    $porcion = $receta->getPorciones()['cantidad'];
                    $scRecpIgs[] = $receta->getCategoriaIngredientes($categoria_id, $key1, $key, 'side', $get_servings, $porcion);
                }
            }
        }
    }
    $meal_merge = array_merge($scRecpIgs, $mcRecpIgs);
    //dd($scRecpIgs);
    $ingrediente_ids = [];
    $ingredients_data = [];
    $ingrediente_id = '';
    $check_taken = true;
    foreach ($meal_merge as $ingredients) {
        if ($ingredients) {
            foreach ($ingredients as $key => $ingredient) {
                if ($use == 'list') {
                    $check_taken = true;
                } else {
                    $check_taken = check_taken_ingredientes($categoria_id, $ingredient['ingrediente_id'], 'receta', $calendario_id, $use);
                }
                if ($ingredient['categoria_id'] == $categoria_id && $check_taken) {
                    if (!in_array($ingredient['ingrediente_id'], $ingrediente_ids)) {
                        $ingrediente_id = $ingredient['ingrediente_id'];
                        //$ingredient['count'] = 1;
                        $ingredient['repeat'] = [];
                        $ingredients_data[] = $ingredient;
                    } else {
                        foreach ($ingredients_data as $key => $value) {
                            if ($value['ingrediente_id'] == $ingredient['ingrediente_id']) {
                                // Preserve every occurrence exactly as scheduled. Replacing an
                                // initial "al gusto" occurrence with the next numeric occurrence
                                // both lost the taste entry and caused the numeric amount to be
                                // counted twice by shopping-list aggregation.
                                $value['repeat'][] = $ingredient;
                                // $value['cantidad']= $ingredient['cantidad'] + $value['cantidad'];
                                //$value['get_servings'] = $ingredient['get_servings'] + $value['get_servings'];
                                $ingredients_data[$key] = $value;
                            }
                        }

                    }
                    // $ingredients_data[] = $ingredient;
                    $ingrediente_ids[] = $ingredient['ingrediente_id'];
                }
            }
        }
    }
    usort($ingredients_data, function ($first, $second) {
        //  dd($first,$second);
        return strtolower($first['ingrediente']) > strtolower($second['ingrediente']);
    });
    return $ingredients_data;
}
function check_taken_ingredientes($categoria_id, $ingrediente_id, $type, $calendario_id, $use)
{
    if ($use == 'pdf') {
        $taken_ingredientes = DB::table('lista_ingrediente_taken')
            ->where(['calendario_id' => $calendario_id,
                'categoria_id' => $categoria_id,
                'ingrediente_type' => $type,
                'ingrediente_id' => $ingrediente_id])->first();
        if ($taken_ingredientes) {
            return false;
        }

    }
    return true;
}

function getUnitMeasure($unit_measure, $medida, $whole, $roundedDecimal)
{
    if ($unit_measure == 'metric') {
        if ($roundedDecimal >= 1000) {
            $data['unit'] = 'kg';
            $kg = $roundedDecimal / 1000;
            $roundedDecimal = decToFractionNew($kg)['roundedDecimal'];
            $whole = decToFractionNew($kg)['whole'];
            if ($whole) {
                $data['unit'] = 'kg';
            } else {
                $data['unit'] = 'kg';
            }
            $data['roundedDecimal'] = $roundedDecimal;
            $data['whole'] = $whole;
        } else {
            $data['unit'] = 'g';
        }
    } else {
        $oz = $roundedDecimal * 0.035274;
        if ($oz < 16) {
            if ($whole) {
                $data['unit'] = 'ozs';
            } else {
                $data['unit'] = 'oz';
            }
            $data['roundedDecimal'] = $oz;
            $data['whole'] = $whole;
        } elseif ($oz >= 16) {
            $lbs = $oz / 16;
            if ($whole) {
                $data['unit'] = 'lbs';
            } else {
                $data['unit'] = 'lb';
            }
            $data['roundedDecimal'] = $roundedDecimal;
            $data['whole'] = $whole;
        }
    }
    //dd($data);
    return $data;
}
function getNearestFractionBase($value)
{

    $fractions = [
        '1/8' => '0.125',
        '1/4' => '0.25',
        '1/3' => '0.3333333333333333',
        '1/2' => '0.5',
        '2/3' => '0.6666666666666666',
        '3/4' => '0.75',
        '1/16' => '0.0625',
        '1' => '1',
    ];

    $entero = intval($value);
    $decimal = $value - $entero;
    //dd('value1:'.$value,'entero:'.$entero,' decimal:'.$decimal);
    $cercano = 0;
    $fraccion_cercana = '0';
    $diferencia = 10000;

    if ($decimal > 0) {

        foreach ($fractions as $key => $fraction) {
            if ($fractions[$key] == $decimal) {
                $fractionReturn = $key;
                $returnFraction = [
                    'int' => $entero,
                    'fraction' => $fractionReturn,
                ];
                return $returnFraction;
            } else {
                if ($fractions[$key] - $decimal < $diferencia) {
                    $cercano = $fractions[$key];
                    $fraccion_cercana = $key;
                    $diferencia = $fractions[$key] - $decimal;
                }
            }
        }
    }

    if ($fraccion_cercana == 1) {

        $fractionReturn = $fraccion_cercana;
        $returnFraction = [
            'int' => $entero + 1,
            'fraction' => $fractionReturn,
        ];
    } else {

        $fractionReturn = $fraccion_cercana;
        $returnFraction = [
            'int' => $entero,
            'fraction' => $fractionReturn,
        ];
    }
    // dd($returnFraction);
    return $returnFraction;

}

function getDayNutritionForModal($daykey, $calendar, $visible_info, $filter_info)
{
    $cLabels = json_decode($calendar->labels, true);
    $cMains = json_decode($calendar->main_schedule, true);
    $cSides = json_decode($calendar->sides_schedule, true);
    $cMainLeftovers = json_decode($calendar->main_leftovers, true);
    $cMainServings = json_decode($calendar->main_servings, true);
    $cSideLeftovers = json_decode($calendar->sides_leftovers, true);
    $cSideServings = json_decode($calendar->sides_servings, true);

    $cMracion = json_decode($calendar->main_racion, true);
    $cSracion = json_decode($calendar->sides_racion, true);
    $constants_nutritients = config()->get('constants.nutritients');
    // $cRecpIds = array();
    // foreach ($cMains as $cMain) {
    //     foreach ($cMain as $cRecpId) {
    //         if ($cRecpId && !in_array($cRecpId, $cRecpIds)) {
    //             $cRecpIds[] = $cRecpId;
    //         }
    //     }
    // }
    // foreach ($cSides as $cSide) {
    //     foreach ($cSide as $cRecpId) {
    //         if ($cRecpId && !in_array($cRecpId, $cRecpIds)) {
    //             $cRecpIds[] = $cRecpId;
    //         }
    //     }
    // }

    // $cRecipes = array();
    // $cRecps = $recipes_list->whereIn('id', $cRecpIds);
    // foreach ($cRecps as $cRecipe) {
    //     $cRecipes[$cRecipe->id] = $cRecipe;
    // }
    $allRecipeIds = array_filter(array_unique(([...array_values($cMains[$daykey]), ...array_values($cSides[$daykey])])));

    $cRecipes = array();
    $cRecps = Receta::select('id', 'titulo')->whereIn('id', $allRecipeIds)->get();
    foreach ($cRecps as $cRecipe) {
        $cRecipes[$cRecipe->id] = $cRecipe;
    }

    $calories = 0;
    $nutrientes_data = [];
    $data[$daykey] = [];
    $nutriente_ids = [];
    $percentage[$daykey] = [];
    foreach ($cLabels['meals'] as $mealkey => $mealname) {
        $mRecipeId = $cMains[$daykey][$mealkey];
        if ($mRecipeId) {
            $recipeInf = $cRecipes[$mRecipeId];
            $nutrientes = $recipeInf->getInformacionNutrimental();
            usort($nutrientes['info'], function ($item1, $item2) {
                if (!$item1['orden']) {
                    return 1;
                }

                if (!$item2['orden']) {
                    return -1;
                }

                return $item1['orden'] <=> $item2['orden'];
            });
            $nutrientes = $nutrientes['info'];

        } else {
            $nutrientes = $constants_nutritients;
            usort($nutrientes, function ($item1, $item2) {
                if (!$item1['orden']) {
                    return 1;
                }

                if (!$item2['orden']) {
                    return -1;
                }

                return $item1['orden'] <=> $item2['orden'];
            });

        }
        $cantidad = 0;

        $porcentaje = 0;
        $constants_nutritients = array_values($constants_nutritients);
        foreach ($nutrientes as $key => $nutriente) {
            if (in_array($nutriente['id'], $visible_info)) {
                if (isset($nutriente['constant'])) {
                    $nutriente['cantidad'] = 0;
                }
                if (!in_array($nutriente['id'], $nutriente_ids)) {
                    $nutriente_id = $nutriente['id'];

                    $needed_color = array_filter(
                        $constants_nutritients,
                        function ($e) use ($nutriente_id) {
                            if ($e['id'] == $nutriente_id) {
                                return $e['color'];
                            }
                        }
                    );
                    $nutriente['meal_id'] = $mealkey;
                    $nutriente['main_color'] = reset($needed_color)['color'];
                    $nutriente['meal_name'] = $mealname;
                    $nutriente['receta_id'] = @$mRecipeId;
                    $nutriente['receta_info'] = @$cRecipes[$mRecipeId];
                    $nutriente['racion'] = $cMracion[$daykey][$mealkey];
                    $nutriente['serving'] = $cMainServings[$daykey][$mealkey];
                    $nutriente['leftover'] = $cMainLeftovers[$daykey][$mealkey];
                    $sRecipeId = $cSides[$daykey][$mealkey];
                    if ($sRecipeId) {
                        $SrecipeInf = $cRecipes[$sRecipeId];
                        $sub_nutrientes = $SrecipeInf->getInformacionNutrimental();
                        $nut_id = $nutriente['id'];
                        $ids = array_filter(array_map(function ($ar) use ($nut_id) {
                            if ($ar['id'] == $nut_id) {
                                return $ar;
                            }
                        }, $sub_nutrientes["info"]));
                        $nutriente['subreceta_id'] = $sRecipeId;

                        $nutriente['subreceta_info'] = @$cRecipes[$mRecipeId];
                        $nutriente['sub_nutrientes'] = $ids;
                        $nutriente['sub_serving'] = $cSideServings[$daykey][$mealkey];
                        $nutriente['sub_racion'] = $cSracion[$daykey][$mealkey];
                        $nutriente['sub_leftover'] = $cSideLeftovers[$daykey][$mealkey];
                    } else {
                        $nutriente['sub_nutrientes'] = [];
                    }
                    $data[$daykey][] = $nutriente;
                } else {
                    foreach ($data[$daykey] as $key => $value) {
                        if ($value['id'] == $nutriente['id']) {
                            $nutriente['meal_id'] = $mealkey;
                            $nutriente['meal_name'] = $mealname;
                            $nutriente['receta_id'] = @$mRecipeId;

                            $nutriente['receta_info'] = @$cRecipes[$mRecipeId];
                            $nutriente['racion'] = $cMracion[$daykey][$mealkey];
                            $nutriente['serving'] = $cMainServings[$daykey][$mealkey];
                            $nutriente['leftover'] = $cMainLeftovers[$daykey][$mealkey];
                            $sRecipeId = $cSides[$daykey][$mealkey];
                            if ($sRecipeId) {
                                $SrecipeInf = $cRecipes[$sRecipeId];
                                $sub_nutrientes = $SrecipeInf->getInformacionNutrimental();
                                $nut_id = $nutriente['id'];
                                $ids = array_filter(array_map(function ($ar) use ($nut_id) {
                                    if ($ar['id'] == $nut_id) {
                                        return $ar;
                                    }
                                }, $sub_nutrientes["info"]));
                                $nutriente['subreceta_id'] = @$sRecipeId;

                                $nutriente['subreceta_info'] = @$cRecipes[$mRecipeId];
                                $nutriente['sub_nutrientes'] = $ids;

                                $nutriente['sub_serving'] = $cSideServings[$daykey][$mealkey];
                                $nutriente['sub_racion'] = $cSracion[$daykey][$mealkey];
                                $nutriente['sub_leftover'] = $cSideLeftovers[$daykey][$mealkey];
                            } else {
                                $nutriente['sub_nutrientes'] = [];
                            }
                            $data[$daykey][$key]['repeat'][] = $nutriente;
                        }
                    }
                }

                $nutriente_ids[] = $nutriente['id'];

            }
            if ($nutriente['id'] == '94' || $nutriente['id'] == '96' || $nutriente['id'] == '97' || $nutriente['id'] == '99') {
                $percentage[$daykey][] = $nutriente;
            }

        }

    }
    $percentage_total[$daykey] = array_sum(array_map(function ($item) {
        if ($item['id'] == 96 || $item['id'] == 99 || $item['id'] == 97) {
            $cantidad = $item['cantidad'] * $item['racion'];
            if (isset($item['subreceta_id'])) {
                foreach ($item['sub_nutrientes'] as $key => $sub_nutrientes) {
                    $cantidad += $sub_nutrientes['cantidad'] * $item['sub_racion'];
                }
            }
            return $cantidad;
        }
    }, $percentage[$daykey]));

    $response = [
        'percentage_total' => $percentage_total,
        'data' => $data,
    ];

    return $response;

}

function getDayNutritionData($daykey, $calendar, $visible_info, $filter_info)
{
    $cMains = json_decode($calendar->main_schedule, true) ?? [];
    $cSides = json_decode($calendar->sides_schedule, true) ?? [];
    $cMracion = json_decode($calendar->main_racion, true) ?? [];
    $cSracion = json_decode($calendar->sides_racion, true) ?? [];
    $recipeRacionMapping = [];
    $constants_nutritients = config()->get('constants.nutritients');

    // Check if day exists in the arrays, provide empty array if not
    $mainDayData = $cMains[$daykey] ?? [];
    $sidesDayData = $cSides[$daykey] ?? [];
    $mainRacionDayData = $cMracion[$daykey] ?? [];
    $sidesRacionDayData = $cSracion[$daykey] ?? [];

    $allRecipeIds = array_values(array_filter((([...array_values($mainDayData), ...array_values($sidesDayData)]))));
    $countsPerRecipe = array_count_values($allRecipeIds);
    foreach ($mainDayData as $meal => $id) {
        if ($id) {
            $recipeRacionMapping[$id] = $mainRacionDayData[$meal] ?? 1;
        }
    }
    foreach ($sidesDayData as $meal => $id) {
        if ($id) {
            $recipeRacionMapping[$id] = $sidesRacionDayData[$meal] ?? 1;
        }
    }
    if (count($countsPerRecipe) === 0) {
        // No recipes — return defaults.
        return array_values($constants_nutritients);
    }

    $recipes = Receta::whereIn('id', array_keys($countsPerRecipe))->get()->keyBy('id');

    $totals = []; // nutrientId => ['id','nombre','unidad_medida','cantidad','percentage','mcolor']
    $pieTotal = 0.0;

    foreach ($countsPerRecipe as $recipeId => $count) {
        $recipe = $recipes[$recipeId] ?? null;
        if (!$recipe) continue;

        $racion = (float) ($recipeRacionMapping[$recipeId] ?? 1);
        $multiplier = $racion * (float) max(1, (int) $count);

        // Prefer using precomputed `nutrient_info` (JSON column) to avoid expensive
        // relationship loading inside `getInformacionNutrimental()`.
        $info = $recipe->nutrient_info;
        if (!is_array($info) || empty($info)) {
            $nutrientes = $recipe->getInformacionNutrimental();
            $info = $nutrientes['info'] ?? [];
        }

        foreach ($info as $nut) {
            if (!isset($nut['id']) || !in_array($nut['id'], $visible_info)) {
                continue;
            }

            $nutId = (string) $nut['id'];
            $amount = ((float) ($nut['cantidad'] ?? 0)) * $multiplier;
            $unit = (string) ($nut['unidad_medida'] ?? '');
            $name = (string) ($nut['nombre'] ?? '');

            // Prefer constant color if present for this nutrient.
            $mcolor = $nut['color'] ?? '';
            $needed_color = array_filter(
                $constants_nutritients,
                function ($e) use ($nutId) {
                    return (string) ($e['id'] ?? '') === (string) $nutId;
                }
            );
            if ($needed_color) {
                $mcolor = array_values($needed_color)[0]['color'] ?? $mcolor;
            }

            if (!isset($totals[$nutId])) {
                $totals[$nutId] = [$nutId, $name, $unit, 0.0, 0.0, $mcolor];
            }
            $totals[$nutId][3] += $amount;

            if (in_array($nutId, ['96', '97', '99'], true)) {
                $pieTotal += $amount;
            }
        }
    }

    // Fill percentages for macros.
    if ($pieTotal > 0) {
        foreach (['96', '97', '99'] as $macroId) {
            if (isset($totals[$macroId])) {
                $totals[$macroId][4] = 100 * ($totals[$macroId][3] / $pieTotal);
            }
        }
    }

    return array_values($totals);
}

/**
 * Return per-nutrient per-recipe breakdown for a calendar day.
 *
 * Output shape:
 * [
 *   nutrientId => [
 *     'unidad_medida' => 'g',
 *     'recipes' => [
 *       ['id' => 123, 'titulo' => '...', 'cantidad' => 12.3, 'unidad_medida' => 'g'],
 *       ...
 *     ]
 *   ],
 *   ...
 * ]
 */
function getDayNutritionRecipeBreakdown($daykey, $calendar, $visible_info): array
{
    $cMains = json_decode($calendar->main_schedule, true) ?? [];
    $cSides = json_decode($calendar->sides_schedule, true) ?? [];
    $cMracion = json_decode($calendar->main_racion, true) ?? [];
    $cSracion = json_decode($calendar->sides_racion, true) ?? [];

    $mainDayData = $cMains[$daykey] ?? [];
    $sidesDayData = $cSides[$daykey] ?? [];
    $mainRacionDayData = $cMracion[$daykey] ?? [];
    $sidesRacionDayData = $cSracion[$daykey] ?? [];

    $recipeRacionMapping = [];
    foreach ($mainDayData as $meal => $id) {
        $recipeRacionMapping[$id] = $mainRacionDayData[$meal] ?? 1;
    }
    foreach ($sidesDayData as $meal => $id) {
        $recipeRacionMapping[$id] = $sidesRacionDayData[$meal] ?? 1;
    }

    $allRecipeIds = array_values(array_filter((([...array_values($mainDayData), ...array_values($sidesDayData)]))));
    if (count($allRecipeIds) === 0) {
        return [];
    }

    $countsPerRecipe = array_count_values($allRecipeIds);
    $recipes = Receta::whereIn('id', array_keys($countsPerRecipe))->get()->keyBy('id');

    $breakdown = [];
    foreach ($countsPerRecipe as $recipeId => $count) {
        $recipe = $recipes[$recipeId] ?? null;
        if (!$recipe) {
            continue;
        }

        $racion = $recipeRacionMapping[$recipeId] ?? 1;
        $nutrientes = $recipe->getInformacionNutrimental();
        $info = $nutrientes['info'] ?? [];

        for ($i = 0; $i < $count; $i++) {
            foreach ($info as $nut) {
                if (!isset($nut['id']) || !in_array($nut['id'], $visible_info)) {
                    continue;
                }

                $nutId = (string) $nut['id'];
                $unit = (string) ($nut['unidad_medida'] ?? '');
                $amount = (float) ($nut['cantidad'] ?? 0) * (float) ($racion ?: 1);

                if (!isset($breakdown[$nutId])) {
                    $breakdown[$nutId] = [
                        'unidad_medida' => $unit,
                        'recipes' => [],
                    ];
                }

                if (!isset($breakdown[$nutId]['recipes'][$recipeId])) {
                    $breakdown[$nutId]['recipes'][$recipeId] = [
                        'id' => (int) $recipe->id,
                        'titulo' => (string) ($recipe->titulo ?? ''),
                        'cantidad' => 0.0,
                        'unidad_medida' => $unit,
                    ];
                }

                $breakdown[$nutId]['recipes'][$recipeId]['cantidad'] += $amount;
            }
        }
    }

    // Normalize maps to lists + stable sort (highest contribution first).
    foreach ($breakdown as $nutId => $data) {
        $recipesList = array_values($data['recipes'] ?? []);
        usort($recipesList, function ($a, $b) {
            return ((float) ($b['cantidad'] ?? 0)) <=> ((float) ($a['cantidad'] ?? 0));
        });
        $breakdown[$nutId]['recipes'] = $recipesList;
    }

    return $breakdown;
}
function todaySpanishDay()
{
    $day = date('l',time());
    switch ($day) {
        case "Monday":
          $day_string = "Lunes";
          break;
        case "Tuesday":
          $day_string = "Martes";
          break;
        case "Wednesday":
          $day_string = "Miércoles";
          break;
        case "Thursday":
          $day_string = "Jueves";
          break;
        case "Friday":
          $day_string = "Viernes";
          break;
        case "Saturday":
          $day_string = "Sábado";
          break;
        default:
          $day_string = "Domingo";
      }
    $month_date_string = date('M j,Y h:i A',time());
    $final_date_string = $day_string.', '.$month_date_string;
    return $final_date_string;
}
