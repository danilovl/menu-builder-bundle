<template>
  <form class="mb-form" @submit.prevent="onSubmit">
    <div class="mb-form__header">
      <h3 class="mb-form__title">{{ form.id ? t('form.editTitle') : t('form.newTitle') }}</h3>
      <p class="mb-form__subtitle">{{ form.id ? t('form.editSubtitle') : t('form.newSubtitle') }}</p>
    </div>

    <div class="mb-form__tabs">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="mb-form__tab"
        :class="{ 'mb-form__tab--active': activeTab === tab.id }"
        @click="activeTab = tab.id"
      >
        {{ tab.label }}
        <span v-if="tab.badge" class="mb-form__tab-badge">{{ tab.badge }}</span>
      </button>
    </div>

    <div class="mb-form__body">
      <MenuItemFormContent
        v-show="activeTab === 'content'"
        :form="form"
        :route-params-error="routeParamsError"
        :attributes-error="attributesError"
        :show-attrs="showAttrs"
        :route-params-json="routeParamsJson"
        :attributes-json="attributesJson"
        :dependent-routes-list="dependentRoutesList"
        :parent-options="parentOptions"
        @update:show-attrs="onShowAttrsUpdate"
        @update:route-params-json="onRouteParamsJsonUpdate"
        @update:attributes-json="onAttributesJsonUpdate"
        @update:dependent-routes-list="onDependentRoutesUpdate"
      />
      <MenuItemFormAccess
        v-show="activeTab === 'access'"
        :form="form"
        :roles-list="rolesList"
        :users-list="usersList"
        @update:roles-list="onRolesListUpdate"
        @update:users-list="onUsersListUpdate"
      />
      <MenuItemFormTranslations v-show="activeTab === 'translations'" :form="form" />
    </div>

    <div class="mb-form__actions">
      <button type="submit" class="mb-btn mb-btn--primary">
        {{ form.id ? t('form.save') : t('form.create') }}
      </button>
      <button type="button" class="mb-btn mb-btn--ghost" @click="$emit('cancel')">
        {{ t('form.cancel') }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { parseJson } from '../../helpers/parsers'
import { useI18n } from '../../i18n'
import MenuItemFormContent from './MenuItemFormContent.vue'
import MenuItemFormAccess from './MenuItemFormAccess.vue'
import MenuItemFormTranslations from './MenuItemFormTranslations.vue'
import type { MenuItem, MenuTranslation, MenuVisibility } from '../../types/menu'

const props = withDefaults(
  defineProps<{
    modelValue: MenuItem
    menus?: string[]
    tree?: MenuItem[]
  }>(),
  {
    menus: () => {
      return []
    },
    tree: () => {
      return []
    },
  },
)

interface ParentOption {
  id: string
  label: string
  depth: number
  disabled: boolean
}

const parentOptions = computed((): ParentOption[] => {
  const editingId = props.modelValue.id
  const blocked = new Set<string>()
  if (editingId) {
    collectIds(props.modelValue.children ?? [], blocked)
  }

  const out: ParentOption[] = []
  const walk = (nodes: MenuItem[], depth: number): void => {
    for (const node of nodes) {
      if (!node.id) {
        continue
      }
      out.push({
        id: node.id,
        label: node.resolvedLabel || node.label || '(untitled)',
        depth,
        disabled: node.id === editingId || blocked.has(node.id),
      })
      if (node.children?.length) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(props.tree, 0)

  return out
})

function collectIds(nodes: MenuItem[], acc: Set<string>): void {
  for (const node of nodes) {
    if (node.id) {
      acc.add(node.id)
    }
    if (node.children?.length) {
      collectIds(node.children, acc)
    }
  }
}

const emit = defineEmits<{
  (e: 'save', payload: MenuItem): void
  (e: 'cancel'): void
}>()

const { t } = useI18n()

type TabId = 'content' | 'access' | 'translations'
const activeTab = ref<TabId>('content')

function cloneItem(item: MenuItem): MenuItem {
  return JSON.parse(JSON.stringify(item)) as MenuItem
}

function applyDefaults(item: MenuItem): MenuItem {
  const next = cloneItem(item)
  next.target = next.target ?? null
  next.visibility = (next.visibility ?? 'always') as MenuVisibility
  next.translations = next.translations ?? {}
  next.dependentActiveRoutes = next.dependentActiveRoutes ?? []
  next.labelTranslated = next.labelTranslated ?? false
  next.translationDomain = next.translationDomain ?? null
  next.iconImage = next.iconImage ?? null
  next.type = next.type ?? 'link'
  next.column = next.column ?? 0
  next.cssClasses = next.cssClasses ?? []
  next.publishedAt = next.publishedAt ?? null
  next.unpublishedAt = next.unpublishedAt ?? null

  return next
}

const form = ref<MenuItem>(applyDefaults(props.modelValue))
const routeParamsJson = ref(JSON.stringify(props.modelValue.routeParams || {}, null, 2))
const attributesJson = ref(JSON.stringify(props.modelValue.attributes || {}, null, 2))
const rolesList = ref<string[]>([...(props.modelValue.requiredRoles || [])])
const usersList = ref<string[]>([...(props.modelValue.allowedUsers || [])])
const dependentRoutesList = ref<string[]>([...(props.modelValue.dependentActiveRoutes || [])])
const routeParamsError = ref<string | null>(null)
const attributesError = ref<string | null>(null)
const showAttrs = ref(false)

watch(
  () => {
    return props.modelValue
  },
  (v) => {
    form.value = applyDefaults(v)
    routeParamsJson.value = JSON.stringify(v.routeParams || {}, null, 2)
    attributesJson.value = JSON.stringify(v.attributes || {}, null, 2)
    rolesList.value = [...(v.requiredRoles || [])]
    usersList.value = [...(v.allowedUsers || [])]
    dependentRoutesList.value = [...(v.dependentActiveRoutes || [])]
    activeTab.value = 'content'
    showAttrs.value = false
  },
  { deep: true },
)

const accessBadge = computed((): string | null => {
  const restrictions =
    (rolesList.value.length > 0 ? 1 : 0) +
    (usersList.value.length > 0 ? 1 : 0) +
    (form.value.visibility !== 'always' ? 1 : 0) +
    (form.value.active === false ? 1 : 0)

  return restrictions > 0 ? String(restrictions) : null
})

const translationCount = computed((): number => {
  return Object.keys(form.value.translations ?? {}).length
})

const tabs = computed((): { id: TabId; label: string; badge: string | null }[] => {
  return [
    { id: 'content', label: t('form.section.general'), badge: null },
    { id: 'access', label: t('form.section.visibility'), badge: accessBadge.value },
    {
      id: 'translations',
      label: t('form.section.translations'),
      badge: translationCount.value > 0 ? String(translationCount.value) : null,
    },
  ]
})

function onShowAttrsUpdate(value: boolean): void {
  showAttrs.value = value
}

function onRouteParamsJsonUpdate(value: string): void {
  routeParamsJson.value = value
}

function onAttributesJsonUpdate(value: string): void {
  attributesJson.value = value
}

function onDependentRoutesUpdate(value: string[]): void {
  dependentRoutesList.value = value
}

function onRolesListUpdate(value: string[]): void {
  rolesList.value = value
}

function onUsersListUpdate(value: string[]): void {
  usersList.value = value
}

function cleanTranslations(input: Record<string, MenuTranslation>): Record<string, MenuTranslation> {
  const out: Record<string, MenuTranslation> = {}
  for (const [code, entry] of Object.entries(input)) {
    const e: MenuTranslation = {}
    if (entry.label && entry.label.trim() !== '') {
      e.label = entry.label.trim()
    }
    if (entry.uri && entry.uri.trim() !== '') {
      e.uri = entry.uri.trim()
    }
    if (e.label || e.uri) {
      out[code] = e
    }
  }

  return out
}

function onSubmit(): void {
  const [routeParams, rpErr] = parseJson<Record<string, unknown>>(routeParamsJson.value, {})
  const [attributes, atErr] = parseJson<Record<string, unknown>>(attributesJson.value, {})
  routeParamsError.value = rpErr
  attributesError.value = atErr

  if (rpErr || atErr) {
    activeTab.value = 'content'
    if (atErr) {
      showAttrs.value = true
    }

    return
  }

  emit('save', {
    ...form.value,
    routeParams,
    attributes,
    requiredRoles: [...rolesList.value],
    allowedUsers: [...usersList.value],
    dependentActiveRoutes: [...dependentRoutesList.value],
    translations: cleanTranslations(form.value.translations ?? {}),
  })
}
</script>
