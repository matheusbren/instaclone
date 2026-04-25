<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { formatShortDateTime } from '@/utils/dates'

const props = defineProps({
  comment: {
    type: Object,
    required: true,
  },
})

defineEmits(['delete'])

const { currentUser } = storeToRefs(useAuthStore())

const isAuthor = computed(
  () => Boolean(currentUser.value?.id) && currentUser.value.id === props.comment.authorId,
)
const formattedDate = computed(() => formatShortDateTime(props.comment.createdAt))
</script>

<template>
  <li class="comment-item">
    <div class="comment-item__meta">
      <strong>{{ comment.author.name }}</strong>
      <span>@{{ comment.author.username }}</span>
    </div>

    <p class="comment-item__body">{{ comment.body }}</p>

    <div class="comment-item__footer">
      <time :datetime="comment.createdAt">{{ formattedDate }}</time>
      <button
        v-if="isAuthor"
        type="button"
        class="comment-item__delete"
        @click="$emit('delete', comment)"
      >
        Apagar
      </button>
    </div>
  </li>
</template>

<style scoped>
.comment-item {
  display: grid;
  gap: 0.55rem;
  padding: 1rem;
  border-radius: 1.1rem;
  background: var(--app-surface-soft);
}

.comment-item__meta {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.comment-item__meta strong {
  color: var(--app-text);
}

.comment-item__meta span {
  color: var(--app-muted);
}

.comment-item__body {
  margin: 0;
  line-height: 1.7;
}

.comment-item__footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  font-size: 0.9rem;
  color: var(--app-muted);
}

.comment-item__delete {
  border: 0;
  background: none;
  color: var(--app-link);
  font-weight: 700;
  cursor: pointer;
}

.comment-item__delete:hover {
  text-decoration: underline;
}
</style>
