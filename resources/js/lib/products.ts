export type Product = {
    id: number,
    name: string,
    description: string,
    price: number,
    formatted_price: number,
    currency: string,
    thumbnail: string,
    images: string[] | null,
    billing_product_id: string,
    colour: string,
    shipping: number,
    handling: number,
    tax: number,
    insurance: number,
    discount: number,
    shipping_discount: number,
}

export type Money = {
    currency_code: string,
    value: number
} 

export type AmountBreakdown = Money & {
    breakdown: Record<string, Money>;
};

export type PartialProduct = Partial<Product> & {
    id: number;
    name: string;
    description: string;
    quantity: number;
    thumbnail: string;
    unit_amount: Money & { formatted_value: string , total_formatted_value: string };
    tax: Money;
};

