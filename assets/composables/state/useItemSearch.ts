import { ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, ToastType } from '../../types/menu'

export function useItemSearch(api: MenuApi, showToast: (msg: string, type?: ToastType) => void) {
  const searchTerm = ref('')
  const searchResults = ref<MenuItem[]>([])
  const searchLoading = ref(false)
  const searchedOnce = ref(false)
  let searchTimeout: ReturnType<typeof setTimeout> | null = null

  function onSearchInput(): void {
    if (searchTimeout) {
      clearTimeout(searchTimeout)
    }
    const term = searchTerm.value.trim()
    if (term === '') {
      searchResults.value = []
      searchedOnce.value = false

      return
    }
    searchTimeout = setTimeout(() => {
      void runSearch(term)
    }, 250)
  }

  async function runSearch(term: string): Promise<void> {
    searchLoading.value = true
    try {
      const data = await api.search(term, 30)
      searchResults.value = data.items ?? []
      searchedOnce.value = true
    } catch (e) {
      showToast((e as Error).message, 'error')
    } finally {
      searchLoading.value = false
    }
  }

  function clearSearch(): void {
    searchTerm.value = ''
    searchResults.value = []
    searchedOnce.value = false
  }

  return {
    searchTerm,
    searchResults,
    searchLoading,
    searchedOnce,
    onSearchInput,
    clearSearch,
  }
}
