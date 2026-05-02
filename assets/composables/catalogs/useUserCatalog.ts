import { ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { UserCatalogState } from './userCatalogKey'

export function useUserCatalog(api: MenuApi) {
  const state = ref<UserCatalogState>({
    items: [],
    loading: false,
    truncated: false,
  })

  let token = 0
  let timer: ReturnType<typeof setTimeout> | null = null

  function search(term: string, debounceMs = 200): Promise<void> {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    const trimmed = term.trim()
    if (trimmed === '') {
      state.value = { items: [], loading: false, truncated: false }

      return Promise.resolve()
    }
    const my = ++token

    return new Promise((resolve) => {
      timer = setTimeout(async () => {
        state.value = { ...state.value, loading: true }
        try {
          const res = await api.listUsers(trimmed)
          if (my !== token) {
            return
          }
          state.value = {
            items: res.items,
            loading: false,
            truncated: res.truncated,
          }
        } catch {
          if (my === token) {
            state.value = { items: [], loading: false, truncated: false }
          }
        } finally {
          resolve()
        }
      }, debounceMs)
    })
  }

  return { search, state }
}
