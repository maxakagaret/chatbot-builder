<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-btn flat round icon="arrow_back" :to="`/bots/${id}`" />
      <div class="text-h5">Conversations</div>
      <q-space />
      <q-chip v-for="(count, status) in stats" :key="status" :color="statusColor[status] ?? 'grey-5'" text-color="white" dense>
        {{ status }}: {{ count }}
      </q-chip>
    </div>

    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      v-model:pagination="pagination"
      :rows-number="total"
      @request="(r) => load(r.pagination.page)"
      @row-click="(_, row) => open(row.id)"
    >
      <template #body-cell-status="{ row }">
        <q-td><q-badge :color="statusColor[row.status] ?? 'grey-5'" :label="row.status" /></q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialog">
      <q-card v-if="transcript" style="width: 520px; max-width: 95vw">
        <q-card-section class="row items-center">
          <div class="text-h6">{{ transcript.contact.name }}</div>
          <q-space />
          <q-badge :color="statusColor[transcript.status] ?? 'grey-5'" :label="transcript.status" />
        </q-card-section>
        <q-card-section class="q-pt-none text-caption text-grey-7">
          {{ transcript.channel }} · version {{ transcript.version ?? 'draft' }} · tags {{ transcript.contact.tags?.join(', ') || '—' }}
          <div>vars: {{ JSON.stringify(transcript.variables ?? {}) }}</div>
        </q-card-section>
        <q-card-section class="chat">
          <div v-for="(m, i) in transcript.messages" :key="i" class="row" :class="m.direction === 'in' ? 'justify-end' : 'justify-start'">
            <div class="bubble" :class="m.direction">
              <div style="white-space: pre-wrap">{{ m.text }}</div>
              <div v-if="m.buttons" class="text-caption text-grey-7">[{{ m.buttons.map((b) => b.label).join(' | ') }}]</div>
              <div class="text-caption text-grey-6">{{ new Date(m.created_at).toLocaleTimeString() }} <span v-if="m.node_id">· {{ m.node_id }}</span></div>
            </div>
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn v-if="transcript.status === 'handoff'" color="deep-purple" label="Close hand-off" @click="closeHandoff" />
          <q-btn flat label="Close" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { QTableColumn } from 'quasar'
import { api } from '../api/client'
import type { SessionRow, Transcript } from '../api/types'

const props = defineProps<{ id: number }>()
const rows = ref<SessionRow[]>([])
const total = ref(0)
const stats = ref<Record<string, number>>({})
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })
const dialog = ref(false)
const transcript = ref<Transcript | null>(null)

const statusColor: Record<string, string> = {
  active: 'blue-6', completed: 'green-7', handoff: 'deep-orange-6', expired: 'grey-6', failed: 'red-7',
}

const columns: QTableColumn<SessionRow>[] = [
  { name: 'id', label: '#', field: 'id', align: 'left' },
  { name: 'contact', label: 'Contact', field: 'contact', align: 'left' },
  { name: 'channel', label: 'Channel', field: 'channel', align: 'left' },
  { name: 'version', label: 'Version', field: (r) => (r.version ? `v${r.version}` : 'draft') },
  { name: 'status', label: 'Status', field: 'status' },
  { name: 'step', label: 'Last step', field: (r) => r.current_node_id ?? '', align: 'left' },
  { name: 'messages', label: 'Messages', field: 'messages_count' },
  { name: 'last', label: 'Last activity', field: (r) => new Date(r.last_activity_at).toLocaleString(), align: 'left' },
]

async function load(page = 1) {
  const res = await api.sessions(props.id, page)
  rows.value = res.data
  total.value = res.meta.total
  stats.value = res.stats
  pagination.value = { ...pagination.value, page, rowsNumber: res.meta.total }
}

async function open(sessionId: number) {
  transcript.value = await api.transcript(sessionId)
  dialog.value = true
}

async function closeHandoff() {
  if (!transcript.value) return
  await api.closeSession(transcript.value.id)
  transcript.value.status = 'completed'
  await load(pagination.value.page)
}

onMounted(() => load())
</script>

<style scoped>
.chat { max-height: 60vh; overflow-y: auto; background: #f5f5f7; }
.bubble { max-width: 80%; margin: 4px 0; padding: 6px 10px; border-radius: 10px; font-size: 13px; }
.bubble.in { background: #d1c4e9; }
.bubble.out { background: white; border: 1px solid #e0e0e0; }
</style>
