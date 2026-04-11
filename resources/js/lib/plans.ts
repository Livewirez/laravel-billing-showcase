export interface PlanPaymentProviderInformation {
    id: number,
    billing_plan_id: number,
    payment_provider:string,
    payment_provider_plan_id: string,
    status: string,
    billing_plan_price?: PlanPrice[];
}

export interface PlanPrice {
    id: number;
    billing_plan_id: number;
    interval: 'DAILY' | 'WEEKLY' | 'MONTHLY' | 'YEARLY' | 'CUSTOM';
    amount: number;
    formatted_amount: number;
    currency: string;
    tax: number;
    tax_type?: string;
    discount: number;
    billing_plan_payment_provider_information?: PlanPaymentProviderInformation[];
}

export interface Plan {
    id: number;
    billing_plan_id: string;
    name: string;
    description?: string;
    features: string[]
    billing_prices: PlanPrice[],
    billing_plan_payment_provider_information?: PlanPaymentProviderInformation[];
}
