<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex items-center justify-center">
                <div
                    class="mb-3 w-auto rounded-full border border-border bg-card p-0.5 shadow-sm"
                >
                    <div
                        class="relative overflow-hidden rounded-full border border-border bg-muted p-2.5"
                    >
                        <div
                            class="absolute inset-0 grid grid-cols-5 opacity-50"
                        >
                            <div
                                v-for="i in 5"
                                :key="`col-${i}`"
                                class="border-r border-border last:border-r-0"
                            />
                        </div>
                        <div
                            class="absolute inset-0 grid grid-rows-5 opacity-50"
                        >
                            <div
                                v-for="i in 5"
                                :key="`row-${i}`"
                                class="border-b border-border last:border-b-0"
                            />
                        </div>
                        <ScanLine
                            class="relative z-20 size-6 text-foreground"
                        />
                    </div>
                </div>
                <DialogTitle>Delete {{ passkey.name ?? 'this' }} Passkey</DialogTitle>
                <DialogDescription class="text-center">
                    Are you sure you want to delete {{ passkey.name ?? 'this' }} passkey.
                    You will need to enter your password. Once you delete this passkey, it cannot be used again.
                </DialogDescription>
            </DialogHeader>


            <div>
                <form class="space-y-6" @submit.prevent="deletePasskey">
                    
                    <div class="grid gap-2">
                        <Label for="password" class="">Password</Label>
                        <Label for="password" class="sr-only">Password</Label>
                        <Input id="password" type="password" name="password" ref="passwordInput" v-model="form.password" placeholder="Password" />
                        <template v-if="errors.password" v-for="error in errors.password" :key="error">
                            <InputError :message="error" />
                        </template>
                    </div>

                    <template v-if="server_error">
                        <InputError :message="server_error" />
                    </template>
    
                    <div class="grid grid-cols-2 gap-4">
                        <Button type="button" class="dark:bg-neutral-800 dark:hover:bg-neutral-700 mt-4 w-full text-white" @click="isOpen = false">
                            Cancel
                        </Button>

                        <Button
                            type="submit"
                            class="mt-4 w-full"
                            :tabindex="4"
                            :disabled="working"
                            data-test="login-button"
                        >
                            <Spinner v-if="working" />
                            Delete
                        </Button>
                    </div>
                </form>
            </div>


        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { Check, Copy, ScanLine } from 'lucide-vue-next';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { useAppearance } from '@/composables/useAppearance';
import { 
    register_request_options,
    login, register_creation_options,
    store as storePasskey, update_passkey,
    delete_passkey  
} from '@/routes/passkeys/webauthn/passkeys';

import { HttpError, HttpRequest, HttpResponse } from '@/lib/utils';

import { startRegistration, startAuthentication } from '@simplewebauthn/browser';
import type { PublicKeyCredentialCreationOptionsJSON } from '@simplewebauthn/browser';
import { type PublicKeyCredentialSource } from '@/types';
import passkeys from '@/routes/passkeys';

const { resolvedAppearance } = useAppearance();

const props = defineProps<{
    passkey: PublicKeyCredentialSource;
}>();
const isOpen = defineModel<boolean>('isOpen');

const working = ref<boolean>(false);
const server_error = ref<string | null>(null);

const form = ref({
    password: '',
})

const errors = ref<{
    password?: string[];
}>({});

const deletePasskey = async () => {
    working.value = true;
    server_error.value = null;
    errors.value = {};

    await HttpRequest.patch(delete_passkey.url(props.passkey.id), form.value)
        .then(() => {
            isOpen.value = false;
        })
        .catch((error: HttpError) => {
            if (error.response.status === 422) {
                errors.value = error.response.data.errors;
            } else {
                server_error.value =
                    error.response.data.message || 'An unexpected error occurred. Please try again.';
            }
        })
        .finally(() => {
            working.value = false;

            router.visit(passkeys.index.url(), {
                preserveState: true,
                preserveScroll: true,
            });
        });
}

</script>