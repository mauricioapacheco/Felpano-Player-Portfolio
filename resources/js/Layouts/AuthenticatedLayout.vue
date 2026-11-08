<script setup>
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const user = computed(() => page.props?.auth?.user);
</script>

<template>
  <div class="min-h-screen">
    <header class="border-b px-4 py-3">
      <div class="max-w-5xl mx-auto flex items-center justify-between">
        <div class="font-semibold">FELPANO</div>
        <div class="text-sm opacity-80" v-if="user">Olá, {{ user.name }}</div>
      </div>
    </header>

    <main class="max-w-5xl mx-auto p-4">
      <slot />
    </main>
  </div>
</template>