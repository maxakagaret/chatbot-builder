import { defineStore } from 'pinia'
import { api } from '../api/client'
import type { Meta } from '../api/types'

/** Palette metadata comes from the backend registries — the editor hardcodes no action or field list. */
export const useMetaStore = defineStore('meta', {
  state: () => ({ meta: null as Meta | null }),
  actions: {
    async load() {
      this.meta ??= await api.meta()
      return this.meta
    },
  },
})
