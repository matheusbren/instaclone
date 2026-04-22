<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import { useAuth } from '@/composables/useAuth'
import * as usersService from '@/services/users.service'
import * as followsService from '@/services/follows.service'
import { extractErrorMessage } from '@/services/api'
import { normalizeUser } from '@/stores/profileUtils'
import { normalizePost } from '@/stores/feed'

const route = useRoute()
const { currentUser } = useAuth()

const profile = ref(null)
const postsList = ref([])
const postsCount = ref(0)
const followersList = ref([])
const followersCount = ref(0)
const followingList = ref([])
const followingCount = ref(0)
const isFollowedByViewer = ref(false)
const isLoading = ref(false)
const loadError = ref('')
const feedbackMessage = ref('')
const followPending = ref(false)

const selectedUsername = computed(() =>
  typeof route.query.user === 'string' ? route.query.user.trim().toLowerCase() : '',
)

const isOwnProfile = computed(() => {
  if (!profile.value || !currentUser.value) {
    return false
  }
  return profile.value.id === currentUser.value.id
})

const followButtonLabel = computed(() =>
  isFollowedByViewer.value ? 'Deixar de seguir' : 'Seguir perfil',
)

const postSectionTitle = computed(() => {
  if (!profile.value) {
    return ''
  }
  return isOwnProfile.value ? 'Seus posts' : `Posts de @${profile.value.username}`
})

const connectionsQuery = computed(() => {
  if (!profile.value || isOwnProfile.value) {
    return {}
  }
  return { user: profile.value.username }
})

const followersPreview = computed(() => followersList.value.slice(0, 4))
const followingPreview = computed(() => followingList.value.slice(0, 4))

function getProfileLink(username) {
  if (currentUser.value?.username === username) {
    return { name: 'perfil' }
  }
  return { name: 'perfil', query: { user: username } }
}

async function loadProfile() {
  isLoading.value = true
  loadError.value = ''
  feedbackMessage.value = ''

  profile.value = null
  postsList.value = []
  postsCount.value = 0
  followersList.value = []
  followersCount.value = 0
  followingList.value = []
  followingCount.value = 0
  isFollowedByViewer.value = false

  try {
    let targetUser

    if (selectedUsername.value) {
      const raw = await usersService.getByUsername(selectedUsername.value)
      targetUser = normalizeUser(raw)
    } else if (currentUser.value?.username) {
      const raw = await usersService.getByUsername(currentUser.value.username)
      targetUser = normalizeUser(raw)
    }

    if (!targetUser) {
      loadError.value = 'Perfil não encontrado.'
      return
    }

    profile.value = targetUser

    const [postsResp, followersResp, followingResp] = await Promise.all([
      usersService.getPostsByUser(targetUser.id, 9, 1),
      followsService.followers(targetUser.id, 8, 1),
      followsService.following(targetUser.id, 8, 1),
    ])

    postsList.value = (postsResp.data ?? []).map(normalizePost).filter(Boolean)
    postsCount.value = Number(postsResp.total ?? postsList.value.length)

    followersList.value = (followersResp.data ?? []).map(normalizeUser).filter(Boolean)
    followersCount.value = Number(followersResp.total ?? followersList.value.length)

    followingList.value = (followingResp.data ?? []).map(normalizeUser).filter(Boolean)
    followingCount.value = Number(followingResp.total ?? followingList.value.length)

    if (currentUser.value?.id && currentUser.value.id !== targetUser.id) {
      try {
        const result = await followsService.isFollowing(targetUser.id)
        isFollowedByViewer.value = Boolean(result.is_following)
      } catch {
        isFollowedByViewer.value = false
      }
    }
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o perfil.')
  } finally {
    isLoading.value = false
  }
}

async function handleToggleFollow() {
  if (!profile.value || isOwnProfile.value || followPending.value) {
    return
  }

  followPending.value = true

  try {
    if (isFollowedByViewer.value) {
      await followsService.unfollow(profile.value.id)
      isFollowedByViewer.value = false
      followersCount.value = Math.max(0, followersCount.value - 1)
      feedbackMessage.value = `Você deixou de seguir @${profile.value.username}.`
    } else {
      await followsService.follow(profile.value.id)
      isFollowedByViewer.value = true
      followersCount.value = followersCount.value + 1
      feedbackMessage.value = `Agora você segue @${profile.value.username}.`
    }
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(
      error,
      'Não foi possível atualizar o relacionamento agora.',
    )
  } finally {
    followPending.value = false
  }
}

watch(selectedUsername, loadProfile, { immediate: true })
watch(
  () => currentUser.value?.id,
  () => {
    if (!selectedUsername.value) {
      loadProfile()
    }
  },
)
</script>

<template>
  <section v-if="isLoading && !profile" class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <p class="mb-0 text-body-secondary">Carregando perfil...</p>
    </div>
  </section>

  <section v-else-if="loadError" class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <h2 class="h4 mb-3">Perfil indisponível</h2>
      <p class="text-body-secondary mb-0">{{ loadError }}</p>
    </div>
  </section>

  <section v-else-if="profile" class="profile-view">
    <p v-if="feedbackMessage" class="profile-view__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section class="profile-hero card border-0">
      <div class="profile-hero__identity">
        <ProfileAvatar
          :name="profile.name"
          :username="profile.username"
          :avatar-url="profile.avatarUrl"
          :colors="profile.colors"
          size="xl"
        />

        <div class="profile-hero__copy">
          <span class="profile-hero__eyebrow">
            {{ isOwnProfile ? 'Seu espaço pessoal' : 'Perfil acessado pelo feed' }}
          </span>
          <h2>{{ profile.name }}</h2>
          <p class="profile-hero__username">@{{ profile.username }}</p>
          <p v-if="profile.bio">{{ profile.bio }}</p>
        </div>
      </div>

      <div class="profile-hero__actions">
        <RouterLink
          v-if="isOwnProfile"
          class="btn btn-primary"
          :to="{ name: 'perfil-editar' }"
        >
          Editar perfil
        </RouterLink>

        <button
          v-else
          class="btn"
          :class="isFollowedByViewer ? 'btn-outline-secondary' : 'btn-primary'"
          type="button"
          :disabled="followPending"
          @click="handleToggleFollow"
        >
          {{ followButtonLabel }}
        </button>

        <RouterLink class="btn btn-outline-secondary" :to="{ name: 'feed' }">
          Voltar para o feed
        </RouterLink>
      </div>

      <div class="profile-hero__stats">
        <article>
          <strong>{{ postsCount }}</strong>
          <span>posts</span>
        </article>

        <RouterLink
          class="profile-hero__stat-link"
          :to="{
            name: 'perfil-lista',
            params: { type: 'seguidores' },
            query: connectionsQuery,
          }"
        >
          <strong>{{ followersCount }}</strong>
          <span>seguidores</span>
        </RouterLink>

        <RouterLink
          class="profile-hero__stat-link"
          :to="{
            name: 'perfil-lista',
            params: { type: 'seguindo' },
            query: connectionsQuery,
          }"
        >
          <strong>{{ followingCount }}</strong>
          <span>seguindo</span>
        </RouterLink>
      </div>
    </section>

    <section class="profile-connections">
      <article class="profile-connections__card card border-0">
        <div class="profile-connections__head">
          <div>
            <span class="profile-connections__eyebrow">Seguidores</span>
            <h3>{{ followersCount }} pessoas acompanham este perfil</h3>
          </div>

          <RouterLink
            class="btn btn-outline-secondary btn-sm"
            :to="{
              name: 'perfil-lista',
              params: { type: 'seguidores' },
              query: connectionsQuery,
            }"
          >
            Ver lista
          </RouterLink>
        </div>

        <ul v-if="followersPreview.length > 0" class="profile-connections__list">
          <li v-for="account in followersPreview" :key="account.id">
            <RouterLink :to="getProfileLink(account.username)" class="profile-connections__item">
              <ProfileAvatar
                :name="account.name"
                :username="account.username"
                :avatar-url="account.avatarUrl"
                :colors="account.colors"
                size="sm"
              />
              <span>
                <strong>{{ account.name }}</strong>
                <small>@{{ account.username }}</small>
              </span>
            </RouterLink>
          </li>
        </ul>

        <p v-else class="profile-connections__empty">
          Nenhum seguidor ainda. Conforme as relações crescerem, a lista aparece aqui.
        </p>
      </article>

      <article class="profile-connections__card card border-0">
        <div class="profile-connections__head">
          <div>
            <span class="profile-connections__eyebrow">Seguindo</span>
            <h3>{{ followingCount }} perfis no radar</h3>
          </div>

          <RouterLink
            class="btn btn-outline-secondary btn-sm"
            :to="{
              name: 'perfil-lista',
              params: { type: 'seguindo' },
              query: connectionsQuery,
            }"
          >
            Ver lista
          </RouterLink>
        </div>

        <ul v-if="followingPreview.length > 0" class="profile-connections__list">
          <li v-for="account in followingPreview" :key="account.id">
            <RouterLink :to="getProfileLink(account.username)" class="profile-connections__item">
              <ProfileAvatar
                :name="account.name"
                :username="account.username"
                :avatar-url="account.avatarUrl"
                :colors="account.colors"
                size="sm"
              />
              <span>
                <strong>{{ account.name }}</strong>
                <small>@{{ account.username }}</small>
              </span>
            </RouterLink>
          </li>
        </ul>

        <p v-else class="profile-connections__empty">
          Este perfil ainda não segue ninguém. Assim que seguir contas, elas aparecem aqui.
        </p>
      </article>
    </section>

    <section class="profile-posts">
      <div class="profile-posts__head">
        <div>
          <span class="profile-connections__eyebrow">Grade de posts</span>
          <h3>{{ postSectionTitle }}</h3>
        </div>
        <span class="profile-posts__count">{{ postsCount }} itens</span>
      </div>

      <div v-if="postsList.length > 0" class="profile-posts__grid">
        <article v-for="post in postsList" :key="post.id" class="profile-post-card card border-0">
          <RouterLink
            :to="{ name: 'post-detalhes', params: { postId: post.id } }"
            class="profile-post-card__media-link"
          >
            <img :src="post.imageUrl" :alt="post.imageAlt" loading="lazy" />
          </RouterLink>

          <div class="profile-post-card__body">
            <p v-if="post.caption">{{ post.caption }}</p>

            <div class="profile-post-card__meta">
              <span>{{ post.likesCount }} curtidas</span>
              <span>{{ post.commentsCount }} comentários</span>
            </div>

            <RouterLink
              :to="{ name: 'post-detalhes', params: { postId: post.id } }"
              class="profile-post-card__link"
            >
              Abrir post
            </RouterLink>
          </div>
        </article>
      </div>

      <section v-else class="profile-posts__empty card border-0">
        <h3>Nenhum post por aqui ainda</h3>
        <p>
          {{ isOwnProfile
            ? 'Publique algo para preencher sua grade e mostrar atividade no perfil.'
            : 'Quando este usuário publicar, a grade começa a aparecer aqui.' }}
        </p>
        <RouterLink
          v-if="isOwnProfile"
          class="btn btn-primary align-self-start"
          :to="{ name: 'criar' }"
        >
          Criar primeiro post
        </RouterLink>
      </section>
    </section>
  </section>
</template>

<style scoped>
.profile-view {
  display: grid;
  gap: 1rem;
}

.profile-view__feedback {
  margin: 0;
  padding: 0.95rem 1rem;
  border: 1px solid rgba(240, 90, 40, 0.14);
  border-radius: 1rem;
  color: var(--app-accent-strong);
  font-weight: 700;
  background: rgba(255, 255, 255, 0.84);
}

.profile-hero,
.profile-connections__card,
.profile-post-card,
.profile-posts__empty {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: rgba(255, 252, 248, 0.9);
}

.profile-hero {
  display: grid;
  gap: 1.2rem;
}

.profile-hero__identity {
  display: grid;
  gap: 1rem;
}

.profile-hero__copy h2,
.profile-connections__head h3,
.profile-posts__head h3,
.profile-posts__empty h3 {
  margin: 0 0 0.25rem;
  font-size: clamp(1.6rem, 4vw, 2.35rem);
  font-weight: 800;
}

.profile-hero__copy p,
.profile-connections__head h3,
.profile-connections__empty,
.profile-post-card__body p,
.profile-posts__empty p {
  margin: 0;
}

.profile-hero__copy p,
.profile-hero__username,
.profile-connections__empty,
.profile-post-card__meta span,
.profile-posts__count,
.profile-posts__empty p {
  color: var(--app-muted);
  line-height: 1.65;
}

.profile-hero__eyebrow,
.profile-connections__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.profile-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.profile-hero__stats {
  display: grid;
  gap: 0.85rem;
}

.profile-hero__stats article,
.profile-hero__stat-link {
  display: grid;
  gap: 0.15rem;
  padding: 1rem;
  border-radius: 1.2rem;
  color: inherit;
  text-decoration: none;
  background: rgba(255, 255, 255, 0.74);
}

.profile-hero__stats strong {
  font-size: 1.25rem;
}

.profile-hero__stats span {
  color: var(--app-muted);
}

.profile-connections {
  display: grid;
  gap: 1rem;
}

.profile-connections__card {
  display: grid;
  gap: 1rem;
}

.profile-connections__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
}

.profile-connections__head h3 {
  font-size: 1.1rem;
}

.profile-connections__list {
  display: grid;
  gap: 0.85rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.profile-connections__item {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  color: inherit;
  text-decoration: none;
}

.profile-connections__item span {
  display: grid;
  gap: 0.05rem;
}

.profile-connections__item strong {
  font-size: 0.98rem;
}

.profile-connections__item small {
  color: var(--app-muted);
  font-size: 0.9rem;
}

.profile-posts {
  display: grid;
  gap: 1rem;
}

.profile-posts__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: end;
}

.profile-posts__head h3 {
  font-size: 1.25rem;
}

.profile-posts__count {
  font-weight: 700;
}

.profile-posts__grid {
  display: grid;
  gap: 1rem;
}

.profile-post-card {
  overflow: hidden;
  padding: 0;
}

.profile-post-card img {
  display: block;
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
}

.profile-post-card__media-link,
.profile-post-card__link {
  text-decoration: none;
}

.profile-post-card__body {
  display: grid;
  gap: 0.9rem;
  padding: 1rem;
}

.profile-post-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1rem;
  font-size: 0.94rem;
  font-weight: 700;
}

.profile-post-card__link {
  color: var(--app-accent-strong);
  font-weight: 800;
}

.profile-post-card__link:hover,
.profile-post-card__link:focus-visible {
  text-decoration: underline;
}

.profile-posts__empty {
  display: grid;
  gap: 0.75rem;
}

@media (min-width: 768px) {
  .profile-hero__identity {
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
  }

  .profile-hero__stats,
  .profile-connections,
  .profile-posts__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1100px) {
  .profile-hero {
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.75fr);
    align-items: start;
  }

  .profile-hero__identity {
    grid-column: 1;
  }

  .profile-hero__actions,
  .profile-hero__stats {
    grid-column: 2;
  }

  .profile-posts__grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
