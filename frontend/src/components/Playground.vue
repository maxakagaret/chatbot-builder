<template>
  <div class="column full-height">
    <div class="row items-center q-pa-sm q-gutter-sm">
      <q-select v-model="channel" :options="channels" emit-value map-options dense outlined label="Channel" style="min-width: 140px" />
      <q-toggle v-model="showWire" dense label="wire format" />
      <q-space />
      <q-btn flat dense icon="restart_alt" label="Restart" @click="restart" />
    </div>
    <div class="text-caption text-grey-7 q-px-sm">Runs the saved draft. The current step is highlighted on the canvas.</div>

    <div ref="scroller" class="col chat q-pa-sm">
      <div v-for="(m, i) in log" :key="i" class="row" :class="m.from === 'user' ? 'justify-end' : 'justify-start'">
        <div class="bubble" :class="m.from">
          <div style="white-space: pre-wrap">{{ m.text }}</div>
          <div v-if="m.buttons?.length && channel !== 'whatsapp' && channel !== 'sms'" class="q-mt-xs q-gutter-xs">
            <q-btn v-for="b in m.buttons" :key="b.id" dense no-caps outline size="sm" color="deep-purple" :label="b.label" @click="send(b.label, b.id)" />
          </div>
          <pre v-if="showWire && m.wire" class="wire">{{ JSON.stringify(m.wire, null, 2) }}</pre>
        </div>
      </div>
      <div v-if="state" class="text-caption text-grey-7 q-mt-sm">
        session #{{ state.id }} · {{ state.status }} · vars: {{ JSON.stringify(state.variables ?? {}) }}
      </div>
    </div>

    <q-form class="row q-pa-sm q-gutter-sm" @submit.prevent="send(input)">
      <q-input v-model="input" dense outlined class="col" placeholder="Type a message…" autofocus />
      <q-btn type="submit" color="deep-purple" icon="send" :loading="busy" />
    </q-form>
  </div>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { useQuasar } from 'quasar'
import { api, errorText } from '../api/client'
import type { ChannelType, SimResult } from '../api/types'
import { useEditorStore } from '../stores/editor'

const store = useEditorStore()
const $q = useQuasar()

const channels = [
  { label: 'Web widget', value: 'web' },
  { label: 'Telegram', value: 'telegram' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'SMS', value: 'sms' },
]
const channel = ref<ChannelType>('web')
const showWire = ref(false)
const input = ref('')
const busy = ref(false)
const testerId = `editor-${Math.random().toString(36).slice(2, 8)}`
const scroller = ref<HTMLElement | null>(null)
const state = ref<SimResult['session'] | null>(null)
const log = ref<{ from: 'user' | 'bot'; text: string; buttons?: { id: string; label: string }[]; wire?: unknown }[]>([])

async function send(text: string, buttonId?: string) {
  if (!store.bot || (!text.trim() && !buttonId)) return
  busy.value = true
  try {
    if (store.dirty) await store.save() // the simulator runs the stored draft
    log.value.push({ from: 'user', text })
    input.value = ''
    const result = await api.simulate(store.bot.id, { channel: channel.value, tester_id: testerId, text, button_id: buttonId })
    for (const reply of result.replies) {
      log.value.push({ from: 'bot', text: reply.text, buttons: reply.buttons, wire: reply.wire })
    }
    state.value = result.session
    store.activeNodeId = result.session.status === 'active' ? result.session.current_node_id : null
    await nextTick()
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  } finally {
    busy.value = false
  }
}

async function restart() {
  if (!store.bot) return
  await api.resetSimulation(store.bot.id, testerId)
  log.value = []
  state.value = null
  store.activeNodeId = null
}
</script>

<style scoped>
.chat {
  overflow-y: auto;
  background: #f5f5f7;
}
.bubble {
  max-width: 85%;
  margin: 4px 0;
  padding: 6px 10px;
  border-radius: 10px;
  font-size: 13px;
}
.bubble.user { background: #d1c4e9; }
.bubble.bot { background: white; border: 1px solid #e0e0e0; }
.wire {
  font-size: 10px;
  background: #263238;
  color: #c3e88d;
  padding: 6px;
  border-radius: 4px;
  overflow-x: auto;
  margin: 6px 0 0;
}
</style>
