<template>
  <div class="mb-form__panel">
    <div class="mb-form__group">
      <label class="mb-form__toggle">
        <input type="checkbox" v-model="form.active" />
        <span class="mb-form__toggle-track"><span class="mb-form__toggle-thumb"></span></span>
        <span class="mb-form__toggle-text">
          {{ t('form.active') }}
          <span class="mb-form__toggle-hint">{{ t('form.activeHint') }}</span>
        </span>
      </label>
    </div>

    <div class="mb-form__group">
      <div class="mb-form__group-title">{{ t('form.visibilityMode') }}</div>
      <div class="mb-form__radio-group">
        <label class="mb-form__radio" :class="{ 'mb-form__radio--active': form.visibility === 'always' }">
          <input type="radio" value="always" v-model="form.visibility" />
          <span class="mb-form__radio-icon">👥</span>
          <span>{{ t('form.visibility.always') }}</span>
        </label>
        <label class="mb-form__radio" :class="{ 'mb-form__radio--active': form.visibility === 'authenticated' }">
          <input type="radio" value="authenticated" v-model="form.visibility" />
          <span class="mb-form__radio-icon">🔐</span>
          <span>{{ t('form.visibility.authenticated') }}</span>
        </label>
        <label class="mb-form__radio" :class="{ 'mb-form__radio--active': form.visibility === 'anonymous' }">
          <input type="radio" value="anonymous" v-model="form.visibility" />
          <span class="mb-form__radio-icon">👤</span>
          <span>{{ t('form.visibility.anonymous') }}</span>
        </label>
      </div>
    </div>

    <div class="mb-form__group">
      <div class="mb-form__group-title">{{ t('form.section.access') }}</div>
      <div class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.requiredRoles') }}</span>
        <RoleAutocomplete :model-value="rolesList" @update:model-value="onRolesChange" placeholder="ROLE_ADMIN" />
      </div>
      <div class="mb-form__field">
        <span class="mb-form__field-label">{{ t('form.allowedUsers') }}</span>
        <UserAutocomplete :model-value="usersList" @update:model-value="onUsersChange" placeholder="john@example.com" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from '../../i18n'
import UserAutocomplete from '../inputs/UserAutocomplete.vue'
import RoleAutocomplete from '../inputs/RoleAutocomplete.vue'
import type { MenuItem } from '../../types/menu'

const { t } = useI18n()

defineProps<{
  form: MenuItem
  rolesList: string[]
  usersList: string[]
}>()

const emit = defineEmits<{
  (e: 'update:rolesList', value: string[]): void
  (e: 'update:usersList', value: string[]): void
}>()

function onRolesChange(value: string[]): void {
  emit('update:rolesList', value)
}

function onUsersChange(value: string[]): void {
  emit('update:usersList', value)
}
</script>
