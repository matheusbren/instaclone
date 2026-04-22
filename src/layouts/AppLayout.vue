<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/layout/AppIcon.vue'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import AppShell from '@/components/layout/AppShell.vue'
import { useAuth } from '@/composables/useAuth'

const route = useRoute()
const router = useRouter()
const { currentUser, logout } = useAuth()

const navItems = [
  {
    name: 'feed',
    label: 'Home',
    icon: 'home',
  },
  {
    name: 'criar',
    label: 'Criar',
    icon: 'create',
  },
  {
    name: 'perfil',
    label: 'Perfil',
    icon: 'profile',
  },
]

const activeNavName = computed(() => route.meta.navItem ?? route.name)

const accountLabel = computed(() => {
  if (!currentUser.value) {
    return 'Conta local'
  }

  return currentUser.value.username
    ? `@${currentUser.value.username}`
    : currentUser.value.name
})

const currentSection = computed(() => route.meta.sectionTitle ?? 'InstaClone')
const currentDescription = computed(
  () =>
    route.meta.sectionDescription ??
    'Acompanhe seu conteúdo, descubra novidades e navegue pelo app com fluidez.',
)
const currentFooterLabel = computed(
  () =>
    route.meta.footerLabel ??
    'Layout principal pronto para receber o conteúdo dinâmico de cada módulo.',
)

async function handleLogout() {
  await logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <RouterView v-slot="{ Component }">
    <AppShell>
      <template #sidebar>
        <div class="app-sidebar">
          <div class="app-sidebar__brand">
            <span class="app-sidebar__eyebrow">Painel principal</span>
            <h1 class="app-sidebar__title">InstaClone</h1>
            <p class="app-sidebar__description">
              Navegue entre as áreas principais com um layout que se adapta ao dispositivo.
            </p>
          </div>

          <nav class="app-nav" aria-label="Navegação principal">
            <RouterLink
              v-for="item in navItems"
              :key="item.name"
              :to="{ name: item.name }"
              class="app-nav__link"
              :class="{ 'is-active': activeNavName === item.name }"
            >
              <AppIcon :name="item.icon" />
              <span>{{ item.label }}</span>
            </RouterLink>
          </nav>
        </div>
      </template>

      <template #header>
        <section class="app-topbar">
          <div class="app-topbar__copy">
            <span class="app-topbar__eyebrow">{{ accountLabel }}</span>
            <h2 class="app-topbar__title">{{ currentSection }}</h2>
            <p class="app-topbar__description">{{ currentDescription }}</p>
          </div>

          <div class="app-account-card">
            <ProfileAvatar
              :name="currentUser?.name"
              :username="currentUser?.username"
              :avatar-url="currentUser?.avatarUrl"
              :colors="currentUser?.colors"
              size="sm"
            />
            <div class="app-account-card__meta">
              <strong>{{ accountLabel }}</strong>
              <span>Sessão autenticada</span>
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="button" @click="handleLogout">
              Sair
            </button>
          </div>
        </section>
      </template>

      <component :is="Component" />

      <template #footer>
        <section class="app-footer-card">
          <p class="app-footer-card__title">{{ currentFooterLabel }}</p>
          <p class="app-footer-card__description">
            Header, conteúdo principal e rodapé seguem o mesmo shell para facilitar a evolução
            das próximas telas.
          </p>
        </section>
      </template>
    </AppShell>
  </RouterView>
</template>
