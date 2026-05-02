<template>
  <div class="mb-form__panel">
    <p class="mb-form__panel-hint">{{ t('form.translations.hint') }}</p>

    <div v-for="entry in translationEntries" :key="entry.code" class="mb-form__locale">
      <div class="mb-form__locale-head">
        <span class="mb-form__locale-flag">{{ entry.flag }}</span>
        <span class="mb-form__locale-name">{{ entry.name }}</span>
        <span class="mb-form__locale-code">{{ entry.code }}</span>
        <button
          type="button"
          class="mb-form__locale-remove"
          @click="removeTranslation(entry.code)"
          :title="t('form.cancel')"
        >
          <svg
            width="11"
            height="11"
            viewBox="0 0 12 12"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path d="M3 3l6 6M9 3l-6 6" />
          </svg>
        </button>
      </div>
      <input
        class="mb-form__locale-input"
        type="text"
        :placeholder="t('form.translations.label')"
        :value="form.translations[entry.code]?.label ?? ''"
        @input="updateTranslation(entry.code, 'label', ($event.target as HTMLInputElement).value)"
      />
      <input
        class="mb-form__locale-input"
        type="text"
        :placeholder="t('form.translations.uri')"
        :value="form.translations[entry.code]?.uri ?? ''"
        @input="updateTranslation(entry.code, 'uri', ($event.target as HTMLInputElement).value)"
      />
    </div>

    <div v-if="translationEntries.length === 0" class="mb-form__locale-empty">🌐 {{ t('form.translations.hint') }}</div>

    <div class="mb-form__locale-add" v-if="availableLocales.length > 0">
      <select v-model="newLocale" class="mb-form__locale-select">
        <option value="">{{ t('form.translations.add') }}</option>
        <option v-for="l in availableLocales" :key="l.code" :value="l.code">{{ l.flag }} {{ l.name }}</option>
      </select>
      <button type="button" class="mb-btn mb-btn--ghost mb-btn--sm" @click="addTranslation" :disabled="!newLocale">
        +
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useI18n, SUPPORTED_LOCALES } from '../../i18n'
import type { MenuItem, MenuTranslation } from '../../types/menu'

const { t } = useI18n()

const props = defineProps<{
  form: MenuItem
}>()

const newLocale = ref('')

const translationEntries = computed(() => {
  const codes = Object.keys(props.form.translations ?? {})

  return SUPPORTED_LOCALES.filter((l) => {
    return codes.includes(l.code)
  })
})

const availableLocales = computed(() => {
  const used = new Set(Object.keys(props.form.translations ?? {}))

  return SUPPORTED_LOCALES.filter((l) => {
    return !used.has(l.code)
  })
})

function addTranslation(): void {
  const code = newLocale.value
  if (!code) {
    return
  }
  const current = props.form.translations ?? {}
  if (current[code]) {
    newLocale.value = ''

    return
  }
  props.form.translations = { ...current, [code]: { label: '', uri: '' } }
  newLocale.value = ''
}

function removeTranslation(code: string): void {
  const current = props.form.translations ?? {}
  if (!(code in current)) {
    return
  }
  const next: Record<string, MenuTranslation> = {}
  for (const k of Object.keys(current)) {
    if (k !== code) {
      next[k] = current[k]
    }
  }
  props.form.translations = next
}

function updateTranslation(code: string, field: keyof MenuTranslation, value: string): void {
  const current = props.form.translations ?? {}
  const entry = { ...(current[code] ?? {}), [field]: value }
  props.form.translations = { ...current, [code]: entry }
}
</script>
