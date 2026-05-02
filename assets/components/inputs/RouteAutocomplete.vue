<template>
  <div
    class="mb-route-ac"
    :class="{ 'mb-route-ac--multi': multiple, 'mb-route-ac--focused': focused }"
    @click="focusInput"
  >
    <template v-if="multiple">
      <span v-for="tag in modelValue as string[]" :key="tag" class="mb-tags__chip">
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
    </template>
    <input
      ref="inputEl"
      v-model="draft"
      type="text"
      class="mb-route-ac__input"
      :placeholder="resolvedPlaceholder"
      autocomplete="off"
      spellcheck="false"
      @focus="onFocus"
      @blur="onBlur"
      @keydown="onKeydown"
      @input="onInput"
    />
    <div v-if="open && (state.items.length > 0 || state.loading || lazyMissingTerm)" class="mb-route-ac__dropdown">
      <div v-if="state.loading" class="mb-route-ac__row mb-route-ac__row--meta">…</div>
      <template v-else-if="lazyMissingTerm">
        <div class="mb-route-ac__row mb-route-ac__row--meta">{{ totalLabel }}</div>
      </template>
      <template v-else>
        <button
          v-for="(item, idx) in state.items"
          :key="item.name"
          type="button"
          class="mb-route-ac__row"
          :class="{ 'mb-route-ac__row--active': idx === highlight }"
          @mousedown.prevent="pick(item.name)"
          @mouseenter="highlight = idx"
        >
          <span class="mb-route-ac__name">{{ item.name }}</span>
          <span class="mb-route-ac__path">{{ item.path }}</span>
        </button>
        <div v-if="state.truncated" class="mb-route-ac__row mb-route-ac__row--meta">
          {{ truncatedLabel }}
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from 'vue'
import type { RouteCatalogKey } from '../../composables/catalogs/routeCatalogKey'
import { ROUTE_CATALOG_KEY } from '../../composables/catalogs/routeCatalogKey'
import type { RouteInfo } from '../../api/menuApi'

const props = withDefaults(
  defineProps<{
    modelValue: string | string[]
    multiple?: boolean
    placeholder?: string
  }>(),
  {
    multiple: false,
    placeholder: '',
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string | string[]): void
}>()

const catalog = inject<RouteCatalogKey | null>(ROUTE_CATALOG_KEY, null)

const draft = ref<string>(props.multiple ? '' : ((props.modelValue as string) ?? ''))
const focused = ref(false)
const open = ref(false)
const highlight = ref(0)
const inputEl = ref<HTMLInputElement | null>(null)

watch(
  () => {
    return props.modelValue
  },
  (v) => {
    if (!props.multiple) {
      draft.value = (v as string) ?? ''
    }
  },
)

const state = computed(() => {
  if (!catalog) {
    return { items: [] as RouteInfo[], matched: 0, truncated: false, loading: false }
  }

  return catalog.query(draft.value)
})

const resolvedPlaceholder = computed((): string => {
  if (props.multiple && (props.modelValue as string[]).length > 0) {
    return ''
  }

  return props.placeholder
})

const lazyMissingTerm = computed((): boolean => {
  if (!catalog) {
    return false
  }
  if (!catalog.lazyMode.value) {
    return false
  }

  return draft.value.trim().length === 0
})

const totalLabel = computed((): string => {
  if (!catalog) {
    return ''
  }

  return `${catalog.total.value} routes — type to search`
})

const truncatedLabel = computed((): string => {
  return `+${state.value.matched - state.value.items.length} more — refine search`
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
    if (props.multiple) {
      commitTagFromDraft()
    } else {
      emit('update:modelValue', draft.value.trim())
    }
  }, 120)
}

function onInput(): void {
  open.value = true
  highlight.value = 0
  if (catalog?.lazyMode.value) {
    void catalog.search(draft.value)
  }
  if (!props.multiple) {
    emit('update:modelValue', draft.value)
  }
}

function onKeydown(e: KeyboardEvent): void {
  const items = state.value.items
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (items.length > 0) {
      highlight.value = (highlight.value + 1) % items.length
      open.value = true
    }

    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (items.length > 0) {
      highlight.value = (highlight.value - 1 + items.length) % items.length
      open.value = true
    }

    return
  }
  if (e.key === 'Enter') {
    if (items.length > 0 && open.value) {
      e.preventDefault()
      pick(items[highlight.value].name)

      return
    }
    if (props.multiple) {
      e.preventDefault()
      commitTagFromDraft()
    }

    return
  }
  if (e.key === 'Escape') {
    open.value = false

    return
  }
  if (props.multiple && e.key === ',') {
    e.preventDefault()
    commitTagFromDraft()

    return
  }
  if (props.multiple && e.key === 'Backspace' && draft.value === '') {
    const list = props.modelValue as string[]
    if (list.length > 0) {
      const next = [...list]
      next.pop()
      emit('update:modelValue', next)
    }
  }
}

function pick(name: string): void {
  if (props.multiple) {
    const list = props.modelValue as string[]
    if (!list.includes(name)) {
      emit('update:modelValue', [...list, name])
    }
    draft.value = ''
    open.value = true
    inputEl.value?.focus()

    return
  }
  draft.value = name
  emit('update:modelValue', name)
  open.value = false
  inputEl.value?.blur()
}

function commitTagFromDraft(): void {
  const v = draft.value.trim()
  draft.value = ''
  if (!v) {
    return
  }
  const list = props.modelValue as string[]
  if (list.includes(v)) {
    return
  }
  emit('update:modelValue', [...list, v])
}

function removeTag(tag: string): void {
  const list = props.modelValue as string[]
  emit(
    'update:modelValue',
    list.filter((t: string) => {
      return t !== tag
    }),
  )
}
</script>
