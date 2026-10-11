<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="text-h5">Chatbots</div>
      <q-space />
      <q-btn color="deep-purple" icon="add" label="New bot" @click="create" />
    </div>

    <div class="row q-col-gutter-md">
      <div v-for="bot in bots" :key="bot.id" class="col-12 col-md-6 col-lg-4">
        <q-card flat bordered>
          <q-card-section>
            <div class="row items-center">
              <div class="text-h6">{{ bot.name }}</div>
              <q-space />
              <q-badge v-if="bot.published_version" color="green-7" :label="`v${bot.published_version}`" />
              <q-badge v-else color="grey-6" label="draft" />
            </div>
            <div class="text-grey-7">{{ bot.description }}</div>
            <div class="text-caption text-grey-6 q-mt-xs">{{ bot.sessions_count }} conversations</div>
          </q-card-section>

          <q-card-section class="q-pt-none">
            <div class="text-caption text-grey-7 q-mb-xs">Answers in channels (one bot per channel):</div>
            <q-option-group
              :model-value="bot.channels"
              :options="channelOptions"
              type="checkbox"
              inline
              dense
              @update:model-value="(v: ChannelType[]) => setChannels(bot, v)"
            />
          </q-card-section>

          <q-card-actions>
            <q-btn flat color="deep-purple" icon="account_tree" label="Edit scenario" :to="`/bots/${bot.id}`" />
            <q-btn flat icon="forum" label="Conversations" :to="`/bots/${bot.id}/sessions`" />
            <q-space />
            <q-btn flat round dense icon="delete" color="grey-7" @click="remove(bot)" />
          </q-card-actions>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { api, errorText } from '../api/client'
import type { BotSummary, ChannelType } from '../api/types'

const $q = useQuasar()
const router = useRouter()
const bots = ref<BotSummary[]>([])
const channelOptions = [
  { label: 'Web', value: 'web' },
  { label: 'Telegram', value: 'telegram' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'SMS', value: 'sms' },
]

const load = async () => { bots.value = await api.bots() }
onMounted(load)

async function setChannels(bot: BotSummary, channels: ChannelType[]) {
  try {
    await api.setChannels(bot.id, channels)
    await load() // a channel may have moved away from another bot
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}

function create() {
  $q.dialog({ title: 'New bot', prompt: { model: '', type: 'text', label: 'Name' }, cancel: true }).onOk(async (name: string) => {
    if (!name) return
    const bot = await api.createBot(name)
    await router.push(`/bots/${bot.id}`)
  })
}

function remove(bot: BotSummary) {
  $q.dialog({ title: `Delete "${bot.name}"?`, message: 'Its versions and conversation history are deleted too.', cancel: true })
    .onOk(async () => {
      await api.deleteBot(bot.id)
      await load()
    })
}
</script>
