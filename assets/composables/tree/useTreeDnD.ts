import { inject, ref, type Ref } from 'vue'
import type { MenuItem, MovePayload } from '../../types/menu'

export interface DraggedInfo {
  itemId: string
  parentId: string | null
}

export interface DropTarget {
  itemId: string
  parentId: string | null
  position: number
  mode: 'before' | 'after' | 'child' | 'row'
}

const HOVER_TO_NEST_MS = 500
const DRAG_MIME = 'application/x-menu-item-id'

export interface UseTreeDnDOptions {
  items: () => MenuItem[]
  parentId: () => string | null
  isRenaming: (id: string | null) => boolean
  emitMove: (payload: MovePayload) => void
}

export function useTreeDnD(options: UseTreeDnDOptions) {
  const draggedRef = inject<Ref<DraggedInfo | null> | null>('mb-dragged', null)
  const dropTargetRef = inject<Ref<DropTarget | null> | null>('mb-drop-target', null)

  let hoverTimer: ReturnType<typeof setTimeout> | null = null
  let hoveredItemId: string | null = null
  let childLocked = false
  const hoveringRowId = ref<string | null>(null)

  function clearHoverState(): void {
    if (hoverTimer !== null) {
      clearTimeout(hoverTimer)
      hoverTimer = null
    }
    hoveredItemId = null
    childLocked = false
    hoveringRowId.value = null
  }

  function setDropTarget(value: DropTarget | null): void {
    if (dropTargetRef) {
      dropTargetRef.value = value
    }
  }

  function canDrag(element: MenuItem): boolean {
    return Boolean(element.id) && !options.isRenaming(element.id ?? null)
  }

  function onHandleDragStart(evt: DragEvent, element: MenuItem): void {
    if (!element.id) {
      evt.preventDefault()

      return
    }
    if (!evt.dataTransfer) {
      return
    }

    evt.dataTransfer.effectAllowed = 'move'
    evt.dataTransfer.setData(DRAG_MIME, element.id)
    evt.dataTransfer.setData('text/plain', element.id)

    const handle = evt.currentTarget as HTMLElement
    const node = handle.closest('.mb-tree__node') as HTMLElement | null
    if (node) {
      const rect = node.getBoundingClientRect()
      evt.dataTransfer.setDragImage(node, evt.clientX - rect.left, evt.clientY - rect.top)
    }

    document.body.classList.add('mb-dragging')
    clearHoverState()

    if (draggedRef) {
      draggedRef.value = {
        itemId: element.id,
        parentId: options.parentId(),
      }
    }
    setDropTarget(null)
  }

  function onHandleDragEnd(): void {
    document.body.classList.remove('mb-dragging')
    clearHoverState()
    if (draggedRef) {
      draggedRef.value = null
    }
    setDropTarget(null)
  }

  function startHoverTimer(targetItemId: string): void {
    const localItems = options.items()
    const localParentId = options.parentId()

    hoverTimer = setTimeout(() => {
      if (hoveredItemId !== targetItemId) {
        return
      }
      const lockedIdx = localItems.findIndex((i: MenuItem) => {
        return i.id === targetItemId
      })
      if (lockedIdx < 0) {
        return
      }
      childLocked = true
      hoveringRowId.value = null
      setDropTarget({
        itemId: targetItemId,
        parentId: localParentId,
        position: lockedIdx,
        mode: 'child',
      })
    }, HOVER_TO_NEST_MS)
  }

  function onRowDragOver(evt: DragEvent, element: MenuItem, idx: number): void {
    const dragged = draggedRef?.value
    if (!dragged || !element.id) {
      return
    }
    if (dragged.itemId === element.id) {
      setDropTarget(null)

      return
    }
    if (evt.dataTransfer) {
      evt.dataTransfer.dropEffect = 'move'
    }

    if (hoveredItemId !== element.id) {
      if (hoverTimer !== null) {
        clearTimeout(hoverTimer)
        hoverTimer = null
      }
      hoveredItemId = element.id
      childLocked = false
      hoveringRowId.value = element.id
      startHoverTimer(element.id)
    }

    if (childLocked) {
      setDropTarget({
        itemId: element.id,
        parentId: options.parentId(),
        position: idx,
        mode: 'child',
      })

      return
    }

    setDropTarget({
      itemId: element.id,
      parentId: options.parentId(),
      position: idx,
      mode: 'row',
    })
  }

  function onRowDragLeave(evt: DragEvent, element: MenuItem): void {
    if (!element.id) {
      return
    }
    const row = evt.currentTarget as HTMLElement
    const related = evt.relatedTarget as Node | null
    if (related && row.contains(related)) {
      return
    }
    if (hoveredItemId === element.id) {
      if (hoverTimer !== null) {
        clearTimeout(hoverTimer)
        hoverTimer = null
      }
      hoveredItemId = null
      childLocked = false
      hoveringRowId.value = null
    }
  }

  function onSlotDragOver(evt: DragEvent, element: MenuItem, idx: number, mode: 'before' | 'after'): void {
    const dragged = draggedRef?.value
    if (!dragged || !element.id || dragged.itemId === element.id) {
      return
    }
    if (isSlotNoop(idx, mode)) {
      setDropTarget(null)

      return
    }
    if (evt.dataTransfer) {
      evt.dataTransfer.dropEffect = 'move'
    }
    setDropTarget({
      itemId: element.id,
      parentId: options.parentId(),
      position: idx,
      mode,
    })
  }

  function onSlotDrop(_evt: DragEvent, element: MenuItem, idx: number, mode: 'before' | 'after'): void {
    const dragged = draggedRef?.value
    if (!dragged || !element.id || String(dragged.itemId) === String(element.id)) {
      return
    }
    const draggedItem = getDraggedItem()
    const draggedPos = draggedItem?.position ?? getDraggedIdx()
    const basePos = element.position ?? idx
    const targetPos = mode === 'before' ? basePos : basePos + 1
    const isSameParent = String(dragged.parentId) === String(options.parentId())

    const finalPosition = (isSameParent && draggedPos !== -1 && targetPos > draggedPos)
      ? targetPos - 1
      : targetPos

    options.emitMove({
      id: dragged.itemId,
      parentId: options.parentId(),
      position: finalPosition,
    })
  }

  function onRowDrop(evt: DragEvent, element: MenuItem, idx: number): void {
    const dragged = draggedRef?.value
    if (!dragged || !element.id || String(dragged.itemId) === String(element.id)) {
      return
    }

    let mode: 'before' | 'after' | 'child' | 'row' = 'after'
    const target = dropTargetRef?.value
    if (target && target.itemId === element.id && target.mode !== 'row') {
      mode = target.mode
    } else {
      const row = evt.currentTarget as HTMLElement
      const rect = row.getBoundingClientRect()
      const yRel = (evt.clientY - rect.top) / rect.height
      mode = yRel < 0.5 ? 'before' : 'after'
    }

    if (mode === 'child') {
      options.emitMove({ id: dragged.itemId, parentId: element.id, position: 0 })

      return
    }

    const draggedItem = getDraggedItem()
    const draggedPos = draggedItem?.position ?? getDraggedIdx()
    const basePos = element.position ?? idx
    const targetPos = mode === 'before' ? basePos : basePos + 1
    const isSameParent = String(dragged.parentId) === String(options.parentId())

    const finalPosition = (isSameParent && draggedPos !== -1 && targetPos > draggedPos)
      ? targetPos - 1
      : targetPos

    options.emitMove({
      id: dragged.itemId,
      parentId: options.parentId(),
      position: finalPosition,
    })
  }

  function isDropTarget(element: MenuItem, mode: 'before' | 'after' | 'child'): boolean {
    const target = dropTargetRef?.value

    return target != null && target.mode === mode && target.itemId === element.id
  }

  function getDraggedIdx(): number {
    const dragged = draggedRef?.value
    if (!dragged) {
      return -1
    }

    return options.items().findIndex((i: MenuItem) => {
      return String(i.id) === String(dragged.itemId)
    })
  }

  function getDraggedItem(): MenuItem | undefined {
    const dragged = draggedRef?.value
    if (!dragged) {
      return undefined
    }

    return options.items().find((i: MenuItem) => {
      return String(i.id) === String(dragged.itemId)
    })
  }

  function isSlotNoop(itemIdx: number, mode: 'before' | 'after'): boolean {
    const draggedIdx = getDraggedIdx()
    if (draggedIdx < 0) {
      return false
    }
    if (mode === 'before' && (itemIdx === draggedIdx || itemIdx === draggedIdx + 1)) {
      return true
    }
    if (mode === 'after' && itemIdx === draggedIdx) {
      return true
    }

    return false
  }

  function isSlotActive(element: MenuItem, idx: number, mode: 'before' | 'after'): boolean {
    if (isSlotNoop(idx, mode)) {
      return false
    }

    return isDropTarget(element, mode)
  }

  function isSlotNearCursor(itemIdx: number, mode: 'before' | 'after'): boolean {
    if (isSlotNoop(itemIdx, mode)) {
      return false
    }
    const target = dropTargetRef?.value
    if (!target) {
      return false
    }
    const overIdx = options.items().findIndex((i: MenuItem) => {
      return i.id === target.itemId
    })
    if (overIdx < 0) {
      return false
    }
    if (mode === 'before') {
      return Math.abs(itemIdx - overIdx) <= 1
    }

    return itemIdx === overIdx
  }

  function isBeingDragged(element: MenuItem): boolean {
    const dragged = draggedRef?.value

    return dragged != null && dragged.itemId === element.id
  }

  function isHoverPending(element: MenuItem): boolean {
    return hoveringRowId.value != null && hoveringRowId.value === element.id
  }

  return {
    canDrag,
    onHandleDragStart,
    onHandleDragEnd,
    onRowDragOver,
    onRowDragLeave,
    onSlotDragOver,
    onSlotDrop,
    onRowDrop,
    isDropTarget,
    isSlotActive,
    isSlotNearCursor,
    isBeingDragged,
    isHoverPending,
  }
}
