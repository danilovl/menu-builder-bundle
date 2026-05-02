<template>
  <Transition name="mb-fade">
    <div v-if="open" class="mb-modal" @click.self="$emit('close')">
      <div class="mb-modal__panel">
        <div class="mb-modal__header">
          <span>🗑 {{ t('tree.trash') }} — {{ menuName }}</span>
          <button class="mb-modal__close" @click="$emit('close')" type="button">×</button>
        </div>
        <div class="mb-modal__body">
          <div v-if="loading" class="mb-admin__placeholder">
            <div class="mb-admin__skeleton"></div>
            <div class="mb-admin__skeleton"></div>
          </div>
          <div v-else-if="items.length === 0" class="mb-admin__placeholder">
            <div class="mb-admin__placeholder-icon">✦</div>
            <p class="mb-admin__placeholder-text">{{ t('tree.trashEmpty') }}</p>
          </div>
          <div v-else class="mb-trash">
            <div v-for="entry in items" :key="entry.id ?? ''" class="mb-trash__row">
              <span class="mb-trash__label">{{ entry.label || t('tree.untitled') }}</span>
              <span v-if="entry.uri || entry.route" class="mb-trash__target">
                {{ entry.route ? '→ ' + entry.route : entry.uri }}
              </span>
              <button class="mb-btn mb-btn--ghost mb-btn--sm" @click="$emit('restore', entry)" type="button">
                {{ t('tree.restore') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { useI18n } from '../../i18n'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

defineProps<{
  open: boolean
  menuName: string | null
  items: MenuItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'close'): void
  (e: 'restore', item: MenuItem): void
}>()
</script>
