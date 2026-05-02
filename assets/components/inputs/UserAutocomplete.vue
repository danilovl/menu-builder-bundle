<template>
  <div class="mb-route-ac mb-route-ac--multi" :class="{ 'mb-route-ac--focused': focused }" @click="focusInput">
    <span v-for="tag in modelValue" :key="tag" class="mb-tags__chip">
      <span class="mb-tags__chip-text">{{ tag }}</span>
      <button type="button" class="mb-tags__chip-remove" title="Remove" @click.stop="removeTag(tag)">
        <svg
          width="9"
          height="9"
          viewBox="0 0 12 12"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M3 3l6 6M9 3l-6 6" />
        </svg>
      </button>
    </span>
    <input
      ref="inputEl"
      v-model="draft"
      type="text"
      class="mb-route-ac__input"
      :placeholder="modelValue.length === 0 ? placeholder : ''"
      autocomplete="off"
      spellcheck="false"
      @focus="onFocus"
      @blur="onBlur"
      @keydown="onKeydown"
      @input="onInput"
    />
    <div v-if="open && (suggestions.length > 0 || state.loading)" class="mb-route-ac__dropdown">
      <div v-if="state.loading" class="mb-route-ac__row mb-route-ac__row--meta">…</div>
      <template v-else>
        <button
          v-for="(value, idx) in suggestions"
          :key="value"
          type="button"
          class="mb-route-ac__row"
          :class="{ 'mb-route-ac__row--active': idx === highlight }"
          @mousedown.prevent="pick(value)"
          @mouseenter="highlight = idx"
        >
          <span class="mb-route-ac__name">{{ value }}</span>
        </button>
        <div v-if="state.truncated" class="mb-route-ac__row mb-route-ac__row--meta">+ more — refine search</div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import type { UserCatalogKey } from '../../composables/catalogs/userCatalogKey'
import { USER_CATALOG_KEY } from '../../composables/catalogs/userCatalogKey'

const props = withDefaults(
  defineProps<{
    modelValue: string[]
    placeholder?: string
  }>(),
  {
    placeholder: '',
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string[]): void
}>()

const catalog = inject<UserCatalogKey | null>(USER_CATALOG_KEY, null)

const draft = ref('')
const focused = ref(false)
const open = ref(false)
const highlight = ref(0)
const inputEl = ref<HTMLInputElement | null>(null)

const state = computed(() => {
  if (!catalog) {
    return { items: [], loading: false, truncated: false }
  }

  return catalog.state.value
})

const suggestions = computed((): string[] => {
  return state.value.items.filter((u: string) => {
    return !props.modelValue.includes(u)
  })
})

function focusInput(): void {
  inputEl.value?.focus()
}

function onFocus(): void {
  focused.value = true
  open.value = true
}

function onBlur(): void {
  focused.value = false
  setTimeout(() => {
    open.value = false
    commitTagFromDraft()
  }, 120)
}

function onInput(): void {
  open.value = true
  highlight.value = 0
  if (catalog) {
    void catalog.search(draft.value)
  }
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (suggestions.value.length > 0) {
      highlight.value = (highlight.value + 1) % suggestions.value.length
      open.value = true
    }

    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (suggestions.value.length > 0) {
      highlight.value = (highlight.value - 1 + suggestions.value.length) % suggestions.value.length
      open.value = true
    }

    return
  }
  if (e.key === 'Enter') {
    if (suggestions.value.length > 0 && open.value) {
      e.preventDefault()
      pick(suggestions.value[highlight.value])

      return
    }
    e.preventDefault()
    commitTagFromDraft()

    return
  }
  if (e.key === ',') {
    e.preventDefault()
    commitTagFromDraft()

    return
  }
  if (e.key === 'Escape') {
    open.value = false

    return
  }
  if (e.key === 'Backspace' && draft.value === '' && props.modelValue.length > 0) {
    const next = [...props.modelValue]
    next.pop()
    emit('update:modelValue', next)
  }
}

function pick(value: string): void {
  if (!props.modelValue.includes(value)) {
    emit('update:modelValue', [...props.modelValue, value])
  }
  draft.value = ''
  open.value = true
  inputEl.value?.focus()
}

function commitTagFromDraft(): void {
  const v = draft.value.trim()
  draft.value = ''
  if (!v) {
    return
  }
  if (props.modelValue.includes(v)) {
    return
  }
  emit('update:modelValue', [...props.modelValue, v])
}

function removeTag(tag: string): void {
  emit(
    'update:modelValue',
    props.modelValue.filter((t: string) => {
      return t !== tag
    }),
  )
}
</script>
