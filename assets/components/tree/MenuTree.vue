<template>
  <div class="mb-tree">
    <div
      v-for="(element, idx) in items"
      :key="element.id ?? element.label"
      class="mb-tree__node"
      :class="{ 'mb-tree__node--being-dragged': dnd.isBeingDragged(element) }"
    >
      <div
        class="mb-tree__drop-slot"
        :class="{
          'mb-tree__drop-slot--visible': dnd.isSlotNearCursor(idx, 'before'),
          'mb-tree__drop-slot--active': dnd.isSlotActive(element, idx, 'before'),
        }"
        @dragenter.prevent="dnd.onSlotDragOver($event, element, idx, 'before')"
        @dragover.prevent="dnd.onSlotDragOver($event, element, idx, 'before')"
        @drop.prevent="dnd.onSlotDrop($event, element, idx, 'before')"
      ></div>
      <div
        class="mb-tree__row"
        :data-item-id="element.id ?? undefined"
        :class="{
          'mb-tree__row--inactive': !element.active,
          'mb-tree__row--editing': element.id != null && element.id === editingId,
          'mb-tree__row--drop-child': dnd.isDropTarget(element, 'child'),
          'mb-tree__row--hover-pending': dnd.isHoverPending(element),
        }"
        @dragenter.prevent="dnd.onRowDragOver($event, element, idx)"
        @dragover.prevent="dnd.onRowDragOver($event, element, idx)"
        @dragleave="dnd.onRowDragLeave($event, element)"
        @drop.prevent="dnd.onRowDrop($event, element, idx)"
      >
        <input
          v-if="selectMode && element.id"
          type="checkbox"
          class="mb-tree__select"
          :checked="selectedIds.has(element.id)"
          @click.stop
          @change="$emit('toggle-select', element.id!)"
        />
        <span
          class="mb-tree__handle"
          :title="t('tree.dragHandle')"
          :draggable="dnd.canDrag(element)"
          @dragstart="dnd.onHandleDragStart($event, element)"
          @dragend="dnd.onHandleDragEnd"
        >
          <svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor" aria-hidden="true">
            <circle cx="2" cy="2" r="1.2" />
            <circle cx="8" cy="2" r="1.2" />
            <circle cx="2" cy="7" r="1.2" />
            <circle cx="8" cy="7" r="1.2" />
            <circle cx="2" cy="12" r="1.2" />
            <circle cx="8" cy="12" r="1.2" />
          </svg>
        </span>
        <span class="mb-tree__status" :title="element.active ? 'Active' : 'Inactive'"></span>
        <img
          v-if="element.iconImage"
          class="mb-tree__icon mb-tree__icon--image"
          :src="element.iconImage"
          alt=""
          aria-hidden="true"
        />
        <i v-else-if="element.icon" :class="['mb-tree__icon', element.icon]"></i>
        <input
          v-if="renamingId === element.id"
          ref="renameInputEl"
          v-model="renameValue"
          type="text"
          class="mb-tree__label-input"
          @keydown.enter.stop.prevent="commitRename(element)"
          @keydown.escape.stop.prevent="cancelRename()"
          @blur="commitRename(element)"
          @click.stop
        />
        <span
          v-else
          class="mb-tree__label"
          :class="{ 'mb-tree__label--fallback': display.isFallback(element) }"
          :title="display.isFallback(element) ? t('tree.previewFallback') : t('tree.dblclickRename')"
          @click="$emit('select', element)"
          @dblclick.stop="startRename(element)"
        >
          {{ display.displayLabel(element) }}
        </span>
        <span
          v-if="element.labelTranslated"
          class="mb-tree__trans-key"
          :title="`Symfony translator key${element.translationDomain ? ' (' + element.translationDomain + ')' : ''}`"
          >🌐</span
        >
        <span
          v-if="display.visibilityIcon(element)"
          class="mb-tree__visibility"
          :title="display.visibilityTitle(element)"
          >{{ display.visibilityIcon(element) }}</span
        >
        <span v-if="display.localeFlags(element).length" class="mb-tree__locales">
          <span
            v-for="l in display.localeFlags(element)"
            :key="l.code"
            class="mb-tree__locale-flag"
            :class="{ 'mb-tree__locale-flag--current': previewLocale === l.code }"
            :title="l.name"
            >{{ l.flag }}</span
          >
        </span>
        <span v-if="element.requiredRoles?.length" class="mb-tree__badge" title="Restricted by role">
          🔒 {{ element.requiredRoles.length }}
        </span>
        <div class="mb-tree__actions">
          <button
            v-if="parentId !== null && depth >= 2"
            class="mb-tree__btn"
            @click.stop="onUnnestToRootClick(element)"
            :title="t('tree.unnestToRoot')"
            type="button"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 14 14"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M12 3l-5 4 5 4" />
              <path d="M6 3l-5 4 5 4" />
            </svg>
          </button>
          <button
            v-if="parentId !== null"
            class="mb-tree__btn"
            @click.stop="onUnnestClick(element)"
            :title="t('tree.unnest')"
            type="button"
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 14 14"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M9 3l-5 4 5 4" />
              <path d="M4 7h8" />
            </svg>
          </button>
          <button
            class="mb-tree__btn"
            @click.stop="$emit('add-child', element)"
            :title="t('tree.addChild')"
            type="button"
          >
            <svg
              width="12"
              height="12"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              aria-hidden="true"
            >
              <path d="M6 2v8M2 6h8" />
            </svg>
          </button>
          <button
            class="mb-tree__btn"
            @click.stop="$emit('duplicate', element)"
            :title="t('tree.duplicate')"
            type="button"
          >
            <svg
              width="12"
              height="12"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <rect x="3" y="3" width="6" height="6" rx="1" />
              <path d="M5.5 3V1.75A.75.75 0 0 1 6.25 1h4a.75.75 0 0 1 .75.75v4a.75.75 0 0 1-.75.75H9" />
            </svg>
          </button>
          <button
            class="mb-tree__btn"
            :class="{ 'mb-tree__btn--off': !element.active }"
            @click.stop="$emit('toggle-active', element)"
            :title="element.active ? t('tree.deactivate') : t('tree.activate')"
            type="button"
          >
            <svg
              v-if="element.active"
              width="12"
              height="12"
              viewBox="0 0 12 12"
              fill="currentColor"
              aria-hidden="true"
            >
              <circle cx="6" cy="6" r="3.5" />
            </svg>
            <svg
              v-else
              width="12"
              height="12"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              aria-hidden="true"
            >
              <circle cx="6" cy="6" r="3.5" />
            </svg>
          </button>
          <button
            class="mb-tree__btn mb-tree__btn--danger"
            @click.stop="$emit('delete', element)"
            :title="t('tree.delete')"
            type="button"
          >
            <svg
              width="12"
              height="12"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              aria-hidden="true"
            >
              <path d="M3 3l6 6M9 3l-6 6" />
            </svg>
          </button>
        </div>
      </div>

      <MenuTree
        v-if="display.hasChildren(element)"
        v-model:items="element.children!"
        :parent-id="element.id"
        :preview-locale="previewLocale"
        :editing-id="editingId"
        :select-mode="selectMode"
        :selected-ids="selectedIds"
        :depth="depth + 1"
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
        class="mb-tree--nested"
      />
      <div
        v-if="idx === items.length - 1"
        class="mb-tree__drop-slot"
        :class="{
          'mb-tree__drop-slot--visible': dnd.isSlotNearCursor(idx, 'after'),
          'mb-tree__drop-slot--active': dnd.isSlotActive(element, idx, 'after'),
        }"
        @dragenter.prevent="dnd.onSlotDragOver($event, element, idx, 'after')"
        @dragover.prevent="dnd.onSlotDragOver($event, element, idx, 'after')"
        @drop.prevent="dnd.onSlotDrop($event, element, idx, 'after')"
      ></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from '../../i18n'
import { useTreeDnD } from '../../composables/tree/useTreeDnD'
import { useTreeRename } from '../../composables/tree/useTreeRename'
import { useTreeItemDisplay } from '../../composables/tree/useTreeItemDisplay'
import type { MenuItem, MovePayload } from '../../types/menu'

const { t } = useI18n()

const props = withDefaults(
  defineProps<{
    items: MenuItem[]
    parentId?: string | null
    previewLocale?: string | null
    editingId?: string | null
    selectMode?: boolean
    selectedIds?: Set<string>
    depth?: number
  }>(),
  {
    parentId: null,
    previewLocale: null,
    editingId: null,
    selectMode: false,
    selectedIds: () => {
      return new Set<string>()
    },
    depth: 0,
  },
)

const emit = defineEmits<{
  (e: 'update:items', value: MenuItem[]): void
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
}>()

const items = computed<MenuItem[]>({
  get: () => {
    return props.items
  },
  set: (value: MenuItem[]) => {
    return emit('update:items', value)
  },
})

const renameInputEl = ref<HTMLInputElement | HTMLInputElement[] | null>(null)

const { renamingId, renameValue, startRename, cancelRename, commitRename, isRenaming } = useTreeRename({
  inputRef: () => {
    return renameInputEl.value
  },
  onCommit: (payload) => {
    return emit('rename', payload)
  },
})

const display = useTreeItemDisplay({
  previewLocale: () => {
    return props.previewLocale ?? null
  },
})

const dnd = useTreeDnD({
  items: () => {
    return props.items
  },
  parentId: () => {
    return props.parentId ?? null
  },
  isRenaming: (id) => {
    return isRenaming(id)
  },
  emitMove: (payload) => {
    return emit('move', payload)
  },
})

function onUnnestClick(element: MenuItem): void {
  if (!element.id) {
    return
  }
  emit('unnest', element.id)
}

function onUnnestToRootClick(element: MenuItem): void {
  if (!element.id) {
    return
  }
  emit('unnest-to-root', element.id)
}
</script>
