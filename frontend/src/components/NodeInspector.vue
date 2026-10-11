<template>
  <div v-if="node && meta" class="q-pa-md q-gutter-sm">
    <div class="row items-center">
      <q-icon :name="nodeStyle[node.type].icon" :color="nodeStyle[node.type].color" size="sm" />
      <div class="text-subtitle1 q-ml-sm">{{ nodeStyle[node.type].label }}</div>
      <q-space />
      <q-btn v-if="node.type !== 'start'" flat dense round icon="delete" color="negative" @click="store.removeNode(node.id)">
        <q-tooltip>Delete step</q-tooltip>
      </q-btn>
    </div>
    <div class="text-caption text-grey-7">id: <code>{{ node.id }}</code></div>

    <q-banner v-for="(issue, i) in issues" :key="i" dense :class="issue.severity === 'error' ? 'bg-red-1 text-red-9' : 'bg-amber-1 text-amber-10'">
      {{ issue.message }}
    </q-banner>

    <q-input
      v-if="hasText"
      v-model="node.data.text"
      type="textarea"
      autogrow
      outlined
      dense
      :label="node.type === 'end' ? 'Closing text (optional)' : 'Text'"
      hint="Placeholders: {{contact.first_name}}, {{contact.name}}, {{vars.email}}"
      @update:model-value="store.touch()"
    />

    <!-- Question -->
    <template v-if="node.type === 'question'">
      <div class="text-weight-medium q-mt-md">Options</div>
      <div v-for="(option, i) in node.data.options" :key="option.id" class="row q-col-gutter-xs items-center">
        <q-input v-model="option.label" dense outlined class="col" :label="`#${i + 1} label`" @update:model-value="onOptionsChanged" />
        <q-select
          v-model="option.synonyms"
          dense
          outlined
          use-input
          use-chips
          multiple
          hide-dropdown-icon
          new-value-mode="add-unique"
          class="col"
          label="synonyms"
          @update:model-value="store.touch()"
        />
        <q-btn flat dense round icon="close" :disable="(node.data.options?.length ?? 0) <= 1" @click="removeOption(i)" />
      </div>
      <q-btn flat dense icon="add" label="Add option" :disable="(node.data.options?.length ?? 0) >= 10" @click="addOption" />
      <q-input v-model="node.data.retry_text" dense outlined label="Re-ask text when the answer is not recognised" @update:model-value="store.touch()" />
    </template>

    <!-- Input -->
    <template v-if="node.type === 'input'">
      <q-input v-model="node.data.variable" dense outlined label="Save answer to variable" prefix="vars." @update:model-value="store.touch()" />
      <q-select v-model="node.data.validation" :options="meta.input_validations" dense outlined label="Validation" @update:model-value="store.touch()" />
      <q-input v-model="node.data.retry_text" dense outlined label="Text when the answer is invalid" @update:model-value="store.touch()" />
    </template>

    <!-- Condition -->
    <template v-if="node.type === 'condition'">
      <q-btn-toggle
        v-model="node.data.match"
        dense
        unelevated
        toggle-color="orange-8"
        :options="[{ label: 'all rules (AND)', value: 'all' }, { label: 'any rule (OR)', value: 'any' }]"
        @update:model-value="store.touch()"
      />
      <div v-for="(rule, i) in node.data.rules" :key="i" class="rule q-pa-sm q-gutter-xs">
        <q-select
          v-model="rule.field"
          :options="fieldOptions"
          dense
          outlined
          use-input
          new-value-mode="add-unique"
          emit-value
          map-options
          label="Field (or vars.your_variable)"
          @update:model-value="store.touch()"
        />
        <div class="row q-col-gutter-xs">
          <q-select v-model="rule.operator" :options="operatorKeys" dense outlined class="col-5" label="Operator" @update:model-value="store.touch()" />
          <q-input v-if="needsValue(rule.operator)" v-model="rule.value" dense outlined class="col" label="Value" @update:model-value="store.touch()" />
          <q-btn flat dense round icon="close" @click="node.data.rules?.splice(i, 1); store.touch()" />
        </div>
      </div>
      <q-btn flat dense icon="add" label="Add rule" @click="node.data.rules?.push({ field: 'contact.visits', operator: 'gte', value: '1' }); store.touch()" />
    </template>

    <!-- Action -->
    <template v-if="node.type === 'action'">
      <q-select
        v-model="node.data.action"
        :options="meta.actions.map((a) => ({ label: a.label, value: a.key }))"
        emit-value
        map-options
        dense
        outlined
        label="Action"
        @update:model-value="node.data.params = {}; store.touch()"
      />
      <q-input
        v-for="(schema, key) in actionSchema"
        :key="key"
        :model-value="node.data.params?.[key] ?? ''"
        dense
        outlined
        :label="schema.label + (schema.required ? ' *' : '')"
        @update:model-value="(v) => { node!.data.params = { ...node!.data.params, [key]: String(v ?? '') }; store.touch() }"
      />
    </template>
  </div>
  <div v-else class="q-pa-md text-grey-7">
    Select a step to edit it. Drag steps from the palette, connect outputs (●) to the next step.
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { nodeStyle } from '../nodeStyle'
import { useEditorStore } from '../stores/editor'
import { useMetaStore } from '../stores/meta'

const store = useEditorStore()
const metaStore = useMetaStore()
const meta = computed(() => metaStore.meta)
const node = computed(() => store.selected)
const issues = computed(() => (node.value ? store.issuesByNode[node.value.id] ?? [] : []))
const hasText = computed(() => node.value && ['message', 'question', 'input', 'end'].includes(node.value.type))

const fieldOptions = computed(() => meta.value?.condition_fields.map((f) => ({ label: `${f.label} (${f.key})`, value: f.key })) ?? [])
const operatorKeys = computed(() => meta.value?.operators.map((o) => o.key) ?? [])
const needsValue = (op: string) => meta.value?.operators.find((o) => o.key === op)?.needs_value ?? true
const actionSchema = computed(() => meta.value?.actions.find((a) => a.key === node.value?.data.action)?.params ?? {})

function slug(label: string, taken: string[]): string {
  const base = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'option'
  let id = base
  for (let n = 2; taken.includes(id); n++) id = `${base}_${n}`
  return id
}

function addOption() {
  const options = node.value!.data.options ?? []
  options.push({ id: slug(`option ${options.length + 1}`, options.map((o) => o.id)), label: `Option ${options.length + 1}` })
  node.value!.data.options = options
  onOptionsChanged()
}

function removeOption(index: number) {
  node.value!.data.options?.splice(index, 1)
  onOptionsChanged()
}

/** Option ids are stable once connected; removed options lose their edges. */
function onOptionsChanged() {
  const n = node.value!
  store.pruneEdges(n.id, (n.data.options ?? []).map((o) => `option:${o.id}`))
  store.touch()
}
</script>

<style scoped>
.rule {
  border: 1px solid #eee;
  border-radius: 6px;
}
</style>
