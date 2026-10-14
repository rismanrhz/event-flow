<script setup lang="ts">
import { useId } from 'vue'

withDefaults(
  defineProps<{
    modelValue?: string
    label?: string
    type?: string
    placeholder?: string
    autocomplete?: string
    error?: string
    required?: boolean
    disabled?: boolean
  }>(),
  {
    modelValue: '',
    label: '',
    type: 'text',
    placeholder: '',
    autocomplete: 'off',
    error: '',
    required: false,
    disabled: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const inputId = useId()
</script>

<template>
  <div class="w-full">
    <label v-if="label !== ''" :for="inputId" class="mb-1.5 block text-sm font-medium text-slate-700">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>
    <input
      :id="inputId"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :disabled="disabled"
      :class="[
        'input-base',
        error !== '' ? 'border-red-400 focus:border-red-500 focus:ring-red-500/30' : '',
      ]"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="error !== ''" class="mt-1.5 text-sm text-red-600">{{ error }}</p>
  </div>
</template>