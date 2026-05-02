import type { InjectionKey, Ref } from 'vue'

export interface UserCatalogState {
  items: string[]
  loading: boolean
  truncated: boolean
}

export interface UserCatalogKey {
  search(term: string, debounceMs?: number): Promise<void>
  state: Ref<UserCatalogState>
}

export const USER_CATALOG_KEY: InjectionKey<UserCatalogKey> = Symbol('userCatalog')
