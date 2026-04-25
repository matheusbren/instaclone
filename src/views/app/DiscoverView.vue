<script setup>
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import AccountCard from '@/components/profile/AccountCard.vue'
import { useAuthStore } from '@/stores/auth'
import { useFollowsStore } from '@/stores/follows'
import * as usersService from '@/services/users.service'
import { extractErrorMessage } from '@/services/api'
import { normalizeUser } from '@/stores/profileUtils'

const { currentUser } = storeToRefs(useAuthStore())
const followsStore = useFollowsStore()

const people = ref([])
const totalPeople = ref(0)
const currentPage = ref(1)
const hasMore = ref(false)
const isLoading = ref(false)
const loadError = ref('')
const feedbackMessage = ref('')

async function loadPeople({ reset = true } = {}) {
  isLoading.value = true
  loadError.value = ''

  try {
    const page = reset ? 1 : currentPage.value + 1
    const response = await usersService.suggestions(20, page)
    const users = (response.data ?? []).map(normalizeUser).filter(Boolean)

    people.value = reset ? users : [...people.value, ...users]
    totalPeople.value = Number(response.total ?? people.value.length)
    currentPage.value = Number(response.current_page ?? page)
    hasMore.value = Boolean(response.next_page_url)
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar sugestões agora.')
  } finally {
    isLoading.value = false
  }
}

function handleFollowChanged({ account, wasFollowing }) {
  feedbackMessage.value = wasFollowing
    ? `Você deixou de seguir @${account.username}.`
    : `Agora você segue @${account.username}.`
}

onMounted(async () => {
  await Promise.all([
    followsStore.hydrateFor(currentUser.value?.id),
    loadPeople({ reset: true }),
  ])
})
</script>

<template>
  <section class="discover">
    <section class="discover__hero card border-0">
      <div>
        <span class="discover__eyebrow">Pessoas que você pode conhecer</span>
        <h2>Descubra novos perfis</h2>
        <p>
          Conheça todas as contas da rede e comece a seguir quem combina com o seu radar.
        </p>
      </div>

      <div class="discover__hero-stat">
        <strong>{{ totalPeople }}</strong>
        <span>perfis disponíveis</span>
      </div>
    </section>

    <p v-if="loadError" class="discover__feedback is-error" role="alert">
      {{ loadError }}
    </p>

    <p v-if="feedbackMessage" class="discover__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section v-if="people.length > 0" class="discover__grid">
      <AccountCard
        v-for="account in people"
        :key="account.id"
        :account="account"
        @follow-changed="handleFollowChanged"
      />
    </section>

    <section v-else-if="!isLoading" class="discover__empty card border-0">
      <h3>Nenhum perfil para sugerir</h3>
      <p>Assim que novas pessoas entrarem na rede, elas aparecem por aqui.</p>
    </section>

    <section v-else class="discover__empty card border-0">
      <p class="mb-0">Carregando pessoas...</p>
    </section>

    <div v-if="hasMore" class="discover__more">
      <button
        class="btn btn-outline-secondary"
        type="button"
        :disabled="isLoading"
        @click="loadPeople({ reset: false })"
      >
        {{ isLoading ? 'Carregando...' : 'Carregar mais pessoas' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.discover {
  display: grid;
  gap: 1rem;
}

.discover__hero,
.discover__empty {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.discover__hero {
  display: grid;
  gap: 1rem;
}

.discover__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.discover__hero h2,
.discover__empty h3 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.6rem, 4vw, 2.3rem);
  font-weight: 800;
}

.discover__hero p,
.discover__empty p {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.discover__hero-stat {
  display: grid;
  gap: 0.2rem;
  padding: 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1.15rem;
  background: var(--app-surface-soft);
}

.discover__hero-stat strong {
  font-size: 1.4rem;
}

.discover__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1rem;
  color: var(--app-text);
  font-weight: 600;
  background: var(--app-surface-soft);
}

.discover__feedback.is-error {
  color: #ffb4ba;
  border-color: rgba(255, 48, 64, 0.28);
  background: rgba(255, 48, 64, 0.08);
}

.discover__grid {
  display: grid;
  gap: 1rem;
}

.discover__more {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0;
}

@media (min-width: 768px) {
  .discover__hero {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }
}
</style>
