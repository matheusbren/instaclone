<script setup>
import { onMounted, ref } from 'vue'
import PostCard from '@/components/feed/PostCard.vue'
import { useFeed } from '@/composables/useFeed'
import { extractErrorMessage } from '@/services/api'

const feedbackMessage = ref('')
const loadError = ref('')

const {
  feedPosts,
  feedHasNext,
  feedLoaded,
  feedLoading,
  fetchFeed,
  loadMoreFeed,
  toggleLike,
  addComment,
} = useFeed()

onMounted(async () => {
  try {
    await fetchFeed({ reset: true })
  } catch (error) {
    loadError.value = extractErrorMessage(error, 'Não foi possível carregar o feed agora.')
  }
})

async function handleToggleLike(postId) {
  const post = feedPosts.value.find((item) => item.id === postId)
  if (!post) {
    return
  }

  try {
    await toggleLike(post)
    feedbackMessage.value = post.likedByMe ? 'Curtida removida.' : 'Post curtido.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível atualizar a curtida.')
  }
}

async function handleSubmitComment(payload) {
  try {
    await addComment(payload.postId, payload.text)
    feedbackMessage.value = 'Comentário enviado ao post.'
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível enviar o comentário.')
  }
}

async function handleLoadMore() {
  try {
    await loadMoreFeed()
  } catch (error) {
    feedbackMessage.value = extractErrorMessage(error, 'Não foi possível carregar mais posts.')
  }
}
</script>

<template>
  <section class="feed-view">
    <section class="feed-summary card border-0">
      <div class="feed-summary__copy">
        <span class="feed-summary__eyebrow">Seu ritmo agora</span>
        <h2>{{ feedPosts.length }} posts no radar</h2>
        <p>
          O feed mistura publicações recentes dos perfis que você segue, com curtidas e
          comentários inline para manter a interação no mesmo fluxo.
        </p>
      </div>

      <div class="feed-summary__stats">
        <div>
          <strong>{{ feedPosts.length }} publicações carregadas</strong>
          <span>posts ordenados do mais recente para o mais antigo</span>
        </div>

        <div>
          <strong>{{ feedHasNext ? 'Há mais posts' : 'Você está em dia' }}</strong>
          <span>paginação via cursor para carregar mais</span>
        </div>
      </div>
    </section>

    <p v-if="loadError" class="feed-feedback is-error" role="alert">
      {{ loadError }}
    </p>

    <p v-if="feedbackMessage" class="feed-feedback" role="status">
      {{ feedbackMessage }}
    </p>

    <section v-if="feedPosts.length > 0" class="feed-list" aria-label="Lista de posts">
      <PostCard
        v-for="post in feedPosts"
        :key="post.id"
        :post="post"
        @toggle-like="handleToggleLike"
        @submit-comment="handleSubmitComment"
      />
    </section>

    <section v-else-if="feedLoaded && !feedLoading" class="feed-empty card border-0">
      <h3>Nenhum post para mostrar</h3>
      <p>
        Assim que você seguir perfis ou publicar algo, o feed passa a aparecer aqui com a mesma
        estrutura de interação.
      </p>
    </section>

    <section v-else-if="feedLoading && feedPosts.length === 0" class="feed-empty card border-0">
      <h3>Carregando feed...</h3>
      <p>Buscando as publicações mais recentes dos perfis que você acompanha.</p>
    </section>

    <div v-if="feedHasNext" class="feed-pagination">
      <button
        class="btn btn-outline-secondary btn-lg"
        type="button"
        :disabled="feedLoading"
        @click="handleLoadMore"
      >
        {{ feedLoading ? 'Carregando...' : 'Carregar mais posts' }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.feed-view,
.feed-list {
  display: grid;
  gap: 1rem;
}

.feed-summary,
.feed-empty {
  padding: 1.4rem;
  border-radius: 1.75rem;
  background: rgba(255, 252, 248, 0.88);
}

.feed-summary {
  display: grid;
  gap: 1rem;
}

.feed-summary__eyebrow {
  display: inline-block;
  margin-bottom: 0.35rem;
  color: var(--app-accent-strong);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.feed-summary h2,
.feed-empty h3 {
  margin: 0 0 0.35rem;
  font-size: clamp(1.55rem, 4vw, 2.2rem);
  font-weight: 800;
}

.feed-summary p,
.feed-empty p,
.feed-summary__stats span {
  margin: 0;
  color: var(--app-muted);
  line-height: 1.65;
}

.feed-summary__stats {
  display: grid;
  gap: 0.8rem;
}

.feed-summary__stats div {
  display: grid;
  gap: 0.2rem;
  padding: 1rem;
  border: 1px solid rgba(131, 93, 65, 0.14);
  border-radius: 1.15rem;
  background: rgba(255, 255, 255, 0.72);
}

.feed-summary__stats strong {
  font-size: 1.02rem;
}

.feed-feedback {
  margin: 0;
  padding: 0.9rem 1rem;
  border: 1px solid rgba(240, 90, 40, 0.14);
  border-radius: 1rem;
  color: var(--app-accent-strong);
  font-weight: 700;
  background: rgba(255, 255, 255, 0.78);
}

.feed-feedback.is-error {
  color: #8b2b17;
  border-color: rgba(167, 52, 31, 0.18);
  background: rgba(255, 241, 238, 0.92);
}

.feed-pagination {
  display: flex;
  justify-content: center;
  padding-bottom: 0.5rem;
}

@media (min-width: 768px) {
  .feed-summary__stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
