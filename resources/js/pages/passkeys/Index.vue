<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash } from 'lucide-vue-next';
import CreateTeamModal from '@/components/CreateTeamModal.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/passkeys';
import { type PublicKeyCredentialSource } from '@/types';
import PasskeySetupModal from '@/components/PasskeySetupModal.vue';
import PasskeyUpdateModal from '@/components/PasskeyUpdateModal.vue';
import PasskeyDeleteModal from '@/components/PasskeyDeleteModal.vue';


type Props = {
    passkeys: PublicKeyCredentialSource[];
    className?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Passkeys',
                href: index(),
            },
        ],
    },
});

const showPasskeySetupModal = ref<boolean>(false);
const showPasskeyUpdateModal = ref<boolean>(false);
const showPasskeyDeleteModal = ref<boolean>(false);
</script>

<template>
    <Head title="Passkeys" />

    <h1 class="sr-only">Passkeys</h1>

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                title="Passkeys"
                description="Manage your passkeys"
            />

            <Button @click="showPasskeySetupModal = true">
                <Plus /> New Passkey
            </Button>

            <PasskeySetupModal v-model:isOpen="showPasskeySetupModal"/>
        </div>

        <div class="space-y-3">
            <div
                v-for="passkey in passkeys"
                :key="passkey.id"
                data-test="passkey-row"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <div class="flex items-center gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ passkey.name }}</span>
                            
                        </div>
                        <span class="text-sm text-muted-foreground">
                            {{ passkey.device_name }}
                        </span>
                    </div>
                </div>

                <PasskeyUpdateModal v-model:isOpen="showPasskeyUpdateModal" :passkey="passkey"/>
                <PasskeyDeleteModal v-model:isOpen="showPasskeyDeleteModal" :passkey="passkey"/>

                <TooltipProvider>
                    <div class="flex items-center gap-2">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    @click="showPasskeyUpdateModal = true"
                                    data-test="passkey-edit-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <div>
                                        <Pencil class="h-4 w-4" />
                                    </div>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Edit passkey</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    @click="showPasskeyDeleteModal = true"
                                    data-test="passkey-delete-button"
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                >
                                    <div>
                                        <Trash class="h-4 w-4 text-red-400" />
                                    </div>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Delete passkey</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </TooltipProvider>
            </div>

            <p
                v-if="passkeys.length === 0"
                class="py-8 text-center text-muted-foreground"
            >
                You haven't set up any passkys yet.
            </p>
        </div>
    </div>
</template>