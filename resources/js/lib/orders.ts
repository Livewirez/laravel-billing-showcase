import type { Product } from './products';

export type PaymentOptions = CardOptions & Record<string, any>

export interface CardOptions {
    card_gateway?: 'cybersource' | 'mastercard';
}


export interface Order {
    id: number,
    billing_order_id: string,
    order_number: string,
    status: string,
    currency: string,

    subtotal: string,
    discount: string, 
    tax: string,
    shipping: string,
    total: string,
    payment_status: string,
    payment_provider:string,
    billing_order_items?: OrderItem[]
}

export interface OrderItem {
    name: string,
    price: string,
    quantity: string,
    thumbnail?: string,
    currency: string,

    subtotal: string, // before tax & shipping
    discount: string,
    tax: string,
    shipping: string,
    total: string,

    status: string,
    billing_order_item_id: string,
    payment_status: string,
    delivery_status: string,
    fulfillment_status: string,
    billing_product: Product,
    billing_order?: Order,
}