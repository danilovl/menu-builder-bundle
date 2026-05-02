/// <reference path="./shims-vue.d.ts" />
import { createApp, type ComponentPublicInstance } from 'vue'
import App from './App.vue'
import './styles/menu-admin.css'
import { fetchConfig } from './api/menuApi'

interface MountOptions {
  apiPrefix: string
  defaultMenu?: string | null
}

export function mountMenuAdmin(selector: string, options: MountOptions): ComponentPublicInstance | null {
  const el = document.querySelector(selector)
  if (!el) {
    console.warn(`[menu-builder] Mount target ${selector} not found`)

    return null
  }

  const app = createApp(App, {
    apiBase: options.apiPrefix,
    defaultMenu: options.defaultMenu ?? null,
  })

  return app.mount(el)
}

document.addEventListener('DOMContentLoaded', async () => {
  const auto = document.querySelector<HTMLElement>('[data-menu-admin]')
  if (!auto) {
    return
  }

  const raw = auto.dataset.configUrl ?? '/api/menu/config'
  const configUrl = raw.startsWith('/') || raw.startsWith('http') ? raw : `/${raw}`
  const defaultMenu = auto.dataset.defaultMenu ?? null
  const selector = `#${auto.id || 'menu-admin'}`

  try {
    const config = await fetchConfig(configUrl)
    mountMenuAdmin(selector, { apiPrefix: config.apiPrefix, defaultMenu })
  } catch {
    console.warn('[menu-builder] Failed to load config, using default prefix')
    mountMenuAdmin(selector, { apiPrefix: '/api/menu', defaultMenu })
  }
})
