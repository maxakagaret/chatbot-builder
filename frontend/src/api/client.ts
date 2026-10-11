import axios from 'axios'
import type {
  BotDetail, BotSummary, ChannelType, Graph, Issue, Meta, SessionRow, SimResult, Transcript, Version,
} from './types'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  headers: { Accept: 'application/json' },
})

/** Stateless API layer, one function per endpoint. */
export const api = {
  meta: () => http.get<{ data: Meta }>('/meta').then((r) => r.data.data),

  bots: () => http.get<{ data: BotSummary[] }>('/chatbots').then((r) => r.data.data),
  bot: (id: number) => http.get<{ data: BotDetail }>(`/chatbots/${id}`).then((r) => r.data.data),
  createBot: (name: string, description?: string) =>
    http.post<{ data: BotDetail }>('/chatbots', { name, description }).then((r) => r.data.data),
  deleteBot: (id: number) => http.delete(`/chatbots/${id}`),

  saveGraph: (id: number, graph: Graph) =>
    http.put<{ data: { saved_at: string; issues: Issue[] } }>(`/chatbots/${id}/graph`, { graph }).then((r) => r.data.data),
  validateGraph: (graph: Graph) =>
    http.post<{ data: { issues: Issue[] } }>('/chatbots/validate', { graph }).then((r) => r.data.data.issues),
  publish: (id: number) =>
    http.post<{ data: { version: number; published_at: string } }>(`/chatbots/${id}/publish`).then((r) => r.data.data),
  versions: (id: number) => http.get<{ data: Version[] }>(`/chatbots/${id}/versions`).then((r) => r.data.data),
  setChannels: (id: number, channels: ChannelType[]) =>
    http.put<{ data: BotSummary }>(`/chatbots/${id}/channels`, { channels }).then((r) => r.data.data),

  simulate: (id: number, payload: { channel: ChannelType; tester_id: string; text: string; button_id?: string }) =>
    http.post<{ data: SimResult }>(`/chatbots/${id}/simulate`, payload).then((r) => r.data.data),
  resetSimulation: (id: number, testerId: string) => http.post(`/chatbots/${id}/simulate/reset`, { tester_id: testerId }),

  sessions: (id: number, page = 1) =>
    http.get<{ data: SessionRow[]; meta: { total: number }; stats: Record<string, number> }>(`/chatbots/${id}/sessions`, { params: { page } })
      .then((r) => r.data),
  transcript: (sessionId: number) => http.get<{ data: Transcript }>(`/sessions/${sessionId}`).then((r) => r.data.data),
  closeSession: (sessionId: number) => http.post(`/sessions/${sessionId}/close`),
}

export function errorText(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    return (data?.errors && Object.values(data.errors)[0]?.[0]) || data?.message || error.message
  }
  return String(error)
}
