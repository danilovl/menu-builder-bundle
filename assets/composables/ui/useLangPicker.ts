import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from '../../i18n'

export function useLangPicker() {
  const { t, locale, setLocale, locales } = useI18n()
  const langOpen = ref(false)

  const currentLocaleFlag = computed((): string => {
    const found = locales.find((l) => {
      return l.code === locale.value
    })

    return found?.flag ?? '🌐'
  })

  function pickLocale(code: string): void {
    setLocale(code)
    langOpen.value = false
  }

  function closeLangMenu(): void {
    langOpen.value = false
  }

  onMounted(() => {
    document.addEventListener('click', closeLangMenu)
  })

  onBeforeUnmount(() => {
    document.removeEventListener('click', closeLangMenu)
  })

  return {
    t,
    locale,
    locales,
    langOpen,
    currentLocaleFlag,
    pickLocale,
  }
}
