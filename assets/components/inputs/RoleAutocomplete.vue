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
    <div v-if="open && suggestions.length > 0" class="mb-route-ac__dropdown">
      <button
        v-for="(role, idx) in suggestions"
        :key="role"
        type="button"
        class="mb-route-ac__row"
        :class="{ 'mb-route-ac__row--active': idx === highlight }"
        @mousedown.prevent="pick(role)"
        @mouseenter="highlight = idx"
      >
        <span class="mb-route-ac__name">{{ role }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, ref } from 'vue'
import type { RoleCatalogKey } from '../../composables/catalogs/roleCatalogKey'
import { ROLE_CATALOG_KEY } from '../../composables/catalogs/roleCatalogKey'

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

const catalog = inject<RoleCatalogKey | null>(ROLE_CATALOG_KEY, null)

const draft = ref('')
const focused = ref(false)
const open = ref(false)
const highlight = ref(0)
const inputEl = ref<HTMLInputElement | null>(null)

const suggestions = computed((): string[] => {
  if (!catalog) {
    return []
  }
  const matches = catalog.suggest(draft.value)

  return matches.filter((r: string) => {
    return !props.modelValue.includes(r)
  })
})

onMounted(() => {
  if (catalog) {
    void catalog.init()
  }
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

function pick(role: string): void {
  if (!props.modelValue.includes(role)) {
    emit('update:modelValue', [...props.modelValue, role])
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
