<?php

namespace App\Http\Controllers\Api\V1\Calendars;

use App\Http\Controllers\Controller;
use App\Http\Resources\Lista\CategoryResource;
use App\Http\Resources\Lista\ListaItemResource;
use App\Models\Calendar;
use App\Models\Categoria;
use App\Models\ListaIngredientes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ListaController extends Controller
{
    private function listaCacheKey(int $calendarId, string $suffix = 'all', ?string $calendarUpdatedAt = null): string
    {
        return sprintf(
            'lista:v2:user:%d:calendar:%d:updated:%s:%s',
            (int) Auth::id(),
            $calendarId,
            $calendarUpdatedAt ?: 'na',
            $suffix
        );
    }

    private function forgetListaCache(Calendar $calendar): void
    {
        $baseUpdatedAt = optional($calendar->updated_at)->timestamp ?: 'na';
        Cache::forget($this->listaCacheKey((int) $calendar->id, 'all', (string) $baseUpdatedAt));

        $categoryIds = Categoria::pluck('id');
        foreach ($categoryIds as $categoryId) {
            Cache::forget($this->listaCacheKey((int) $calendar->id, 'category:' . (int) $categoryId, (string) $baseUpdatedAt));
        }
    }

    /**
     * Get all ingredients for a calendar's lista.
     * Returns ingredients grouped by categories with taken status.
     */
    public function index(Request $request, int $calendarId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $cacheKey = $this->listaCacheKey(
            (int) $calendar->id,
            'all',
            (string) (optional($calendar->updated_at)->timestamp ?: 'na')
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($calendar) {
            $takenIngredients = DB::table('lista_ingrediente_taken')
                ->where('calendario_id', $calendar->id)
                ->get();

            $categorias = Categoria::orderBy('sort', 'ASC')->get();
            $listaIngredientes = ListaIngredientes::where('calendario_id', $calendar->id)->get();

            $ingredients = [];
            $totalCount = 0;
            foreach ($categorias as $category) {
                $categoryIngredients = getRelatedIngrediente($calendar->id, $category->id, 'list');
                $ingredients[$category->id] = $categoryIngredients;
                $totalCount += count($categoryIngredients);
            }

            return [
                'calendar' => [
                    'id' => $calendar->id,
                    'title' => $calendar->title,
                ],
                'categories' => CategoryResource::collection($categorias)->resolve(),
                'ingredients' => $ingredients,
                'taken_ingredients' => $takenIngredients,
                'custom_items' => ListaItemResource::collection($listaIngredientes)->resolve(),
                'total_count' => $totalCount,
            ];
        });

        return response()->json($payload);
    }

    /**
     * Get ingredients for a specific category.
     */
    public function category(Request $request, int $calendarId, int $categoryId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $cacheKey = $this->listaCacheKey(
            (int) $calendar->id,
            'category:' . (int) $categoryId,
            (string) (optional($calendar->updated_at)->timestamp ?: 'na')
        );

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($calendar, $categoryId) {
            $takenIngredients = DB::table('lista_ingrediente_taken')
                ->where('calendario_id', $calendar->id)
                ->get();

            $category = Categoria::findOrFail($categoryId);

            $listaIngredientes = ListaIngredientes::where('categoria', $categoryId)
                ->where('calendario_id', $calendar->id)
                ->get();

            $ingredients = getRelatedIngrediente($calendar->id, $categoryId, 'list');

            $ingredientsDataSorted = [];
            if ($calendar->labels) {
                $calendarLabels = json_decode($calendar->labels, true);
                if (isset($calendarLabels['days'])) {
                    foreach ($calendarLabels['days'] as $dayKey => $dayValue) {
                        foreach ($ingredients as $ingredient) {
                            if ($ingredient['day'] == $dayKey) {
                                $ingredientsDataSorted[$dayKey][] = $ingredient;
                                if (isset($ingredient['repeat'])) {
                                    foreach ($ingredient['repeat'] as $repeat) {
                                        if ($dayKey == $repeat['day']) {
                                            $ingredientsDataSorted[$dayKey][] = $repeat;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return [
                'calendar_id' => $calendar->id,
                'category' => (new CategoryResource($category))->resolve(),
                'ingredients' => $ingredients,
                'ingredients_sorted' => $ingredientsDataSorted,
                'taken_ingredients' => $takenIngredients,
                'custom_items' => ListaItemResource::collection($listaIngredientes)->resolve(),
                'count' => count($ingredients),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Toggle an ingredient as taken/unchecked.
     */
    public function toggleTaken(Request $request, int $calendarId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'categoria_id' => 'required|integer|exists:categorias,id',
            'ingrediente_id' => 'required|integer',
            'ingrediente_type' => 'required|string',
        ]);

        $taken = DB::table('lista_ingrediente_taken')
            ->where([
                'calendario_id' => $calendar->id,
                'categoria_id' => $validated['categoria_id'],
                'ingrediente_id' => $validated['ingrediente_id'],
                'ingrediente_type' => $validated['ingrediente_type'],
            ]);

        $action = 'created';
        
        if ($taken->exists()) {
            $taken->delete();
            $action = 'deleted';
        } else {
            DB::table('lista_ingrediente_taken')->insert([
                'calendario_id' => $calendar->id,
                'categoria_id' => $validated['categoria_id'],
                'ingrediente_id' => $validated['ingrediente_id'],
                'ingrediente_type' => $validated['ingrediente_type'],
            ]);
        }

        // Get updated taken list
        $takenIngredients = DB::table('lista_ingrediente_taken')
            ->where('calendario_id', $calendar->id)
            ->get();

        $this->forgetListaCache($calendar);

        return response()->json([
            'success' => true,
            'action' => $action,
            'taken_ingredients' => $takenIngredients,
            'message' => $action === 'created' ? 'Ingredient marked as taken' : 'Ingredient unmarked',
        ]);
    }

    /**
     * Create a custom lista ingredient.
     */
    public function storeCustom(Request $request, int $calendarId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'cantidad' => 'required|numeric',
            'unidad_medida' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|integer|exists:categorias,id',
        ]);

        $listaItem = ListaIngredientes::create([
            'calendario_id' => $calendar->id,
            'cantidad' => $validated['cantidad'],
            'unidad_medida' => $validated['unidad_medida'],
            'nombre' => $validated['nombre'],
            'categoria' => $validated['categoria'],
        ]);

        $this->forgetListaCache($calendar);

        return response()->json([
            'success' => true,
            'item' => new ListaItemResource($listaItem),
            'message' => 'Custom ingredient added successfully',
        ], 201);
    }

    /**
     * Update a custom lista ingredient.
     */
    public function updateCustom(Request $request, int $calendarId, int $itemId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'cantidad' => 'required|numeric',
            'unidad_medida' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|integer|exists:categorias,id',
        ]);

        $listaItem = ListaIngredientes::where('id', $itemId)
            ->where('calendario_id', $calendar->id)
            ->firstOrFail();

        $listaItem->update([
            'cantidad' => $validated['cantidad'],
            'unidad_medida' => $validated['unidad_medida'],
            'nombre' => $validated['nombre'],
            'categoria' => $validated['categoria'],
        ]);

        $this->forgetListaCache($calendar);

        return response()->json([
            'success' => true,
            'item' => new ListaItemResource($listaItem),
            'message' => 'Custom ingredient updated successfully',
        ]);
    }

    /**
     * Delete a custom lista ingredient.
     */
    public function destroyCustom(int $calendarId, int $itemId): JsonResponse
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $listaItem = ListaIngredientes::where('id', $itemId)
            ->where('calendario_id', $calendar->id)
            ->firstOrFail();

        $listaItem->delete();

        $this->forgetListaCache($calendar);

        return response()->json([
            'success' => true,
            'message' => 'Custom ingredient deleted successfully',
        ]);
    }
}
