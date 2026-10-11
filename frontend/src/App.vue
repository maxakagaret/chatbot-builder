<template>
  <q-layout view="hHh lpR fFf">
    <q-header elevated class="bg-deep-purple-8 text-white">
      <q-toolbar>
        <q-icon name="smart_toy" size="sm" class="q-mr-sm" />
        <q-toolbar-title>
          <router-link to="/" class="text-white" style="text-decoration: none">Chatbot Builder</router-link>
        </q-toolbar-title>
        <q-badge color="amber-8" text-color="black" label="fake channels" />
      </q-toolbar>
    </q-header>
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>
