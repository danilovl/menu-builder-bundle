import type { MenuItem } from '../types/menu'

export interface AppConfig {
  apiPrefix: string
  dashboardPath: string
}

export interface MenuListEntry {
  name: string
  active: boolean
}

export interface RouteInfo {
  name: string
  path: string
  methods: string[]
}

export interface RouteCatalogResponse {
  items: RouteInfo[]
  total: number
  matched: number
  limit: number
  truncated: boolean
}

interface RequestOptions {
  method?: string
  body?: unknown
  headers?: Record<string, string>
}

interface BulkResult {
  deleted?: number
  updated?: number
  errors: { id: string; error: string }[]
}

export interface MenuApi {
  listMenus(): Promise<{ menus: MenuListEntry[] }>
  search(term: string, limit?: number): Promise<{ items: MenuItem[] }>
  getTree(name: string, previewLocale?: string | null): Promise<{ menu: string; items: MenuItem[] }>
  createItem(data: Partial<MenuItem>, previewLocale?: string | null): Promise<MenuItem>
  updateItem(id: string, data: Partial<MenuItem>, previewLocale?: string | null): Promise<MenuItem>
  deleteItem(id: string): Promise<null>
  exportMenu(name: string): Promise<{ menu: string; exportedAt: string; items: unknown[] }>
  importMenu(payload: unknown): Promise<{ menu: string; created: number }>
  listTrash(name: string): Promise<{ menu: string; items: MenuItem[] }>
  restoreItem(id: string): Promise<MenuItem>
  duplicateItem(id: string): Promise<MenuItem>
  bulkDelete(ids: string[]): Promise<{ deleted: number; errors: { id: string; error: string }[] }>
  bulkSetActive(ids: string[], active: boolean): Promise<{ updated: number; errors: { id: string; error: string }[] }>
  toggleActive(id: string, active: boolean): Promise<MenuItem>
  moveItem(id: string, parentId: string | null, position: number): Promise<MenuItem>
  renameMenu(oldName: string, newName: string): Promise<{ name: string }>
  deleteMenu(name: string): Promise<null>
  toggleMenuActive(name: string, active: boolean): Promise<{ name: string; active: boolean }>
  listRoutes(term?: string): Promise<RouteCatalogResponse>
  listRoles(): Promise<{ items: string[] }>
  listUsers(term: string, limit?: number): Promise<{ items: string[]; matched: number; truncated: boolean }>
}

const JSON_HEADERS: Record<string, string> = {
  Accept: 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
}

export async function fetchConfig(configUrl: string): Promise<AppConfig> {
  const res = await fetch(configUrl, {
    credentials: 'same-origin',
    headers: JSON_HEADERS,
  })

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`)
  }

  return res.json() as Promise<AppConfig>
}

function buildQuery(params: Record<string, string | number | null | undefined>): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === undefined || value === '') {
      continue
    }
    search.set(key, String(value))
  }
  const qs = search.toString()

  return qs ? `?${qs}` : ''
}

function previewLocaleQuery(previewLocale?: string | null): string {
  return buildQuery({ previewLocale })
}

export function createApi(apiPrefix: string): MenuApi {
  const base = apiPrefix.replace(/\/$/, '')

  async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
    const { method = 'GET', body, headers = {} } = options
    const opts: RequestInit = {
      method,
      credentials: 'same-origin',
      headers: { ...JSON_HEADERS, ...headers } as Record<string, string>,
    }

    if (body !== undefined) {
      ;(opts.headers as Record<string, string>)['Content-Type'] = 'application/json'
      opts.body = JSON.stringify(body)
    }

    const res = await fetch(`${base}${path}`, opts)

    if (res.status === 204) {
      return null as T
    }

    const text = await res.text()
    const data = text ? (JSON.parse(text) as { error?: string }) : null

    if (!res.ok) {
      throw new Error(data?.error ?? `HTTP ${res.status}`)
    }

    return data as T
  }

  const encode = encodeURIComponent

  const listMenus = (): Promise<{ menus: MenuListEntry[] }> => {
    return request('')
  }

  const search = (term: string, limit?: number): Promise<{ items: MenuItem[] }> => {
    const qs = buildQuery({ q: term, limit })

    return request(`/admin/search${qs}`)
  }

  const getTree = (name: string, previewLocale?: string | null) => {
    return request<{ menu: string; items: MenuItem[] }>(
      `/admin/${encode(name)}/items${previewLocaleQuery(previewLocale)}`,
    )
  }

  const createItem = (data: Partial<MenuItem>, previewLocale?: string | null) => {
    return request<MenuItem>(`/admin/items${previewLocaleQuery(previewLocale)}`, { method: 'POST', body: data })
  }

  const updateItem = (id: string, data: Partial<MenuItem>, previewLocale?: string | null) => {
    return request<MenuItem>(`/admin/items/${id}${previewLocaleQuery(previewLocale)}`, { method: 'PUT', body: data })
  }

  const deleteItem = (id: string): Promise<null> => {
    return request(`/admin/items/${id}`, { method: 'DELETE' })
  }

  const exportMenu = (name: string) => {
    return request<{ menu: string; exportedAt: string; items: unknown[] }>(`/admin/menus/${encode(name)}/export`)
  }

  const importMenu = (payload: unknown) => {
    return request<{ menu: string; created: number }>('/admin/menus/import', { method: 'POST', body: payload })
  }

  const listTrash = (name: string) => {
    return request<{ menu: string; items: MenuItem[] }>(`/admin/${encode(name)}/trash`)
  }

  const restoreItem = (id: string) => {
    return request<MenuItem>(`/admin/items/${id}/restore`, { method: 'POST' })
  }

  const duplicateItem = (id: string) => {
    return request<MenuItem>(`/admin/items/${id}/duplicate`, { method: 'POST' })
  }

  const bulkDelete = (ids: string[]) => {
    return request<BulkResult & { deleted: number }>('/admin/items/bulk-delete', { method: 'POST', body: { ids } })
  }

  const bulkSetActive = (ids: string[], active: boolean) => {
    return request<BulkResult & { updated: number }>('/admin/items/bulk-active', {
      method: 'POST',
      body: { ids, active },
    })
  }

  const toggleActive = (id: string, active: boolean) => {
    return request<MenuItem>(`/admin/items/${id}/active`, { method: 'POST', body: { active } })
  }

  const moveItem = (id: string, parentId: string | null, position: number) => {
    return request<MenuItem>(`/admin/items/${id}/move`, { method: 'POST', body: { parentId, position } })
  }

  const renameMenu = (oldName: string, newName: string) => {
    return request<{ name: string }>(`/admin/menus/${encode(oldName)}`, { method: 'PATCH', body: { name: newName } })
  }

  const deleteMenu = (name: string): Promise<null> => {
    return request(`/admin/menus/${encode(name)}`, { method: 'DELETE' })
  }

  const toggleMenuActive = (name: string, active: boolean) => {
    return request<{ name: string; active: boolean }>(`/admin/menus/${encode(name)}/active`, {
      method: 'POST',
      body: { active },
    })
  }

  const listRoutes = (term?: string) => {
    return request<RouteCatalogResponse>(`/routes${buildQuery({ q: term })}`)
  }

  const listRoles = () => {
    return request<{ items: string[] }>('/roles')
  }

  const listUsers = (term: string, limit?: number) => {
    return request<{ items: string[]; matched: number; truncated: boolean }>(`/users${buildQuery({ q: term, limit })}`)
  }

  return {
    listMenus,
    search,
    getTree,
    createItem,
    updateItem,
    deleteItem,
    exportMenu,
    importMenu,
    listTrash,
    restoreItem,
    duplicateItem,
    bulkDelete,
    bulkSetActive,
    toggleActive,
    moveItem,
    renameMenu,
    deleteMenu,
    toggleMenuActive,
    listRoutes,
    listRoles,
    listUsers,
  }
}
