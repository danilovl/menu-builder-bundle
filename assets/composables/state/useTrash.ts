import { ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, ToastType } from '../../types/menu'

export function useTrash(api: MenuApi, showToast: (msg: string, type?: ToastType) => void) {
  const trashOpen = ref(false)
  const trashItems = ref<MenuItem[]>([])
  const trashLoading = ref(false)

  async function openTrash(menuName: string): Promise<void> {
    trashOpen.value = true
    trashLoading.value = true
    try {
      const data = await api.listTrash(menuName)
      trashItems.value = data.items ?? []
    } catch (e) {
      showToast((e as Error).message, 'error')
    } finally {
      trashLoading.value = false
    }
  }

  function closeTrash(): void {
    trashOpen.value = false
    trashItems.value = []
  }

  async function restoreItem(id: string): Promise<boolean> {
    try {
      await api.restoreItem(id)
      trashItems.value = trashItems.value.filter((entry: MenuItem) => {
        return entry.id !== id
      })

      return true
    } catch (e) {
      showToast((e as Error).message, 'error')

      return false
    }
  }

  return {
    trashOpen,
    trashItems,
    trashLoading,
    openTrash,
    closeTrash,
    restoreItem,
  }
}
