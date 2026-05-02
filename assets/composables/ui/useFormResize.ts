import { ref, onMounted, onBeforeUnmount } from 'vue'

const STORAGE_KEY = 'menu-builder.form-width'
const MIN = 320
const MAX = 760
const DEFAULT_WIDTH = 440

export function useFormResize() {
  const formPaneWidth = ref(DEFAULT_WIDTH)
  const isResizing = ref(false)
  let startX = 0
  let startWidth = 0

  function clamp(value: number): number {
    if (Number.isNaN(value)) {
      return DEFAULT_WIDTH
    }

    return Math.max(MIN, Math.min(MAX, value))
  }

  function startResize(event: MouseEvent): void {
    isResizing.value = true
    startX = event.clientX
    startWidth = formPaneWidth.value
    document.body.style.cursor = 'ew-resize'
    document.addEventListener('mousemove', onMove)
    document.addEventListener('mouseup', onEnd)
  }

  function onMove(event: MouseEvent): void {
    if (!isResizing.value) {
      return
    }
    const delta = startX - event.clientX
    formPaneWidth.value = clamp(startWidth + delta)
  }

  function onEnd(): void {
    if (!isResizing.value) {
      return
    }
    isResizing.value = false
    document.body.style.cursor = ''
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onEnd)
    try {
      localStorage.setItem(STORAGE_KEY, String(formPaneWidth.value))
    } catch {
      /* localStorage unavailable */
    }
  }

  onMounted(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY)
      if (stored !== null) {
        formPaneWidth.value = clamp(Number(stored))
      }
    } catch {
      /* localStorage unavailable */
    }
  })

  onBeforeUnmount(() => {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onEnd)
    document.body.style.cursor = ''
  })

  return {
    formPaneWidth,
    isResizing,
    startResize,
  }
}
