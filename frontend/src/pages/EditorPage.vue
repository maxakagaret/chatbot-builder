<template>
  <q-page class="editor">
    <div class="toolbar row items-center q-px-md q-py-xs q-gutter-sm bg-grey-1">
      <q-btn flat dense round icon="arrow_back" to="/" />
      <div class="text-subtitle1 text-weight-medium">{{ store.bot?.name }}</div>
      <q-badge v-if="store.bot?.published_version" color="green-7" :label="`live: v${store.bot.published_version}`" />
      <q-badge v-else color="grey-6" label="not published" />
      <q-badge v-if="store.dirty" color="orange-8" label="unsaved" />
      <q-badge v-else-if="store.bot?.has_unpublished_changes" color="blue-grey-5" label="draft differs from live" />

      <q-space />

      <q-btn-dropdown
        flat
        dense
        no-caps
        :color="store.errorCount ? 'negative' : store.issues.length ? 'warning' : 'positive'"
        :icon="store.errorCount ? 'error' : store.issues.length ? 'warning' : 'check_circle'"
        :label="store.issues.length ? `${store.errorCount} errors · ${store.issues.length - store.errorCount} warnings` : 'No issues'"
      >
        <q-list dense style="min-width: 320px">
          <q-item v-for="(issue, i) in store.issues" :key="i" clickable v-close-popup @click="focus(issue.node_id)">
            <q-item-section avatar>
              <q-icon :name="issue.severity === 'error' ? 'error' : 'warning'" :color="issue.severity === 'error' ? 'negative' : 'warning'" />
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ issue.message }}</q-item-label>
              <q-item-label caption>{{ issue.node_id ?? 'scenario' }} · {{ issue.code }}</q-item-label>
            </q-item-section>
          </q-item>
          <q-item v-if="!store.issues.length"><q-item-section>The scenario looks good.</q-item-section></q-item>
        </q-list>
      </q-btn-dropdown>

      <q-btn flat dense no-caps icon="rule" label="Validate" @click="store.validate()" />
      <q-btn flat dense no-caps icon="save" label="Save" :loading="store.saving" :disable="!store.dirty" @click="save" />
      <q-btn unelevated dense no-caps color="deep-purple" icon="rocket_launch" label="Publish" :disable="store.errorCount > 0" @click="publish" />
      <q-btn-dropdown flat dense no-caps icon="history" label="Versions" @show="loadVersions">
        <q-list dense style="min-width: 260px">
          <q-item v-for="v in versions" :key="v.id">
            <q-item-section>
              <q-item-label>v{{ v.version }} <q-badge v-if="v.is_live" color="green-7" label="live" /></q-item-label>
              <q-item-label caption>{{ new Date(v.published_at).toLocaleString() }} · {{ v.active_sessions }} active conversations</q-item-label>
            </q-item-section>
          </q-item>
          <q-item v-if="!versions.length"><q-item-section>No versions yet.</q-item-section></q-item>
        </q-list>
      </q-btn-dropdown>
      <q-btn flat dense no-caps icon="forum" label="Conversations" :to="`/bots/${id}/sessions`" />
      <q-btn flat dense no-caps :icon="playground ? 'close' : 'play_circle'" :label="playground ? 'Close test' : 'Test'" @click="playground = !playground" />
    </div>

    <div class="workspace row no-wrap">
      <!-- Palette -->
      <div class="palette column q-pa-sm q-gutter-xs">
        <div class="text-caption text-grey-7">Drag or click</div>
        <div
          v-for="type in paletteTypes"
          :key="type"
          class="palette-item row items-center"
          draggable="true"
          @dragstart="(e) => e.dataTransfer?.setData('application/step', type)"
          @click="addAtCenter(type)"
        >
          <q-icon :name="nodeStyle[type].icon" :color="nodeStyle[type].color" size="18px" />
          <span class="q-ml-xs">{{ nodeStyle[type].label }}</span>
        </div>
      </div>

      <!-- Canvas -->
      <div ref="canvas" class="col canvas" @dragover.prevent @drop="onDrop">
        <VueFlow
          id="editor"
          v-model:nodes="flowNodes"
          :edges="flowEdges"
          :default-edge-options="{ type: 'smoothstep', markerEnd: MarkerType.ArrowClosed }"
          :delete-key-code="['Delete', 'Backspace']"
          fit-view-on-init
          @connect="onConnect"
          @edges-change="onEdgesChange"
          @nodes-change="onNodesChange"
          @node-drag-stop="store.touch()"
          @node-click="({ node }) => (store.selectedId = node.id)"
          @pane-click="store.selectedId = null"
        >
          <template v-for="type in allTypes" :key="type" #[`node-${type}`]="nodeProps">
            <StepNode v-bind="nodeProps" />
          </template>
          <Background pattern-color="#cfd8dc" :gap="18" />
          <Controls />
          <MiniMap pannable zoomable />
        </VueFlow>
      </div>

      <!-- Side panel -->
      <div class="side" :class="{ wide: playground }">
        <Playground v-if="playground" />
        <q-scroll-area v-else class="full-height"><NodeInspector /></q-scroll-area>
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { MarkerType, VueFlow, useVueFlow, type Connection, type EdgeChange, type Node, type NodeChange } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { MiniMap } from '@vue-flow/minimap'
import { api, errorText } from '../api/client'
import type { GraphNode, NodeType, Version } from '../api/types'
import NodeInspector from '../components/NodeInspector.vue'
import Playground from '../components/Playground.vue'
import StepNode from '../components/nodes/StepNode.vue'
import { nodeStyle, paletteTypes } from '../nodeStyle'
import { useEditorStore } from '../stores/editor'
import { useMetaStore } from '../stores/meta'

const props = defineProps<{ id: number }>()
const store = useEditorStore()
const $q = useQuasar()
const { screenToFlowCoordinate, fitView } = useVueFlow('editor')

const allTypes: NodeType[] = ['start', ...paletteTypes]
const canvas = ref<HTMLElement | null>(null)
const playground = ref(false)
const versions = ref<Version[]>([])

// Vue Flow owns positions/selection on the node objects; the store keeps the same objects.
const flowNodes = computed({
  get: () => store.nodes as unknown as Node[],
  set: (value) => { store.nodes = value as unknown as GraphNode[] },
})

/** Edge labels are derived from the source step: option label, yes/no. */
const flowEdges = computed(() => store.edges.map((edge) => {
  const source = store.nodes.find((n) => n.id === edge.source)
  let label: string | undefined
  if (edge.sourceHandle.startsWith('option:')) {
    label = source?.data.options?.find((o) => `option:${o.id}` === edge.sourceHandle)?.label
  } else if (edge.sourceHandle === 'true' || edge.sourceHandle === 'false') {
    label = edge.sourceHandle === 'true' ? 'yes' : 'no'
  }
  return { ...edge, label, animated: store.activeNodeId === edge.source }
}))

function onConnect(connection: Connection) {
  store.connect(connection.source, connection.sourceHandle ?? 'next', connection.target)
}

function onEdgesChange(changes: EdgeChange[]) {
  const removed = changes.filter((c) => c.type === 'remove').map((c) => (c as { id: string }).id)
  if (removed.length) store.removeEdges(removed)
}

function onNodesChange(changes: NodeChange[]) {
  for (const change of changes) {
    if (change.type === 'remove') store.removeNode(change.id)
  }
}

function onDrop(event: DragEvent) {
  const type = event.dataTransfer?.getData('application/step') as NodeType | undefined
  if (!type) return
  store.addNode(type, screenToFlowCoordinate({ x: event.clientX, y: event.clientY }))
}

function addAtCenter(type: NodeType) {
  const rect = canvas.value!.getBoundingClientRect()
  store.addNode(type, screenToFlowCoordinate({ x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 }))
}

function focus(nodeId: string | null) {
  if (!nodeId) return
  store.selectedId = nodeId
  void fitView({ nodes: [nodeId], duration: 400, maxZoom: 1.2 })
}

async function save() {
  try {
    await store.save()
    $q.notify({ type: 'positive', message: 'Draft saved', timeout: 1200 })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}

async function publish() {
  try {
    const version = await store.publish()
    $q.notify({ type: 'positive', message: `Published v${version}. Running conversations stay on their version.` })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorText(e) })
  }
}

async function loadVersions() {
  versions.value = await api.versions(props.id)
}

function onKey(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault()
    if (store.dirty) void save()
  }
}

onMounted(async () => {
  await Promise.all([useMetaStore().load(), store.load(props.id)])
  window.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<style scoped>
.editor { display: flex; flex-direction: column; height: calc(100vh - 50px); }
.toolbar { border-bottom: 1px solid #e0e0e0; }
.workspace { flex: 1; min-height: 0; }
.palette { width: 130px; border-right: 1px solid #e0e0e0; background: #fafafa; }
.palette-item {
  padding: 6px 8px;
  background: white;
  border: 1px solid #e0e0e0;
  border-radius: 6px;
  cursor: grab;
  font-size: 13px;
}
.canvas { position: relative; min-width: 0; }
.side { width: 340px; border-left: 1px solid #e0e0e0; background: white; }
.side.wide { width: 420px; }
</style>
