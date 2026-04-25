<script setup>
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import PostCommentList from '@/components/feed/PostCommentList.vue'
import PostCommentForm from '@/components/feed/PostCommentForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useFeedStore, normalizePost, normalizeComment } from '@/stores/feed'
import * as postsService from '@/services/posts.service'
import * as likesService from '@/services/likes.service'
import * as commentsService from '@/services/comments.service'
import { extractErrorMessage } from '@/services/api'
import { ROUTE_NAMES } from '@/router/routeNames'
import { formatFullDateTime } from '@/utils/dates'

const COMMENTS_PAGE_SIZE = 10

const route = useRoute()
const router = useRouter()
const { currentUser } = storeToRefs(useAuthStore())
const feedStore = useFeedStore()

const post = ref(null)
const loadError = ref('')
const isLoading = ref(false)
const feedbackMessage = ref('')

const comments = ref([])
const commentsCurrentPage = ref(1)
const commentsHasMore = ref(false)
const commentsLoading = ref(false)
const isSubmittingComment = ref(false)
const likePending = ref(false)
const deletePending = ref(false)

const postId = computed(() => {
  const raw = route.params.postId
  return typeof raw === 'string' ? raw.trim() : String(raw ?? '')
})

const isOwner = computed(
  () => Boolean(currentUser.value?.id && post.value?.author.id === currentUser.value.id),
)

const authorLink = computed(() => {
  if (!post.value || currentUser.value?.username === post.value.author.username) {
    return { name: ROUTE_NAMES.profile }
  }
  return { name: ROUTE_NAMES.profile, query: { user: post.value.author.username } }
})

const likesLabel = computed(() => {
  const total = post.value?.likesCount ?? 0
  return `${total} ${total === 1 ? 'curtida' : 'curtidas'}`
})

const commentsLabel = computed(() => {
  const total = post.value?.commentsCount ?? 0
  return `${total} ${total === 1 ? 'comentário' : 'comentários'}`
})

const publishedLabel = computed(() => formatFullDateTime(post.value?.createdAt))

async function loadPost() {
  if (!postId.value) {
    return
  }

  isLoading.value = true
  loadError.value = ''
  feedbackMessage.value = ''

  try {
    const raw = await postsService.show(postId.value)
    post.value = normalizePost(raw)
  } catch (error) {
    post.value = null
    loadError.value = extractErrorMessage(error, 'Post não encontrado.')
  } finally {
    isLoading.value = false
  }
}

async function loadComments({ reset = true } = {}) {
  if (!postId.value) {
    return
  }

  commentsLoading.value = true

  try {
    const page = reset ? 1 : commentsCurrentPage.value + 1
    const response = await commentsService.listByPost(postId.value, COMMENTS_PAGE_SIZE, page)
    const items = (response.data ?? []).map(normalizeComment).filter(Boolean)

    comments.value = reset ? items : [...comments.value, ...items]
    commentsCurrentPage.value = Number(response.current_page ?? page)
    commentsHasMore.value = Boolean(response.next_page_url)
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível carregar os comentários.')
  } finally {
    commentsLoading.value = false
  }
}

async function handleToggleLike() {
  if (!post.value || likePending.value) {
    return
  }

  likePending.value = true

  try {
    const action = post.value.likedByMe ? likesService.unlike : likesService.like
    const response = await action(post.value.id)
    post.value = {
      ...post.value,
      likedByMe: Boolean(response.liked),
      likesCount: Number(response.likes_count ?? post.value.likesCount),
    }
    feedStore.applyPostPatch(post.value.id, {
      likedByMe: post.value.likedByMe,
      likesCount: post.value.likesCount,
    })
    feedbackMessage.value = post.value.likedByMe ? 'Post curtido.' : 'Curtida removida.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível atualizar a curtida.')
  } finally {
    likePending.value = false
  }
}

async function handleSubmitComment(text, reset) {
  if (!post.value || isSubmittingComment.value) {
    return
  }

  isSubmittingComment.value = true

  try {
    const created = await commentsService.create(post.value.id, text)
    const normalized = normalizeComment(created)
    if (normalized) {
      comments.value = [normalized, ...comments.value]
    }
    post.value = { ...post.value, commentsCount: post.value.commentsCount + 1 }
    feedStore.applyPostPatch(post.value.id, (current) => ({
      commentsCount: current.commentsCount + 1,
    }))
    reset?.()
    feedbackMessage.value = 'Comentário enviado ao post.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível enviar o comentário.')
  } finally {
    isSubmittingComment.value = false
  }
}

async function handleDeleteComment(comment) {
  if (!comment || comment.authorId !== currentUser.value?.id) {
    return
  }
  if (!window.confirm('Deseja realmente apagar este comentário?')) {
    return
  }

  try {
    await commentsService.destroy(comment.id)
    comments.value = comments.value.filter((item) => item.id !== comment.id)
    if (post.value) {
      const nextCount = Math.max(0, post.value.commentsCount - 1)
      post.value = { ...post.value, commentsCount: nextCount }
      feedStore.applyPostPatch(post.value.id, { commentsCount: nextCount })
    }
    feedbackMessage.value = 'Comentário apagado.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível apagar o comentário.')
  }
}

async function handleDeletePost() {
  if (!post.value || !isOwner.value || deletePending.value) {
    return
  }
  if (!window.confirm('Deseja realmente deletar este post?')) {
    return
  }

  deletePending.value = true

  try {
    await postsService.destroy(post.value.id)
    router.replace({ name: ROUTE_NAMES.profile })
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível deletar o post agora.')
  } finally {
    deletePending.value = false
  }
}

watch(
  postId,
  async () => {
    feedbackMessage.value = ''
    comments.value = []
    commentsCurrentPage.value = 1
    commentsHasMore.value = false
    await loadPost()
    if (post.value) {
      await loadComments({ reset: true })
    }
  },
  { immediate: true },
)
</script>

<template>
  <section v-if="isLoading && !post" class="card border-0 shadow-sm">
    <div class="card-body p-4">
      <p class="mb-0 text-body-secondary">Carregando post...</p>
    </div>
  </section>

  <section v-else-if="post" class="post-details">
    <p v-if="feedbackMessage" class="post-details__feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <article class="post-details__card card border-0">
      <div class="post-details__media-panel">
        <img class="post-details__image" :src="post.imageUrl" :alt="post.imageAlt" />
      </div>

      <div class="post-details__content">
        <header class="post-details__header">
          <RouterLink :to="authorLink" class="post-details__author">
            <ProfileAvatar
              :name="post.author.name"
              :username="post.author.username"
              :avatar-url="post.author.avatarUrl"
              :colors="post.author.colors"
              size="md"
            />

            <span class="post-details__author-meta">
              <strong>{{ post.author.name }}</strong>
              <span>@{{ post.author.username }}</span>
            </span>
          </RouterLink>

          <div class="post-details__header-copy">
            <time :datetime="post.createdAt">{{ publishedLabel }}</time>
          </div>
        </header>

        <section v-if="post.caption" class="post-details__caption-block">
          <span class="post-details__eyebrow">Legenda</span>
          <p>{{ post.caption }}</p>
        </section>

        <section class="post-details__stats">
          <div>
            <strong>{{ likesLabel }}</strong>
            <span>interações com a publicação</span>
          </div>

          <div>
            <strong>{{ commentsLabel }}</strong>
            <span>comentários acumulados neste post</span>
          </div>
        </section>

        <div class="post-details__actions">
          <button
            v-if="!isOwner"
            class="post-details__action"
            :class="{ 'is-active': post.likedByMe }"
            type="button"
            :disabled="likePending"
            @click="handleToggleLike"
          >
            {{ post.likedByMe ? 'Descurtir' : 'Curtir post' }}
          </button>

          <RouterLink class="btn btn-outline-secondary" :to="{ name: ROUTE_NAMES.feed }">
            Voltar para o feed
          </RouterLink>

          <button
            v-if="isOwner"
            class="btn btn-outline-danger"
            type="button"
            :disabled="deletePending"
            @click="handleDeletePost"
          >
            {{ deletePending ? 'Deletando...' : 'Deletar post' }}
          </button>
        </div>

        <section class="post-details__comments card border-0">
          <div class="post-details__comments-head">
            <div>
              <span class="post-details__eyebrow">Comentários</span>
              <h3>Conversa do post</h3>
            </div>

            <span class="post-details__comments-meta">{{ commentsLabel }}</span>
          </div>

          <PostCommentList
            :comments="comments"
            :has-more="commentsHasMore"
            :loading="commentsLoading"
            @delete-comment="handleDeleteComment"
            @load-more="loadComments({ reset: false })"
          />

          <PostCommentForm
            :submitting="isSubmittingComment"
            @submit="handleSubmitComment"
          />
        </section>
      </div>
    </article>
  </section>

  <section v-else class="post-details__missing card border-0">
    <h2>Post não encontrado</h2>
    <p>
      {{ loadError || 'Esse post não existe mais ou foi removido.' }}
      Volte para o feed para continuar navegando.
    </p>
    <RouterLink class="btn btn-primary align-self-start" :to="{ name: ROUTE_NAMES.feed }">
      Ir para o feed
    </RouterLink>
  </section>
</template>

<style scoped>
.post-details {
  display: grid;
  gap: 1rem;
}

.post-details__card,
.post-details__comments,
.post-details__missing {
  overflow: hidden;
  border-radius: 1.75rem;
  background: var(--app-surface);
}

.post-details__card {
  display: grid;
}

.post-details__media-panel {
  position: relative;
  min-height: 18rem;
  background:
    linear-gradient(135deg, rgba(0, 149, 246, 0.14) 0%, rgba(0, 0, 0, 0) 48%),
    var(--app-surface-soft);
}

.post-details__image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  aspect-ratio: 1 / 1;
}

.post-details__content {
  display: grid;
  gap: 1.1rem;
  padding: 1.25rem;
}

.post-details__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.post-details__author {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.post-details__author-meta {
  display: grid;
}

.post-details__author-meta strong {
  color: var(--app-text);
}

.post-details__author-meta span,
.post-details__header-copy,
.post-details__comments-meta,
.post-details__feedback,
.post-details__stats span,
.post-details__missing p {
  color: var(--app-muted);
}

.post-details__header-copy {
  display: grid;
  justify-items: end;
  gap: 0.2rem;
  font-size: 0.94rem;
  text-align: right;
}

.post-details__eyebrow {
  display: inline-block;
  color: var(--app-accent-strong);
  font-size: 0.76rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.post-details__caption-block {
  display: grid;
  gap: 0.45rem;
}

.post-details__caption-block p,
.post-details__missing h2 {
  margin: 0;
}

.post-details__caption-block p,
.post-details__missing p {
  line-height: 1.7;
}

.post-details__stats {
  display: grid;
  gap: 0.8rem;
}

.post-details__stats div {
  display: grid;
  gap: 0.22rem;
  padding: 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1.15rem;
  background: var(--app-surface-soft);
}

.post-details__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.post-details__action {
  padding: 0.75rem 1.1rem;
  border: 0;
  border-radius: 999px;
  color: var(--app-text);
  font-weight: 800;
  background: var(--app-accent-soft);
  transition: transform 180ms ease, background-color 180ms ease, color 180ms ease;
}

.post-details__action.is-active,
.post-details__action:hover:not(:disabled),
.post-details__action:focus-visible {
  color: #fff;
  background: var(--app-link);
  transform: translateY(-1px);
}

.post-details__comments {
  display: grid;
  gap: 1rem;
  padding: 1.1rem;
  border: 1px solid var(--app-border);
}

.post-details__comments-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.post-details__comments-head div {
  display: grid;
}

.post-details__comments-head h3,
.post-details__missing h2 {
  margin: 0.2rem 0 0;
  font-size: clamp(1.3rem, 3vw, 1.7rem);
  font-weight: 800;
}

.post-details__feedback {
  margin: 0;
  padding: 0.9rem 1rem;
  border: 1px solid var(--app-border);
  border-radius: 1rem;
  font-weight: 600;
  background: var(--app-surface-soft);
}

.post-details__missing {
  display: grid;
  gap: 0.9rem;
  padding: 1.4rem;
}

@media (min-width: 992px) {
  .post-details__card {
    grid-template-columns: minmax(0, 1.1fr) minmax(20rem, 0.9fr);
    align-items: stretch;
  }

  .post-details__media-panel {
    min-height: 100%;
  }

  .post-details__content {
    padding: 1.45rem;
  }
}

@media (max-width: 575.98px) {
  .post-details__header,
  .post-details__comments-head {
    flex-direction: column;
  }

  .post-details__header-copy {
    justify-items: start;
    text-align: left;
  }
}
</style>
