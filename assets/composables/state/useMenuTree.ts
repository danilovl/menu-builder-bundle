import { ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, ToastType } from '../../types/menu'

export function useMenuTree(api: MenuApi, showToast: (msg: string, type?: ToastType) => void) {
  const tree = ref<MenuItem[]>([])
  const loading = ref(false)
  const initiallyLoaded = ref(false)

  function ensureChildren(items: MenuItem[]): void {
    for (const item of items) {
      if (!Array.isArray(item.children)) {
        item.children = []
      }
      ensureChildren(item.children)
    }
  }

  async function loadTree(menuName: string, previewLocale?: string | null): Promise<void> {
    if (!initiallyLoaded.value) {
      loading.value = true
    }
    try {
      const data = await api.getTree(menuName, previewLocale ?? null)
      const items = data.items ?? []
      ensureChildren(items)
      tree.value = items
      initiallyLoaded.value = true
    } catch (e) {
      showToast((e as Error).message, 'error')
    } finally {
      loading.value = false
    }
  }

  function resetForMenu(): void {
    tree.value = []
    initiallyLoaded.value = false
  }

  function findItem(id: string, list: MenuItem[] = tree.value): MenuItem | null {
    for (const item of list) {
      if (item.id === id) {
        return item
      }
      if (item.children?.length) {
        const found = findItem(id, item.children)
        if (found) {
          return found
        }
      }
    }

    return null
  }

  function findParentList(id: string, list: MenuItem[] = tree.value): MenuItem[] | null {
    for (const item of list) {
      if (item.id === id) {
        return list
      }
      if (item.children?.length) {
        const found = findParentList(id, item.children)
        if (found) {
          return found
        }
      }
    }

    return null
  }

  function patchItem(id: string, patch: Partial<MenuItem>): void {
    const item = findItem(id)
    if (item) {
      Object.assign(item, patch)
    }
  }

  function removeItem(id: string): void {
    const list = findParentList(id)
    if (!list) {
      return
    }
    const idx = list.findIndex((i) => {
      return i.id === id
    })
    if (idx >= 0) {
      list.splice(idx, 1)
    }
  }

  function insertItem(item: MenuItem): void {
    if (!Array.isArray(item.children)) {
      item.children = []
    }
    if (item.parentId) {
      const parent = findItem(item.parentId)
      if (parent) {
        if (!parent.children) {
          parent.children = []
        }
        parent.children.push(item)
        parent.hasChildren = true

        return
      }
    }
    tree.value.push(item)
  }

  return {
    tree,
    loading,
    loadTree,
    resetForMenu,
    findItem,
    patchItem,
    removeItem,
    insertItem,
  }
}
