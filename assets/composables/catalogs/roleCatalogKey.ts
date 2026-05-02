import type { InjectionKey, Ref } from 'vue'

export interface RoleCatalogKey {
  init(): Promise<void>
  suggest(term: string, limit?: number): string[]
  items: Ref<string[]>
  initialized: Ref<boolean>
}

export const ROLE_CATALOG_KEY: InjectionKey<RoleCatalogKey> = Symbol('roleCatalog')
