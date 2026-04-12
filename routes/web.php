<?php

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use App\Services\SessionDataResolver;
use Laravel\Fortify\Features;
use Livewirez\Billing\Lib\Cart;
use Livewirez\Billing\Lib\CartItem;
use Livewirez\Billing\Enums\ProductType;
use Livewirez\Billing\Models\BillingPlan;
use App\Http\Controllers\OrdersController;
use Livewirez\Billing\Models\BillingProduct;
use Livewirez\Billing\Lib\BillingCartWrapper;
use Livewirez\Billing\Enums\SubscriptionStatus;
use App\Http\Controllers\SubscriptionsController;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Livewirez\Billing\Http\Resources\BillingProductResource;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    }); 


Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {

     Route::get('/dashboard', function (Request $request) {

        $defaultTeam = $request->user()->currentTeam()->firstOr(
            fn () => $request->user()->teams()->first()
        );

        return redirect()->route('dashboard', ['current_team' => $defaultTeam?->slug ?? '']);
    })->name('dashboard.default');

    Route::get('/store', fn () => Inertia::render('store/Store', [
        'products' => BillingProductResource::collection(
            BillingProduct::whereIn(
                'product_type', 
                [ProductType::PHYSICAL, ProductType::DIGITAL]
                )->get()
        )->resolve()
    ]))->name('store');

    Route::get('/store/products/{product}', fn (Request $request, BillingProduct $product) => Inertia::render('store/Product', [
        'product' => BillingProductResource::make($product)->resolve()
    ]))->name('store.product');
    
    
    Route::get('plans', function () {

        $plans = BillingPlan::with(['billing_prices' => fn ($q) => $q->where('currency', 'KES'), 'billing_plan_payment_provider_information', 'billing_prices.billing_plan_payment_provider_information'])
        ->where(['is_active' => true])->get();
    
        return Inertia::render('Plans', [
            'plans' => \Livewirez\Billing\Http\Resources\BillingPlanResource::collection($plans)->resolve()
        ]); 
    })->name('plans');

    Route::get('plansv2', function () {
    
        $plans = BillingPlan::with(['billing_prices' => fn ($q) => $q->where('currency', 'KES'), 'billing_plan_payment_provider_information', 'billing_prices.billing_plan_payment_provider_information'])
        ->where(['is_active' => true])->get();
    
        return Inertia::render('PlansV2', [
            'plans' => \Livewirez\Billing\Http\Resources\BillingPlanResource::collection($plans)->resolve()
        ]); 
    })->name('plansv2');
    
    
    Route::get('checkout/plan/{name}', function (Request $request, string $name) {
    
        $plan_id = $request->query('plan_id');
        $interval = $request->query('interval');
    
        $plan = BillingPlan::with(['billing_prices' => fn ($q) => $q->where('currency', 'USD'), 'billing_plan_payment_provider_information', 'billing_prices.billing_plan_payment_provider_information'])
        ->where(['is_active' => true, 'name' => $name, 'billing_plan_id' => $plan_id])->firstOrFail();
    
        $plan_price = $plan->billing_prices->firstOrFail(
            fn ($pp) => $pp->interval === ($interval === 'yearly' ? \Livewirez\Billing\Enums\SubscriptionInterval::YEARLY : \Livewirez\Billing\Enums\SubscriptionInterval::MONTHLY)
        );

        $plan_price->billing_plan_payment_provider_information = $plan_price->billing_plan_payment_provider_information->merge(
            $plan->billing_plan_payment_provider_information->filter(fn ($bpp) => $bpp->payment_provider === Livewirez\Billing\Enums\PaymentProvider::PayPal)
        );

        SessionDataResolver::put($request, 'selected_plan', $plan);
        SessionDataResolver::put($request, 'selected_plan_price', $plan_price);

        return Inertia::render('checkout/Plan', [
           'plan' => \Livewirez\Billing\Http\Resources\BillingPlanResource::make($plan)->resolve(),
           'plan_price' => \Livewirez\Billing\Http\Resources\BillingPlanPriceResource::make($plan_price)->resolve(),
        ]); 
    })->name('checkout.plan');
    
    Route::get('checkout/planv2/{name}', function (Request $request, string $name) {
    
        $plan_id = $request->query('plan_id');
        $interval = $request->query('interval');
    
        $plan = BillingPlan::with(['billing_prices' => fn ($q) => $q->where('currency', 'USD'), 'billing_plan_payment_provider_information', 'billing_prices.billing_plan_payment_provider_information'])
        ->where(['is_active' => true, 'name' => $name, 'billing_plan_id' => $plan_id])->firstOrFail();
    
        $plan_price = $plan->billing_prices->firstOrFail(
            fn ($pp) => $pp->interval === ($interval === 'yearly' ? \Livewirez\Billing\Enums\SubscriptionInterval::YEARLY : \Livewirez\Billing\Enums\SubscriptionInterval::MONTHLY)
        );

        $plan_price->billing_plan_payment_provider_information = $plan_price->billing_plan_payment_provider_information->merge(
            $plan->billing_plan_payment_provider_information->filter(fn ($bpp) => $bpp->payment_provider === Livewirez\Billing\Enums\PaymentProvider::PayPal)
        );
      
        SessionDataResolver::put($request, 'selected_plan', $plan);
        SessionDataResolver::put($request, 'selected_plan_price', $plan_price);

        return Inertia::render('checkout/PlanV2', [
           'plan' => \Livewirez\Billing\Http\Resources\BillingPlanResource::make($plan)->resolve(),
           'plan_price' => \Livewirez\Billing\Http\Resources\BillingPlanPriceResource::make($plan_price)->resolve(),
        ]); 
    })->name('checkout.planv2');


    Route::get('/store/product/{product}/buynow', function (Request $request, BillingProduct $product) {

        $sci = array_map(function (array $data) {
            return CartItem::fromArray($data);
        }, [['product' => $product, 'quantity' => 1]]);

        $request->session()->put('cart', $cart = new Cart($sci));

        $request->session()->put(
            'billing_cart',
            BillingCartWrapper::fromCart(
                $request->user(), 
                $cart
            )
        );
         
        return redirect(route('store.cart.checkout'));

    })->name('store.product.buy_now');

    Route::get('/store/cart/checkout', function (Request $request) {

        if (!  $cart = $request->session()->get('cart')) {
            return redirect(route('store'));
        }
        

        $extra_tax = 0.33;

        $cart = $request->session()->get('cart');

        if (is_array($cart)) {
            $items = collect($cart)
                ->map(fn (array $sci) => new CartItem(
                   \Livewirez\Billing\Billing::$billingProduct::findOrFail($sci['product']['id']),
                   $sci['quantity']
                ));
            
           $cart = new Cart($items->toArray());
        }

        return Inertia::render('store/CheckoutCart', [
            'payment_methods' => \Livewirez\Billing\Http\Resources\BillablePaymentMethodResource::collection(
                $request->user()->billable_payment_methods()->get()
            )->resolve(),
            'cart_items' => array_map(fn (CartItem $ci) => ['product' => $ci->getProduct()->getId(), 'quantity' => $ci->getQuantity()], $cart->items),
            'cart_info' => [
                'items' => array_map(function (CartItem $sci) use ($extra_tax) {
                    return [
                        'id' => $sci->getProduct()->getId(),
                        'name' => $sci->getProduct()->getName(),
                        'description' => $sci->getProduct()->getDescription(),
                        'quantity' => $sci->getQuantity(),
                        'thumbnail' => $sci->getProduct()->getImageUrl(),
                        'unit_amount' => [
                            'currency_code' => $sci->getProduct()->getCurrencyCode(),
                            'value' => $sci->getProduct()->getListedPrice(),
                            'formatted_value' => Livewirez\Billing\Money::formatAmountUsingCurrency(
                                $sci->getProduct()->getListedPrice(),
                                $sci->getProduct()->getCurrencyCode(),
                            ),
                            'total_formatted_value' => Livewirez\Billing\Money::formatAmountUsingCurrency(
                                $sci->getProduct()->getListedPrice() * $sci->getQuantity(),
                                $sci->getProduct()->getCurrencyCode(),
                            )
                        ],
                        'tax' => [
                            'currency_code' => $sci->getProduct()->getCurrencyCode(),
                            'value' => ($sci->getProduct()->getTax() + $extra_tax)
                        ]
                    ];
                }, $cart->all()),

                'amount' => [
                    'currency_code' => $cart->getCurrencyCode(),
                    'value' => ($cart->getGrandTotalFromExtraTax(true, $extra_tax)),
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getItemTotals(false)
                        ],
                        'tax_total' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getItemExtraTaxTotals($extra_tax)
                        ],
                        'shipping' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getShippingTotal()
                        ],
                        'handling' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getHandlingTotal()
                        ],
                        'insurance' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getInsuranceTotal()
                        ],
                        'shipping_discount' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getShippingDiscountTotal()
                        ],
                        'discount' => [
                            'currency_code' => $cart->getCurrencyCode(),
                            'value' => $cart->getDiscountTotal()
                        ],
                    ]
                ]
            ]
        ]);

    })->name('store.cart.checkout');

    Route::get('/orders/complete_orderxx', function (Request $request) {

        // if ($request->session()->has('product') && $request->session()->has('shopping_cart')) {
        //     $request->session()->forget('product');
        // }

        // if ($request->session()->has('product') && ! $request->session()->has('shopping_cart')) {
        //     $request->session()->forget('product');
        // }

        // if ($request->session()->has('shopping_cart') && ! $request->session()->has('product')) {
        //     $request->session()->forget('shopping_cart');
        // }

        return Inertia::render('checkout/PaymentSuccess', [
            'message' => 'Thank you for your purchase!'
        ]);
    })->name('payment.complete_orderxx');

    Route::get('/orders/complete_order', [OrdersController::class, 'completePayment'])->name('payment.complete_order');
    Route::get('/orders/complete_order_with_token', [OrdersController::class, 'completePaymentWithToken'])->name('payment.complete_order_with_token');
    Route::get('/orders/cancel_order', [OrdersController::class, 'cancelPayment'])->name('payment.cancel_order');

    Route::get('/subscriptions/startsubscription', [SubscriptionsController::class, 'startSubscription'])->name('subscriptions.start_subscription');
    Route::get('/subscriptions/start_subscription_with_token', [SubscriptionsController::class, 'startSubscriptionWithToken'])->name('subscriptions.start_subscription_with_token');
    Route::get('/subscriptions', function (Request $request) {

        $subscription = $request->user()->billing_subscription()
        ->where('is_active', true)
        ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::CANCELLATION_PENDING])
        ->first();

        return Inertia::render('Subscriptions', [
            'subscription' => $subscription?->only(['id', 'interval', 'payment_provider', 'billing_plan_name',  'status', 'ends_at'])
        ]);
    })->name('subscriptions');

});


require __DIR__.'/settings.php';
