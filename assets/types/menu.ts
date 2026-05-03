export type MenuVisibility = 'always' | 'authenticated' | 'anonymous'

export type MenuItemType = 'link' | 'divider' | 'heading' | 'external' | 'mega'

export interface MenuTranslation {
  label?: string
  uri?: string
}

export interface MenuItem {
  id: string | null
  menuName: string
  label: string
  resolvedLabel?: string
  uri: string | null
  route: string | null
  routeParams: Record<string, unknown>
  icon: string | null
  iconImage: string | null
  attributes: Record<string, unknown>
  position?: number
  active: boolean
  isDisplayed: boolean
  requiredRoles: string[]
  allowedUsers: string[]
  target: string | null
  visibility: MenuVisibility
  translations: Record<string, MenuTranslation>
  dependentActiveRoutes: string[]
  labelTranslated: boolean
  translationDomain: string | null
  parentId: string | null
  type: MenuItemType
  column: number
  cssClasses: string[]
  publishedAt: string | null
  unpublishedAt: string | null
  children?: MenuItem[]
  hasChildren?: boolean
}

export type ToastType = 'info' | 'success' | 'error'

export interface ToastState {
  message: string
  type: ToastType
}

export interface MovePayload {
  id: string
  parentId: string | null
  position: number
}
