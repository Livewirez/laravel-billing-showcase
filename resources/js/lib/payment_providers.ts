import { JWT } from '@/lib/utils';
import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';
import { createCyberSourceMicroformSession } from "@/actions/App/Http/Controllers/OrdersController";

export type PayPalCreateOrderLink = {
    href: string,
    rel: string,
    method: string
}

export type PaymentMethod = {
    id: number,
    billable_payment_method_id: string,
    payment_provider: string,
    billing_email: string,
    is_active?: boolean,
}


export class CybersourceMicroformHandler {
    private _token: string;
    private _libraryUrl: string;
    private _integrity: string;

    constructor(
        token: string,
        libraryUrl: string,
        integrity: string,
    ) {
        this._token = token;
        this._libraryUrl = libraryUrl;
        this._integrity = integrity;
    }

    /** Return the session token */
    get token(): string {
        return this._token;
    }

    get libraryUrl(): string {
        return this._libraryUrl;
    }

    get integrity(): string {
        return this._integrity;
    }

    static async make() {
        
        return await HttpRequest.post(createCyberSourceMicroformSession.url(), {
            // Include any necessary data for the session creation
        })
        .then((res: HttpResponse) => {
            console.log('CybersourceToken', res)
            console.log('Parsed JWT', JWT.parse(res.data.token));
            const clientLibrary: Record<string, any> = JWT.parse(res.data.token).ctx[0].data;
            console.log('Client Library', clientLibrary);
    
            return new CybersourceMicroformHandler(
                res.data.token, clientLibrary.clientLibrary,
                clientLibrary.clientLibraryIntegrity
            )
        }).catch((error: HttpError) => {
            console.error('Error: ', error.response, error.response?.data)
        })
    }
}