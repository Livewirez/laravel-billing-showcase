<?php 

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SessionDataResolver 
{
    public static function get(Request $request, string $key): ?Model
    {
        if (app()->environment('production') || config('app.force_session_model_storage')) {
            return match ($key) {
                'selected_plan' => \Livewirez\Billing\Billing::$billingPlan::findOrFail($request->session()->get('selected_plan')),
                'selected_plan_price' => \Livewirez\Billing\Billing::$billingPlanPrice::findOrFail($request->session()->get('selected_plan_price')),
                default => null
            };
        }
        
        return match ($key) {
            'selected_plan' => is_array($selected_plan = $request->session()->get('selected_plan')) ? 
                \Livewirez\Billing\Billing::$billingPlan::findOrFail($selected_plan['id']) : $selected_plan,
            'selected_plan_price' => is_array($selected_plan_price = $request->session()->get('selected_plan_price')) ? 
                \Livewirez\Billing\Billing::$billingPlanPrice::findOrFail($selected_plan_price['id']) : $selected_plan_price,
            default => null
        };
    }

    public static function put(Request $request, string $key, Model $model)
    {
        if (app()->environment('production') || config('app.force_session_model_storage')) {
            return match ($key) {
                'selected_plan' => $request->session()->put('selected_plan', $model->id),
                'selected_plan_price' => $request->session()->put('selected_plan_price', $model->id),
                default => null
            };
        }

        return match ($key) {
            'selected_plan' => $request->session()->put('selected_plan', $model),
            'selected_plan_price' => $request->session()->put('selected_plan_price', $model),
            default => null
        };
    }
}