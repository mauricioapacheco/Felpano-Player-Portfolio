<script setup>
import { ref, computed, onMounted } from "vue";

const props = defineProps({
  title: { type: String, default: "Nenhuma música tocando" },
  artist: { type: String, default: "" },
  // opcional: você pode passar a URL por prop também
  src: { type: String, default: "" },
});

// URL padrão do seu teste (storage:link)
const localUrl = ref(props.src || "/storage/audio/teste_iron.mp3");

const audioRef = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const error = ref("");

const canSeek = computed(() => Number.isFinite(duration.value) && duration.value > 0);

function fmtTime(sec) {
  if (!Number.isFinite(sec)) return "0:00";
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60).toString().padStart(2, "0");
  return `${m}:${s}`;
}

function play() {
  error.value = "";
  if (!audioRef.value) return;

  audioRef.value
    .play()
    .then(() => (isPlaying.value = true))
    .catch(() => {
      error.value =
        "Não consegui tocar. Confirme se o arquivo existe e a URL abre no navegador.";
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
  if (!audioRef.value || !canSeek.value) return;
  audioRef.value.currentTime = Number(e.target.value || 0);
}

function setVolume(e) {
  if (!audioRef.value) return;
  audioRef.value.volume = Number(e.target.value || 1);
}

function onLoadedMeta(e) {
  duration.value = e.target.duration || 0;
}

function onTimeUpdate(e) {
  currentTime.value = e.target.currentTime || 0;
}

function onEnded() {
  isPlaying.value = false;
}

onMounted(() => {
  // garante que o src vindo por prop atualize o localUrl no primeiro load
  if (props.src) localUrl.value = props.src;
});
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 border-t border-white/10 bg-[#0B0B0F]/95 backdrop-blur z-50">
    <div class="max-w-[1400px] mx-auto px-4 py-3 flex flex-col gap-3">

      <!-- Linha principal -->
      <div class="flex flex-col md:flex-row md:items-center gap-3 md:gap-4">
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold truncate">{{ props.title }}</div>
          <div class="text-xs text-white/60 truncate">{{ props.artist }}</div>
          <div class="text-[11px] text-white/40 truncate">
            {{ localUrl }}
          </div>
        </div>

        <!-- Controles -->
        <div class="flex-[2] flex flex-col md:flex-row gap-2">
          <input
            v-model="localUrl"
            placeholder="Cole a URL do MP3 local... ex: /storage/audio/teste_iron.mp3"
            class="w-full bg-[#15151D] border border-white/10 rounded-xl px-3 py-2 text-sm outline-none focus:border-[#F6C90E]/70"
          />

          <div class="flex gap-2">
            <button
              @click="play"
              class="px-4 py-2 rounded-xl bg-[#F6C90E] text-black font-semibold hover:opacity-95"
            >
              {{ isPlaying ? "⏸ Pausar" : "▶ Tocar" }}
            </button>

            <button
              v-if="isPlaying"
              @click="pause"
              class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 font-semibold"
            >
              ⏸ Pause
            </button>

            <button
              @click="stop"
              class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 font-semibold"
            >
              ⏹ Parar
            </button>

            
          </div>
        </div>
      </div>

      <!-- Barra de progresso + volume -->
      <div class="flex flex-col md:flex-row md:items-center gap-3">
        <div class="text-xs text-white/60 min-w-[110px]">
          {{ fmtTime(currentTime) }} / {{ fmtTime(duration) }}
        </div>

        <input
          type="range"
          class="w-full"
          min="0"
          :max="duration || 0"
          step="0.25"
          :value="currentTime"
          @input="seek"
          :disabled="!canSeek"
        />

        <div class="flex items-center gap-2 justify-end min-w-[180px]">
          <div class="text-xs text-white/50">Vol</div>
          <input type="range" min="0" max="1" step="0.01" value="1" @input="setVolume" />
        </div>
      </div>

      <div v-if="error" class="text-xs text-red-300">
        {{ error }}
      </div>

      <!-- Áudio real -->
      <audio
        ref="audioRef"
        :src="localUrl"
        preload="metadata"
        @loadedmetadata="onLoadedMeta"
        @timeupdate="onTimeUpdate"
        @ended="onEnded"
      />
    </div>
  </div>
</template>
