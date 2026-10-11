import type { NodeType } from './api/types'

export const nodeStyle: Record<NodeType, { icon: string; color: string; label: string }> = {
  start: { icon: 'play_arrow', color: 'green-7', label: 'Start' },
  message: { icon: 'chat_bubble', color: 'blue-7', label: 'Message' },
  question: { icon: 'help', color: 'deep-purple-6', label: 'Question' },
  input: { icon: 'edit_note', color: 'indigo-6', label: 'Input' },
  condition: { icon: 'call_split', color: 'orange-8', label: 'Condition' },
  action: { icon: 'bolt', color: 'teal-7', label: 'Action' },
  end: { icon: 'stop_circle', color: 'grey-8', label: 'End' },
}

export const paletteTypes: NodeType[] = ['message', 'question', 'input', 'condition', 'action', 'end']
