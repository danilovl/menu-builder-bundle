import { nextTick, ref } from 'vue'
import type { MenuItem } from '../../types/menu'

export interface UseTreeRenameOptions {
  inputRef: () => HTMLInputElement | HTMLInputElement[] | null
  onCommit: (payload: { id: string; label: string }) => void
}

export function useTreeRename(options: UseTreeRenameOptions) {
  const renamingId = ref<string | null>(null)
  const renameValue = ref('')
  let renameInProgress = false

  function startRename(item: MenuItem): void {
    if (!item.id) {
      return
    }
    renamingId.value = item.id
    renameValue.value = item.label
    nextTick(() => {
      const el = options.inputRef()
      const input = Array.isArray(el) ? el[0] : el
      input?.focus()
      input?.select()
    })
  }

  function cancelRename(): void {
    renamingId.value = null
    renameValue.value = ''
  }

  function commitRename(item: MenuItem): void {
    if (renameInProgress) {
      return
    }
    renameInProgress = true
    const id = item.id
    const next = renameValue.value.trim()
    renamingId.value = null
    renameValue.value = ''
    if (id && next && next !== item.label) {
      options.onCommit({ id, label: next })
    }
    renameInProgress = false
  }

  function isRenaming(id: string | null): boolean {
    return id !== null && renamingId.value === id
  }

  return {
    renamingId,
    renameValue,
    startRename,
    cancelRename,
    commitRename,
    isRenaming,
  }
}
