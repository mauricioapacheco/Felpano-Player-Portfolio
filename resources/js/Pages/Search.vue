<script setup>
import { ref, computed } from "vue";
import SimbaLayout from "@/Layouts/SimbaLayout.vue";

// MP3 público via storage:link
const mp3Url = ref("/storage/audio/teste_iron.mp3");

// player state
const audioRef = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const error = ref("");

function fmtTime(sec) {
  if (!Number.isFinite(sec)) return "0:00";
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60).toString().padStart(2, "0");
  return `${m}:${s}`;
}

const progressMax = computed(() => (Number.isFinite(duration.value) ? duration.value : 0));

function play() {
  error.value = "";
  if (!audioRef.value) return;

  audioRef.value.play()
    .then(() => (isPlaying.value = true))
    .catch(() => {
      error.value =
        "Não consegui tocar o áudio. Confirme a URL e se o arquivo existe em storage/app/public/audio.";
    });
}

function pause() {
  if (!audioRef.value) return;
  audioRef.value.pause();
  isPlaying.value = false;
}

function stop() {
  if (!audioRef.value) return;
  audioRef.value.pause();
  audioRef.value.currentTime = 0;
  isPlaying.value = false;
}

function seek(e) {
  if (!audioRef.value) return;
  audioRef.value.currentTime = Number(e.target.value || 0);
}

function setVolume(e) {
  if (!audioRef.value) return;
  audioRef.value.volume = Number(e.target.value || 1);
}
</script>

<template>
  <SimbaLayout>
    <div class="px-6 pt-6 pb-28">
      <h1 class="text-2xl font-bold">Player MP3 (teste)</h1>
      <p class="text-sm text-white/60 mt-1">
        Tocando: <span class="text-[#F6C90E] font-semibold">{{ mp3Url }}</span>
      </p>

      <div class="mt-6 rounded-2xl border border-white/10 bg-[#0F1016] p-4">
        <audio ref="audioRef" :src="mp3Url" preload="metadata" @loadedmetadata="duration = $event.target.duration"
          @timeupdate="currentTime = $event.target.currentTime" @ended="isPlaying = false" @play="isPlaying = true"
          @pause="isPlaying = false" />

        <div class="flex flex-col md:flex-row gap-3 items-center justify-between">
          <div class="text-sm text-white/70">
            {{ fmtTime(currentTime) }} / {{ fmtTime(duration) }}
          </div>

          <div class="flex gap-2">
            <button @click="play" class="px-5 py-2 rounded-xl bg-[#F6C90E] text-black font-semibold hover:opacity-95">
              ▶ Play
            </button>
            <button @click="pause" class="px-5 py-2 rounded-xl bg-white/5 hover:bg-white/10 font-semibold">
              ⏸ Pause
            </button>
            <button @click="stop" class="px-5 py-2 rounded-xl bg-white/5 hover:bg-white/10 font-semibold">
              ⏹ Stop
            </button>
          </div>
        </div>

        <div class="mt-4">
          <input type="range" class="w-full" min="0" :max="progressMax" step="0.25" :value="currentTime"
            @input="seek" />
        </div>

        <div class="mt-4 flex items-center gap-3 justify-end">
          <div class="text-xs text-white/50">Volume</div>
          <input type="range" min="0" max="1" step="0.01" value="1" @input="setVolume" />
        </div>

        <div v-if="error" class="mt-3 text-sm text-red-300">
          {{ error }}
        </div>
      </div>
    </div>
  </SimbaLayout>
</template>
