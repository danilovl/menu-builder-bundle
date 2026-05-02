<template>
  <div class="mb-admin__bulk-bar">
    <span class="mb-admin__bulk-count">{{ t('tree.bulkSelected', { count: String(count) }) }}</span>
    <button class="mb-btn mb-btn--ghost mb-btn--sm" @click="$emit('activate')" type="button">
      {{ t('tree.bulkActivate') }}
    </button>
    <button class="mb-btn mb-btn--ghost mb-btn--sm" @click="$emit('deactivate')" type="button">
      {{ t('tree.bulkDeactivate') }}
    </button>
    <button class="mb-btn mb-btn--ghost mb-btn--sm mb-btn--danger" @click="$emit('delete')" type="button">
      {{ t('tree.bulkDelete') }}
    </button>
    <button class="mb-btn mb-btn--ghost mb-btn--sm" @click="$emit('clear')" type="button">
      {{ t('tree.bulkClear') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '../../i18n'

const { t } = useI18n()

defineProps<{
  count: number
}>()

defineEmits<{
  (e: 'activate'): void
  (e: 'deactivate'): void
  (e: 'delete'): void
  (e: 'clear'): void
}>()
</script>
