import type { InjectionKey, Ref } from 'vue'
import type { RouteInfo } from '../../api/menuApi'

export interface RouteCatalogQuery {
  items: RouteInfo[]
  matched: number
  truncated: boolean
  loading: boolean
}

export interface RouteCatalogKey {
  init(): Promise<void>
  search(term: string, debounceMs?: number): Promise<void>
  query(term: string): RouteCatalogQuery
  total: Ref<number>
  lazyMode: Ref<boolean>
  initialized: Ref<boolean>
}

export const ROUTE_CATALOG_KEY: InjectionKey<RouteCatalogKey> = Symbol('routeCatalog')
