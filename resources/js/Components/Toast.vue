<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');
let timer = null;

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            type.value = 'success';
            message.value = flash.success;
            showToast();
        } else if (flash?.error) {
            type.value = 'error';
            message.value = flash.error;
            showToast();
        }
    },
    { deep: true, immediate: true }
);

function showToast() {
    visible.value = true;
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        visible.value = false;
    }, 4500);
}

function dismiss() {
    visible.value = false;
    if (timer) clearTimeout(timer);
}
</script>

<template>
    <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible && message"
            class="fixed bottom-5 right-5 z-50 max-w-md w-full shadow-xl rounded-xl border p-4 flex items-start space-x-3 pointer-events-auto"
            :class="[
                type === 'success'
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                    : 'bg-rose-50 border-rose-200 text-rose-900'
            ]"
        >
            <div class="shrink-0 mt-0.5">
                <svg
                    v-if="type === 'success'"
                    class="w-5 h-5 text-emerald-600"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg
                    v-else
                    class="w-5 h-5 text-rose-600"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="flex-1 text-xs font-medium leading-relaxed">
                {{ message }}
            </div>
            <button
                type="button"
                @click="dismiss"
                class="shrink-0 text-slate-400 hover:text-slate-600 rounded-lg p-1 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </Transition>
</template>
