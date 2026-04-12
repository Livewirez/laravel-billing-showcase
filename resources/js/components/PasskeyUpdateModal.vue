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
                <DialogTitle>Create a Passkey</DialogTitle>
                <DialogDescription class="text-center">
                    Please enter the name of your passkey.
                </DialogDescription>
            </DialogHeader>


            <div>
                <form class="space-y-6" @submit.prevent="updatePasskey">
                    
                    <div class="grid gap-2">
                        <Label for="name" class="">Name</Label>
                        <Label for="name" class="sr-only">Name</Label>
                        <Input id="name" type="text" name="name" ref="nameInput" v-model="form.name" placeholder="Name" />
                        <template v-if="errors.name" v-for="error in errors.name" :key="error">
                            <InputError :message="error" />
                        </template>
                    </div>

                    <div class="grid gap-2">
                        <Label for="device_name" class="">Device Name</Label>
                        <Label for="device_name" class="sr-only">Device Name</Label>
                        <Input id="device_name" type="text" name="device_name" ref="deviceNameInput" v-model="form.device_name" placeholder="Device Name" />
                        <template v-if="errors.device_name" v-for="error in errors.device_name" :key="error">
                            <InputError :message="error" />
                        </template>
                    </div>

                    <template v-if="registration_error">
                        <InputError :message="registration_error" />
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
                            Save
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
import { Form, Head } from '@inertiajs/vue3';
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
const registration_error = ref<string | null>(null);

const form = ref({
    name: props.passkey?.name ?? '',
    device_name: props.passkey?.device_name ?? '',
})

const errors = ref<{
    name?: string[];
    device_name?: string[];
}>({});

const updatePasskey = async () => {
    working.value = true;
    registration_error.value = null;
    errors.value = {};

    await HttpRequest.patch(update_passkey.url(props.passkey.id), form.value)
        .then(() => {
            isOpen.value = false;
        })
        .catch((error: HttpError) => {
            if (error.response.status === 422) {
                errors.value = error.response.data.errors;
            } else {
                registration_error.value =
                    error.response.data.message || 'An unexpected error occurred. Please try again.';
            }
        })
        .finally(() => {
            working.value = false;
        });
}

</script>