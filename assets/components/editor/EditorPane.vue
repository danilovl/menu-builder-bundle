<template>
  <aside class="mb-admin__form-pane">
    <div
      class="mb-admin__resize-handle"
      :class="{ 'mb-admin__resize-handle--active': isResizing }"
      @mousedown="$emit('resize-start', $event)"
      title="Drag to resize"
    >
      <span class="mb-admin__resize-grip"></span>
    </div>
    <Transition name="mb-slide">
      <MenuItemForm
        v-if="editing"
        :key="editingItem.id ?? 'new'"
        :model-value="editingItem"
        :menus="menus"
        :tree="tree"
        @save="$emit('save', $event)"
        @cancel="$emit('cancel')"
      />
      <div v-else class="mb-admin__placeholder">
        <div class="mb-admin__placeholder-icon">✎</div>
        <p class="mb-admin__placeholder-text">{{ t('form.placeholder') }}</p>
      </div>
    </Transition>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '../../i18n'
import MenuItemForm from './MenuItemForm.vue'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

const props = defineProps<{
  editing: MenuItem | null
  menus: string[]
  tree: MenuItem[]
  isResizing: boolean
}>()

defineEmits<{
  (e: 'save', payload: MenuItem): void
  (e: 'cancel'): void
  (e: 'resize-start', event: MouseEvent): void
}>()

const editingItem = computed((): MenuItem => {
  return props.editing as MenuItem
})
</script>
