<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CancelPayment;
use App\Actions\Orders\CompletePayment;
use App\Actions\Orders\CompletePaymentWithToken;
use App\Actions\Orders\InitializePayment;
use App\Actions\Orders\SetupPaymentToken;
use App\Services\Payments\CardHandler;
use App\Services\Payments\PaddleHandler;
use App\Services\Payments\PayPalHandler;
use App\Services\Payments\PolarHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Livewirez\Billing\Actions\InitializeOrder;
use Livewirez\Billing\Actions\ResumeOrder;
use Livewirez\Billing\Enums\PaymentProvider;
use Livewirez\Billing\Enums\PaymentStatus;
use Livewirez\Billing\Facades\Billing;
use Livewirez\Billing\Lib\Card\CardManager;
use Livewirez\Billing\Lib\Cart;
use Livewirez\Billing\Lib\CartItem;
use Livewirez\Billing\Models\Product;

class OrdersController extends Controller
{

    public function createCyberSourceMicroformSession(Request $request)
    {
        // $response = CardManager::createSession('cybersource');
        // return [
        //     'token' => $response->body()
        // ];
        
        $provider = Billing::provider(PaymentProvider::Card->value);

        if (method_exists($provider, 'createPartialCardSession')) {
            $cardSession = $provider->createPartialCardSession();

            $request->session()->put('partial_card_session', $cardSession);
            return [
                'token' => $cardSession->ctx->token,
            ];
        } else {
            $response = CardManager::createSession('cybersource');
            return [
                'token' => $response->body()
            ];
        }
        
    }

    public function setupPaymentToken(Request $request, SetupPaymentToken $action)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'provider' => [new Enum(PaymentProvider::class), 'required'],
        ]);

        return match (PaymentProvider::tryFrom($validated['provider'])) {
            PaymentProvider::PayPal => PayPalHandler::setupPaymentToken($request, $action),
        
            default => throw ValidationException::withMessages([
                'provider' => 'Unsupported Payment Provider'
            ])
        };
    }


    public function completePaymentWithToken(Request $request, CompletePaymentWithToken $action)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'provider' => [new Enum(PaymentProvider::class), 'required'],
        ]);

        return match (PaymentProvider::tryFrom($validated['provider'])) {
            PaymentProvider::Card => CardHandler::completePaymentWithToken($request, $action),
            PaymentProvider::PayPal => PayPalHandler::completePaymentWithToken($request, $action),
        
            default => throw ValidationException::withMessages([
                'provider' => 'Unsupported Payment Provider'
            ])
        };
    }

    public function initializePayment(Request $request, InitializePayment $action)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'provider' => [new Enum(PaymentProvider::class), 'required'],
            'card_gateway' => ['nullable', 'string',  Rule::in(['cybersource_unified_checkout', 'cybersource_click_to_pay']),],
            'card_gateway_phase' => ['int', 'required_if:card_gateway,cybersource_unified_checkout,cybersource_click_to_pay'],
            'products' => ['required', 'array'],
            'products.*' => ['required', 'array', 'required_array_keys:product,quantity'],
            'products.*.product' => ['required', 'numeric', 'exists:billing_products,id'],
            'products.*.quantity' =>  ['required', 'numeric']
        ]);

        return match (PaymentProvider::tryFrom($validated['provider'])) {
            PaymentProvider::Card => match ($validated['card_gateway'] ?? null) {
                'cybersource_unified_checkout', 'cybersource_click_to_pay' => match ($validated['card_gateway_phase'] ?? 1) {
                    1 , 'phase_one' => CardHandler::initializePaymentForUnified($request, app(InitializeOrder::class)),
                    2 , 'phase_two' => CardHandler::resumeOrderForUnified($request, app(ResumeOrder::class)),
                    default =>  CardHandler::initializePaymentForUnified($request, app(InitializeOrder::class))
                },
                default => CardHandler::initializePayment($request, $action)
            },
            PaymentProvider::PayPal => PayPalHandler::initializePayment($request, $action),
            PaymentProvider::Polar => PolarHandler::initializePayment($request, $action),
            PaymentProvider::Paddle => PaddleHandler::initializePayment($request, $action),

            default => throw ValidationException::withMessages([
                'provider' => 'Unsupported Payment Provider'
            ])
        };
    }

    public function completePayment(Request $request, CompletePayment $action)
    {
        if (! $provider = $request->query('provider')) {
            \Illuminate\Support\Facades\Log::warning('No Provider in cancel', [__METHOD__ . __LINE__]);
            return Redirect::route('dashboard');
        }

        return match (PaymentProvider::tryFrom($provider)) {
            PaymentProvider::Card => CardHandler::completePayment($request, $action),
            PaymentProvider::PayPal => PayPalHandler::completePayment($request, $action),
            PaymentProvider::Polar => PolarHandler::completePayment($request, $action),
            PaymentProvider::Paddle => PaddleHandler::completePayment($request, $action),
            default => Redirect::route('dashboard')
        };  
    }

    public function cancelPayment(Request $request, CancelPayment $action)
    {
        // capture_request_vars($request);

        if (! $provider = $request->query('provider')) {
            \Illuminate\Support\Facades\Log::warning('No Provider in cancel', [__METHOD__ . __LINE__]);
            return Redirect::route('dashboard');
        }

        return match (PaymentProvider::tryFrom($provider)) {
            PaymentProvider::Card => CardHandler::cancelPayment($request, $action),
            PaymentProvider::PayPal => PayPalHandler::cancelPayment($request, $action),
            PaymentProvider::Polar => PolarHandler::cancelPayment($request, $action),
            PaymentProvider::Paddle => PaddleHandler::cancelPayment($request, $action),
            default => Redirect::route('dashboard')
        };  
    }
}
