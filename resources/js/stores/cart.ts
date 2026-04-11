// stores/counter.js
import Cart,  { type CartItem }  from '@/lib/store_items';
import type { Product } from "@/lib/products";
import { defineStore } from 'pinia'
import { ref } from 'vue';
import { HttpRequest, HttpResponse, HttpError } from '@/lib/utils'

import { sync as syncCart, clear as clearCart } from '@/routes/api/cart'

export const useCart = defineStore('cart', {
    state: () => {
        return { cart: new Cart([]) }
    },
    // could also be defined as
    // state: () => ({ count: 0 })
    actions: {
        async sync(): Promise<Cart> {
            try {
                const response = await HttpRequest.get(syncCart.url());
                const items = response.data.items as CartItem[];
                return this.cart.replaceItems(items);
            } catch (error: any) {
                console.error('Shopping Cart Sync Error: ', error, error?.response);
                return this.cart.replaceItems([]); // Return empty cart on error
            }
        },
        async deleteCart(): Promise<Cart>{
            try {
                const response = await HttpRequest.delete(clearCart.url());
                const items = response.data.items as Cart[];
                return this.cart.clear();
            } catch (error: any) {
                console.error('Shopping Cart Sync Error: ', error, error?.response);
                return this.cart.clear(); // Return empty cart on error
            }
        },
        getCurrencyCode(): string {
            return this.cart.getCurrencyCode();
        },
    
        setCurrencyCode(currencyCode: string): Cart {
            return this.cart.setCurrencyCode(currencyCode)
        },
    
        fromCartItem(item: CartItem): Cart {
            return this.cart = Cart.fromCartItem(item);
        },
    
        fromProduct(product: Product, quantity: number = 1): Cart {
            return this.cart = Cart.fromProduct(product, quantity);
        },
    
        getTotalItemCount(): number {
            return this.cart.getTotalItemCount();
        },

        replaceItems(items: CartItem[]): Cart {
            return this.cart.replaceItems(items);
        },

        add(item: CartItem): Cart {
            return this.cart.add(item);
        },
    
        addProduct(product: Product, quantity: number = 1): Cart {
            return this.cart.addProduct(product, quantity);
        },

        updateQuantity(productId: number, quantity: number): Cart {
            return this.cart.updateQuantity(productId, quantity);
        },

        incrementQuantity(productId: number, amount: number = 1): Cart {
            return this.cart.incrementQuantity(productId, amount);
        },
    
        decrementQuantity(productId: number, amount: number = 1): Cart {
            return this.cart.decrementQuantity(productId, amount);
        },
    
        clear(): Cart {
            return this.cart.clear();
        },
    
        get(productId: number): CartItem | undefined {
            return this.cart.get(productId);
        },
    
        remove(productId: number): Cart {
            return this.cart.remove(productId);
        },
    
        all(): CartItem[] {
            return this.cart.all();
        },
    
        toArray(): CartItem[] {
            return this.cart.toArray();
        },
    
        toJson(): string {
            return this.cart.toJson()
        },
    
        getItemTotals(useDiscounts: boolean = true): number {
            return this.cart.getItemTotals(useDiscounts);
        },
    
        getItemTaxTotals(): number {
            return this.cart.getItemTaxTotals();
        },
    
        getItemExtraTaxTotals(extraTax: number = 0): number {
            return this.cart.getItemExtraTaxTotals(extraTax);
        },
    
        getDiscountTotal(): number {
            return this.cart.getDiscountTotal();
        },
    
        getShippingTotal(): number {
            return this.cart.getShippingTotal();
        },
    
        getShippingDiscountTotal(): number {
            return this.cart.getShippingDiscountTotal();
        },
    
        getHandlingTotal(): number {
            return this.cart.getHandlingTotal();
        },
    
        getInsuranceTotal(): number {
            return this.cart.getInsuranceTotal();
        },
    
        getGrandTotal(useDiscounts: boolean = true): number {
            return (
                this.cart.getItemTotals(useDiscounts) +
                this.cart.getItemTaxTotals() +
                this.cart.getShippingTotal() +
                this.cart.getHandlingTotal() +
                this.cart.getInsuranceTotal()
            );
        },
    
        getGrandTotalFromExtraTax(useDiscounts: boolean = true, extraTax: number = 0): number {
            return (
                this.cart.getItemTotals(useDiscounts) +
                this.cart.getItemExtraTaxTotals(extraTax) +
                this.cart.getShippingTotal() +
                this.cart.getHandlingTotal() +
                this.cart.getInsuranceTotal()
            );
        }
    },
    persist: true
})