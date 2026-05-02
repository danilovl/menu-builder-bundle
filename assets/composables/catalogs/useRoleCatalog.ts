import { ref, shallowRef } from 'vue'
import type { MenuApi } from '../../api/menuApi'

export function useRoleCatalog(api: MenuApi) {
  const items = shallowRef<string[]>([])
  const initialized = ref(false)
  let initPromise: Promise<void> | null = null

  async function init(): Promise<void> {
    if (initialized.value) {
      return
    }
    if (initPromise) {
      return initPromise
    }
    initPromise = (async () => {
      const res = await api.listRoles()
      items.value = res.items
      initialized.value = true
    })().finally(() => {
      initPromise = null
    })

    return initPromise
  }

  function suggest(term: string, limit = 30): string[] {
    const needle = term.trim().toLowerCase()
    const list = items.value
    if (!needle) {
      return list.slice(0, limit)
    }
    const matches = list.filter((r: string) => {
      return r.toLowerCase().includes(needle)
    })

    return matches.slice(0, limit)
  }

  return {
    init,
    suggest,
    items,
    initialized,
  }
}
