<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import ProfileAvatar from '@/components/profile/ProfileAvatar.vue'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  post: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['toggle-like', 'submit-comment'])

const { currentUser } = useAuth()

const isOwnPost = computed(
  () => Boolean(currentUser.value?.id && props.post.author?.id === currentUser.value.id),
)

const commentText = ref('')

const postLink = computed(() => ({
  name: 'post-detalhes',
  params: {
    postId: props.post.id,
  },
}))

const authorLink = computed(() => ({
  name: 'perfil',
  query: {
    user: props.post.author.username,
  },
}))

const likeLabel = computed(() => {
  const total = props.post.likesCount ?? 0
  return `${total} ${total === 1 ? 'curtida' : 'curtidas'}`
})

const commentLabel = computed(() => {
  const total = props.post.commentsCount ?? 0
  return `${total} ${total === 1 ? 'comentário' : 'comentários'}`
})

const publishedLabel = computed(() => {
  if (!props.post.createdAt) {
    return ''
  }
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(props.post.createdAt))
})

const trimmedComment = computed(() => commentText.value.trim())

function handleCommentSubmit() {
  if (!trimmedComment.value) {
    return
  }

  emit('submit-comment', {
    postId: props.post.id,
    text: trimmedComment.value,
  })

  commentText.value = ''
}
</script>

<template>
  <article class="feed-post card border-0">
    <header class="feed-post__header">
      <RouterLink :to="authorLink" class="feed-post__author">
        <ProfileAvatar
          :name="post.author.name"
          :username="post.author.username"
          :avatar-url="post.author.avatarUrl"
          :colors="post.author.colors"
          size="sm"
        />

        <span class="feed-post__author-meta">
          <strong>{{ post.author.name }}</strong>
          <span>@{{ post.author.username }}</span>
        </span>
      </RouterLink>

      <time class="feed-post__date" :datetime="post.createdAt">{{ publishedLabel }}</time>
    </header>

    <RouterLink :to="postLink" class="feed-post__media-link">
      <img class="feed-post__media" :src="post.imageUrl" :alt="post.imageAlt" loading="lazy" />
    </RouterLink>

    <div class="feed-post__body">
      <p class="feed-post__caption">
        <RouterLink :to="authorLink" class="feed-post__caption-link">@{{ post.author.username }}</RouterLink>
        {{ post.caption }}
      </p>

      <div class="feed-post__actions">
        <button
          v-if="!isOwnPost"
          class="feed-post__action"
          :class="{ 'is-active': post.likedByMe }"
          type="button"
          @click="emit('toggle-like', post.id)"
        >
          {{ post.likedByMe ? 'Descurtir' : 'Curtir' }}
        </button>
        <span>{{ likeLabel }}</span>
        <RouterLink :to="postLink" class="feed-post__details-link">
          {{ commentLabel }}
        </RouterLink>
        <RouterLink :to="postLink" class="feed-post__details-link">
          Ver detalhes
        </RouterLink>
      </div>

      <form class="feed-post__comment-form" @submit.prevent="handleCommentSubmit">
        <input
          v-model="commentText"
          class="feed-post__comment-input"
          type="text"
          maxlength="2200"
          placeholder="Adicione um comentário"
        />
        <button class="feed-post__submit" type="submit" :disabled="!trimmedComment">
          Enviar
        </button>
      </form>
    </div>
  </article>
</template>

<style scoped>
.feed-post {
  overflow: hidden;
  border-radius: 1.75rem;
  background: rgba(255, 255, 255, 0.96);
}

.feed-post__header,
.feed-post__body {
  padding: 1.1rem 1.1rem 0;
}

.feed-post__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.feed-post__author {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.feed-post__author-meta {
  display: grid;
  min-width: 0;
}

.feed-post__author-meta strong,
.feed-post__author-meta span,
.feed-post__date,
.feed-post__actions span {
  color: var(--app-muted);
}

.feed-post__author-meta strong {
  color: var(--app-text);
}

.feed-post__author-meta span,
.feed-post__date,
.feed-post__actions span {
  font-size: 0.94rem;
}

.feed-post__date {
  white-space: nowrap;
}

.feed-post__media-link {
  display: block;
  margin-top: 1rem;
}

.feed-post__media {
  display: block;
  width: 100%;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  background: rgba(0, 0, 0, 0.04);
}

.feed-post__body {
  padding-bottom: 1.1rem;
}

.feed-post__caption {
  margin: 0 0 1rem;
  line-height: 1.7;
  white-space: pre-line;
}

.feed-post__caption-link {
  margin-right: 0.45rem;
  color: var(--app-accent-strong);
  font-weight: 800;
  text-decoration: none;
}

.feed-post__details-link {
  color: var(--app-accent-strong);
  font-weight: 700;
  text-decoration: none;
}

.feed-post__details-link:hover,
.feed-post__details-link:focus-visible {
  text-decoration: underline;
}

.feed-post__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
  margin-bottom: 1rem;
}

.feed-post__action,
.feed-post__submit {
  border: 0;
  border-radius: 999px;
  font-weight: 800;
  transition:
    transform 180ms ease,
    background-color 180ms ease,
    color 180ms ease,
    box-shadow 180ms ease;
}

.feed-post__action {
  padding: 0.65rem 1rem;
  color: var(--app-accent-strong);
  background: var(--app-accent-soft);
}

.feed-post__action.is-active,
.feed-post__action:hover,
.feed-post__action:focus-visible,
.feed-post__submit:hover,
.feed-post__submit:focus-visible {
  color: #fff;
  background: linear-gradient(135deg, var(--app-accent) 0%, #ff8c4a 100%);
  box-shadow: 0 14px 24px rgba(240, 90, 40, 0.22);
  transform: translateY(-1px);
}

.feed-post__comment-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.75rem;
}

.feed-post__comment-input {
  width: 100%;
  padding: 0.85rem 1rem;
  border: 1px solid rgba(122, 101, 91, 0.2);
  border-radius: 1rem;
  color: var(--app-text);
  background: rgba(255, 255, 255, 0.92);
}

.feed-post__comment-input:focus-visible {
  outline: 2px solid rgba(240, 90, 40, 0.18);
  border-color: rgba(240, 90, 40, 0.45);
}

.feed-post__submit {
  min-width: 6.75rem;
  padding: 0.85rem 1rem;
  color: #fff;
  background: linear-gradient(135deg, var(--app-accent-strong) 0%, var(--app-accent) 100%);
}

.feed-post__submit:disabled {
  cursor: not-allowed;
  opacity: 0.55;
  transform: none;
  box-shadow: none;
}

@media (max-width: 575.98px) {
  .feed-post__header {
    align-items: flex-start;
  }

  .feed-post__comment-form {
    grid-template-columns: 1fr;
  }

  .feed-post__submit {
    width: 100%;
  }
}
</style>
