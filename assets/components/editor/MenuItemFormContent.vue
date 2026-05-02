<template>
  <div class="mb-form__panel">
    <div class="mb-form__group">
      <div class="mb-form__group-title">{{ t('form.section.general') }}</div>
      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.menu') }}</span>
        <input :value="form.menuName" type="text" readonly tabindex="-1" />
      </label>
      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.parent') }}</span>
        <select :value="form.parentId ?? ''" @change="onParentChange(($event.target as HTMLSelectElement).value)">
          <option value="">{{ t('form.parentRoot') }}</option>
          <option v-for="opt in parentOptions" :key="opt.id" :value="opt.id" :disabled="opt.disabled">
            {{ '— '.repeat(opt.depth) }}{{ opt.label }}
          </option>
        </select>
        <span class="mb-form__field-hint">{{ t('form.parentHint') }}</span>
      </label>
      <label class="mb-form__field">
        <span class="mb-form__field-label"> {{ t('form.label') }}<i class="mb-form__field-required">*</i> </span>
        <input
          v-model="form.label"
          type="text"
          required
          maxlength="255"
          :placeholder="form.labelTranslated ? 'menu.home' : ''"
        />
      </label>

      <label class="mb-form__toggle">
        <input type="checkbox" v-model="form.labelTranslated" />
        <span class="mb-form__toggle-track"><span class="mb-form__toggle-thumb"></span></span>
        <span class="mb-form__toggle-text">
          {{ t('form.useTranslator') }}
          <span class="mb-form__toggle-hint">{{ t('form.useTranslatorHint') }}</span>
        </span>
      </label>

      <label class="mb-form__field" v-if="form.labelTranslated">
        <span class="mb-form__field-label">{{ t('form.translationDomain') }}</span>
        <input
          :value="form.translationDomain ?? ''"
          @input="form.translationDomain = ($event.target as HTMLInputElement).value || null"
          type="text"
          placeholder="messages"
        />
        <span class="mb-form__field-hint">{{ t('form.translationDomainHint') }}</span>
      </label>

      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.icon') }}</span>
        <input v-model="form.icon" type="text" placeholder="fa fa-home" />
      </label>

      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.iconImage') }}</span>
        <input
          :value="form.iconImage ?? ''"
          @input="form.iconImage = ($event.target as HTMLInputElement).value || null"
          type="text"
          placeholder="https://… /uploads/icon.png"
          maxlength="1024"
        />
        <span class="mb-form__field-hint">{{ t('form.iconImageHint') }}</span>
      </label>
    </div>

    <div class="mb-form__group">
      <div class="mb-form__group-title">{{ t('form.section.link') }}</div>
      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.uri') }}</span>
        <input v-model="form.uri" type="text" :placeholder="t('form.uriHint')" />
      </label>
      <div class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.route') }}</span>
        <RouteAutocomplete :model-value="form.route ?? ''" @update:model-value="onRouteUpdate" placeholder="app_home" />
      </div>
      <label class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.routeParams') }}</span>
        <textarea
          :value="routeParamsJson"
          @input="emit('update:routeParamsJson', ($event.target as HTMLTextAreaElement).value)"
          rows="2"
          placeholder='{"id": 1}'
        ></textarea>
        <span v-if="routeParamsError" class="mb-form__field-error">{{ routeParamsError }}</span>
      </label>
      <div class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.dependentRoutes') }}</span>
        <RouteAutocomplete
          multiple
          :model-value="dependentRoutesList"
          @update:model-value="onDependentRoutesChange"
          placeholder="app_route"
        />
        <span class="mb-form__field-hint">{{ t('form.dependentRoutesHint') }}</span>
      </div>

      <label class="mb-form__toggle">
        <input type="checkbox" :checked="form.target === '_blank'" @change="onTargetToggle" />
        <span class="mb-form__toggle-track"><span class="mb-form__toggle-thumb"></span></span>
        <span class="mb-form__toggle-text">
          {{ t('form.openInNewTab') }}
          <span class="mb-form__toggle-hint">{{ t('form.openInNewTabHint') }}</span>
        </span>
      </label>
    </div>

    <div class="mb-form__group mb-form__group--collapsible">
      <button
        type="button"
        class="mb-form__group-toggle"
        :class="{ 'mb-form__group-toggle--open': showAttrs }"
        @click="emit('update:showAttrs', !showAttrs)"
      >
        <svg
          width="10"
          height="10"
          viewBox="0 0 12 12"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          aria-hidden="true"
        >
          <path d="M4 2.5L8 6l-4 3.5" />
        </svg>
        {{ t('form.section.attributes') }}
      </button>
      <div v-show="showAttrs" class="mb-form__group-collapsed">
        <textarea
          :value="attributesJson"
          @input="emit('update:attributesJson', ($event.target as HTMLTextAreaElement).value)"
          rows="3"
          placeholder='{"class": "highlight"}'
        ></textarea>
        <span v-if="attributesError" class="mb-form__field-error">{{ attributesError }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '../../i18n'
import RouteAutocomplete from '../inputs/RouteAutocomplete.vue'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

interface ParentOption {
  id: string
  label: string
  depth: number
  disabled: boolean
}

const props = defineProps<{
  form: MenuItem
  routeParamsError: string | null
  attributesError: string | null
  showAttrs: boolean
  routeParamsJson: string
  attributesJson: string
  dependentRoutesList: string[]
  parentOptions: ParentOption[]
}>()

const emit = defineEmits<{
  (e: 'update:showAttrs', value: boolean): void
  (e: 'update:routeParamsJson', value: string): void
  (e: 'update:attributesJson', value: string): void
  (e: 'update:dependentRoutesList', value: string[]): void
}>()

function onTargetToggle(event: Event): void {
  const checked = (event.target as HTMLInputElement).checked
  props.form.target = checked ? '_blank' : null
}

function onParentChange(value: string): void {
  props.form.parentId = value === '' ? null : value
}

function onDependentRoutesChange(value: string | string[]): void {
  emit('update:dependentRoutesList', value as string[])
}

function onRouteUpdate(value: string | string[]): void {
  const next = (value as string).trim()
  props.form.route = next === '' ? null : next
}
</script>
