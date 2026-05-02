<template>
  <div class="mb-toast" :class="`mb-toast--${type}`" @click="$emit('close')">
    <span class="mb-toast__icon">{{ icon }}</span>
    <div class="mb-toast__body">
      <div class="mb-toast__message">{{ message }}</div>
      <div class="mb-toast__hint">{{ t('toast.dismiss') }}</div>
    </div>
    <span class="mb-toast__close">✕</span>
    <div class="mb-toast__progress"></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '../../i18n'
import type { ToastType } from '../../types/menu'

const { t } = useI18n()

const props = defineProps<{
  message: string
  type: ToastType
}>()

defineEmits<{ (e: 'close'): void }>()

const icon = computed(() => {
  if (props.type === 'success') {
    return '✓'
  }
  if (props.type === 'error') {
    return '✕'
  }

  return 'ℹ'
})
</script>
