<template>
    <div>
        <div
            class="text-center text-sm text-muted-foreground"
        >
            Log in with a passkey instead?
            <Button
                class="ml-2" 
                :disabled="working" @click="startWebauthnLogin()" :class="{ 'opacity-25 cursor-not-allowed': working }"
                :tabindex="6"
            >
                <Spinner v-if="working" />
                <Key />
            </Button>
        </div>

        <template v-if="authentication_error">
            <InputError :message="authentication_error" />
        </template>
    </div>

</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Key } from 'lucide-vue-next';

import { 
    register_request_options,
    login, register_creation_options,
    store as storePasskey, update_passkey,
    delete_passkey  
} from '@/routes/passkeys/webauthn/passkeys';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';
import { startRegistration, startAuthentication } from '@simplewebauthn/browser';

import type { PublicKeyCredentialRequestOptionsJSON } from '@simplewebauthn/browser';
import { WebAuthnError } from '@simplewebauthn/browser';

const working = ref<boolean>(false);
const authentication_error = ref<string | null>(null);

function startWebauthnLogin() {
    return new Promise((resolve, reject) => {
        // Initialize store state
        working.value = true

        // Fetch authentication options
        HttpRequest.get(register_request_options.url())
            .then(async (response) => {
                console.log('Response:Login Options', response) 

                try {
                    // Parse authentication options
                    const publicKeyCredentialRequestOptions = JSON.parse(
                        response.data.publicKeyCredentialRequestOptions || '{}'
                    ) as PublicKeyCredentialRequestOptionsJSON;

                    // Start authentication process
                    const authResponse = await startAuthentication({ optionsJSON: publicKeyCredentialRequestOptions });

                    // Log authentication response for debugging
                    console.log('Authentication Response:', {
                        raw: authResponse,
                        serialized: JSON.stringify(authResponse)
                    });

                    // Verify authentication with server
                    const verificationResponse = await HttpRequest.post(login.url(), {
                        credentials: JSON.stringify(authResponse),
                        credentials_id: authResponse.id
                    });

                    // Reset store state on success
                    working.value = false

                    console.log('verificationResponse', verificationResponse) 

                    // Handle successful login
                    if (verificationResponse.data.redirect) {
                        window.location.href = verificationResponse.data.redirect;
                    }

                    resolve(verificationResponse.data);

                } catch (error: unknown) {
                    handleAuthenticationError(error, reject);
                }
            })
            .catch(error => handleNetworkError(error, reject));
    });
}


function handleAuthenticationError(error: unknown, reject: Function) {
    let errorMessage = 'Authentication failed';

    // Check if error is an AxiosError with response data
    if (error instanceof HttpError && error.response?.data) {
        // Handle structured error responses
        errorMessage = extractErrorMessage(error.response.data);
    } 
    // Handle WebAuthn specific errors
    else if (error instanceof Error) {
        if (error.name === 'NotAllowedError') {
            errorMessage = 'Authentication was declined or timed out';
        } else if (error.name === 'SecurityError') {
            errorMessage = 'A security error occurred';
        } else {
            errorMessage = error.message || 'An unknown error occurred';
        }
    } 
    // Fallback for any other type of error
    else {
        errorMessage = 'An unknown error occurred';
    }

    updateStoreWithError(errorMessage);
    reject(error);
}

function handleNetworkError(error: Error | WebAuthnError | HttpError, reject: Function) {
    console.error('Network error during authentication:', error);
    
    const errorMessage = 'Unable to connect to authentication service. Please try again.';
    updateStoreWithError(errorMessage);
    reject(error);
}


function extractErrorMessage(responseData: any) {
    if (responseData.credentials) {
        return Array.isArray(responseData.credentials) 
            ? responseData.credentials[0] 
            : responseData.credentials;
    }
    return responseData.message || 'Authentication failed';
}

function updateStoreWithError(errorMessage: string) {
    working.value = false;
    authentication_error.value = errorMessage;
}

</script>