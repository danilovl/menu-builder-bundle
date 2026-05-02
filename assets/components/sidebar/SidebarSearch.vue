<template>
  <div class="mb-admin__search">
    <input
      :value="term"
      @input="emit('update:term', ($event.target as HTMLInputElement).value)"
      type="text"
      class="mb-admin__search-input"
      :placeholder="t('sidebar.searchPlaceholder')"
      @keyup="emit('input')"
    />
    <button v-if="term" class="mb-admin__search-clear" @click="emit('clear')" type="button" :title="t('form.cancel')">
      ×
    </button>
  </div>

  <div v-if="term && results.length > 0" class="mb-admin__search-results">
    <button
      v-for="r in results"
      :key="r.id ?? ''"
      class="mb-admin__search-result"
      @click="emit('select', r)"
      type="button"
    >
      <span class="mb-admin__search-result-menu">{{ r.menuName }}</span>
      <span class="mb-admin__search-result-label">{{ r.label || t('tree.untitled') }}</span>
      <span v-if="r.uri || r.route" class="mb-admin__search-result-target">
        {{ r.route ? '→ ' + r.route : r.uri }}
      </span>
    </button>
  </div>
  <div v-else-if="term && searchedOnce && !loading" class="mb-admin__search-empty">
    {{ t('sidebar.searchEmpty') }}
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '../../i18n'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

defineProps<{
  term: string
  results: MenuItem[]
  loading: boolean
  searchedOnce: boolean
}>()

const emit = defineEmits<{
  (e: 'update:term', value: string): void
  (e: 'input'): void
  (e: 'clear'): void
  (e: 'select', item: MenuItem): void
}>()
</script>
