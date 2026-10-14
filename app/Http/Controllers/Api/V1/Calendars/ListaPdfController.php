<?php

namespace App\Http\Controllers\Api\V1\Calendars;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Categoria;
use App\Models\ListaIngredientes;
use App\Services\Calendar\ExternalPdfExportService;
use App\Services\Mail\ExternalMailService;
use App\Support\ShoppingListAggregator;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListaPdfController extends Controller
{
    private function buildListaData(Calendar $calendar): array
    {
        $takenIngredients = DB::table('lista_ingrediente_taken')
            ->where('calendario_id', $calendar->id)
            ->get();

        $takenIds = $takenIngredients->pluck('ingrediente_id')->toArray();

        $categorias = Categoria::orderBy('sort', 'ASC')->get();

        $manualItems = ListaIngredientes::where('calendario_id', $calendar->id)->get();

        $byCategory = [];
        foreach ($categorias as $category) {
            $items = getRelatedIngrediente($calendar->id, $category->id, 'list');
            if (!empty($items)) {
                $byCategory[] = [
                    'id'    => $category->id,
                    'nombre'=> $category->nombre,
                    'name'  => $category->nombre,
                    'items' => collect($items)->map(function ($item) use ($category) {
                        $item = ShoppingListAggregator::aggregateIngredient($item);
                        return [
                            'ingrediente_id' => $item['ingrediente_id'] ?? null,
                            'categoria_id'   => $item['categoria_id'] ?? $category->id,
                            'nombre'         => $item['ingrediente'] ?? ($item['nombre'] ?? ($item['name'] ?? 'Ingrediente')),
                            'cantidad'       => $item['cantidad'] ?? null,
                            'unidad'         => $item['medida'] ?? ($item['unidad'] ?? ''),
                            'taken'          => false,
                        ];
                    })->toArray(),
                ];
            }
        }

        if ($manualItems->count()) {
            $byCategory[] = [
                'name'  => 'Otros',
                'items' => $manualItems->map(fn($i) => [
                    'ingrediente_id' => $i->id,
                    'nombre'   => $i->nombre,
                    'cantidad' => $i->cantidad,
                    'unidad'   => $i->unidad_medida,
                    'taken'    => false,
                ])->toArray(),
            ];
        }

        $ingredientsCount = collect($byCategory)->sum(function ($category) {
            return count($category['items'] ?? []);
        });

        return [
            'categories' => $byCategory,
            'taken_ids'  => $takenIds,
            'ingredients_count' => $ingredientsCount,
        ];
    }

    private function buildExternalPayload(Calendar $calendar): array
    {
        $user = Auth::user();
        $listaData = $this->buildListaData($calendar);

        return [
            'template' => 'bold',
            'export_param' => [2],
            'hero_recipe_id' => null,
            'heroRecipe' => null,
            'selected_recipes' => [],
            'recipePages' => [],
            'nutritionByDay' => [],
            'listaData' => [
                'taken_ids' => $listaData['taken_ids'] ?? [],
                'categories' => array_map(function ($cat) {
                    return [
                        'name' => $cat['name'] ?? ($cat['nombre'] ?? 'Categoría'),
                        'items' => array_map(function ($item) {
                            return [
                                'ingrediente_id' => $item['ingrediente_id'] ?? null,
                                'nombre' => $item['nombre'] ?? ($item['ingrediente'] ?? 'Ingrediente'),
                                'cantidad' => $item['cantidad'] ?? null,
                                'unidad' => $item['unidad'] ?? '',
                            ];
                        }, $cat['items'] ?? []),
                    ];
                }, $listaData['categories'] ?? []),
            ],
            'brandName' => (string) ($user->bname ?: 'Healthy Martina'),
            'brandEmail' => (string) ($user->bemail ?: $user->email),
            'brandLogo' => (string) ($user->bimage ?: ''),
            'brandColor' => (string) ($user->color ?: '#36544e'),
            'calendar_snapshot' => [
                'id' => (int) $calendar->id,
                'title' => (string) ($calendar->title ?? 'lista'),
                'labels' => json_decode($calendar->labels, true) ?? [],
                'main_schedule' => [],
                'sides_schedule' => [],
                'main_servings' => [],
                'sides_servings' => [],
                'main_racion' => [],
                'sides_racion' => [],
                'main_leftovers' => [],
                'sides_leftovers' => [],
                'recipe_ids' => [],
                'recipes_map' => [],
            ],
        ];
    }

    private function renderExternalPdf(Calendar $calendar, ExternalPdfExportService $externalPdfExportService): string
    {
        $jobId = (string) Str::uuid();
        $payload = $this->buildExternalPayload($calendar);
        $serviceResponse = $externalPdfExportService->enqueue([
            'job_id' => $jobId,
            'user_id' => Auth::id(),
            'calendar_id' => $calendar->id,
            'request_payload' => [
                'calendar' => $calendar->id,
                'export_param' => [2],
            ],
            'payload' => $payload,
        ]);

        $externalJobId = (string) ($serviceResponse['job_id'] ?? $jobId);
        $deadline = microtime(true) + 75;

        while (microtime(true) < $deadline) {
            $status = $externalPdfExportService->status($externalJobId);
            $state = (string) ($status['status'] ?? '');
            if ($state === 'completed') {
                $binary = $externalPdfExportService->downloadBinary($externalJobId);
                return (string) ($binary['body'] ?? '');
            }
            if ($state === 'failed') {
                $message = (string) ($status['error_message'] ?? $status['message'] ?? 'Export failed');
                throw new \RuntimeException($message);
            }
            usleep(800000); // 0.8s
        }

        throw new \RuntimeException('Export timed out.');
    }

    /**
     * Generate and download lista PDF.
     */
    public function download(Request $request, int $calendarId, ExternalPdfExportService $externalPdfExportService)
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Prefer external Node export service for parity with other exports.
        try {
            $pdfBinary = $this->renderExternalPdf($calendar, $externalPdfExportService);
            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . ($calendar->title ?? 'lista') . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            // Fallback to legacy Dompdf path if external export is unavailable.
            $user = Auth::user();

        // Get ingredients data
        $takenIngredients = DB::table('lista_ingrediente_taken')
            ->where('calendario_id', $calendar->id)
            ->get();

        $categorias = Categoria::orderBy('sort', 'ASC')->get();
        $listaIngredientes = ListaIngredientes::where('calendario_id', $calendar->id)->get();

        // Get recipe ingredients from request (JSON)
        $recipeIngredients = $request->has('lista_ingredients')
            ? json_decode($request->lista_ingredients)
            : (object)[];

        // Calculate ingredients count
        $ingredientsCount = (count((array) $recipeIngredients) + $listaIngredientes->count()) - count($takenIngredients);

        // Professional users (role_id == 3) get themed PDFs
        if ($user->role_id == 3) {
            $theme = $user->theme ?? 3;

            $viewMap = [
                1 => 'pdf.classic.classic-lista',
                2 => 'pdf.modern.modern-lista',
                3 => 'pdf.bold.bold-lista',
            ];

            $view = $viewMap[$theme] ?? 'pdf.bold.bold-lista';

            $pdf = PDF::loadView($view, [
                'ingredients_count' => $ingredientsCount,
                'recipe_ingredients' => $recipeIngredients,
                'calendario' => $calendar,
                'categorias' => $categorias,
                'taken_ingredientes' => $takenIngredients,
                'lista_ingredientes' => $listaIngredientes,
            ]);
        } else {
            // Free users get standard PDF
            $pdf = PDF::loadView('pdf.lista-pdf', [
                'ingredients_count' => $ingredientsCount,
                'recipe_ingredients' => $recipeIngredients,
                'calendario' => $calendar,
                'categorias' => $categorias,
                'taken_ingredientes' => $takenIngredients,
                'lista_ingredientes' => $listaIngredientes,
            ]);
        }

            return $pdf->download($calendar->title . '.pdf');
        }
    }

    /**
     * Email lista PDF.
     */
    public function email(
        Request $request,
        int $calendarId,
        ExternalPdfExportService $externalPdfExportService,
        ExternalMailService $externalMailService
    )
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $user = Auth::user();

        $validated = $request->validate([
            'recipient_email' => 'nullable|email',
            'lista_ingredients' => 'required|json', // kept for backwards compatibility
            'plantillas' => 'nullable|string',
        ]);

        // Default to user's email if not provided
        $recipientEmail = $validated['recipient_email'] ?? $user->email;

        // Validate email format
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico del destinatario no es válido',
            ], 422);
        }

        $pdfBinary = $this->renderExternalPdf($calendar, $externalPdfExportService);

        $title = '¡Tu lista está lista!';
        $fileName = ($calendar->title ?? 'lista') . '.pdf';
        try {
            $externalMailService->sendPdf(
                $recipientEmail,
                $title,
                'Adjuntamos tu lista de compras en PDF.',
                $fileName,
                $pdfBinary
            );

            // Send delivery confirmation to user's business email
            if ($user->bemail) {
                $externalMailService->sendDeliveryNotice(
                    (string) $user->bemail,
                    'Tu lista de compras fue entregada',
                    'Se entregó correctamente el PDF de la lista de compras al destinatario.',
                    $fileName,
                    $pdfBinary
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Se envió por mail exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Email lista as HTML (without PDF attachment).
     */
    public function emailHtml(Request $request, int $calendarId)
    {
        $calendar = Calendar::where('id', $calendarId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $user = Auth::user();

        $validated = $request->validate([
            'lista_ingredients' => 'required|json',
        ]);

        // Get ingredients data
        $takenIngredients = DB::table('lista_ingrediente_taken')
            ->where('calendario_id', $calendar->id)
            ->get();

        $categorias = Categoria::orderBy('sort', 'ASC')->get();
        $listaIngredientes = ListaIngredientes::where('calendario_id', $calendar->id)->get();

        // Get recipe ingredients from request
        $recipeIngredients = json_decode($validated['lista_ingredients']);

        $emailData = [
            'subject' => '¡Tu lista de "' . $calendar->title . '" está lista!',
            'email' => $user->email,
            'categorias' => $categorias,
            'calendario' => $calendar,
            'recipe_ingredients' => $recipeIngredients,
            'lista_ingredientes' => $listaIngredientes,
            'taken_ingredientes' => $takenIngredients,
        ];

        try {
            Mail::send('email.lista-email', $emailData, function ($message) use ($emailData) {
                $message->to($emailData['email'])
                    ->subject($emailData['subject']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Lista enviada por correo electrónico exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
