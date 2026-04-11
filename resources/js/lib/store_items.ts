
import type { Product, PartialProduct, AmountBreakdown } from '@/lib/products'

export type CartItem = {
    product: Product, quantity: number
}

export type CartInfo = {
    items: PartialProduct[];
    amount: AmountBreakdown;
}

export type PartialCartItem = {
    product: Partial<Product>, quantity: number
};

export default class Cart {
    private items: CartItem[] = [];
    private currencyCode: string;

    constructor(items: CartItem[] = [], currencyCode: string = "USD") {
        this.items = items;
        this.currencyCode = currencyCode;
    }

    getCurrencyCode(): string {
        return this.currencyCode;
    }

    setCurrencyCode(currencyCode: string): Cart {
        this.currencyCode = currencyCode;

        return this;
    }

    fromCart(cart: Cart): Cart {
        this.replaceItems(cart.all());
        
        return this
    }

    static fromCartItem(item: CartItem): Cart {
        return new Cart([item]);
    }

    static fromProduct(product: Product, quantity: number = 1): Cart {
        return new Cart([{ product, quantity }]);
    }

    replaceItems(items: CartItem[]): Cart {
        this.items = items;

        return this;
    }

    getTotalItemCount(): number {
        return this.items.reduce((sum, item) => sum + item.quantity, 0);
    }

    add(item: CartItem): Cart {

        let sc_item = this.get(item.product.id); 

        if (sc_item) {
            this.incrementQuantity(item.product.id, item.quantity)
        } else {
            this.items.push(item);
        }

        return this;
    }

    addProduct(product: Product, quantity: number = 1): Cart {
       return this.add({ product, quantity })
    }

    updateQuantity(productId: number, quantity: number): Cart {
        this.items = this.items.map((item) =>
            item.product.id === productId
                ? { ...item, quantity: Math.max(1, quantity) }
                : item
        );

        return this;
    }

    incrementQuantity(productId: number, amount: number = 1): Cart {
        this.items = this.items.map((item) =>
            item.product.id === productId
                ? { ...item, quantity: item.quantity + amount }
                : item
        );
        return this;
    }

    decrementQuantity(productId: number, amount: number = 1): Cart {
        this.items = this.items.map((item) =>
            item.product.id === productId
                ? { ...item, quantity: Math.max(1, item.quantity - amount) }
                : item
        );
        return this;
    }

    clear(): Cart {
        this.items = [];

        return this;
    }

    get(productId: number): CartItem | undefined {
        return this.items.find((item) => item.product.id === productId);
    }

    remove(productId: number): Cart {
        this.items = this.items.filter((item) => item.product.id !== productId);

        return this;
    }

    all(): CartItem[] {
        return this.items;
    }

    toArray(): CartItem[] {
        return this.items;
    }

    toJson(): string {
        return JSON.stringify(this.items);
    }

    getItemTotals(useDiscounts: boolean = true): number {
        return this.items.reduce((sum, item) => {
            const price = item.product.price;
            const discount = useDiscounts ? item.product.discount + item.product.shipping_discount : 0;
            return sum + (price - discount) * item.quantity;
        }, 0);
    }

    getItemTaxTotals(): number {
        return this.items.reduce((sum, item) => sum + item.product.tax * item.quantity, 0);
    }

    getItemExtraTaxTotals(extraTax: number = 0): number {
        return this.items.reduce((sum, item) => sum + (item.product.tax + extraTax) * item.quantity, 0);
    }

    getDiscountTotal(): number {
        return this.items.reduce((sum, item) => sum + item.product.discount * item.quantity, 0);
    }

    getShippingTotal(): number {
        return this.items.reduce((sum, item) => sum + item.product.shipping * item.quantity, 0);
    }

    getShippingDiscountTotal(): number {
        return this.items.reduce((sum, item) => sum + item.product.shipping_discount * item.quantity, 0);
    }

    getHandlingTotal(): number {
        return this.items.reduce((sum, item) => sum + item.product.handling * item.quantity, 0);
    }

    getInsuranceTotal(): number {
        return this.items.reduce((sum, item) => sum + item.product.insurance * item.quantity, 0);
    }

    getGrandTotal(useDiscounts: boolean = true): number {
        return (
            this.getItemTotals(useDiscounts) +
            this.getItemTaxTotals() +
            this.getShippingTotal() +
            this.getHandlingTotal() +
            this.getInsuranceTotal()
        );
    }

    getGrandTotalFromExtraTax(useDiscounts: boolean = true, extraTax: number = 0): number {
        return (
            this.getItemTotals(useDiscounts) +
            this.getItemExtraTaxTotals(extraTax) +
            this.getShippingTotal() +
            this.getHandlingTotal() +
            this.getInsuranceTotal()
        );
    }
}