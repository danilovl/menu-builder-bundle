import { provide, ref, type Ref } from 'vue'
import type { MenuApi } from '../../api/menuApi'
import type { MenuItem, MovePayload, ToastType } from '../../types/menu'

interface DraggedInfo {
  itemId: string
  parentId: string | null
}

interface DropTarget {
  itemId: string
  parentId: string | null
  position: number
  mode: 'before' | 'after' | 'child'
}

export interface UseItemMoveOptions {
  api: MenuApi
  showToast: (msg: string, type?: ToastType) => void
  selectedMenu: Ref<string | null>
  tree: Ref<MenuItem[]>
  editing: Ref<MenuItem | null>
  findItem: (id: string) => MenuItem | null
  loadTree: (name: string) => Promise<void>
}

export function useItemMove(options: UseItemMoveOptions) {
  const draggedRef = ref<DraggedInfo | null>(null)
  provide('mb-dragged', draggedRef)

  const dropTargetRef = ref<DropTarget | null>(null)
  provide('mb-drop-target', dropTargetRef)

  async function reloadCurrentMenu(): Promise<void> {
    const menu = options.selectedMenu.value
    if (menu) {
      await options.loadTree(menu)
    }
  }

  async function move(payload: MovePayload): Promise<void> {
    try {
      await options.api.moveItem(payload.id, payload.parentId, payload.position)
      await reloadCurrentMenu()
      const editing = options.editing.value
      if (editing && editing.id === payload.id) {
        editing.parentId = payload.parentId
        editing.position = payload.position
      }
    } catch (e) {
      options.showToast((e as Error).message, 'error')
      await reloadCurrentMenu()
    }
  }

  async function unnest(id: string): Promise<void> {
    const item = options.findItem(id)
    if (!item) {
      return
    }
    const parentId = item.parentId
    if (!parentId) {
      return
    }
    const parent = options.findItem(parentId)
    if (!parent) {
      return
    }

    const grandparentId = parent.parentId ?? null
    const grandparentList = grandparentId ? (options.findItem(grandparentId)?.children ?? []) : options.tree.value

    const parentIdx = grandparentList.findIndex((i: MenuItem) => {
      return i.id === parent.id
    })
    const newPosition = parentIdx >= 0 ? parentIdx + 1 : grandparentList.length

    await move({
      id,
      parentId: grandparentId,
      position: newPosition,
    })
  }

  async function unnestToRoot(id: string): Promise<void> {
    const item = options.findItem(id)
    if (!item || !item.parentId) {
      return
    }

    await move({
      id,
      parentId: null,
      position: options.tree.value.length,
    })
  }

  return {
    move,
    unnest,
    unnestToRoot,
  }
}
