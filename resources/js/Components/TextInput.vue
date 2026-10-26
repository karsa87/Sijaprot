<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value && input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        class="border-slate-300 focus:border-[#1A5276] focus:ring-[#1A5276] rounded-lg shadow-2xs text-sm text-slate-800 placeholder-slate-400 disabled:bg-slate-100 disabled:text-slate-500 transition"
    />
</template>
