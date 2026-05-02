<template>
  <section class="mb-admin__tree-pane">
    <div class="mb-admin__pane-header">
      <span class="mb-admin__pane-title">
        {{ selectedMenu ?? t('tree.noMenuTitle') }}
      </span>
      <div class="mb-admin__pane-actions" v-if="selectedMenu">
        <div class="mb-admin__preview" :class="{ 'mb-admin__preview--active': previewLocale !== null }">
          <span class="mb-admin__preview-label">{{ t('tree.previewIn') }}</span>
          <select v-model="previewLocaleModel" class="mb-admin__preview-select">
            <option :value="null">{{ t('tree.previewDefault') }}</option>
            <option v-for="l in locales" :key="l.code" :value="l.code">{{ l.flag }} {{ l.name }}</option>
          </select>
        </div>
        <button
          class="mb-btn mb-btn--ghost mb-btn--sm"
          @click="$emit('reload')"
          type="button"
          :title="t('tree.reload')"
        >
          ↻ {{ t('tree.reload') }}
        </button>
        <button
          class="mb-btn mb-btn--ghost mb-btn--sm"
          :class="{ 'mb-btn--active': selectMode }"
          @click="$emit('toggle-select-mode')"
          type="button"
          :title="t('tree.bulkMode')"
        >
          ☑ {{ t('tree.bulkMode') }}
        </button>
        <button
          class="mb-btn mb-btn--ghost mb-btn--sm"
          @click="$emit('open-trash')"
          type="button"
          :title="t('tree.trash')"
        >
          🗑 {{ t('tree.trash') }}
        </button>
        <button class="mb-btn mb-btn--primary mb-btn--sm" @click="$emit('add-item')">
          {{ t('tree.addItem') }}
        </button>
      </div>
    </div>

    <div class="mb-admin__tree-body">
      <div v-if="loading" class="mb-admin__loading">
        <div class="mb-admin__skeleton"></div>
        <div class="mb-admin__skeleton"></div>
        <div class="mb-admin__skeleton"></div>
      </div>

      <div v-else-if="!selectedMenu" class="mb-admin__placeholder">
        <div class="mb-admin__placeholder-icon">←</div>
        <p class="mb-admin__placeholder-text">{{ t('tree.selectMenu') }}</p>
      </div>

      <div v-else-if="tree.length === 0" class="mb-admin__placeholder">
        <div class="mb-admin__placeholder-icon">✦</div>
        <p class="mb-admin__placeholder-text">{{ t('tree.empty') }}</p>
      </div>

      <template v-else>
        <BulkActionBar
          v-if="selectMode && selectedIds.size > 0"
          :count="selectedIds.size"
          @activate="$emit('bulk-activate')"
          @deactivate="$emit('bulk-deactivate')"
          @delete="$emit('bulk-delete')"
          @clear="$emit('bulk-clear')"
        />
        <MenuTree
          :items="tree"
          :preview-locale="previewLocale"
          :editing-id="editingId"
          :select-mode="selectMode"
          :selected-ids="selectedIds"
          @update:items="$emit('update:tree', $event)"
          @select="$emit('select', $event)"
          @move="$emit('move', $event)"
          @unnest="$emit('unnest', $event)"
          @unnest-to-root="$emit('unnest-to-root', $event)"
          @delete="$emit('delete', $event)"
          @toggle-active="$emit('toggle-active', $event)"
          @add-child="$emit('add-child', $event)"
          @duplicate="$emit('duplicate', $event)"
          @rename="$emit('rename', $event)"
          @toggle-select="$emit('toggle-select', $event)"
        />
      </template>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '../../i18n'
import MenuTree from './MenuTree.vue'
import BulkActionBar from './BulkActionBar.vue'
import type { MenuItem, MovePayload } from '../../types/menu'

const { t, locales } = useI18n()

const props = defineProps<{
  selectedMenu: string | null
  tree: MenuItem[]
  loading: boolean
  previewLocale: string | null
  editingId: string | null
  selectMode: boolean
  selectedIds: Set<string>
}>()

const emit = defineEmits<{
  (e: 'update:tree', items: MenuItem[]): void
  (e: 'update:previewLocale', value: string | null): void
  (e: 'toggle-select-mode'): void
  (e: 'open-trash'): void
  (e: 'reload'): void
  (e: 'add-item'): void
  (e: 'select', item: MenuItem): void
  (e: 'move', payload: MovePayload): void
  (e: 'unnest', id: string): void
  (e: 'unnest-to-root', id: string): void
  (e: 'delete', item: MenuItem): void
  (e: 'toggle-active', item: MenuItem): void
  (e: 'add-child', item: MenuItem): void
  (e: 'duplicate', item: MenuItem): void
  (e: 'rename', payload: { id: string; label: string }): void
  (e: 'toggle-select', id: string): void
  (e: 'bulk-activate'): void
  (e: 'bulk-deactivate'): void
  (e: 'bulk-delete'): void
  (e: 'bulk-clear'): void
}>()

const previewLocaleModel = computed<string | null>({
  get: (): string | null => {
    return props.previewLocale
  },
  set: (value: string | null): void => {
    emit('update:previewLocale', value)
  },
})
</script>
