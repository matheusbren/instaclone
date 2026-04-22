<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true,
  },
})

const paths = {
  home: ['M3 10.5 12 3l9 7.5', 'M5.25 9.75V21h13.5V9.75'],
  create: ['M12 5.25v13.5', 'M5.25 12h13.5', 'M4.5 4.5h15v15h-15z'],
  profile: ['M12 12a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5Z', 'M4.5 20.25a7.5 7.5 0 0 1 15 0'],
}

const iconPaths = computed(() => paths[props.name] ?? paths.home)
</script>

<template>
  <svg
    class="app-icon"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    stroke-width="1.8"
    aria-hidden="true"
  >
    <path v-for="path in iconPaths" :key="path" :d="path" />
  </svg>
</template>
