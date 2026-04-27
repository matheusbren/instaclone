import { defineStore } from 'pinia'
import * as feedService from '@/services/feed.service'
import * as postsService from '@/services/posts.service'
import * as likesService from '@/services/likes.service'
import * as commentsService from '@/services/comments.service'
import { defaultAuthor, normalizeUser } from '@/stores/profileUtils'

export const POST_CAPTION_MAX_LENGTH = 2200
export const FEED_PAGE_SIZE = 10

export function normalizePost(rawPost) {
  if (!rawPost || typeof rawPost !== 'object') {
    return null
  }

  const post = rawPost.data ?? rawPost
  const author = normalizeUser(post.user) || defaultAuthor(post.user_id)
  const caption = post.caption ?? ''

  return {
    id: post.id,
    author,
    caption,
    imageUrl: post.image_url ?? post.imageUrl ?? '',
    imageAlt: caption
      ? `Publicação de @${author.username}: ${caption.slice(0, 96)}`
      : `Publicação de @${author.username}.`,
    likesCount: Number(post.likes_count ?? post.likesCount ?? 0),
    commentsCount: Number(post.comments_count ?? post.commentsCount ?? 0),
    likedByMe: Boolean(post.liked_by_me ?? post.likedByMe ?? false),
    createdAt: post.created_at ?? post.createdAt ?? null,
    updatedAt: post.updated_at ?? post.updatedAt ?? null,
  }
}

export function normalizeComment(rawComment) {
  if (!rawComment || typeof rawComment !== 'object') {
    return null
  }

  const comment = rawComment.data ?? rawComment
  const author = normalizeUser(comment.user) || defaultAuthor(comment.user_id)

  return {
    id: comment.id,
    body: comment.body ?? '',
    author,
    authorId: comment.user_id ?? author.id,
    createdAt: comment.created_at ?? comment.createdAt ?? null,
  }
}

export const useFeedStore = defineStore('feed', {
  state: () => ({
    feedPosts: [],
    feedCursor: null,
    feedHasNext: false,
    feedLoaded: false,
    feedLoading: false,
  }),
  actions: {
    async fetchFeed({ reset = false } = {}) {
      if (this.feedLoading) {
        return
      }

      this.feedLoading = true

      try {
        const cursor = reset ? null : this.feedCursor
        const response = await feedService.getFeed({ cursor, perPage: FEED_PAGE_SIZE })
        const normalized = (response.items ?? []).map(normalizePost).filter(Boolean)

        this.feedPosts = reset ? normalized : [...this.feedPosts, ...normalized]
        this.feedCursor = response.next_cursor ?? null
        this.feedHasNext = Boolean(response.next_cursor)
        this.feedLoaded = true
      } finally {
        this.feedLoading = false
      }
    },

    async loadMoreFeed() {
      if (!this.feedHasNext) {
        return
      }
      await this.fetchFeed({ reset: false })
    },

    async createPost({ image, caption }) {
      const created = await postsService.create({ image, caption })
      const normalized = normalizePost(created)
      if (normalized) {
        this.feedPosts = [normalized, ...this.feedPosts]
      }
      return normalized
    },

    async deletePost(postId) {
      await postsService.destroy(postId)
      this.feedPosts = this.feedPosts.filter((post) => post.id !== postId)
    },

    async toggleLike(post) {
      if (!post) {
        return null
      }

      const action = post.likedByMe ? likesService.unlike : likesService.like
      const response = await action(post.id)
      this.applyPostPatch(post.id, {
        likedByMe: Boolean(response.liked),
        likesCount: Number(response.likes_count ?? post.likesCount),
      })
      return response
    },

    async addComment(postId, body) {
      const comment = await commentsService.create(postId, body)
      this.applyPostPatch(postId, (post) => ({
        commentsCount: post.commentsCount + 1,
      }))
      return comment
    },

    applyPostPatch(postId, patch) {
      const index = this.feedPosts.findIndex((post) => post.id === postId)
      if (index < 0) {
        return
      }

      const current = this.feedPosts[index]
      const nextPatch = typeof patch === 'function' ? patch(current) : patch
      this.feedPosts[index] = { ...current, ...nextPatch }
    },
  },
})
