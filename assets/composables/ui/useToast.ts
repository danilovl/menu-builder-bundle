import { ref } from 'vue'
import type { ToastState, ToastType } from '../../types/menu'

export function useToast() {
  const toast = ref<ToastState | null>(null)

  function showToast(message: string, type: ToastType = 'info'): void {
    toast.value = { message, type }
    setTimeout(() => {
      toast.value = null
    }, 3000)
  }

  return { toast, showToast }
}
