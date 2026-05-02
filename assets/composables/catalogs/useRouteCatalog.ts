import { ref, shallowRef } from 'vue'
import type { MenuApi, RouteInfo } from '../../api/menuApi'

export interface RouteCatalogState {
  items: RouteInfo[]
  matched: number
  truncated: boolean
  loading: boolean
}

export function useRouteCatalog(api: MenuApi) {
  const allItems = shallowRef<RouteInfo[]>([])
  const total = ref(0)
  const lazyMode = ref(false)
  const initialized = ref(false)
  let initPromise: Promise<void> | null = null

  const remoteItems = shallowRef<RouteInfo[]>([])
  const remoteMatched = ref(0)
  const remoteTruncated = ref(false)
  const remoteLoading = ref(false)
  let searchToken = 0
  let searchTimeout: ReturnType<typeof setTimeout> | null = null

  async function init(): Promise<void> {
    if (initialized.value) {
      return
    }
    if (initPromise) {
      return initPromise
    }
    initPromise = (async () => {
      const res = await api.listRoutes()
      allItems.value = res.items
      total.value = res.total
      lazyMode.value = res.truncated
      initialized.value = true
    })().finally(() => {
      initPromise = null
    })

    return initPromise
  }

  function localFilter(term: string): RouteInfo[] {
    const needle = term.trim().toLowerCase()
    if (!needle) {
      return allItems.value
    }

    return allItems.value.filter((r: RouteInfo) => {
      return r.name.toLowerCase().includes(needle) || r.path.toLowerCase().includes(needle)
    })
  }

  function search(term: string, debounceMs = 200): Promise<void> {
    if (!lazyMode.value) {
      return Promise.resolve()
    }
    if (searchTimeout) {
      clearTimeout(searchTimeout)
      searchTimeout = null
    }
    const token = ++searchToken

    return new Promise((resolve) => {
      searchTimeout = setTimeout(async () => {
        remoteLoading.value = true
        try {
          const res = await api.listRoutes(term)
          if (token !== searchToken) {
            return
          }
          remoteItems.value = res.items
          remoteMatched.value = res.matched
          remoteTruncated.value = res.truncated
        } catch {
          if (token === searchToken) {
            remoteItems.value = []
            remoteMatched.value = 0
            remoteTruncated.value = false
          }
        } finally {
          if (token === searchToken) {
            remoteLoading.value = false
          }
          resolve()
        }
      }, debounceMs)
    })
  }

  function query(term: string): RouteCatalogState {
    if (!lazyMode.value) {
      const list = localFilter(term)

      return {
        items: list.slice(0, 50),
        matched: list.length,
        truncated: list.length > 50,
        loading: false,
      }
    }

    return {
      items: remoteItems.value,
      matched: remoteMatched.value,
      truncated: remoteTruncated.value,
      loading: remoteLoading.value,
    }
  }

  return {
    init,
    search,
    query,
    total,
    lazyMode,
    initialized,
  }
}
