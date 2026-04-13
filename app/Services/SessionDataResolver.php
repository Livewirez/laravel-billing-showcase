<?php 

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Livewirez\Billing\Lib\Cart;
use Livewirez\Billing\Lib\CartItem;
use Livewirez\Billing\Lib\CheckoutDetails;

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

    public static function getCart(Request $request, string $key = 'cart'): Cart
    {
        $cart = $request->session()->get($key);

        if (is_array($cart)) {
            $sci = array_map(function (array $cartItem) {
                return new CartItem(\Livewirez\Billing\Billing::$billingProduct::findOrFail($cartItem['product']['id']), $cartItem['quantity']);
            }, $cart);

            $cart = new Cart($sci);
        }

        return $cart;
    }

    public static function getCartFromArray(Request $request, array $cart): Cart
    {
        $sci = array_map(function (array $cartItem) {
            return new CartItem(\Livewirez\Billing\Billing::$billingProduct::findOrFail($cartItem['product']['id']), $cartItem['quantity']);
        }, $cart);

       return new Cart($sci);
    }

    public static function getgetCheckoutDetails(Request $request, ?array $checkout_details = null): CheckoutDetails
    {
        $checkout_details ??= $request->session()->get('checkout_details');

        throw_if($checkout_details === null, 'RuntimeException', 'CheckoutDetails does not exist in session or is empty');

        return CheckoutDetails::fromArray($checkout_details);
    }
}