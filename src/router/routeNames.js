export const ROUTE_NAMES = {
  feed: 'feed',
  postDetails: 'post-details',
  createPost: 'create-post',
  discover: 'discover',
  profile: 'profile',
  editProfile: 'edit-profile',
  profileConnections: 'profile-connections',
  login: 'login',
  register: 'register',
  notFound: 'not-found',
}

export const CONNECTION_LIST_TYPES = {
  followers: 'followers',
  following: 'following',
}

export function isConnectionListType(value) {
  return value === CONNECTION_LIST_TYPES.followers || value === CONNECTION_LIST_TYPES.following
}
