import { ref, computed } from 'vue'
import type { MenuApi, MenuListEntry } from '../../api/menuApi'
import type { ToastType } from '../../types/menu'

export function useMenus(
  api: MenuApi,
  showToast: (msg: string, type?: ToastType) => void,
  initialMenu: string | null = null,
) {
  const menuEntries = ref<MenuListEntry[]>([])
  const selectedMenu = ref<string | null>(initialMenu)
  const newMenuName = ref('')

  const menus = computed((): string[] => {
    return menuEntries.value.map((entry: MenuListEntry) => {
      return entry.name
    })
  })

  function isMenuActive(name: string): boolean {
    const found = menuEntries.value.find((entry: MenuListEntry) => {
      return entry.name === name
    })

    return found?.active ?? false
  }

  function setMenuActiveLocal(name: string, active: boolean): void {
    const found = menuEntries.value.find((entry: MenuListEntry) => {
      return entry.name === name
    })
    if (found) {
      found.active = active
    }
  }

  function addMenuLocal(name: string): void {
    const exists = menuEntries.value.some((entry: MenuListEntry) => {
      return entry.name === name
    })
    if (exists) {
      return
    }
    menuEntries.value.push({ name, active: false })
  }

  function removeMenuLocal(name: string): void {
    const idx = menuEntries.value.findIndex((entry: MenuListEntry) => {
      return entry.name === name
    })
    if (idx >= 0) {
      menuEntries.value.splice(idx, 1)
    }
  }

  function renameMenuLocal(oldName: string, newName: string): void {
    const found = menuEntries.value.find((entry: MenuListEntry) => {
      return entry.name === oldName
    })
    if (found) {
      found.name = newName
    }
  }

  async function loadMenus(): Promise<void> {
    try {
      const data = await api.listMenus()
      menuEntries.value = data.menus ?? []
      if (!selectedMenu.value && menuEntries.value.length) {
        selectedMenu.value = menuEntries.value[0].name
      }
    } catch (e) {
      showToast((e as Error).message, 'error')
    }
  }

  return {
    menus,
    menuEntries,
    selectedMenu,
    newMenuName,
    loadMenus,
    isMenuActive,
    setMenuActiveLocal,
    addMenuLocal,
    removeMenuLocal,
    renameMenuLocal,
  }
}
