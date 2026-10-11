import { defineStore } from 'pinia'
import { api } from '../api/client'
import type { BotDetail, Graph, GraphEdge, GraphNode, Issue, NodeType, StepData } from '../api/types'

/** Default content for a freshly dropped step. */
function defaults(type: NodeType): StepData {
  switch (type) {
    case 'message': return { text: 'New message' }
    case 'question': return { text: 'Your question?', options: [{ id: 'yes', label: 'Yes' }, { id: 'no', label: 'No' }] }
    case 'input': return { text: 'Please type your answer', variable: 'answer', validation: 'text' }
    case 'condition': return { match: 'all', rules: [{ field: 'contact.visits', operator: 'gte', value: '1' }] }
    case 'action': return { action: 'add_tag', params: { tag: 'lead' } }
    case 'end': return { text: '' }
    default: return {}
  }
}

const uid = (prefix: string) => `${prefix}_${Math.random().toString(36).slice(2, 7)}`

export const useEditorStore = defineStore('editor', {
  state: () => ({
    bot: null as BotDetail | null,
    nodes: [] as GraphNode[],
    edges: [] as GraphEdge[],
    issues: [] as Issue[],
    selectedId: null as string | null,
    activeNodeId: null as string | null, // highlighted by the playground
    dirty: false,
    saving: false,
  }),

  getters: {
    selected: (s) => s.nodes.find((n) => n.id === s.selectedId) ?? null,
    issuesByNode: (s) => {
      const map: Record<string, Issue[]> = {}
      for (const issue of s.issues) {
        if (issue.node_id) (map[issue.node_id] ??= []).push(issue)
      }
      return map
    },
    errorCount: (s) => s.issues.filter((i) => i.severity === 'error').length,
    graph(): Graph {
      return {
        nodes: this.nodes.map(({ id, type, position, data }) => ({
          id, type, position: { x: Math.round(position.x), y: Math.round(position.y) }, data,
        })),
        edges: this.edges.map(({ id, source, sourceHandle, target }) => ({ id, source, sourceHandle, target })),
      }
    },
  },

  actions: {
    async load(id: number) {
      const bot = await api.bot(id)
      this.bot = bot
      this.nodes = bot.graph.nodes.map((n) => ({ ...n, data: { ...n.data } }))
      this.edges = bot.graph.edges.map((e) => ({ ...e }))
      this.issues = bot.issues
      this.dirty = false
      this.selectedId = null
      this.activeNodeId = null
    },

    touch() {
      this.dirty = true
    },

    addNode(type: NodeType, position: { x: number; y: number }) {
      const node: GraphNode = { id: uid(type), type, position, data: defaults(type) }
      this.nodes.push(node)
      this.selectedId = node.id
      this.touch()
    },

    removeNode(id: string) {
      this.nodes = this.nodes.filter((n) => n.id !== id)
      this.edges = this.edges.filter((e) => e.source !== id && e.target !== id)
      if (this.selectedId === id) this.selectedId = null
      this.touch()
    },

    /** One edge per output handle: reconnecting an output replaces its old edge. */
    connect(source: string, sourceHandle: string, target: string) {
      if (source === target) return
      this.edges = this.edges.filter((e) => !(e.source === source && e.sourceHandle === sourceHandle))
      this.edges.push({ id: `${source}:${sourceHandle}`, source, sourceHandle, target })
      this.touch()
    },

    removeEdges(ids: string[]) {
      this.edges = this.edges.filter((e) => !ids.includes(e.id))
      this.touch()
    },

    /** Drops edges whose handle no longer exists (e.g. a deleted question option). */
    pruneEdges(nodeId: string, validHandles: string[]) {
      this.edges = this.edges.filter((e) => e.source !== nodeId || validHandles.includes(e.sourceHandle))
    },

    async save() {
      if (!this.bot) return
      this.saving = true
      try {
        const result = await api.saveGraph(this.bot.id, this.graph)
        this.issues = result.issues
        this.dirty = false
        this.bot.has_unpublished_changes = true
      } finally {
        this.saving = false
      }
    },

    async validate() {
      this.issues = await api.validateGraph(this.graph)
    },

    async publish() {
      if (!this.bot) return null
      if (this.dirty) await this.save()
      const result = await api.publish(this.bot.id)
      this.bot.published_version = result.version
      this.bot.has_unpublished_changes = false
      return result.version
    },
  },
})
