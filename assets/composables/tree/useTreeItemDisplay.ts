import { SUPPORTED_LOCALES, useI18n } from '../../i18n'
import type { MenuItem } from '../../types/menu'

export interface UseTreeItemDisplayOptions {
  previewLocale: () => string | null
}

export interface LocaleFlag {
  code: string
  flag: string
  name: string
}

export function useTreeItemDisplay(options: UseTreeItemDisplayOptions) {
  const { t } = useI18n()

  function hasChildren(item: MenuItem): boolean {
    return Array.isArray(item.children) && item.children.length > 0
  }

  function displayLabel(item: MenuItem): string {
    const code = options.previewLocale()
    if (code) {
      const tr = item.translations?.[code]
      if (tr?.label) {
        return tr.label
      }
    }
    if (item.resolvedLabel && item.resolvedLabel !== '') {
      return item.resolvedLabel
    }

    return item.label || t('tree.untitled')
  }

  function isFallback(item: MenuItem): boolean {
    const code = options.previewLocale()
    if (!code) {
      return false
    }
    if (item.translations?.[code]?.label) {
      return false
    }
    if (item.labelTranslated && item.resolvedLabel && item.resolvedLabel !== item.label) {
      return false
    }

    return true
  }

  function visibilityIcon(item: MenuItem): string | null {
    if (item.visibility === 'authenticated') {
      return '🔐'
    }
    if (item.visibility === 'anonymous') {
      return '👤'
    }

    return null
  }

  function visibilityTitle(item: MenuItem): string {
    if (item.visibility === 'authenticated') {
      return t('form.visibility.authenticated')
    }
    if (item.visibility === 'anonymous') {
      return t('form.visibility.anonymous')
    }

    return t('form.visibility.always')
  }

  function localeFlags(item: MenuItem): LocaleFlag[] {
    const codes = Object.keys(item.translations ?? {})
    if (codes.length === 0) {
      return []
    }

    return SUPPORTED_LOCALES.filter((l) => {
      const tr = item.translations?.[l.code]

      return tr && (tr.label || tr.uri)
    }).map((l) => {
      return { code: l.code, flag: l.flag, name: l.name }
    })
  }

  return {
    hasChildren,
    displayLabel,
    isFallback,
    visibilityIcon,
    visibilityTitle,
    localeFlags,
  }
}
