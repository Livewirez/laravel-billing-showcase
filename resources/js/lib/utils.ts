import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}


// Helper / Utility functions
export function url_to_head(url: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const script: HTMLScriptElement = document.createElement('script') as HTMLScriptElement;
        script.src = url;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Error loading script.'));
        document.head.appendChild(script);
    });
};

export async function convertCurrency(amount: number, fromCurrency: string, toCurrency: string) {
    // 1. Get exchange rates (example using a public API)
    const response = await fetch(`https://api.exchangerate-api.com/v4/latest/${fromCurrency}`);
    const data = await response.json();
    const rates = data.rates;
    
    // 2. Perform the conversion
    const exchangeRate = rates[toCurrency];
    const convertedAmount = amount * exchangeRate;
    
    // 3. Format the result using Intl.NumberFormat
    const formatter = new Intl.NumberFormat("en", {
      style: 'currency',
      currency: toCurrency
    });
    
    return {
        amount: convertedAmount,
        formatted: formatter.format(convertedAmount),
        rate: exchangeRate
    };
}

/**
 * @see https://www.six-group.com/dam/download/financial-information/data-center/iso-currrency/lists/list-one.xml
 * 
 * @param {string} currency 
 * @param {number | bigint | Intl.StringNumericLiteral} amount 
 */
export const defaultFormatter = (currency: string, amount: number | bigint | Intl.StringNumericLiteral) => {

    switch (currency) {
        case 'KES':
        case 'KSH':
            return new Intl.NumberFormat(['en-KE', ...navigator.languages], { 
                    style: "currency", 
                    currency: 'KES', 
                    currencySign: 'accounting', 
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0 
                })
                .format(amount)
        default: 
            return new Intl.NumberFormat(navigator.languages, { style: "currency", currency: currency.toUpperCase(),  currencySign: 'accounting', }).format(amount)
    }
}

export class JWT
{
    static parse(token: string): Record<string, any>
    {
        const parts = token.split('.');

        if (parts.length !== 3) throw new Error('Invalid JWT token format');

        // JWTs use Base64URL encoding, so convert to standard Base64 first
        const base64 = parts[1]
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        // Decode Base64 string and parse the payload
        const jsonPayload = decodeURIComponent(
            atob(base64)
                .split('')
                .map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
                .join('')
        );

        return JSON.parse(jsonPayload);
    }

    static parse2(token: string): Record<string, any>
    {
        const parts = token.split('.');

        if (parts.length !== 3) throw new Error('Invalid JWT token format');

        return JSON.parse(atob(parts[1]
            .replace(/-/g, '+')
            .replace(/_/g, '/'))
        );
    }
}

export class HttpResponse
{
    public readonly data: any;

    public readonly headers: Record<string, any>;

    public readonly _headers: Headers;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/ok) */
    public  readonly ok: boolean;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/redirected) */
    public  readonly redirected: boolean;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/status) */
    public  readonly status: number;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/statusText) */
    public  readonly statusText: string;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/type) */
    public  readonly type: ResponseType;
    /** [MDN Reference](https://developer.mozilla.org/docs/Web/API/Response/url) */
    public  readonly url: string;

    constructor(private response: Response, data: string) {
        try {
            this.data = JSON.parse(data)
        } catch (e) {
            this.data = data
        }
       

        this._headers = response.headers;
        this.headers = this.headersToRecord(response.headers)
        this.ok = response.ok;
        this.redirected = response.redirected;
        this.statusText = response.statusText;
        this.status = response.status;
        this.type = response.type;
        this.url = response.url;
    }

    private headersToRecord(headers: Headers): Record<string, any> {
        const record: Record<string, any> = {};
        headers.forEach((value, key) => {
            record[key] = value;
        });
        return record;
    }
}


export class HttpError extends Error {
    public response: HttpResponse;

    constructor(message: string, response: HttpResponse, options?: ErrorOptions) {
        super(message, options);
        this.response = response;
        Object.setPrototypeOf(this, HttpError.prototype);
    }
}

type HttpRequestCallbacksExx<K extends keyof XMLHttpRequestEventTargetEventMap> =
    Partial<{
        [P in K]: (this: XMLHttpRequest, ev: XMLHttpRequestEventTargetEventMap[P]) => any;
    }>;

type HttpRequestCallbacksEx = Partial<{
    [K in keyof XMLHttpRequestEventTargetEventMap]:
        (this: XMLHttpRequest, ev: XMLHttpRequestEventTargetEventMap[K]) => any;
}>;

type HttpRequestCallbacks = Omit<Partial<{
    [K in keyof XMLHttpRequestEventTargetEventMap]:
        (this: XMLHttpRequest, ev: XMLHttpRequestEventTargetEventMap[K]) => any;
}>, 'error' | 'timeout'>;

type HttpRequestCallbacksOptions = Partial<{
    [K in keyof XMLHttpRequestEventTargetEventMap]:
        {
            listener: (this: XMLHttpRequest, ev: XMLHttpRequestEventTargetEventMap[K]) => any;
            options?: boolean | AddEventListenerOptions;
        }
}>;

export class HttpRequest {
    static async fetch(input: RequestInfo | URL, init?: RequestInit,  callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            
            // Handle URL properly
            const url = typeof input === 'string' || input instanceof URL ? input.toString() : input.url;
            const method = init?.method || 'GET';
            
            xhr.open(method, url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.withCredentials = true;
            
            // Set additional headers from init
            if (init?.headers) {
                const headers = new Headers(init.headers);
                headers.forEach((value, key) => {
                    xhr.setRequestHeader(key, value);
                });
            }
    
            for (const event in callbacks) {
                const callback = callbacks[event as keyof Omit<XMLHttpRequestEventTargetEventMap, 'error' | 'timeout'>];
                if (typeof callback === 'function') {
                    xhr.addEventListener(event, callback as EventListener);
                }
            }
            
            xhr.onreadystatechange = function (ev: Event) {
                
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    const resHeaders = xhr.getAllResponseHeaders()
                        .split('\r\n')
                        .map(v => v.trim().split(':').map(s => s.trim()))
                        .filter(a => a.length === 2 && a[0] !== '') as [string, string][];

                    if (xhr.status >= 200 && xhr.status < 300) {
                    
                        const headers = new Headers(resHeaders);

                        const response = new Response(xhr.responseText, {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            headers: headers
                        });

                        const http_reponse = new HttpResponse(
                            response,
                            xhr.responseText
                        );

                        resolve(http_reponse);
                    
                    } else if (xhr.status >= 300 && xhr.status < 400) {
                        console.log('Rediecting.......')

                        const headers = new Headers(resHeaders);

                        const response = new Response(xhr.responseText, {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            headers: headers
                        });

                        const http_reponse = new HttpResponse(
                            response,
                            xhr.responseText
                        );

                        resolve(http_reponse);
                    } else {
                        console.error('Request failed:', xhr.status, xhr.statusText);
                        
                        // const error = new Error(`HTTP Error: ${xhr.status} ${xhr.statusText}`);
                        // (error as any).response = new Response(xhr.responseText, {
                        //     status: xhr.status,
                        //     statusText: xhr.statusText
                        // });

                        const error = new HttpError(
                            `HTTP Error: ${xhr.status} ${xhr.statusText}`, 
                            new HttpResponse(new Response(xhr.responseText, {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                headers: new Headers(resHeaders)
                            }), xhr.responseText),
                            { cause: xhr.statusText }
                        );
                        
                        reject(error);
                    }
                }
            };
            
            xhr.onerror = function () {
                console.error('Network error occurred.');
        
                let error = null;

                if (xhr.readyState === XMLHttpRequest.DONE) {
                    const resHeaders = xhr.getAllResponseHeaders()
                        .split('\r\n')
                        .map(v => v.trim().split(':').map(s => s.trim()))
                        .filter(a => a.length === 2 && a[0] !== '') as [string, string][];
                    
                    error = new HttpError(
                        `HTTP Error: ${xhr.status} ${xhr.statusText}`, 
                        new HttpResponse(new Response(xhr.responseText, {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            headers: new Headers(resHeaders)
                        }), xhr.responseText),
                        { cause: xhr.statusText }
                    );
                } else {
                    error = new Error('Network error occurred');
                }

                reject(error);
            };
            
            xhr.ontimeout = function () {
                console.error('Request timeout.');
                reject(new Error('Request timeout'));
            };
            
            // Handle request body
            let body: XMLHttpRequestBodyInit | null = null;
            if (init?.body) {
                if (
                    typeof init.body === 'string' ||
                    init.body instanceof Blob ||
                    init.body instanceof FormData ||
                    init.body instanceof URLSearchParams ||
                    init.body instanceof ArrayBuffer 
                ) {
                    body = init.body;
                } else {
                    throw new Error('Unsupported body type for XMLHttpRequest');
                }
            }
            
            xhr.send(body);
        });
    }
    
    static async get(url: string, init?: Omit<RequestInit, 'method'>, callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        return HttpRequest.fetch(url, { ...init, method: 'GET' }, callbacks);
    }
    
    static async post(url: string, data?: BodyInit | Record<string, any> | null, init?: Omit<RequestInit, 'method' | 'body'>, callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        let body: BodyInit | null = null;
        const headers = new Headers(init?.headers);
        
        if (data) {
            if (data instanceof FormData) {
                body = data;
            } else if (typeof data === 'object') {
                body = JSON.stringify(data);
                headers.set('Content-Type', 'application/json');
            } else {
                body = String(data);
            }
        }
        
        return HttpRequest.fetch(url, {
            ...init,
            method: 'POST',
            headers,
            body
        }, callbacks);
    }
    
    static async put(url: string, data?: BodyInit | Record<string, any> | null, init?: Omit<RequestInit, 'method' | 'body'>, callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        let body: BodyInit | null = null;
        const headers = new Headers(init?.headers);
        
        if (data) {
            if (data instanceof FormData) {
                body = data;
            } else if (typeof data === 'object') {
                body = JSON.stringify(data);
                headers.set('Content-Type', 'application/json');
            } else {
                body = String(data);
            }
        }
        
        return HttpRequest.fetch(url, {
            ...init,
            method: 'PUT',
            headers,
            body
        }, callbacks);
    }

     static async patch(url: string, data?: BodyInit | Record<string, any> | null, init?: Omit<RequestInit, 'method' | 'body'>, callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        let body: BodyInit | null = null;
        const headers = new Headers(init?.headers);
        
        if (data) {
            if (data instanceof FormData) {
                body = data;
            } else if (typeof data === 'object') {
                body = JSON.stringify(data);
                headers.set('Content-Type', 'application/json');
            } else {
                body = String(data);
            }
        }
        
        return HttpRequest.fetch(url, {
            ...init,
            method: 'PATCH',
            headers,
            body
        }, callbacks);
    }
    
    static async delete(url: string, init?: Omit<RequestInit, 'method'>, callbacks: HttpRequestCallbacks = {}): Promise<HttpResponse> {
        return HttpRequest.fetch(url, { ...init, method: 'DELETE' }, callbacks);
    }
}