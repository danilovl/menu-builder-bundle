import type { Ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, ToastType } from '../../types/menu'

export interface UseMenuActionsOptions {
  api: MenuApi
  showToast: (msg: string, type?: ToastType) => void
  t: (key: string, params?: Record<string, string>) => string
  menus: Ref<string[]>
  selectedMenu: Ref<string | null>
  tree: Ref<MenuItem[]>
  editing: Ref<MenuItem | null>
  isMenuActive: (name: string) => boolean
  setMenuActiveLocal: (name: string, value: boolean) => void
  addMenuLocal: (name: string) => void
  removeMenuLocal: (name: string) => void
  renameMenuLocal: (oldName: string, newName: string) => void
  resetForMenu: () => void
  loadTree: (name: string) => Promise<void>
  openNewItem: (parent: MenuItem | null) => void
}

function renameMenuInTree(items: MenuItem[], newName: string): void {
  for (const item of items) {
    item.menuName = newName
    if (item.children?.length) {
      renameMenuInTree(item.children, newName)
    }
  }
}

function treeHasActiveItem(items: MenuItem[]): boolean {
  for (const item of items) {
    if (item.active) {
      return true
    }
    if (item.children?.length && treeHasActiveItem(item.children)) {
      return true
    }
  }

  return false
}

export function useMenuActions(options: UseMenuActionsOptions) {
  async function selectMenu(name: string): Promise<void> {
    if (options.selectedMenu.value === name) {
      return
    }
    options.selectedMenu.value = name
    options.editing.value = null
    options.resetForMenu()
    await options.loadTree(name)
  }

  function createMenu(name: string): void {
    options.selectedMenu.value = name
    options.addMenuLocal(name)
    options.resetForMenu()
    options.openNewItem(null)
  }

  async function renameMenu(payload: { from: string; to: string }): Promise<void> {
    const { from: oldName, to: newName } = payload
    if (options.menus.value.includes(newName)) {
      options.showToast(options.t('toast.menuExists', { name: newName }), 'error')

      return
    }
    try {
      await options.api.renameMenu(oldName, newName)
      options.renameMenuLocal(oldName, newName)
      if (options.selectedMenu.value === oldName) {
        options.selectedMenu.value = newName
        renameMenuInTree(options.tree.value, newName)
      }
      if (options.editing.value && options.editing.value.menuName === oldName) {
        options.editing.value.menuName = newName
      }
      options.showToast(options.t('toast.menuRenamed'), 'success')
    } catch (e) {
      options.showToast((e as Error).message, 'error')
    }
  }

  async function deleteMenuConfirm(name: string): Promise<void> {
    if (!confirm(options.t('sidebar.confirmDelete', { name }))) {
      return
    }
    try {
      await options.api.deleteMenu(name)
      options.removeMenuLocal(name)
      if (options.selectedMenu.value === name) {
        options.selectedMenu.value = options.menus.value[0] ?? null
        options.editing.value = null
        options.resetForMenu()
        const next = options.selectedMenu.value
        if (next) {
          await options.loadTree(next)
        }
      }
      options.showToast(options.t('toast.menuDeleted'), 'success')
    } catch (e) {
      options.showToast((e as Error).message, 'error')
    }
  }

  async function toggleMenuActive(name: string): Promise<void> {
    const next = !options.isMenuActive(name)
    options.setMenuActiveLocal(name, next)
    try {
      await options.api.toggleMenuActive(name, next)
      if (options.selectedMenu.value === name) {
        await options.loadTree(name)
        refreshSelectedMenuActiveState()
      }
      options.showToast(options.t(next ? 'toast.menuActivated' : 'toast.menuDeactivated'), 'success')
    } catch (e) {
      options.setMenuActiveLocal(name, !next)
      options.showToast((e as Error).message, 'error')
    }
  }

  function refreshSelectedMenuActiveState(): void {
    const menu = options.selectedMenu.value
    if (!menu) {
      return
    }
    options.setMenuActiveLocal(menu, treeHasActiveItem(options.tree.value))
  }

  return {
    selectMenu,
    createMenu,
    renameMenu,
    deleteMenuConfirm,
    toggleMenuActive,
    refreshSelectedMenuActiveState,
  }
}
