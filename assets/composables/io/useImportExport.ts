import type { MenuApi } from '../../api/menuApi'
import type { ToastType } from '../../types/menu'

export function useImportExport(
  api: MenuApi,
  showToast: (msg: string, type?: ToastType) => void,
  t: (key: string, params?: Record<string, string>) => string,
) {
  async function importFromFile(file: File): Promise<{ menu: string; created: number } | null> {
    let parsed: unknown
    try {
      const text = await file.text()
      parsed = JSON.parse(text)
    } catch {
      showToast(t('toast.importInvalidJson'), 'error')

      return null
    }
    try {
      return await api.importMenu(parsed)
    } catch (e) {
      showToast((e as Error).message, 'error')

      return null
    }
  }

  async function exportToFile(name: string): Promise<void> {
    try {
      const data = await api.exportMenu(name)
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `menu-${name}.json`
      link.click()
      URL.revokeObjectURL(url)
    } catch (e) {
      showToast((e as Error).message, 'error')
    }
  }

  return {
    importFromFile,
    exportToFile,
  }
}
