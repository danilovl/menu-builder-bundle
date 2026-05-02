<template>
  <div class="mb-tags" :class="{ 'mb-tags--focused': focused }" @click="focusInput">
    <span v-for="tag in modelValue" :key="tag" class="mb-tags__chip">
      <span class="mb-tags__chip-text">{{ tag }}</span>
      <button type="button" class="mb-tags__chip-remove" :title="removeLabel" @click.stop="removeByValue(tag)">
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
      class="mb-tags__input"
      :placeholder="modelValue.length === 0 ? placeholder : ''"
      @focus="focused = true"
      @blur="onBlur"
      @keydown="onKeydown"
      @paste="onPaste"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: string[]
    placeholder?: string
    removeLabel?: string
  }>(),
  {
    placeholder: '',
    removeLabel: 'Remove',
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string[]): void
}>()

const draft = ref('')
const focused = ref(false)
const inputEl = ref<HTMLInputElement | null>(null)

function focusInput(): void {
  inputEl.value?.focus()
}

function commit(): boolean {
  const v = draft.value.trim()
  draft.value = ''
  if (!v) {
    return false
  }
  if (props.modelValue.includes(v)) {
    return true
  }
  emit('update:modelValue', [...props.modelValue, v])

  return true
}

function removeByValue(tag: string): void {
  emit(
    'update:modelValue',
    props.modelValue.filter((t: string) => {
      return t !== tag
    }),
  )
}

function onBlur(): void {
  focused.value = false
  commit()
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault()
    commit()

    return
  }
  if (e.key === 'Tab' && draft.value.trim() !== '') {
    if (commit()) {
      e.preventDefault()
    }

    return
  }
  if (e.key === 'Backspace' && draft.value === '' && props.modelValue.length > 0) {
    const next = [...props.modelValue]
    next.pop()
    emit('update:modelValue', next)
  }
}

function onPaste(e: ClipboardEvent): void {
  const text = e.clipboardData?.getData('text') ?? ''
  if (!text.includes(',') && !text.includes('\n')) {
    return
  }
  e.preventDefault()
  const parts = text
    .split(/[,\n]/)
    .map((s: string) => {
      return s.trim()
    })
    .filter((s: string) => {
      return s !== ''
    })
  if (parts.length === 0) {
    return
  }
  const merged = [...props.modelValue]
  for (const p of parts) {
    if (!merged.includes(p)) {
      merged.push(p)
    }
  }
  emit('update:modelValue', merged)
  draft.value = ''
}
</script>
