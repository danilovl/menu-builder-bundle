<template>
  <header class="mb-admin__header">
    <div class="mb-admin__title">
      <span class="mb-admin__title-icon">⬡</span>
      {{ t('app.title') }}
    </div>
    <div class="mb-admin__header-right">
      <span class="mb-admin__pane-hint">⠿ {{ t('app.dragHint') }}</span>
      <div class="mb-admin__lang-picker" @click.stop>
        <button class="mb-admin__lang-btn" @click="langOpen = !langOpen" type="button">
          <span class="mb-admin__lang-flag">{{ currentLocaleFlag }}</span>
          <span class="mb-admin__lang-code">{{ locale.toUpperCase() }}</span>
          <svg
            width="9"
            height="9"
            viewBox="0 0 12 12"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path d="M2.5 4.5L6 8l3.5-3.5" />
          </svg>
        </button>
        <Transition name="mb-fade">
          <div v-if="langOpen" class="mb-admin__lang-menu">
            <button
              v-for="l in locales"
              :key="l.code"
              class="mb-admin__lang-item"
              :class="{ 'mb-admin__lang-item--active': locale === l.code }"
              @click="pickLocale(l.code)"
              type="button"
            >
              <span class="mb-admin__lang-flag">{{ l.flag }}</span>
              <span>{{ l.name }}</span>
              <span class="mb-admin__lang-code-sm">{{ l.code }}</span>
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useLangPicker } from '../../composables/ui/useLangPicker'

const { t, locale, locales, langOpen, currentLocaleFlag, pickLocale } = useLangPicker()
</script>
