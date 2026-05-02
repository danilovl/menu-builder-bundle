import { ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { ToastType } from '../../types/menu'

export function useBulkSelection(api: MenuApi, showToast: (msg: string, type?: ToastType) => void) {
  const selectMode = ref(false)
  const selectedIds = ref<Set<string>>(new Set())

  function toggleSelectMode(): void {
    selectMode.value = !selectMode.value
    if (!selectMode.value) {
      selectedIds.value = new Set()
    }
  }

  function clearSelection(): void {
    selectedIds.value = new Set()
  }

  function onToggleSelect(id: string): void {
    const next = new Set(selectedIds.value)
    if (next.has(id)) {
      next.delete(id)
    } else {
      next.add(id)
    }
    selectedIds.value = next
  }

  async function bulkSetActive(active: boolean): Promise<boolean> {
    const ids = [...selectedIds.value]
    if (ids.length === 0) {
      return false
    }
    try {
      await api.bulkSetActive(ids, active)
      clearSelection()

      return true
    } catch (e) {
      showToast((e as Error).message, 'error')

      return false
    }
  }

  async function bulkDelete(): Promise<string[]> {
    const ids = [...selectedIds.value]
    if (ids.length === 0) {
      return []
    }
    try {
      await api.bulkDelete(ids)
      clearSelection()

      return ids
    } catch (e) {
      showToast((e as Error).message, 'error')

      return []
    }
  }

  return {
    selectMode,
    selectedIds,
    toggleSelectMode,
    clearSelection,
    onToggleSelect,
    bulkSetActive,
    bulkDelete,
  }
}
