<template>
  <aside class="mb-admin__sidebar">
    <div class="mb-admin__sidebar-header">
      <span>{{ t('sidebar.menus') }}</span>
      <div class="mb-admin__sidebar-header-actions">
        <button
          class="mb-admin__sidebar-btn mb-admin__sidebar-btn--icon"
          @click="triggerImport"
          :title="t('sidebar.import')"
          type="button"
        >
          ⇪
        </button>
        <button
          v-if="!creatingMenu"
          class="mb-admin__sidebar-btn"
          @click="startCreateMenu"
          :title="t('sidebar.new')"
          type="button"
        >
          <svg
            width="11"
            height="11"
            viewBox="0 0 12 12"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path d="M6 2v8M2 6h8" />
          </svg>
          {{ t('sidebar.new') }}
        </button>
      </div>
    </div>
    <input ref="importInputEl" type="file" accept="application/json" style="display: none" @change="onImportFile" />

    <SidebarSearch
      :term="searchTerm"
      :results="searchResults"
      :loading="searchLoading"
      :searched-once="searchedOnce"
      @update:term="onSearchTermChange"
      @input="onSearchInput"
      @clear="clearSearch"
      @select="onSearchResultClick"
    />

    <Transition name="mb-collapse">
      <div v-if="creatingMenu" class="mb-admin__new-menu">
        <input
          ref="newMenuInputEl"
          v-model="newMenuName"
          type="text"
          class="mb-admin__new-menu-input"
          :placeholder="t('sidebar.menuPlaceholder')"
          @keydown.enter="confirmCreateMenu"
          @keydown.escape="cancelCreateMenu"
        />
        <div class="mb-admin__new-menu-actions">
          <button
            class="mb-btn mb-btn--primary mb-btn--sm"
            @click="confirmCreateMenu"
            :disabled="!newMenuName.trim()"
            type="button"
          >
            {{ t('sidebar.create') }}
          </button>
          <button class="mb-btn mb-btn--ghost mb-btn--sm" @click="cancelCreateMenu" type="button">
            {{ t('sidebar.cancel') }}
          </button>
        </div>
      </div>
    </Transition>

    <nav class="mb-admin__menu-list">
      <div
        v-for="m in menus"
        :key="m"
        class="mb-admin__menu-row"
        :class="{ 'mb-admin__menu-row--active': selectedMenu === m }"
      >
        <template v-if="renamingMenu === m">
          <span class="mb-admin__menu-dot"></span>
          <input
            ref="renameInputEl"
            v-model="renameValue"
            type="text"
            class="mb-admin__menu-input"
            @keydown.enter="confirmRenameMenu(m)"
            @keydown.escape="cancelRenameMenu"
            @blur="confirmRenameMenu(m)"
          />
        </template>
        <template v-else>
          <button class="mb-admin__menu-item" @click="emit('select', m)" type="button">
            <span class="mb-admin__menu-dot" :class="{ 'mb-admin__menu-dot--active': isMenuActive(m) }"></span>
            <span class="mb-admin__menu-label">{{ m }}</span>
          </button>
          <button
            class="mb-admin__menu-action"
            :class="{ 'mb-admin__menu-action--off': !isMenuActive(m) }"
            @click.stop="emit('toggle-active', m)"
            :title="isMenuActive(m) ? t('sidebar.deactivate') : t('sidebar.activate')"
            type="button"
          >
            <svg
              v-if="isMenuActive(m)"
              width="11"
              height="11"
              viewBox="0 0 12 12"
              fill="currentColor"
              aria-hidden="true"
            >
              <circle cx="6" cy="6" r="3.5" />
            </svg>
            <svg
              v-else
              width="11"
              height="11"
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
            class="mb-admin__menu-action"
            @click.stop="emit('export', m)"
            :title="t('sidebar.export')"
            type="button"
          >
            <svg
              width="11"
              height="11"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M6 8V2M3 5l3 3 3-3M2 10h8" />
            </svg>
          </button>
          <button
            class="mb-admin__menu-action"
            @click.stop="startRenameMenu(m)"
            :title="t('sidebar.rename')"
            type="button"
          >
            <svg
              width="11"
              height="11"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M8.5 1.5l2 2L4 10l-2.5.5L2 8z" />
            </svg>
          </button>
          <button
            class="mb-admin__menu-action mb-admin__menu-action--danger"
            @click.stop="emit('delete', m)"
            :title="t('sidebar.delete')"
            type="button"
          >
            <svg
              width="11"
              height="11"
              viewBox="0 0 12 12"
              fill="none"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path
                d="M2.5 3.5h7M5 3v-.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5V3M3.5 3.5l.4 6.2a.5.5 0 0 0 .5.5h3.2a.5.5 0 0 0 .5-.5L8.5 3.5"
              />
            </svg>
          </button>
        </template>
      </div>

      <div v-if="menus.length === 0 && !creatingMenu" class="mb-admin__menu-empty">
        <div class="mb-admin__menu-empty-icon">⬡</div>
        {{ t('sidebar.empty') }}<br />{{ t('sidebar.emptyHint') }}
      </div>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { useI18n } from '../../i18n'
import SidebarSearch from './SidebarSearch.vue'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

defineProps<{
  menus: string[]
  selectedMenu: string | null
  isMenuActive: (name: string) => boolean
  searchTerm: string
  searchResults: MenuItem[]
  searchLoading: boolean
  searchedOnce: boolean
  onSearchInput: () => void
  clearSearch: () => void
}>()

const emit = defineEmits<{
  (e: 'update:searchTerm', value: string): void
  (e: 'select', name: string): void
  (e: 'create', name: string): void
  (e: 'rename', payload: { from: string; to: string }): void
  (e: 'delete', name: string): void
  (e: 'toggle-active', name: string): void
  (e: 'export', name: string): void
  (e: 'import', file: File): void
  (e: 'select-search-result', item: MenuItem): void
}>()

const creatingMenu = ref(false)
const newMenuName = ref('')
const newMenuInputEl = ref<HTMLInputElement | null>(null)

const renamingMenu = ref<string | null>(null)
const renameValue = ref('')
const renameInputEl = ref<HTMLInputElement | HTMLInputElement[] | null>(null)
let renameInProgress = false

const importInputEl = ref<HTMLInputElement | null>(null)

function startCreateMenu(): void {
  creatingMenu.value = true
  newMenuName.value = ''
  nextTick(() => {
    newMenuInputEl.value?.focus()
  })
}

function cancelCreateMenu(): void {
  creatingMenu.value = false
  newMenuName.value = ''
}

function confirmCreateMenu(): void {
  const name = newMenuName.value.trim()
  if (!name) {
    return
  }
  creatingMenu.value = false
  newMenuName.value = ''
  emit('create', name)
}

function startRenameMenu(name: string): void {
  renamingMenu.value = name
  renameValue.value = name
  nextTick(() => {
    const el = renameInputEl.value
    const input = Array.isArray(el) ? el[0] : el
    input?.focus()
    input?.select()
  })
}

function cancelRenameMenu(): void {
  renamingMenu.value = null
  renameValue.value = ''
}

function confirmRenameMenu(oldName: string): void {
  if (renameInProgress) {
    return
  }
  const newName = renameValue.value.trim()
  if (!newName || newName === oldName) {
    cancelRenameMenu()

    return
  }
  renameInProgress = true
  emit('rename', { from: oldName, to: newName })
  renamingMenu.value = null
  renameValue.value = ''
  renameInProgress = false
}

function triggerImport(): void {
  importInputEl.value?.click()
}

function onImportFile(event: Event): void {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  target.value = ''
  if (file) {
    emit('import', file)
  }
}

function onSearchResultClick(item: MenuItem): void {
  emit('select-search-result', item)
}

function onSearchTermChange(value: string): void {
  emit('update:searchTerm', value)
}
</script>
