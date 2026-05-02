<template>
  <div class="mb-admin">
    <AppHeader />

    <div class="mb-admin__body" :style="{ '--mb-form-w': formPaneWidth + 'px' }">
      <MenuSidebar
        :menus="menus"
        :selected-menu="selectedMenu"
        :is-menu-active="isMenuActive"
        :search-term="searchTerm"
        :search-results="searchResults"
        :search-loading="searchLoading"
        :searched-once="searchedOnce"
        :on-search-input="onSearchInput"
        :clear-search="clearSearch"
        @update:search-term="onSearchTermUpdate"
        @select="selectMenu"
        @create="createMenu"
        @rename="renameMenu"
        @delete="deleteMenuConfirm"
        @toggle-active="toggleMenuActive"
        @export="onExport"
        @import="onImport"
        @select-search-result="onSearchResultClick"
      />

      <TreePane
        :selected-menu="selectedMenu"
        :tree="tree"
        :loading="loading"
        :preview-locale="previewLocale"
        :editing-id="editingId"
        :select-mode="selectMode"
        :selected-ids="selectedIds"
        @update:preview-locale="onPreviewLocaleUpdate"
        @update:tree="onTreeUpdate"
        @reload="onReload"
        @toggle-select-mode="toggleSelectMode"
        @open-trash="onOpenTrash"
        @add-item="openNewItem(null)"
        @select="selectItem"
        @move="move"
        @unnest="unnest"
        @unnest-to-root="unnestToRoot"
        @delete="onDelete"
        @toggle-active="onToggleActive"
        @add-child="openNewItem"
        @duplicate="onDuplicate"
        @rename="onInlineRename"
        @toggle-select="onToggleSelect"
        @bulk-activate="onBulkSetActive(true)"
        @bulk-deactivate="onBulkSetActive(false)"
        @bulk-delete="onBulkDelete"
        @bulk-clear="clearSelection"
      />

      <EditorPane
        :editing="editing"
        :menus="menus"
        :tree="tree"
        :is-resizing="isResizing"
        @save="onSave"
        @cancel="editing = null"
        @resize-start="startResize"
      />
    </div>

    <Transition name="mb-fade">
      <Toast v-if="toast" :message="toast?.message ?? ''" :type="toast?.type ?? 'info'" @close="toast = null" />
    </Transition>

    <TrashModal
      :open="trashOpen"
      :menu-name="selectedMenu"
      :items="trashItems"
      :loading="trashLoading"
      @close="closeTrash"
      @restore="onRestore"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, provide } from 'vue'
import AppHeader from './components/shell/AppHeader.vue'
import MenuSidebar from './components/sidebar/MenuSidebar.vue'
import TreePane from './components/tree/TreePane.vue'
import EditorPane from './components/editor/EditorPane.vue'
import TrashModal from './components/modals/TrashModal.vue'
import Toast from './components/ui/Toast.vue'
import { createApi } from './api/menuApi'
import { useToast } from './composables/ui/useToast'
import { useMenus } from './composables/state/useMenus'
import { useMenuTree } from './composables/state/useMenuTree'
import { useFormResize } from './composables/ui/useFormResize'
import { useItemSearch } from './composables/state/useItemSearch'
import { useBulkSelection } from './composables/state/useBulkSelection'
import { useTrash } from './composables/state/useTrash'
import { useImportExport } from './composables/io/useImportExport'
import { useItemEditor } from './composables/state/useItemEditor'
import { useMenuActions } from './composables/state/useMenuActions'
import { useItemMove } from './composables/state/useItemMove'
import { useRouteCatalog } from './composables/catalogs/useRouteCatalog'
import { ROUTE_CATALOG_KEY } from './composables/catalogs/routeCatalogKey'
import { useRoleCatalog } from './composables/catalogs/useRoleCatalog'
import { ROLE_CATALOG_KEY } from './composables/catalogs/roleCatalogKey'
import { useUserCatalog } from './composables/catalogs/useUserCatalog'
import { USER_CATALOG_KEY } from './composables/catalogs/userCatalogKey'
import { useI18n } from './i18n'
import type { MenuItem } from './types/menu'

const props = withDefaults(
  defineProps<{
    apiBase: string
    defaultMenu?: string | null
  }>(),
  {
    defaultMenu: null,
  },
)

const { t } = useI18n()
const api = createApi(props.apiBase)
const { toast, showToast } = useToast()
const { formPaneWidth, isResizing, startResize } = useFormResize()

const {
  menus,
  selectedMenu,
  loadMenus,
  isMenuActive,
  setMenuActiveLocal,
  addMenuLocal,
  removeMenuLocal,
  renameMenuLocal,
} = useMenus(api, showToast, props.defaultMenu)

const {
  tree,
  loading,
  loadTree: loadTreeRaw,
  resetForMenu,
  findItem,
  patchItem,
  removeItem,
  insertItem,
} = useMenuTree(api, showToast)

const { searchTerm, searchResults, searchLoading, searchedOnce, onSearchInput, clearSearch } = useItemSearch(
  api,
  showToast,
)

const { selectMode, selectedIds, toggleSelectMode, clearSelection, onToggleSelect, bulkSetActive, bulkDelete } =
  useBulkSelection(api, showToast)

const { trashOpen, trashItems, trashLoading, openTrash, closeTrash, restoreItem } = useTrash(api, showToast)

const { importFromFile, exportToFile } = useImportExport(api, showToast, t)

const routeCatalog = useRouteCatalog(api)
provide(ROUTE_CATALOG_KEY, routeCatalog)
const roleCatalog = useRoleCatalog(api)
provide(ROLE_CATALOG_KEY, roleCatalog)
provide(USER_CATALOG_KEY, useUserCatalog(api))

const previewLocale = ref<string | null>(null)

async function loadTree(name: string): Promise<void> {
  await loadTreeRaw(name, previewLocale.value)
}

let refreshSelectedMenuActiveState: () => void = () => {}

const { editing, editingId, selectItem, openNewItem, onSave, onDelete, onDuplicate, onInlineRename, onToggleActive } =
  useItemEditor({
    api,
    showToast,
    t,
    selectedMenu,
    previewLocale,
    tree,
    findItem,
    patchItem,
    removeItem,
    insertItem,
    loadTree,
    afterMutation: () => {
      return refreshSelectedMenuActiveState()
    },
  })

const menuActions = useMenuActions({
  api,
  showToast,
  t,
  menus,
  selectedMenu,
  tree,
  editing,
  isMenuActive,
  setMenuActiveLocal,
  addMenuLocal,
  removeMenuLocal,
  renameMenuLocal,
  resetForMenu,
  loadTree,
  openNewItem,
})
refreshSelectedMenuActiveState = menuActions.refreshSelectedMenuActiveState
const { selectMenu, createMenu, renameMenu, deleteMenuConfirm, toggleMenuActive } = menuActions

const { move, unnest, unnestToRoot } = useItemMove({
  api,
  showToast,
  selectedMenu,
  tree,
  editing,
  findItem,
  loadTree,
})

function onSearchTermUpdate(value: string): void {
  searchTerm.value = value
}

async function onPreviewLocaleUpdate(value: string | null): Promise<void> {
  if (previewLocale.value === value) {
    return
  }
  previewLocale.value = value
  if (selectedMenu.value) {
    await loadTree(selectedMenu.value)
  }
}

function onTreeUpdate(value: MenuItem[]): void {
  tree.value.splice(0, tree.value.length, ...value)
}

async function onReload(): Promise<void> {
  if (selectedMenu.value) {
    await loadTree(selectedMenu.value)
  }
}

async function onImport(file: File): Promise<void> {
  const result = await importFromFile(file)
  if (result === null) {
    return
  }
  await loadMenus()
  if (result.menu) {
    if (selectedMenu.value === result.menu) {
      await loadTree(result.menu)
    } else {
      await selectMenu(result.menu)
    }
  }
  showToast(t('toast.imported', { count: String(result.created) }), 'success')
}

async function onExport(name: string): Promise<void> {
  await exportToFile(name)
}

async function onOpenTrash(): Promise<void> {
  const menu = selectedMenu.value
  if (!menu) {
    return
  }
  await openTrash(menu)
}

async function onRestore(item: MenuItem): Promise<void> {
  if (!item.id) {
    return
  }
  const ok = await restoreItem(item.id)
  if (!ok) {
    return
  }
  const menu = selectedMenu.value
  if (menu) {
    await loadTree(menu)
    refreshSelectedMenuActiveState()
  }
  showToast(t('toast.itemRestored'), 'success')
}

async function onSearchResultClick(item: MenuItem): Promise<void> {
  if (item.menuName !== selectedMenu.value) {
    await selectMenu(item.menuName)
  }
  selectItem(item)
  clearSearch()
}

async function onBulkSetActive(active: boolean): Promise<void> {
  const ok = await bulkSetActive(active)
  if (!ok) {
    return
  }
  const menu = selectedMenu.value
  if (menu) {
    await loadTree(menu)
    refreshSelectedMenuActiveState()
  }
}

async function onBulkDelete(): Promise<void> {
  const count = selectedIds.value.size
  if (count === 0) {
    return
  }
  if (!confirm(t('tree.bulkConfirmDelete', { count: String(count) }))) {
    return
  }
  const deletedIds = await bulkDelete()
  if (deletedIds.length === 0) {
    return
  }
  const menu = selectedMenu.value
  if (menu) {
    await loadTree(menu)
    refreshSelectedMenuActiveState()
  }
  if (editing.value && editing.value.id && deletedIds.includes(editing.value.id)) {
    editing.value = null
  }
}

onMounted(async () => {
  await loadMenus()
  if (selectedMenu.value) {
    await loadTree(selectedMenu.value)
  }
  void routeCatalog.init()
  void roleCatalog.init()
})
</script>
