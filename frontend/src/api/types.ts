export type NodeType = 'start' | 'message' | 'question' | 'input' | 'condition' | 'action' | 'end'
export type ChannelType = 'web' | 'telegram' | 'whatsapp' | 'sms'

export interface Option {
  id: string
  label: string
  synonyms?: string[]
}

export interface Rule {
  field: string
  operator: string
  value?: string
}

/** Union of everything a step can carry; each type uses its own subset. */
export interface StepData {
  text?: string
  options?: Option[]
  retry_text?: string
  max_retries?: number
  variable?: string
  validation?: string
  match?: 'all' | 'any'
  rules?: Rule[]
  action?: string
  params?: Record<string, string>
}

export interface GraphNode {
  id: string
  type: NodeType
  position: { x: number; y: number }
  data: StepData
}

export interface GraphEdge {
  id: string
  source: string
  sourceHandle: string
  target: string
}

export interface Graph {
  nodes: GraphNode[]
  edges: GraphEdge[]
}

export interface Issue {
  severity: 'error' | 'warning'
  code: string
  message: string
  node_id: string | null
}

export interface BotSummary {
  id: number
  name: string
  description: string | null
  published_version: number | null
  published_at: string | null
  channels: ChannelType[]
  sessions_count: number
  updated_at: string | null
}

export interface BotDetail extends BotSummary {
  graph: Graph
  issues: Issue[]
  has_unpublished_changes: boolean
}

export interface Version {
  id: number
  version: number
  published_at: string
  is_live: boolean
  active_sessions: number
}

export interface Meta {
  node_types: { type: NodeType; waiting: boolean; handles: string[] }[]
  actions: { key: string; label: string; params: Record<string, { label: string; required: boolean }> }[]
  condition_fields: { key: string; label: string }[]
  operators: { key: string; needs_value: boolean }[]
  input_validations: string[]
  channels: { key: ChannelType; label: string }[]
}

export interface SimReply {
  text: string
  buttons: { id: string; label: string }[]
  node_id: string | null
  wire: Record<string, unknown>
}

export interface SimResult {
  replies: SimReply[]
  session: { id: number; status: string; current_node_id: string | null; variables: Record<string, string> | null }
}

export interface SessionRow {
  id: number
  contact: string
  channel: ChannelType
  version: number | null
  status: string
  current_node_id: string | null
  messages_count: number
  started_at: string
  last_activity_at: string
}

export interface Transcript {
  id: number
  contact: { id: number; name: string; tags: string[] | null }
  channel: ChannelType
  status: string
  version: number | null
  variables: Record<string, string> | null
  messages: { direction: 'in' | 'out'; text: string; buttons: { id: string; label: string }[] | null; node_id: string | null; created_at: string }[]
}
