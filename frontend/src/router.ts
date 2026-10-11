import { createRouter, createWebHistory } from 'vue-router'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'bots', component: () => import('./pages/BotsPage.vue') },
    { path: '/bots/:id', name: 'editor', component: () => import('./pages/EditorPage.vue'), props: (r) => ({ id: Number(r.params.id) }) },
    { path: '/bots/:id/sessions', name: 'sessions', component: () => import('./pages/SessionsPage.vue'), props: (r) => ({ id: Number(r.params.id) }) },
  ],
})
