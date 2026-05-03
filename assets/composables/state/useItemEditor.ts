import { computed, ref, type ComputedRef, type Ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, ToastType } from '../../types/menu'

export interface UseItemEditorOptions {
  api: MenuApi
  showToast: (msg: string, type?: ToastType) => void
  t: (key: string, params?: Record<string, string>) => string
  selectedMenu: Ref<string | null>
  previewLocale: Ref<string | null>
  tree: Ref<MenuItem[]>
  findItem: (id: string) => MenuItem | null
  patchItem: (id: string, patch: Partial<MenuItem>) => void
  removeItem: (id: string) => void
  insertItem: (item: MenuItem) => void
  loadTree: (name: string) => Promise<void>
  afterMutation: () => void
}

export interface ItemEditor {
  editing: Ref<MenuItem | null>
  editingId: ComputedRef<string | null>
  selectItem: (item: MenuItem) => void
  openNewItem: (parent: MenuItem | null) => void
  onSave: (payload: MenuItem) => Promise<void>
  onDelete: (item: MenuItem) => Promise<void>
  onDuplicate: (item: MenuItem) => Promise<void>
  onInlineRename: (payload: { id: string; label: string }) => Promise<void>
  onToggleActive: (item: MenuItem) => Promise<void>
}

function emptyItem(menuName: string, parentId: string | null): MenuItem {
  return {
    id: null,
    menuName,
    label: '',
    uri: null,
    route: null,
    routeParams: {},
    icon: null,
    iconImage: null,
    attributes: {},
    active: true,
    isDisplayed: true,
    requiredRoles: [],
    allowedUsers: [],
    target: null,
    visibility: 'always',
    translations: {},
    dependentActiveRoutes: [],
    labelTranslated: false,
    translationDomain: null,
    parentId,
    type: 'link',
    column: 0,
    cssClasses: [],
    publishedAt: null,
    unpublishedAt: null,
  }
}

const SAVED_FIELDS = [
  'label',
  'resolvedLabel',
  'uri',
  'route',
  'routeParams',
  'icon',
  'iconImage',
  'attributes',
  'active',
  'isDisplayed',
  'requiredRoles',
  'allowedUsers',
  'target',
  'visibility',
  'translations',
  'dependentActiveRoutes',
  'labelTranslated',
  'translationDomain',
  'type',
  'column',
  'cssClasses',
  'publishedAt',
  'unpublishedAt',
] as const satisfies readonly (keyof MenuItem)[]

function pickSavedFields(saved: MenuItem): Partial<MenuItem> {
  const patch: Partial<MenuItem> = {}
  for (const field of SAVED_FIELDS) {
    ;(patch as Record<string, unknown>)[field] = saved[field]
  }

  return patch
}

export function useItemEditor(options: UseItemEditorOptions): ItemEditor {
  const editing = ref<MenuItem | null>(null)
  const editingId = computed((): string | null => {
    return editing.value?.id ?? null
  })

  function selectItem(item: MenuItem): void {
    editing.value = JSON.parse(JSON.stringify(item)) as MenuItem
  }

  function openNewItem(parent: MenuItem | null): void {
    editing.value = emptyItem(options.selectedMenu.value ?? '', parent?.id ?? null)
  }

  async function reloadCurrentMenu(): Promise<void> {
    const menu = options.selectedMenu.value
    if (menu) {
      await options.loadTree(menu)
    }
  }

  async function updateExistingItem(payload: MenuItem & { id: string }): Promise<void> {
    const existing = options.findItem(payload.id)
    const oldParentId = existing?.parentId ?? null
    const newParentId = payload.parentId ?? null

    const saved = await options.api.updateItem(payload.id, payload, options.previewLocale.value)
    options.patchItem(payload.id, pickSavedFields(saved))

    if (oldParentId === newParentId) {
      return
    }

    const newParent = newParentId ? options.findItem(newParentId) : null
    const siblingsCount = newParentId ? (newParent?.children?.length ?? 0) : options.tree.value.length
    await options.api.moveItem(payload.id, newParentId, siblingsCount)
    await reloadCurrentMenu()
  }

  async function createNewItem(payload: MenuItem): Promise<void> {
    const created = await options.api.createItem(payload, options.previewLocale.value)
    options.insertItem({ ...created, children: created.children ?? [] })
    editing.value = null
  }

  async function onSave(payload: MenuItem): Promise<void> {
    try {
      if (payload.id) {
        await updateExistingItem(payload as MenuItem & { id: string })
        options.showToast(options.t('toast.itemSaved'), 'success')
      } else {
        await createNewItem(payload)
        options.showToast(options.t('toast.itemCreated'), 'success')
      }
      options.afterMutation()
    } catch (e) {
      options.showToast((e as Error).message, 'error')
    }
  }

  async function onDelete(item: MenuItem): Promise<void> {
    if (!item.id) {
      return
    }
    if (!confirm(options.t('tree.confirmDelete', { label: item.label }))) {
      return
    }
    if (editing.value?.id === item.id) {
      editing.value = null
    }
    options.removeItem(item.id)
    try {
      await options.api.deleteItem(item.id)
      options.showToast(options.t('toast.itemDeleted'), 'success')
      options.afterMutation()
    } catch (e) {
      options.showToast((e as Error).message, 'error')
      await reloadCurrentMenu()
    }
  }

  async function onDuplicate(item: MenuItem): Promise<void> {
    if (!item.id) {
      return
    }
    try {
      await options.api.duplicateItem(item.id)
      await reloadCurrentMenu()
      options.showToast(options.t('toast.itemDuplicated'), 'success')
      options.afterMutation()
    } catch (e) {
      options.showToast((e as Error).message, 'error')
    }
  }

  async function onInlineRename(payload: { id: string; label: string }): Promise<void> {
    const previous = options.findItem(payload.id)?.label ?? ''
    options.patchItem(payload.id, { label: payload.label })
    try {
      await options.api.updateItem(payload.id, { label: payload.label })
      if (editing.value?.id === payload.id) {
        editing.value.label = payload.label
      }
      options.showToast(options.t('toast.itemSaved'), 'success')
    } catch (e) {
      options.patchItem(payload.id, { label: previous })
      options.showToast((e as Error).message, 'error')
    }
  }

  async function onToggleActive(item: MenuItem): Promise<void> {
    if (!item.id) {
      return
    }
    const previous = item.active
    options.patchItem(item.id, { active: !previous })
    try {
      await options.api.toggleActive(item.id, !previous)
      options.afterMutation()
    } catch (e) {
      options.patchItem(item.id, { active: previous })
      options.showToast((e as Error).message, 'error')
    }
  }

  return {
    editing,
    editingId,
    selectItem,
    openNewItem,
    onSave,
    onDelete,
    onDuplicate,
    onInlineRename,
    onToggleActive,
  }
}
