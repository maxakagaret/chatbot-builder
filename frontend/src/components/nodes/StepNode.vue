<template>
  <div
    class="step"
    :class="{ selected, active: store.activeNodeId === id, 'has-error': errors.length, 'has-warning': !errors.length && warnings.length }"
  >
    <Handle v-if="type !== 'start'" type="target" :position="Position.Top" />

    <div class="step-header" :class="`bg-${style.color}`">
      <q-icon :name="style.icon" size="16px" />
      <span class="q-ml-xs">{{ style.label }}</span>
      <q-space />
      <q-icon v-if="errors.length" name="error" color="white" size="16px">
        <q-tooltip>{{ errors.map((i) => i.message).join('\n') }}</q-tooltip>
      </q-icon>
      <q-icon v-else-if="warnings.length" name="warning" color="amber-3" size="16px">
        <q-tooltip>{{ warnings.map((i) => i.message).join('\n') }}</q-tooltip>
      </q-icon>
    </div>

    <div class="step-body">
      <div v-if="data.text" class="text">{{ data.text }}</div>

      <template v-if="type === 'input'">
        <div class="meta">→ <code>vars.{{ data.variable }}</code> ({{ data.validation }})</div>
      </template>

      <template v-if="type === 'condition'">
        <div v-for="(rule, i) in data.rules ?? []" :key="i" class="meta">
          <span v-if="i > 0">{{ data.match === 'any' ? 'or ' : 'and ' }}</span>
          <code>{{ rule.field }}</code> {{ rule.operator }} <b>{{ rule.value }}</b>
        </div>
      </template>

      <template v-if="type === 'action'">
        <div class="meta"><b>{{ data.action }}</b></div>
        <div v-for="(value, key) in data.params ?? {}" :key="key" class="meta">{{ key }}: {{ value }}</div>
      </template>

      <!-- One output handle per option -->
      <div v-for="option in data.options ?? []" v-show="type === 'question'" :key="option.id" class="option">
        {{ option.label }}
        <Handle type="source" :id="`option:${option.id}`" :position="Position.Right" class="handle-option" />
      </div>
    </div>

    <template v-if="type === 'condition'">
      <Handle type="source" id="true" :position="Position.Bottom" class="handle-true" />
      <Handle type="source" id="false" :position="Position.Bottom" class="handle-false" />
      <div class="branch-labels"><span class="text-green-8">yes</span><span class="text-red-7">no</span></div>
    </template>
    <Handle v-else-if="['start', 'message', 'input', 'action'].includes(type)" type="source" id="next" :position="Position.Bottom" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Handle, Position } from '@vue-flow/core'
import type { NodeType, StepData } from '../../api/types'
import { nodeStyle } from '../../nodeStyle'
import { useEditorStore } from '../../stores/editor'

const props = defineProps<{ id: string; type: string; data: StepData; selected?: boolean }>()
const store = useEditorStore()

const style = computed(() => nodeStyle[props.type as NodeType])
const nodeIssues = computed(() => store.issuesByNode[props.id] ?? [])
const errors = computed(() => nodeIssues.value.filter((i) => i.severity === 'error'))
const warnings = computed(() => nodeIssues.value.filter((i) => i.severity === 'warning'))
</script>

<style scoped>
.step {
  width: 220px;
  background: white;
  border: 2px solid #d0d4dc;
  border-radius: 8px;
  font-size: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}
.step.selected { border-color: #5e35b1; }
.step.has-warning { border-color: #ffb300; }
.step.has-error { border-color: #e53935; }
.step.active { box-shadow: 0 0 0 4px rgba(76, 175, 80, 0.55); }
.step-header {
  display: flex;
  align-items: center;
  color: white;
  padding: 4px 8px;
  border-radius: 6px 6px 0 0;
  font-weight: 600;
}
.step-body { padding: 6px 8px; }
.text { white-space: pre-wrap; margin-bottom: 4px; }
.meta { color: #555; }
.option {
  position: relative;
  margin-top: 4px;
  padding: 3px 6px;
  background: #ede7f6;
  border-radius: 4px;
}
.handle-option { right: -14px; }
.handle-true { left: 30%; background: #43a047; }
.handle-false { left: 70%; background: #e53935; }
.branch-labels {
  display: flex;
  justify-content: space-around;
  font-size: 10px;
  padding-bottom: 2px;
}
</style>
