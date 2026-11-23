<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Support\NutritionPreferenceSupport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PreferencesController extends Controller
{
    /**
     * Get user preferences.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user()->load('preference');
        $pref = $user->preference;
        $nutritionalPreferences = DB::table('nutritional_preferences')
            ->where('user_id', $user->id)
            ->first();
        $storedNutritionInfo = !empty($nutritionalPreferences->nutritional_info)
            ? json_decode($nutritionalPreferences->nutritional_info, true)
            : null;
        $nutritionOptions = NutritionPreferenceSupport::getRecipeNutritionOptions($storedNutritionInfo);

        return response()->json([
            'unit_measure' => $user->unit_measure,
            'theme' => $user->theme,
            'weekly_reminders' => $pref ? (bool) $pref->weekly_reminders : false,
            'new_updates' => $pref ? (bool) $pref->new_updates : false,
            'mentions' => $pref ? (bool) $pref->mentions : false,
            'nutrition_options' => $nutritionOptions,
            'nutritions' => NutritionPreferenceSupport::getSelectedIdsFromInfo($nutritionOptions),
        ]);
    }

    /**
     * Update user preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'unit_measure' => 'sometimes|nullable|in:metric,us',
            'theme' => 'sometimes|nullable|in:light,dark',
            'weekly_reminders' => 'sometimes|boolean',
            'new_updates' => 'sometimes|boolean',
            'mentions' => 'sometimes|boolean',
            'nutritions' => 'sometimes|array',
            'nutritions.*' => 'integer',
        ]);

        $user = Auth::user();

        // Update user fields
        $userFields = array_intersect_key($validated, array_flip(['unit_measure', 'theme']));
        if (!empty($userFields)) {
            $user->update($userFields);
        }

        // Upsert notification preferences
        $prefFields = array_intersect_key($validated, array_flip(['weekly_reminders', 'new_updates', 'mentions']));
        if (!empty($prefFields)) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id],
                $prefFields
            );
        }

        if ($request->has('nutritions')) {
            $selectedNutritionIds = array_map('intval', $validated['nutritions'] ?? []);
            $nutritionalPreferences = DB::table('nutritional_preferences')
                ->where('user_id', $user->id)
                ->first();
            $storedNutritionInfo = !empty($nutritionalPreferences->nutritional_info)
                ? json_decode($nutritionalPreferences->nutritional_info, true)
                : null;
            $nutritionInfo = NutritionPreferenceSupport::normalizeNutritionInfo($storedNutritionInfo);
            $displayIds = NutritionPreferenceSupport::getRecipeDisplayIds();

            foreach ($nutritionInfo as &$item) {
                $id = (int) ($item['id'] ?? 0);
                if (in_array($id, $displayIds, true)) {
                    $item['mostrar'] = in_array($id, $selectedNutritionIds, true) ? 1 : 0;
                }
            }
            unset($item);
            $nutritionInfo = NutritionPreferenceSupport::markRecipePreferencesCustomized($nutritionInfo);

            DB::table('nutritional_preferences')->updateOrInsert(
                ['user_id' => $user->id],
                ['nutritional_info' => json_encode($nutritionInfo)]
            );
        }

        $user->refresh()->load('preference');
        $pref = $user->preference;
        $nutritionalPreferences = DB::table('nutritional_preferences')
            ->where('user_id', $user->id)
            ->first();
        $storedNutritionInfo = !empty($nutritionalPreferences->nutritional_info)
            ? json_decode($nutritionalPreferences->nutritional_info, true)
            : null;
        $nutritionOptions = NutritionPreferenceSupport::getRecipeNutritionOptions($storedNutritionInfo);

        return response()->json([
            'unit_measure' => $user->unit_measure,
            'theme' => $user->theme,
            'weekly_reminders' => $pref ? (bool) $pref->weekly_reminders : false,
            'new_updates' => $pref ? (bool) $pref->new_updates : false,
            'mentions' => $pref ? (bool) $pref->mentions : false,
            'nutrition_options' => $nutritionOptions,
            'nutritions' => NutritionPreferenceSupport::getSelectedIdsFromInfo($nutritionOptions),
            'message' => 'Preferences updated successfully',
        ]);
    }
}
