<?php

namespace App\Models;

use App\Models\Nutriente;
use Arr;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use PhpUnitsOfMeasure\PhysicalQuantity\Volume;
use App\Support\Base64Image;
use App\Services\NutritionCalculator;

class Receta extends Model
{
    use CrudTrait;
    use SoftDeletes;
    use Sluggable;
    use Searchable;

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('active', function ($query) {
            return $query->where('active', true);
        });
    }
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
     */

    protected $table = 'recetas';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    //protected $fillable = ['tiempo', 'instrucciones', 'tips', 'porciones', 'imagen_principal'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
        'nutrient_info' => 'array',
        'nutrient_data' => 'array',
        'calories' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
     */

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'titulo',
            ],
        ];
    }

    // public function getTips(){
    //   $tips = '';
    //   foreach(preg_split('/\n|\r\n?/', $this->tips) as $tip){
    //     if(strpos($tip, 'receta[') !== false){
    //       $id = explode(']', explode('[', $tip)[1])[0];
    //       $receta = App\Models\Receta::find($id);
    //       $tips .= '<a target="_blank" href="$receta ? backpack_url("Recetas")."?receta=".$receta->id : "No se encontró la receta""> $receta ? "Receta: " . $receta->titulo : "No se encontró la receta"</a>';
    //     }
    //         else{
    //       $tips .= $tip;
    //     }
    //   }
    //
    //   return $tips;
    // }

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->only('titulo');
        // If you want, apply the default transformations
        $array = $this->transform($array);
        // Apply custom treatment
        return $array;
    }

    public function getCantidadIngredientes()
    {
        return count($this->recetaInstruccionReceta);
    }

    public function getInstrucciones()
    {
        $instru = preg_split('/\n|\r\n?/', $this->instrucciones);

        $instrucciones = array();

        foreach ($instru as $key => $instruccion) {
            if ($instruccion != null && $instruccion != '') {
                $instrucciones[] = $instruccion;
            }
        }

        return $instrucciones;
    }

    public function getIngredientes($solo_ingredientes = false)
    {
        $rirs = $this->recetaInstruccionReceta;
        $ingredientes = array();

        foreach ($rirs as $key => $rir) {
            if ($rir->instruccion) {
                if ($rir->instruccion->ingrediente) {
                    $ingredientes[] = array(
                        'ingrediente_id' => $rir->instruccion->ingrediente->id,
                        'receipe_id' => $this->id,
                        'categoria_id' => $rir->instruccion->ingrediente->categoria_id,
                        'ingrediente' => $rir->instruccion->ingrediente->nombre . ' ' . ($rir->instruccion->nombre == 'NA' ? '' : '(' . $rir->instruccion->nombre . ')'),
                        'ingred_uid' => 'ingred' . $rir->instruccion->ingrediente->id,
                        //'ingrediente' => $rir->instruccion->ingrediente->nombre . ' ' . ($rir->instruccion->nombre == 'NA' ? '' : '' . $rir->instruccion->nombre . ''),
                        'medida' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura : '',
                        'medida_plural' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura_plural : '',
                        'medida_english' => $rir->rirm[0] ? ($rir->rirm[0]->medida->nombre_english ? $rir->rirm[0]->medida->nombre_english : 'NA') : '',
                        'cantidad' => $rir->rirm[0] ? $rir->rirm[0]->cantidad : '',
                        'tipo_medida_id' => $rir->rirm[0] ? $rir->rirm[0]->medida->tipo_medida_id : '',
                        'nota' => $rir->rirm[0] ? $rir->nota : '',
                        'info_nutrimental' => $rir->instruccion->ingrediente->fdc_raw ? json_decode($rir->instruccion->ingrediente->fdc_raw, true) : [],
                        'es_ingrediente' => true,
                        'orden' => $rir->instruccion->ingrediente->orden,
                        'type' => 'ingredientes',
                        'sub-url' => '',
                        'nombre_english' => ''
                    );
                }
            } else if ($rir->subreceta) {
                // if($rir->rirm[0]->medida->id == 10) {
                if (!$solo_ingredientes) {
                    // dd($rir->rirm[0]);
                    $ingredientes[] = array(
                        'ingrediente' => '<a target="_blank" href="/receta/' . $rir->subreceta->slug . '">' . $rir->subreceta->titulo . '</a>',
                        'receipe_id' => $this->id,
                        'categoria_id' => $rir->subreceta->categoria_id,
                        'ingred_uid' => 'subrecipe' . $rir->subreceta->id,
                        'medida' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura : '',
                        'medida_plural' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura_plural : '',
                        'medida_english' => $rir->rirm[0] ? ($rir->rirm[0]->medida->nombre_english ? $rir->rirm[0]->medida->nombre_english : 'NA') : '',
                        'tipo_medida_id' => $rir->rirm[0] ? $rir->rirm[0]->medida->tipo_medida_id : '',
                        'cantidad' => $rir->rirm[0] ? $rir->rirm[0]->cantidad : '',
                        'nota' => $rir->rirm[0] ? $rir->nota : '',
                        'info_nutrimental' => [],
                        'es_ingrediente' => false,
                        'type' => 'subrecipe',
                        'sub-url' => url('/receta/' . $rir->subreceta->slug),
                        'nombre_english' => $rir->subreceta->getPorciones()['nombre_english']

                        // 'orden' => $rir->instruccion->ingrediente->orden,
                    );
                }
                // }
            }
        }

        return $ingredientes;
    }
    public function getIngredientesIds($solo_ingredientes = false)
    {
        $rirs = $this->recetaInstruccionReceta;
        $ingredientes = array();
        foreach ($rirs as $key => $rir) {
            if ($rir->instruccion) {
                if ($rir->instruccion->ingrediente) {
                    $ingredientes[] = array(
                        'ingrediente_id' => $rir->instruccion->ingrediente->id,
                    );
                }
            }
        }
        return $ingredientes;
    }
    public function getCategoriaIngredientes($categoria_id = false, $meal, $day, $schedule, $get_servings, $porcion, $solo_ingredientes = false, $subrecetas = '')
    {
        $rirs = $this->recetaInstruccionReceta;
        $ingredientes = array();
        $subreceta_ingres = [];
        foreach ($rirs as $key => $rir) {
            if ($rir->instruccion) {
                if ($rir->instruccion->ingrediente && $rir->instruccion->ingrediente->categoria_id == $categoria_id) {
                    if ($rir->instruccion->ingrediente->id == '378') {
                        // $rir->rirm[0]->cantidad = $rir->instruccion->equivalencia_gramos;
                        // $rir->rirm[0]->medida->abreviatura = 'g';
                        // $rir->rirm[0]->medida->abreviatura_plural = 'g';
                        // $rir->rirm[0]->medida->nombre_english = 'gram';
                        // $rir->rirm[0]->medida->tipo_medida_id = '2';
                    } elseif ($rir->instruccion->ingrediente->id == '379' || $rir->instruccion->ingrediente->id == '377') {
                        // $rir->rirm[0]->cantidad = $rir->instruccion->equivalencia_gramos;
                        // $rir->rirm[0]->medida->abreviatura = 'g';
                        // $rir->rirm[0]->medida->abreviatura_plural = 'g';
                        // $rir->rirm[0]->medida->nombre_english = 'gram';
                        // $rir->rirm[0]->medida->tipo_medida_id = '2';
                    }
                    $ingredientes[] = array(
                        'ingrediente_id' => $rir->instruccion->ingrediente->id,
                        'receipe_id' => $this->id,
                        'receipe_name' => $this->titulo,
                        'categoria_id' => $rir->instruccion->ingrediente->categoria_id,
                        'ingrediente' => $rir->instruccion->ingrediente->nombre,
                        'equivalencia_gramos' => /*$rir->instruccion->ingrediente->id == '378' ? $rir->instruccion->equivalencia_gramos :*/ $rir->instruccion->equivalencia_gramos,
                        'equivalencia_gramos_unit' => $rir->instruccion->medida->nombre_english,
                        'ingred_uid' => 'ingred' . $rir->instruccion->ingrediente->id,
                        //'ingrediente' => $rir->instruccion->ingrediente->nombre . ' ' . ($rir->instruccion->nombre == 'NA' ? '' : '' . $rir->instruccion->nombre . ''),
                        'medida' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura : '',
                        'medida_plural' => $rir->rirm[0] ? $rir->rirm[0]->medida->abreviatura_plural : '',
                        'medida_english' => $rir->rirm[0] ? ($rir->rirm[0]->medida->nombre_english ? $rir->rirm[0]->medida->nombre_english : 'NA') : '',
                        'cantidad' => $rir->rirm[0] ? $rir->rirm[0]->cantidad : '',
                        'tipo_medida_id' => $rir->rirm[0] ? $rir->rirm[0]->medida->tipo_medida_id : '',
                        'nota' => $rir->rirm[0] ? $rir->nota : '',
                        //'info_nutrimental' => $rir->instruccion->ingrediente->fdc_raw ? json_decode($rir->instruccion->ingrediente->fdc_raw, true) : [],
                        'es_ingrediente' => true,
                        'porcion' => $porcion,
                        'meal' => $meal,
                        'day' => $day,
                        'schedule' => $schedule,
                        'get_servings' => $get_servings,
                        'subrecipe' => $subrecetas,
                        'orden' => $rir->instruccion->ingrediente->orden,
                    );
                }
            } else if ($rir->subreceta) {
                $subCantidad = $rir->rirm[0] && is_numeric($rir->rirm[0]->cantidad) ? (float) $rir->rirm[0]->cantidad : 0.0;
                $subMedida = $rir->rirm[0] && $rir->rirm[0]->medida ? $rir->rirm[0]->medida->abreviatura : '';
                $subMedidaPlural = $rir->rirm[0] && $rir->rirm[0]->medida ? $rir->rirm[0]->medida->abreviatura_plural : '';
                $subMedidaEnglish = $rir->rirm[0] && $rir->rirm[0]->medida ? ($rir->rirm[0]->medida->nombre_english ?: 'NA') : '';
                $subTipoMedidaId = $rir->rirm[0] && $rir->rirm[0]->medida ? $rir->rirm[0]->medida->tipo_medida_id : '';

                if ($subTipoMedidaId == 1 && $rir->subreceta) {
                    $subrecetaPorcionNombre = $rir->subreceta->getPorciones()['nombre_english'] ?? '';
                    if ($subrecetaPorcionNombre == 'cup') {
                        if ($subMedidaEnglish == 'tablespoon') {
                            $subCantidad = $subCantidad / 16;
                        } elseif ($subMedidaEnglish == 'teaspoon') {
                            $subCantidad = $subCantidad / 48;
                        }
                        $subMedida = 'tz';
                        $subMedidaPlural = 'tzs';
                        $subMedidaEnglish = 'cup';
                    } elseif ($subrecetaPorcionNombre == 'tablespoon') {
                        if ($subMedidaEnglish == 'teaspoon') {
                            $subCantidad = $subCantidad / 3;
                        }
                        $subMedida = 'cda';
                        $subMedidaPlural = 'cdas';
                        $subMedidaEnglish = 'tablespoon';
                    }
                }
                $subreceta = [
                    'ingred_uid' => 'subrecipe' . $rir->subreceta->id,
                    'subrecipe_id' => $rir->subreceta->id,
                    'subrecipe_title' => $rir->subreceta->titulo,
                    'medida' => $subMedida,
                    'medida_plural' => $subMedidaPlural,
                    'medida_english' => $subMedidaEnglish,
                    'tipo_medida_id' => $subTipoMedidaId,
                    'cantidad' => $subCantidad,
                    'porcion' => $rir->subreceta->getPorciones()['cantidad'],
                    'nombre_english' => $rir->subreceta->getPorciones()['nombre_english'],
                ];
                $subreceta_ingres = $rir->subreceta->getCategoriaIngredientes($categoria_id, $meal, $day, $schedule, $get_servings, $porcion, $solo_ingredientes = false, $subreceta);
                $sub_receta = Receta::where('id', $rir->receta_id)->firstOrFail();
                $recipe_porcion = $sub_receta->getPorciones()['cantidad'];
                $get_recipe_cantidad = round($subCantidad * $get_servings / $recipe_porcion, 2);
                $numpart = explode(".", $get_recipe_cantidad);
                //sub recipe serving
                if (isset($numpart[1])) {
                    if ($numpart[1] > 80 && $numpart[1] <= 99) {
                        $get_recipe_cantidad = round(
                            $get_recipe_cantidad
                        );
                    }
                }
                if (!$solo_ingredientes && $subreceta_ingres) {
                    foreach ($subreceta_ingres as $key => $subreceta_ingre) {
                        
                        ///  dd($subreceta_ingre['ingrediente_id']);
                        $ingredientes[] = array(
                            'ingrediente_id' => $subreceta_ingre['ingrediente_id'],
                            'receipe_id' => $subreceta_ingre['receipe_id'],
                            'receipe_name' =>$subreceta_ingre['receipe_name'],
                            'categoria_id' => $subreceta_ingre['categoria_id'],
                            'ingrediente' => $subreceta_ingre['ingrediente'],
                            'equivalencia_gramos' => $subreceta_ingre['equivalencia_gramos'],
                            'equivalencia_gramos_unit' => $subreceta_ingre['equivalencia_gramos_unit'],
                            'ingred_uid' => $subreceta_ingre['ingred_uid'],
                            //'ingrediente' => $rir->instruccion->ingrediente->nombre . ' ' . ($rir->instruccion->nombre == 'NA' ? '' : '' . $rir->instruccion->nombre . ''),
                            'medida' => $subreceta_ingre['medida'],
                            'medida_plural' => $subreceta_ingre['medida_plural'],
                            'medida_english' => $subreceta_ingre['medida_english'],
                            'cantidad' => $subreceta_ingre['cantidad'],
                            'tipo_medida_id' => $subreceta_ingre['tipo_medida_id'],
                            'nota' => $subreceta_ingre['nota'],
                            //'info_nutrimental' => $rir->instruccion->ingrediente->fdc_raw ? json_decode($rir->instruccion->ingrediente->fdc_raw, true) : [],
                            'es_ingrediente' => $subreceta_ingre['es_ingrediente'],
                            'porcion' => $subreceta_ingre['porcion'],
                            'meal' => $subreceta_ingre['meal'],
                            'day' => $subreceta_ingre['day'],
                            'schedule' => $subreceta_ingre['schedule'],
                            't1' => $recipe_porcion,
                            't2' => $subreceta_ingre['subrecipe']['cantidad'],
                            't3' => $get_servings,
                            'get_servings' => $get_recipe_cantidad,
                            'subrecipe' => $subreceta_ingre['subrecipe'],
                            'orden' => $subreceta_ingre['orden'],
                        );

                    }
                }
            }
        }
        // dd($ingredientes);
        //$ingre_merge = array_merge($ingredientes, $subreceta_ingres);

        return $ingredientes;
    }

    public function getPorciones($active = true)
    {
        $resultados = $this->recetaResultados()->get();

        // dump($resultados);

        $returnArray = array();

        $returnArray['cantidad'] = 2;
        $returnArray['step'] = 1;
        $returnArray['nombre'] = 'Porción';
        $returnArray['nombre_plural'] = 'Porciones';
        $returnArray['nombre_english'] = 'Servings';
        $returnArray['tipo_medida_id'] = '0';

        $existeActivo = false;
        //Revisar activo
        if ($active === true) {
            // dump("hola");
            foreach ($resultados as $key => $resultado) {
                if ($resultado->active) {
                    // dd($resultado->medida);
                    $existeActivo = true;
                    $returnArray['cantidad'] = $resultado->cantidad;
                    $returnArray['step'] = $resultado->medida->tipoMedida->step;
                    $returnArray['nombre'] = $resultado->medida->nombre;
                    $returnArray['nombre_plural'] = $resultado->medida->nombre_plural;
                    $returnArray['nombre_english'] = $resultado->medida->nombre_english;
                    $returnArray['tipo_medida_id'] = $resultado->medida->tipo_medida_id;;

                }
            }
            if (!$existeActivo) {
                //Si No hay Activo
                foreach ($resultados as $key => $resultado) {
                    if ($resultado->medida_id == 10) {
                        $returnArray['cantidad'] = $resultado->cantidad;
                        $returnArray['step'] = $resultado->medida->tipoMedida->step;
                        $returnArray['nombre'] = $resultado->medida->nombre;
                        $returnArray['nombre_plural'] = $resultado->medida->nombre_plural;
                        $returnArray['nombre_english'] = $resultado->medida->nombre_english;
                        $returnArray['tipo_medida_id'] = $resultado->medida->tipo_medida_id;;

                    }
                }
            }
        } else {
            // dump($resultados);
            $resultados = $this->recetaResultados;
            foreach ($resultados as $key => $resultado) {
                // dump("aqui 2");
                // dump($resultado);
                if ($active === false) {
                    if ($resultado->medida_id == 10) {
                        $existeActivo = true;
                        $returnArray['cantidad'] = $resultado->cantidad;
                        $returnArray['step'] = $resultado->medida->tipoMedida->step;
                        $returnArray['nombre'] = $resultado->medida->nombre;
                        $returnArray['nombre_plural'] = $resultado->medida->nombre_plural;
                        $returnArray['nombre_english'] = $resultado->medida->nombre_english;
                        $returnArray['tipo_medida_id'] = $resultado->medida->tipo_medida_id;;
                    }
                } else {
                    if ($resultado->medida_id == $active) {
                        $existeActivo = true;
                        $returnArray['cantidad'] = $resultado->cantidad;
                        $returnArray['step'] = $resultado->medida->tipoMedida->step;
                        $returnArray['nombre'] = $resultado->medida->nombre;
                        $returnArray['nombre_plural'] = $resultado->medida->nombre_plural;
                        $returnArray['nombre_english'] = $resultado->medida->nombre_english;
                        $returnArray['tipo_medida_id'] = $resultado->medida->tipo_medida_id;;
                    }
                }
            }
            if (!$existeActivo) {
                //Si No hay Activo
                foreach ($resultados as $key => $resultado) {
                    // dump("aqui");
                    // dump($resultado);

                    $returnArray['cantidad'] = $resultado->cantidad;
                    $returnArray['step'] = $resultado->medida->tipoMedida->step;
                    $returnArray['nombre'] = $resultado->medida->nombre;
                    $returnArray['nombre_plural'] = $resultado->medida->nombre_plural;
                    $returnArray['nombre_english'] = $resultado->medida->nombre_english;
                    $returnArray['tipo_medida_id'] = $resultado->medida->tipo_medida_id;
                }
            }
        }
        // dump($returnArray);
        return $returnArray;
    }

    public function getPorcionesList()
    {
        $resultados = $this->recetaResultados;

        $returnArray = array();

        foreach ($resultados as $key => $resultado) {
            $returnArrayTmp['cantidad'] = $resultado->cantidad;
            $returnArrayTmp['step'] = $resultado->medida->tipoMedida->step;
            $returnArrayTmp['nombre'] = $resultado->medida->nombre;
            $returnArrayTmp['nombre_plural'] = $resultado->medida->nombre_plural;
            $returnArrayTmp['active'] = $resultado->active;
            $returnArray[] = $returnArrayTmp;
        }

        return $returnArray;
    }

    public function getHasSubrecetaAttribute()
    {
        $rir = $this->recetaInstruccionReceta;
        foreach ($rir as $r) {
            if ($r->subreceta) {
                return true;
            }
        }
        return false;
    }

    /**
     * New entrypoint for nutrition calculation (legacy-compatible).
     *
     * This does NOT modify the legacy getInformacionNutrimental() implementation;
     * it only provides a stable API so we can refactor the internals incrementally.
     *
     * @return array{info: array, debug: array, name: string|null}
     */
    public function calculateNutritionInfo(
        int $porcentajeOverride = 100,
        int $porcionesOverride = 0,
        int $numeroDivision = 0,
        int $porcionesDivision = 0,
    ): array {
        return app(NutritionCalculator::class)->calculateUsingLegacy(
            $this,
            $porcentajeOverride,
            $porcionesOverride,
            $numeroDivision,
            $porcionesDivision,
        );
    }

    public function getInformacionNutrimental($porcentajeOverride = 100, $porcionesOverride = 0, $numeroDivision = 0, $porcionesDivision = 0)
    {

        //When i wrote this code, only God and I understood what it did, now only god knows
        $nutrientInfo = [];
        $nutrientInfoArray = [];
        $data = [];
        $debugInfo = [];

        $nutrientes = Nutriente::all();
        // $nutrientes = Nutriente::where('mostrar','=',1)->get();
        // dd($nutrientes);
        $rir = $this->recetaInstruccionReceta;
        $totalGrams = 0;
        $otherRecipesTable = array();

        foreach ($rir as $r) {
            if ($r->instruccion) {
                // dd($r->instruccion);
                // dump($porcentajeOverride);
                if ($porcentajeOverride == 100) {
                    $totalGrams = $totalGramsDebug = $r->getGramosTotales(); //SE OBTIENE LOS GRAMOS POR CADA INSTRUCCION MULTIPLICADO POR LA CANTIDAD SOLICITADA DE CADA INSTRUCCION
                    $totalGrams = $totalGramsDebugPorciones = $totalGrams / $this->getPorciones(false)['cantidad']; //DIVISION ENTRE CANTIDAD DE PORCIONES YA QUE LA TABLA MUESTRA LA INFORMACION NUTRIMENTAL POR PORCION
                    $totalGrams = $totalGrams * ($porcentajeOverride / 100);
                    $totalGramsDebug .= " - Receta - Porciones: " . $this->getPorciones(false)['cantidad'];
                } else {
                    // dump("recursivo - ".$porcentajeOverride.' - '.$this->getPorciones(false)['cantidad']);

                    $totalGrams = $totalGramsDebug = $r->getGramosTotales();
                    $totalGrams = $totalGrams / (1 / ($porcentajeOverride / 100));
                    $totalGrams = $totalGramsDebugPorciones = $totalGrams / $porcionesOverride;

                    $totalGramsDebug .= " - Subreceta - Porciones: " . $porcionesOverride . ' - Porcentaje - ' . $porcentajeOverride;
                }

                $fdcRaw = json_decode($r->instruccion->ingrediente->fdc_raw, true);

                if ($fdcRaw && !empty($fdcRaw)) {
                    $foodNutrients = $fdcRaw['foodNutrients'];
                    foreach ($foodNutrients as $foodNutrient) {
                        // dd($foodNutrients);
                        if (isset($foodNutrient['amount'])) {
                            // dd($foodNutrient);

                            $id = $foodNutrient['nutrient']['id']; //ID DEL NUTRIENTE
                            $name = $foodNutrient['nutrient']['name']; //ID DEL NUTRIENTE
                            // $valor =  ['cantidad' => $obj['nutrient']['number'], 'unidad' => $obj['nutrient']['unitName']];//CANTIDAD DEL NUTRIENTE Y SU UNIDAD DE MEDIDA ENCONTRADO EN EL JSON RAW
                            $baseGram = 100;
                            $totalNutrientValue = $totalGrams * $foodNutrient['amount'] / $baseGram;

                            if (isset($nutrientInfoArray[$id])) {
                                $nutrientInfoArray[$id]['cantidad'] += $totalNutrientValue;
                            } else {
                                $nutrientInfoArray[$id] = ['nombre' => $name, 'cantidad' => $totalNutrientValue, 'unidad' => $foodNutrient['nutrient']['unitName']];
                            }

                            if (isset($debugInfo[$r->instruccion->ingrediente->id . '_' . $r->instruccion->ingrediente->nombre]['nutrientes'][$id])) {
                                $debugInfo[$r->instruccion->ingrediente->id . '_' . $r->instruccion->ingrediente->nombre]['nutrientes'][$id]['cantidad'] += $totalNutrientValue;
                            } else {
                                @$debugInfo[$r->instruccion->ingrediente->id . '_' . $r->instruccion->ingrediente->nombre]['nutrientes'][$id] = ['nombre' => $name, 'cantidad' => $totalNutrientValue, 'unidad' => $foodNutrient['nutrient']['unitName'], 'totalGrams' => $totalGrams, 'totalGramsDebug' => $totalGramsDebug, 'totalGramsPorciones' => $totalGramsDebugPorciones];
                            }
                        }
                    }
                } else {
                    // dd($r->instruccion->ingrediente);
                }
            } else if ($r->subreceta) {
                // dump($r);
                // dump($r->rirm[0]);
                $subCreacionFinal = 100;
                $recetaResultadoConversion = 0;
                $recetaResultadoFinal = 0;
                $recetaResultadoFinalEmpty = 0;
                foreach ($r->subreceta->recetaResultados as $recetaResultado) {
                    //Obtener medida compatible
                    if ($recetaResultado->medida_id == $r->rirm[0]->medida_id) {
                        $recetaResultadoFinalEmpty = $recetaResultadoFinal = $recetaResultado;
                    }
                    if ($recetaResultado->medida->tipo_medida_id == $r->rirm[0]->medida->tipo_medida_id) {
                        $recetaResultadoConversion = $recetaResultado;
                    }
                }
                if ($recetaResultadoFinal) {
                    // dump("No es necesario convertir subreceta - ".$r->subreceta->titulo);
                    // dump($this);
                    //cantidad total por porciones
                    // $subporcionPorcion = $r->rirm[0]['cantidad'] / $this->getPorciones($recetaResultadoFinal->medida_id)['cantidad'];
                    // dump($r->rirm[0]['cantidad']);
                    $subporcionPorcion = $r->rirm[0]['cantidad'] / $this->getPorciones($recetaResultadoFinal->medida_id)['cantidad'];
                    $subporcionCreacionFinal = $subporcionPorcion;
                    $subCreacionFinal = $subporcionPorcion * 100 / $recetaResultadoFinal->cantidad * $this->getPorciones($recetaResultadoFinal->medida_id)['cantidad']; //Porcentaje del mediado
                    // dump('deberia ser 5 - '.$r->rirm[0]['cantidad']);
                    // dump($subporcionPorcion);
                    // dump($subCreacion);
                } else if ($recetaResultadoConversion) {
                    // dump("*Es necesario convertir subreceta a medida compatible - ".$r->subreceta->titulo);
                    if ($recetaResultadoConversion->medida->tipo_medida_id == $r->rirm[0]->medida->tipo_medida_id && $r->rirm[0]->medida->tipo_medida_id == 1) {
                        $originalUnit = new Volume($r->rirm[0]->cantidad, $r->rirm[0]->medida->nombre_english);
                        $newQuantity = $originalUnit->toUnit($recetaResultadoConversion->medida->nombre_english);

                        $cantidad = $newQuantity;

                        $subporcionPorcion = $cantidad / $this->getPorciones($recetaResultadoConversion->medida_id)['cantidad'];
                        $subCreacionFinal = $subporcionPorcion * 100 / $recetaResultadoConversion->cantidad * $r->rirm[0]->cantidad; //Porcentaje del mediado

                        // dump($originalUnit,$newQuantity,$this->instruccion->medida,$medida->medida);

                    }
                } else {
                    dump("*Es necesario convertir subreceta a medida compatible, subreceta no compatible - " . $r->subreceta->titulo);
                }
                // dump($r->subreceta->getPorcionesList());
                // dump($r->subreceta->getPorciones(false));
                // dump($this->getPorciones(false)['cantidad']);
                // dump($r->subreceta->getPorciones());
                // dump($subCreacionFinal);
                // dump("cantidad - ".$recetaResultadoFinalEmpty.' - '.$this->getPorciones($recetaResultadoFinalEmpty->medida_id)['cantidad']);
                $otherRecipesTable[] = $r->subreceta->getInformacionNutrimental($subCreacionFinal, $this->getPorciones(false)['cantidad'], $r->rirm[0]['cantidad'], $recetaResultadoFinalEmpty->cantidad);
            }
        }
        // dd("termine");
        // if($dump){
        //     dump($debugInfo);
        // }
        foreach ($nutrientInfoArray as $nutrientId => $nutrientInfo) {
            $color = sprintf('#%06X', rand(0x000000, 0xFFFFFF));
            $porcentaje = 1;
            $cantidad = 0;
            $unidad_medida = 'g';

            $nutriente = $nutrientes->first(function ($item) use ($nutrientId) {
                return $item->fdc_id == $nutrientId;
            });

            // IMPORTANT:
            // We DO NOT auto-create missing nutrients in the DB from FDC.
            // The "guidelines" for percentages (cien_porciento/factor/unidad_nueva) are curated in `nutrientes`.
            // Auto-creating nutrients here explodes the Catalogue->Nutrients list (e.g. 20+ pages) and makes
            // porcentaje meaningless (defaults to 1).
            if ($nutriente) {
                $nutriente->unidad_medida = $nutrientInfo['unidad'];
                $nutriente->save();
            }

            $cantidad = $nutrientInfo['cantidad'];
            $unidad_medida = $nutrientInfo['unidad'];
            // $porcentaje = $cantidad * 100 / $nutriente->cien_porciento;

            // $porcentaje = $porcentaje != 0 ? number_format($porcentaje, 2) : 0;
            // $cantidad = ($cantidad > 0.01 ? number_format($cantidad, 2, '.', ',') : $cantidad);

            $data[$nutrientId] = ['orden' => $nutriente->orden, 'nombre' => $nutriente->nombre, 'porcentaje' => $porcentaje, 'color' => $color, 'cantidad' => $cantidad, 'unidad_medida' => $unidad_medida, 'mostrar' => $nutriente->mostrar];
            if (!$nutriente) {
                $data[$nutrientId] = [
                    'orden' => null,
                    'nombre' => $nutrientInfo['nombre'],
                    'porcentaje' => '-',
                    'color' => $color,
                    'cantidad' => $cantidad,
                    'unidad_medida' => $unidad_medida,
                    'mostrar' => 0,
                ];
            }
        }

        //

        //Unir recipes
        foreach ($otherRecipesTable as $otherRecipeTable) {
            foreach ($otherRecipeTable['info'] as $nutrientId => $nutrientInfo) {

                if ($porcentajeOverride == 100) {
                    if (isset($data[$nutrientId])) {
                        // dd($nutrientInfo);
                        $data[$nutrientId]['cantidad'] += $nutrientInfo['cantidad'] * 1;
                    } else {
                        $data[$nutrientId] = $nutrientInfo;
                        $data[$nutrientId]['cantidad'] = $data[$nutrientId]['cantidad'] * 1;
                    }
                } else {

                    if (isset($data[$nutrientId])) {
                        // dd($nutrientInfo);
                        // $data[$nutrientId]['cantidad'] += $nutrientInfo['cantidad'];
                        $data[$nutrientId]['cantidad'] += ($nutrientInfo['cantidad'] * $numeroDivision / $porcionesOverride);
                    } else {
                        $data[$nutrientId] = $nutrientInfo;
                        $data[$nutrientId]['cantidad'] = $data[$nutrientId]['cantidad'] * $numeroDivision / $porcionesOverride;
                    }
                    // dump("porciones".$porcionesOverride." - recursivo - ".$porcentajeOverride.' - '.$this->getPorciones(false)['cantidad']);

                    // $totalGrams = $totalGramsDebug = $r->getGramosTotales();
                    // $totalGrams = $totalGrams / (1 / ($porcentajeOverride / 100));
                    // $totalGrams = $totalGramsDebugPorciones = $totalGrams / $porcionesOverride;

                    // $totalGramsDebug .= " - Subreceta - Porciones: " . $porcionesOverride . ' - Porcentaje - ' . $porcentajeOverride;
                }

                $debugInfo[$otherRecipeTable['name']]['debug'] = $otherRecipeTable['debug'];
            }
            $debugInfo[$otherRecipeTable['name']]['info'] = $otherRecipeTable['info'];
        }

        //Calcular porcentajes
        foreach ($data as $nutrientId => $nutrientInfo) {
            $color = sprintf('#%06X', rand(0x000000, 0xFFFFFF));
            $porcentaje = 1;
            $cantidad = 0;
            $unidad_medida = 'g';

            $nutriente = $nutrientes->first(function ($item) use ($nutrientId) {
                return $item->fdc_id == $nutrientId;
            });

            $cantidad = $nutrientInfo['cantidad'];

            if ($nutriente) {
                $cienPorciento = $nutriente->getCienPorciento();
                $porcentaje = $cienPorciento > 0 ? ($cantidad * 100 / $cienPorciento) : '-';
            } else {
                $porcentaje = '-';
            }
            // $cantidad = ($cantidad > 0.01 ? number_format($cantidad, 2, '.', ',') : $cantidad);

            $data[$nutrientId]['porcentaje'] = $porcentaje;
            $data[$nutrientId]['id'] = $nutriente?->id;
        }

        //Sumar vitaminas k
        // dd($data);

        // ORDENAR ARRAY POR ATRIBUTO DE ORDEN DE MENOR A MAYOR
        // usort($data, function ($item1, $item2) {
        //     if(!$item1['orden']){
        //         return 1;
        //     }

        //     if(!$item2['orden']){
        //         return -1;
        //     }

        //     return $item1['orden'] <=> $item2['orden'];
        // });

        //ORDENAR ARRAY POR ATRIBUTO DE ORDEN DE MAYOR A MENOR
        // usort($data, function ($item1, $item2) {
        //     if(!$item1['orden']){
        //         return -1;
        //     }
        //     if(!$item2['orden']){
        //         return 1;
        //     }
        //     return $item2['orden'] <=> $item1['orden'];
        // });

        // dd($data);
        if (true) {
            $this->nutrient_info = $data;
            $this->save();
        }
        $data2 = array();
        $data2['info'] = $data;
        $data2['debug'] = $debugInfo;
        $data2['name'] = $this->titulo;
        return $data2;
    }

    public function getTips()
    {
        return $this->resolveTipsReferences(true);
    }

    public function getTipsPlain()
    {
        return $this->resolveTipsReferences(false);
    }

    private function resolveTipsReferences(bool $linkify = true): array
    {
        $tips = preg_split('/\n|\r\n?/', (string) $this->tips);
        $tipsReturn = [];

        foreach ($tips as $tip) {
            $tipsReturn[] = preg_replace_callback('/receta\[(\d+)\]/', function ($matches) use ($linkify) {
                $recipeId = (int) ($matches[1] ?? 0);
                if (!$recipeId) {
                    return $matches[0];
                }

                $recetaTip = self::find($recipeId);
                if (!$recetaTip) {
                    return $matches[0];
                }

                if ($linkify) {
                    return '<a target="_blank" href="/receta/' . $recetaTip->slug . '">' . e($recetaTip->titulo) . '</a>';
                }

                return $recetaTip->titulo;
            }, (string) $tip);
        }

        return $tipsReturn;
    }

    public function getNotasTiempo()
    {
        $notas = [];
        $subrecetas = $this->recetaInstruccionReceta()->where('subreceta_id', 'IS NOT', 'NULL')->get();
        /*$subrecetas = $this->recetaInstruccionReceta()->whereHas('subreceta', function ($q){
        $q->where('tiempo_nota', '!=', null);
        })->get();

        dd($this);
        $this->recetaInstruccionReceta()->whereHas('subreceta', function ($q){
        $q->where('tiempo_nota', '!=', null);
        })->get();*/
        $notas[] = $this->tiempo_nota;
        foreach ($subrecetas as $receta) {
            $notas[] = $receta->subreceta->tiempo_nota . '\\n';
        }
        // dump($notas);
        $notas = explode('+', $notas[0]);
        //dd($notas);
        return $notas;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
     */
    // public function ingredientesRecetas(){
    //   return $this->hasMany('App\Models\IngredienteReceta', 'receta_id');
    // }

    public function imagenes()
    {
        return $this->hasMany('App\Models\ImagenReceta');
    }

    public function tags()
    {
        return $this->belongsToMany('App\Models\Tag', 'receta_tag', 'receta_id', 'tag_id');
    }

    public function tags_business()
    {
        return $this->belongsToMany('App\Models\Tag', 'receta_business_tag', 'receta_id', 'tag_id');
    }

    public function medida()
    {
        return $this->belongsTo('App\Models\Medida', 'medida_id');
    }

    public function recetaResultados()
    {
        return $this->hasMany('App\Models\RecetaResultado')->orderBy('active', 'asc', 'created_by', 'asc')->orderBy('created_at', 'asc');
    }

    public function recetaInstruccionReceta()
    {
        return $this->hasMany('App\Models\RecetaInstruccionReceta')->orderBy('id', 'asc');
    }
    public function recetaInstruccion()
    {
        //return $this->belongsToMany()

    }
    public function recetaIngrediente()
    {
        return $this->recetaInstruccion()->ingrediente();
    }

    public function planes()
    {
        return $this->belongsToMany('App\Models\Plan', 'plan_receta', 'receta_id', 'plan_id');
    }

    public function reactions()
    {
        return $this->hasMany('App\Models\Reaction', 'recipe_id');
    }

    public function comments()
    {
        return $this->belongsToMany('App\Models\Comment', 'comment_receta', 'receta_id', 'comment_id');
    }

    public function bookmarks()
    {
        return $this->hasMany('App\Models\Bookmark', 'receta_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
     */

    /*
    |--------------------------------------------------------------------------
    | ACCESORS
    |--------------------------------------------------------------------------
     */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
     */
    public function getImagenSecundariaAttribute()
    {
        if (isset($this->attributes['imagen_secundaria'])) {
            return Storage::url($this->attributes['imagen_secundaria']);
        } else {
            return null;
        }
    }

    public function setImagenSecundariaAttribute($value)
    {
        $attribute_name = "imagen_secundaria";
        $disk = 'spaces'; // or use your own disk, defined in config/filesystems.php
        $destination_path = "recetas/imagenes"; // path relative to the disk above

        // if the image was erased
        if ($value == null) {
            // delete the image from disk
            if (!empty($this->{$attribute_name})) {
                Storage::disk($disk)->delete($this->{$attribute_name});
            }

            // set null in the database column
            $this->attributes[$attribute_name] = null;
        }

        // if a base64 was sent, store it in the db
        if (Str::startsWith($value, 'data:image')) {
            $jpegBinary = Base64Image::toJpegBinary($value, 90);
            // 1. Generate a filename.
            $filename = md5($value . time()) . '.jpg';
            // 2. Store the image on disk.
            Storage::disk($disk)->put($destination_path . '/' . $filename, $jpegBinary);
            // 3. Save the public path to the database
            // but first, remove "public/" from the path, since we're pointing to it from the root folder
            // that way, what gets saved in the database is the user-accesible URL
            $public_destination_path = \Str::replaceFirst('public/', '', $destination_path);
            $this->attributes[$attribute_name] = $public_destination_path . '/' . $filename;
        }
    }

    public function getImagenPrincipalAttribute()
    {
        if (isset($this->attributes['imagen_principal'])) {
            return Storage::url($this->attributes['imagen_principal']);
        }
        return null;
    }

    public function setImagenPrincipalAttribute($value)
    {
        $attribute_name = "imagen_principal";
        $disk = 'spaces'; // or use your own disk, defined in config/filesystems.php
        $destination_path = "recetas/imagenes"; // path relative to the disk above

        // if the image was erased
        if ($value == null) {
            // delete the image from disk
            if (!empty($this->{$attribute_name})) {
                Storage::disk($disk)->delete($this->{$attribute_name});
            }

            // set null in the database column
            $this->attributes[$attribute_name] = null;
        }

        // if a base64 was sent, store it in the db
        if (Str::startsWith($value, 'data:image')) {
            $jpegBinary = Base64Image::toJpegBinary($value, 90);
            // 1. Generate a filename.
            $filename = md5($value . time()) . '.jpg';
            // 2. Store the image on disk.
            Storage::disk($disk)->put($destination_path . '/' . $filename, $jpegBinary);
            // 3. Save the public path to the database
            // but first, remove "public/" from the path, since we're pointing to it from the root folder
            // that way, what gets saved in the database is the user-accesible URL
            $public_destination_path = \Str::replaceFirst('public/', '', $destination_path);
            $this->attributes[$attribute_name] = $public_destination_path . '/' . $filename;
        }
    }

    public function getLikeReactionsAttribute()
    {
        return Reaction::where('recipe_id', $this->id)->where('is_like', 1)->count();
    }

    public function getDislikeReactionsAttribute()
    {
        return Reaction::where('recipe_id', $this->id)->where('is_like', 0)->count();
    }

    /**
     * Get the current user's reaction to this recipe
     * @return int|null 1 for like, 0 for dislike, null for no reaction
     */
    public function getUserReaction()
    {
        if (!auth()->check()) {
            return null;
        }

        $reaction = Reaction::where('recipe_id', $this->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$reaction) {
            return null;
        }

        return $reaction->is_like ? 1 : 0;
    }

    public function getTituloAttribute()
    {
        return strtoupper($this->attributes['titulo']);
    }

    public function getCaloriesAttribute()
    {
        // Check if nutrient_info exists and has the calories key (1008 is FDC ID for calories)
        if (
            !$this->nutrient_info ||
            !is_array($this->nutrient_info) ||
            !isset($this->nutrient_info[1008]) ||
            !isset($this->nutrient_info[1008]['cantidad'])
        ) {
            return 0;
        }

        return $this->nutrient_info[1008]['cantidad'];
    }
}
