<?php

namespace App\Http\Controllers\Api\V1\Recipes;

use App\Http\Controllers\Controller;
use App\Models\Receta;
use App\Support\NutritionPreferenceSupport;
use App\Services\Calendar\ExternalPdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PdfController extends Controller
{
    private function getUserNutritionalsInfo($user): array
    {
        $nutritionals = DB::table('nutritional_preferences')
            ->where('user_id', $user->id)
            ->first();

        $storedNutritionInfo = !empty($nutritionals->nutritional_info)
            ? json_decode($nutritionals->nutritional_info, true)
            : null;

        return array_map(function ($item) {
            return (object) $item;
        }, array_values(NutritionPreferenceSupport::normalizeNutritionInfo($storedNutritionInfo)));
    }

    private function scaleRecipeIngredients(Receta $recipe, float|int $portion): array
    {
        // Sub-recipes are ingredients too. Passing true filters them out, which
        // made standalone recipe exports differ from calendar recipe pages.
        $ingredients = $recipe->getIngredientes(false);
        $basePortion = max(1, (float) ($recipe->getPorciones()['cantidad'] ?? 1));
        $ratio = max(0, (float) $portion) / $basePortion;

        return array_map(function (array $ingredient) use ($ratio) {
            if (isset($ingredient['cantidad']) && is_numeric($ingredient['cantidad'])) {
                $scaled = (float) $ingredient['cantidad'] * $ratio;
                $ingredient['cantidad'] = $this->formatMixedFraction($scaled);
            }

            return $ingredient;
        }, $ingredients);
    }

    private function formatMixedFraction(float $value): string
    {
        $sign = $value < 0 ? '-' : '';
        $abs = abs($value);
        $whole = (int) floor($abs + 1e-9);
        $fraction = $abs - $whole;

        if ($fraction < 1e-6) {
            return $sign . (string) $whole;
        }

        // Keep recipe PDFs consistent with the approved fraction vocabulary.
        // The previous denominator search allowed 5/16, so 0.30 was exported
        // as 5/16 even though the source quantity was 0.30.
        $fractions = [
            ['numerator' => 1, 'denominator' => 16, 'value' => 1 / 16],
            ['numerator' => 1, 'denominator' => 8, 'value' => 1 / 8],
            ['numerator' => 1, 'denominator' => 4, 'value' => 1 / 4],
            ['numerator' => 1, 'denominator' => 3, 'value' => 1 / 3],
            ['numerator' => 1, 'denominator' => 2, 'value' => 1 / 2],
            ['numerator' => 2, 'denominator' => 3, 'value' => 2 / 3],
            ['numerator' => 3, 'denominator' => 4, 'value' => 3 / 4],
            ['numerator' => 1, 'denominator' => 1, 'value' => 1],
        ];

        $best = null;
        foreach ($fractions as $candidate) {
            $error = abs($fraction - $candidate['value']);
            if ($best === null || $error < $best['error']) {
                $best = [...$candidate, 'error' => $error];
            }
        }

        if ($best === null) {
            return $sign . (string) $whole;
        }

        $numerator = $best['numerator'];
        $denominator = $best['denominator'];
        if ($denominator === 1) {
            return $sign . (string) ($whole + 1);
        }

        if ($whole > 0) {
            return sprintf('%s%d %d/%d', $sign, $whole, $numerator, $denominator);
        }

        return sprintf('%s%d/%d', $sign, $numerator, $denominator);
    }

    private function greatestCommonDivisor(int $a, int $b): int
    {
        $x = abs($a);
        $y = abs($b);
        while ($y !== 0) {
            $temp = $y;
            $y = $x % $y;
            $x = $temp;
        }

        return $x ?: 1;
    }

    private function mandatoryExportNutritionIds(): array
    {
        return NutritionPreferenceSupport::RECIPE_DEFAULT_IDS;
    }

    private function filterRecipeNutritionForExport(array $nutrition): array
    {
        if (!isset($nutrition['info']) || !is_array($nutrition['info'])) {
            return $nutrition;
        }

        $selectedNutritionIds = $this->mandatoryExportNutritionIds();

        $nutrition['info'] = array_values(array_filter($nutrition['info'], function ($nutrient) use ($selectedNutritionIds) {
            return in_array((int) ($nutrient['id'] ?? 0), $selectedNutritionIds, true);
        }));

        return $nutrition;
    }

    private function buildExternalPayload(Receta $recipe, float|int $portion, $nutritionalsInfo, $user): array
    {
        $scaledIngredients = $this->scaleRecipeIngredients($recipe, $portion);
        $recipePortion = $recipe->getPorciones();
        $recipeNutrition = $this->filterRecipeNutritionForExport(
            $recipe->getInformacionNutrimental()
        );
        $nutritionRows = [];
        foreach (($recipeNutrition['info'] ?? []) as $nutrient) {
            $nutritionRows[] = [
                'nombre' => $nutrient['nombre'] ?? 'Nutriente',
                'cantidad' => isset($nutrient['cantidad']) ? round((float) $nutrient['cantidad'], 2) : null,
                'unidad_medida' => $nutrient['unidad_medida'] ?? '',
            ];
        }

        $recipePage = [
            'recipe' => [
                'id' => (int) $recipe->id,
                'titulo' => (string) ($recipe->titulo ?? 'Receta'),
                'imagen_principal' => (string) ($recipe->imagen_principal ?? ''),
                'porciones' => (float) ($recipe->porciones ?? ($recipePortion['cantidad'] ?? 1)),
                'porcion_nombre' => (string) ($recipePortion['nombre'] ?? 'Porción'),
                'porcion_nombre_plural' => (string) ($recipePortion['nombre_plural'] ?? 'Porciones'),
                'tiempo_elaboracion' => (int) ($recipe->tiempo_elaboracion ?? ($recipe->tiempo ?? 0)),
                'instrucciones' => array_values($recipe->getInstrucciones() ?? []),
                'tips' => implode(PHP_EOL, array_values($recipe->getTipsPlain() ?? [])),
            ],
            'portion' => $portion,
            'ingredients' => array_map(function (array $ingredient) {
                return [
                    'ingrediente' => $ingredient['ingrediente'] ?? ($ingredient['nombre'] ?? 'Ingrediente'),
                    'cantidad' => $ingredient['cantidad'] ?? null,
                    'medida' => $ingredient['medida'] ?? ($ingredient['unidad'] ?? ''),
                    'unidad' => $ingredient['unidad'] ?? ($ingredient['medida'] ?? ''),
                    'nota' => $ingredient['nota'] ?? '',
                ];
            }, $scaledIngredients),
            'nutrition' => $nutritionRows,
        ];

        return [
            'template' => 'bold',
            'export_param' => [1],
            'hero_recipe_id' => (int) $recipe->id,
            'heroRecipe' => [
                'id' => (int) $recipe->id,
                'titulo' => (string) ($recipe->titulo ?? 'Receta'),
                'imagen_principal' => (string) ($recipe->imagen_principal ?? ''),
            ],
            'selected_recipes' => [(int) $recipe->id],
            'recipePages' => [$recipePage],
            'nutritionByDay' => [],
            'listaData' => [
                'categories' => [],
                'taken_ids' => [],
            ],
            'brandName' => (string) ($user->bname ?: 'Healthy Martina'),
            'brandEmail' => (string) ($user->bemail ?: $user->email),
            'brandLogo' => (string) ($user->bimage ?: ''),
            'brandColor' => (string) ($user->color ?: '#36544e'),
            'calendar_snapshot' => [
                'id' => (int) $recipe->id,
                'title' => (string) ($recipe->titulo ?? 'Receta'),
                'labels' => [],
                'main_schedule' => [],
                'sides_schedule' => [],
                'main_servings' => [],
                'sides_servings' => [],
                'main_racion' => [],
                'sides_racion' => [],
                'main_leftovers' => [],
                'sides_leftovers' => [],
                'recipe_ids' => [(int) $recipe->id],
                'recipes_map' => [
                    (string) $recipe->id => [
                        'id' => (int) $recipe->id,
                        'titulo' => (string) ($recipe->titulo ?? 'Receta'),
                        'imagen_principal' => (string) ($recipe->imagen_principal ?? ''),
                    ],
                ],
            ],
        ];
    }

    private function renderExternalRecipePdf(Receta $recipe, float|int $portion, $nutritionalsInfo, $user, ExternalPdfExportService $externalPdfExportService): string
    {
        $jobId = (string) Str::uuid();
        $payload = $this->buildExternalPayload($recipe, $portion, $nutritionalsInfo, $user);

        Log::info('[pdf-export] recipe external payload summary', [
            'job_id' => $jobId,
            'recipe_id' => $recipe->id,
            'portion' => $portion,
            'selected_recipes' => $payload['selected_recipes'] ?? [],
            'recipe_pages_count' => count($payload['recipePages'] ?? []),
            'ingredient_amounts' => array_map(function ($ingredient) {
                return $ingredient['cantidad'] ?? null;
            }, $payload['recipePages'][0]['ingredients'] ?? []),
        ]);

        $serviceResponse = $externalPdfExportService->enqueue([
            'job_id' => $jobId,
            'user_id' => Auth::id(),
            'calendar_id' => $recipe->id,
            'request_payload' => [
                'calendar' => $recipe->id,
                'export_param' => [1],
                'template' => 'bold',
                'selected_recipes' => [(int) $recipe->id],
                'portion' => $portion,
            ],
            'payload' => $payload,
        ]);

        $externalJobId = (string) ($serviceResponse['job_id'] ?? $jobId);
        $deadline = microtime(true) + 90;

        while (microtime(true) < $deadline) {
            $status = $externalPdfExportService->status($externalJobId);
            $state = (string) ($status['status'] ?? '');
            if ($state === 'completed') {
                $binary = $externalPdfExportService->downloadBinary($externalJobId);
                Log::info('[pdf-export] recipe external download', [
                    'job_id' => $jobId,
                    'external_job_id' => $externalJobId,
                    'content_type' => $binary['content_type'] ?? null,
                    'bytes' => strlen((string) ($binary['body'] ?? '')),
                ]);
                return (string) ($binary['body'] ?? '');
            }
            if ($state === 'failed') {
                $message = (string) ($status['error_message'] ?? $status['message'] ?? 'Export failed');
                throw new \RuntimeException($message);
            }
            usleep(750000);
        }

        throw new \RuntimeException('Recipe PDF export timed out.');
    }

    /**
     * Generate and download recipe PDF.
     */
    public function download(Request $request, int $recipeId, ExternalPdfExportService $externalPdfExportService)
    {
        $validated = $request->validate([
            'portion' => 'nullable|numeric|min:1',
        ]);
        $recipe = Receta::findOrFail($recipeId);
        $user = Auth::user();
        $nutritionals_info = $this->getUserNutritionalsInfo($user);
        $portion = (float) ($validated['portion'] ?? $recipe->getPorciones()['cantidad'] ?? 1);

        try {
            $pdfBinary = $this->renderExternalRecipePdf($recipe, $portion, $nutritionals_info, $user, $externalPdfExportService);

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $recipe->titulo . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            Log::error('[pdf-export] recipe external render failed', [
                'recipe_id' => $recipe->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF export service is unavailable.',
            ], 503);
        }
    }

    /**
     * Generate and email recipe PDF.
     */
    public function email(Request $request, int $recipeId, ExternalPdfExportService $externalPdfExportService)
    {
        $validated = $request->validate([
            'recipient_email_address' => 'nullable|email',
            'plantillas' => 'nullable|string',
            'portion' => 'nullable|numeric|min:1',
        ]);

        $user = Auth::user();
        $recipe = Receta::findOrFail($recipeId);
        $portion = (float) ($validated['portion'] ?? $recipe->getPorciones()['cantidad'] ?? 1);
        
        $recipient_email = $validated['recipient_email_address'] ?? $user->email;

        if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico del destinatario no es válido',
            ], 422);
        }

        $nutritionals_info = $this->getUserNutritionalsInfo($user);

        try {
            $pdfBinary = $this->renderExternalRecipePdf($recipe, $portion, $nutritionals_info, $user, $externalPdfExportService);
        } catch (\Throwable $e) {
            Log::error('[pdf-export] recipe external email render failed', [
                'recipe_id' => $recipe->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF export service is unavailable.',
            ], 503);
        }

        // Prepare email data
        $data = [
            'email' => $recipient_email,
            'title' => '¡Tu receta esta lista!',
            'recipe' => $recipe,
            'nutritionals_info' => $nutritionals_info,
            'current_time' => todaySpanishDay(),
            'plantillas' => isset($validated['plantillas']) 
                ? utf8_decode(urldecode($validated['plantillas'])) 
                : '',
        ];

        try {
            // Send recipe to recipient
            Mail::send('email.send-recipe', $data, function ($message) use ($data, $pdfBinary) {
                $message->to($data['email'], $data['email'])
                    ->subject($data['title'])
                    ->attachData($pdfBinary, $data['recipe']->titulo . ".pdf");
            });

            // Send delivery confirmation to user
            Mail::send('email.delivery-email', [
                'type' => 'Receta',
                'meal_type' => 'Receta',
                'to' => $data['email'],
                'title' => $data['title'],
                'current_time' => todaySpanishDay()
            ], function ($message) use ($data, $pdfBinary, $user) {
                $message->to($user->bemail, $user->bemail)
                    ->subject('Tu receta fue entregada.')
                    ->attachData($pdfBinary, $data['recipe']->titulo . ".pdf");
            });

            return response()->json([
                'success' => true,
                'message' => 'Se envio por mail exitosamente',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
