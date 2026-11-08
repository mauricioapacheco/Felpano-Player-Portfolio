<script setup>
import { ref, computed, onMounted, nextTick, watch, onBeforeUnmount } from "vue";
import { usePage, router } from "@inertiajs/vue3";

/* ----------------------------
   AUTH (Inertia)
-----------------------------*/
const pageProps = usePage().props;
const isLogged = computed(() => !!pageProps?.auth?.user);
const userName = computed(() => pageProps?.auth?.user?.name ?? "Usuário");
const authMsg = ref("");
const showPassword = ref(false);

/* ----------------------------
   APAGAR MÚSICA (MODAL + ACTION)
-----------------------------*/
const deletingTrack = ref(false);
const showDeleteModal = ref(false);

function confirmDeleteTrack() {
  if (!current.value?.file || deletingTrack.value) return;
  showDeleteModal.value = true;
}

function cancelDelete() {
  if (deletingTrack.value) return;
  showDeleteModal.value = false;
}

async function confirmDelete() {
  if (!current.value?.file || deletingTrack.value) return;

  deletingTrack.value = true;

  // ✅ guarda qual faixa vem depois (antes de remover do array)
  const curId = current.value?.id;
  const idxEff = effectiveIndex.value; // você já tem isso no shuffle
  const nextCandidate =
    idxEff >= 0 ? (effectiveList.value[idxEff + 1] || effectiveList.value[idxEff - 1] || null) : null;

  try {
    const res = await fetch("/audio/delete", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
      },
      body: JSON.stringify({
        file_name: current.value.file, // <- bate com DB: tracks.file_name
      }),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data?.message || "Erro ao deletar música.");

    const deletedFile = current.value.file;

    // ✅ remove do array principal (playList é computed de tracks)
    tracks.value = tracks.value.filter((t) => t.file !== deletedFile);

    showToast("Música deletada!", "ok");
    showDeleteModal.value = false;

    // ✅ se deletou a faixa atual, decide o que tocar
    if (curId && current.value?.id === curId) {
      stop();

      if (nextCandidate) {
        // encontra índice real na playList (base)
        const realIdx = playList.value.findIndex((x) => x.id === nextCandidate.id);
        if (realIdx >= 0) {
          await goToIndex(realIdx); // já toca
        } else {
          current.value = null;
        }
      } else {
        current.value = null;
        isPlaying.value = false;
        currentTime.value = 0;
        duration.value = 0;
      }
    }
  } catch (e) {
    console.error(e);
    showToast(e?.message || "Erro ao deletar música.", "err");
  } finally {
    deletingTrack.value = false;
  }
}

/* ----------------------------
   MODAL TERMOS DE USO
-----------------------------*/
const showTermsModal = ref(false);

/* ----------------------------
   TOAST
-----------------------------*/
const toast = ref({ show: false, text: "", type: "ok" });
let toastTimer = null;

function showToast(text, type = "ok") {
  toast.value = { show: true, text, type };
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => (toast.value.show = false), 2500);
}

/* ----------------------------
   SIDEBAR / BUSCA
-----------------------------*/
const search = ref("");
const sidebarOpen = ref(false);


/* ----------------------------
   PLANO / PIX (UI)
-----------------------------*/
const pixKey = "comercial@casadaweb.net";
const pixAmount = 5.0;

const planActive = computed(() => !!pageProps?.plan?.active);
const showPixPay = computed(() => false);

async function copyPixKey() {
  try {
    await navigator.clipboard.writeText(pixKey);
    showToast("Chave PIX copiada!", "ok");
  } catch (e) {
    showToast("Não consegui copiar. Copie manualmente.", "err");
  }
}

let planTimer = null;

async function checkPlan() {
  if (!isLogged.value) return;
  try {
    await fetch("/plan/check", { headers: { Accept: "application/json" } });
  } catch (e) {
    console.error("Erro ao verificar plano", e);
  }
}

const confirmingPaid = ref(false);

function confirmPaid() {
  if (confirmingPaid.value) return;
  confirmingPaid.value = true;

  router.post(
    "/plan/confirm-pix",
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        showToast("Acesso liberado! ✅", "ok");
        router.visit("/", { replace: true, preserveScroll: true });
      },
      onError: () => showToast("Falha ao liberar. Tente novamente.", "err"),
      onFinish: () => (confirmingPaid.value = false),
    }
  );
}

/* ----------------------------
   AUTH UI (abas)
-----------------------------*/
const authTab = ref("login"); // "login" | "register"

/* ----------------------------
   LOGIN FORM
-----------------------------*/
const loginForm = ref({
  email: "",
  password: "",
  remember: false,
});
const loginLoading = ref(false);
const loginError = ref("");

function submitLogin() {
  loginError.value = "";
  authMsg.value = "";
  loginLoading.value = true;

  router.post("/login", loginForm.value, {
    preserveScroll: true,
    onError: (errors) => {
      loginError.value = errors?.email || errors?.password || "Credenciais inválidas.";
    },
    onSuccess: () => {
      router.visit("/", { replace: true });
    },
    onFinish: () => (loginLoading.value = false),
  });
}

/* ----------------------------
   REGISTER FORM
-----------------------------*/
const registerForm = ref({
  name: "",
  email: "",
  telefone: "",
  cpf: "",
  password: "",
  password_confirmation: "",
});
const registerLoading = ref(false);
const registerError = ref("");

function onlyDigits(v = "") {
  return String(v).replace(/\D+/g, "");
}

function maskTelefone(v = "") {
  const d = onlyDigits(v).slice(0, 11);
  if (!d) return "";
  if (d.length <= 2) return `(${d}`;
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

function maskCpf(v = "") {
  const d = onlyDigits(v).slice(0, 11);
  const a = d.slice(0, 3);
  const b = d.slice(3, 6);
  const c = d.slice(6, 9);
  const e = d.slice(9, 11);
  let out = a;
  if (b) out += `.${b}`;
  if (c) out += `.${c}`;
  if (e) out += `-${e}`;
  return out;
}

watch(
  () => registerForm.value.telefone,
  (val) => {
    const m = maskTelefone(val);
    if (val !== m) registerForm.value.telefone = m;
  }
);

watch(
  () => registerForm.value.cpf,
  (val) => {
    const m = maskCpf(val);
    if (val !== m) registerForm.value.cpf = m;
  }
);

function submitRegister() {
  registerError.value = "";
  authMsg.value = "";
  registerLoading.value = true;

  router.post("/register", registerForm.value, {
    preserveScroll: true,
    onError: (errors) => {
      registerError.value =
        errors?.name ||
        errors?.email ||
        errors?.telefone ||
        errors?.cpf ||
        errors?.password ||
        errors?.password_confirmation ||
        "Falha ao cadastrar.";
    },
    onSuccess: () => {
      authMsg.value = "Cadastro realizado com sucesso! Agora faça login.";
      authTab.value = "login";

      registerForm.value = {
        name: "",
        email: "",
        telefone: "",
        cpf: "",
        password: "",
        password_confirmation: "",
      };
    },
    onFinish: () => (registerLoading.value = false),
  });
}

/* ----------------------------
   LOGOUT
-----------------------------*/
function logout() {
  router.post("/logout", {}, {
    preserveScroll: true,
    onSuccess: () => {
      showToast("Você saiu da conta.", "ok");
      sidebarOpen.value = false;
      router.visit("/", { replace: true });
    },
    onError: () => showToast("Não foi possível sair. Tente novamente.", "err"),
  });
}

/* ----------------------------
   DATA FORMAT
-----------------------------*/
function fmtDateBR(v) {
  if (!v) return "";
  const d = new Date(String(v).replace(" ", "T"));
  if (isNaN(d)) return v;

  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const yyyy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2, "0");
  const mi = String(d.getMinutes()).padStart(2, "0");

  return `${dd}/${mm}/${yyyy} - ${hh}:${mi} Hs`;
}

/* ----------------------------
   TRACKS
-----------------------------*/
const tracks = ref([]);
const loadingTracks = ref(false);
const tracksError = ref("");

async function fetchTracks() {
  if (!isLogged.value) return;

  loadingTracks.value = true;
  tracksError.value = "";

  try {
    const res = await fetch("/audio/tracks", { headers: { Accept: "application/json" } });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.error || "Falha ao buscar tracks.");

    tracks.value = (data.tracks || []).map((t) => ({
      ...t,
      duration: "--:--",
    }));

    loadAllDurations();
  } catch (e) {
    tracksError.value = e?.message || "Erro ao carregar a biblioteca.";
  } finally {
    loadingTracks.value = false;
  }
}

/* ----------------------------
   DURAÇÃO
-----------------------------*/
function fmtTime(sec) {
  if (!Number.isFinite(sec)) return "--:--";
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60).toString().padStart(2, "0");
  return `${m}:${s}`;
}

function loadAllDurations() {
  tracks.value.forEach((t) => {
    const audio = new Audio(t.src);
    audio.preload = "metadata";
    audio.onloadedmetadata = () => {
      t.duration = fmtTime(audio.duration);
    };
  });
}

/* ----------------------------
   PAGINAÇÃO 150 em 50 + SCROLL NA PLAYLIST
-----------------------------*/
const page = ref(1);
const perPage = ref(50);      // ✅ 50 por página
const maxItems = ref(150);    // ✅ limita a 150 no total
const scrollBodyRef = ref(null);

const filteredTracks = computed(() => {
  if (!isLogged.value) return [];
  const q = search.value.trim().toLowerCase();
  if (!q) return tracks.value;

  return tracks.value.filter((t) =>
    `${t.title} ${t.artist ?? ""} ${t.file}`.toLowerCase().includes(q)
  );
});

// ✅ aplica limite 150 (depois do filtro)
const limitedTracks = computed(() => filteredTracks.value.slice(0, maxItems.value));

const totalPages = computed(() => Math.max(1, Math.ceil(limitedTracks.value.length / perPage.value)));

const paginatedTracks = computed(() => {
  const start = (page.value - 1) * perPage.value;
  return limitedTracks.value.slice(start, start + perPage.value);
});

function nextPage() {
  if (page.value < totalPages.value) page.value++;
}
function prevPage() {
  if (page.value > 1) page.value--;
}
function goToPage(p) {
  page.value = p;
}

const pages = computed(() => Array.from({ length: totalPages.value }, (_, i) => i + 1));

watch(page, async () => {
  await nextTick();
  if (scrollBodyRef.value) scrollBodyRef.value.scrollTop = 0;
});

watch(search, async () => {
  if (!isLogged.value) return;
  page.value = 1;
  await nextTick();
  if (scrollBodyRef.value) scrollBodyRef.value.scrollTop = 0;
});

watch(totalPages, (tp) => {
  if (page.value > tp) page.value = tp;
});

/* ----------------------------
   PLAYER
-----------------------------*/
const current = ref(null);
const audioRef = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playerError = ref("");

const canSeek = computed(() => Number.isFinite(duration.value) && duration.value > 0);

function load(track, autoPlay = true) {
  playerError.value = "";
  if (!track?.src) return;

  current.value = track;
  if (shuffleOn.value) rebuildShuffle();

  nextTick(() => {
    if (!audioRef.value) return;

    // ✅ força recarregar a mídia
    audioRef.value.pause();
    audioRef.value.currentTime = 0;
    audioRef.value.src = track.src;   // garante troca real do src
    audioRef.value.load();            // força carregar

    currentTime.value = 0;
    duration.value = 0;

    if (autoPlay) {
      audioRef.value.play()
        .then(() => (isPlaying.value = true))
        .catch(() => {
          isPlaying.value = false;
          playerError.value = "Não consegui tocar o áudio.";
        });
    }
  });
}

function play() {
  if (!audioRef.value) return;
  audioRef.value.play().then(() => (isPlaying.value = true));
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

function togglePlay() {
  isPlaying.value ? pause() : play();
}

function toggleRow(t) {
  // mesma regra do botão play/pause da linha
  if (!current.value || current.value.id !== t.id) {
    load(t, true); // carrega e toca
    return;
  }
  togglePlay(); // se é a mesma, alterna play/pause
}

function onLoadedMeta(e) {
  duration.value = e.target.duration || 0;
}

function onTimeUpdate(e) {
  currentTime.value = e.target.currentTime || 0;
}

// ✅ playlist base = lista filtrada + limitada (respeita busca e limite 150)
const playList = computed(() => limitedTracks.value);

// índice do "current" dentro da playlist (usa id; sem chave)
const currentIndex = computed(() => {
  if (!current.value) return -1;
  return playList.value.findIndex((x) => x.id === current.value.id);
});

// vai para um índice da playlist e já toca
async function goToIndex(idx) {
  const list = playList.value;
  if (idx < 0 || idx >= list.length) return;

  // ✅ muda a página para a faixa existir na lista atual (sem mudar layout)
  page.value = Math.floor(idx / perPage.value) + 1;

  await nextTick();
  if (scrollBodyRef.value) scrollBodyRef.value.scrollTop = 0;

  load(list[idx], true);
}

function nextTrack() {
  const idx = effectiveIndex.value;
  if (idx === -1) return;

  const nextIdx = idx + 1;
  if (nextIdx >= effectiveList.value.length) {
    isPlaying.value = false;
    return;
  }

  // pega o índice real dentro da playList original e usa teu goToIndex
  const nextTrackObj = effectiveList.value[nextIdx];
  const realIdx = playList.value.findIndex(x => x.id === nextTrackObj.id);
  if (realIdx >= 0) goToIndex(realIdx);
}

function prevTrack() {
  const idx = effectiveIndex.value;
  if (idx === -1) return;

  const prevIdx = idx - 1;
  if (prevIdx < 0) return;

  const prevTrackObj = effectiveList.value[prevIdx];
  const realIdx = playList.value.findIndex(x => x.id === prevTrackObj.id);
  if (realIdx >= 0) goToIndex(realIdx);
}

function onEnded() {
  isPlaying.value = false;
  nextTrack(); // ✅ terminou -> vai pra próxima e toca
}

function seek(e) {
  if (!audioRef.value || !canSeek.value) return;
  audioRef.value.currentTime = Number(e.target.value || 0);
}

function setVolume(e) {
  if (!audioRef.value) return;
  audioRef.value.volume = Number(e.target.value || 1);
}

function toggleFromList(t) {
  if (!current.value || current.value.id !== t.id) {
    load(t, true);
    return;
  }
  togglePlay();
}

/* ----------------------------
   SHUFFLE (RANDOM)
-----------------------------*/
const shuffleOn = ref(false);

// guarda a ordem embaralhada (lista de ids) baseada na playList atual
const shuffledIds = ref([]);

// helper: embaralhar (Fisher–Yates)
function shuffleArray(arr) {
  const a = arr.slice();
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [a[i], a[j]] = [a[j], a[i]];
  }
  return a;
}

// cria uma ordem embaralhada a partir da playList (mantém a atual como primeira, se existir)
function rebuildShuffle() {
  const list = playList.value || [];
  if (!list.length) {
    shuffledIds.value = [];
    return;
  }

  const ids = list.map(t => t.id);

  // remove o current pra colocar ele no começo
  const curId = current.value?.id;
  const rest = curId ? ids.filter(id => id !== curId) : ids;

  shuffledIds.value = curId
    ? [curId, ...shuffleArray(rest)]
    : shuffleArray(ids);
}

// índice do current dentro da ordem embaralhada
const shuffleIndex = computed(() => {
  if (!shuffleOn.value || !current.value) return -1;
  return shuffledIds.value.indexOf(current.value.id);
});

// playlist efetiva (normal ou embaralhada)
const effectiveList = computed(() => {
  const list = playList.value || [];
  if (!shuffleOn.value) return list;

  // monta a lista pela ordem embaralhada
  const map = new Map(list.map(t => [t.id, t]));
  return shuffledIds.value.map(id => map.get(id)).filter(Boolean);
});

// índice do current na lista efetiva
const effectiveIndex = computed(() => {
  if (!current.value) return -1;
  return effectiveList.value.findIndex(x => x.id === current.value.id);
});

function toggleShuffle() {
  shuffleOn.value = !shuffleOn.value;
  if (shuffleOn.value) rebuildShuffle();
  showToast(shuffleOn.value ? "Shuffle ligado!" : "Shuffle desligado", "ok");
}

// quando a playlist mudar (busca / coletar / paginação afetando limitedTracks), refaz shuffle se estiver ligado
watch(
  () => playList.value.map(t => t.id).join(","), // leve e suficiente
  () => {
    if (shuffleOn.value) rebuildShuffle();
  }
);

/* ----------------------------
   LIFECYCLE
-----------------------------*/
onMounted(async () => {
  if (!isLogged.value) return;

  checkPlan();

  // (mantive “sem mexer muito”)
  planTimer = setInterval(checkPlan, 100000);

  await fetchTracks();

  await nextTick();
  if (scrollBodyRef.value) scrollBodyRef.value.scrollTop = 0;
});

onBeforeUnmount(() => {
  if (planTimer) {
    clearInterval(planTimer);
    planTimer = null;
  }
});

watch(isLogged, async (logged) => {
  if (logged) {
    authTab.value = "login";
    loginError.value = "";
    registerError.value = "";

    await fetchTracks();

    page.value = 1;
    await nextTick();
    if (scrollBodyRef.value) scrollBodyRef.value.scrollTop = 0;
    return;
  }

  search.value = "";
  page.value = 1;

  tracks.value = [];
  current.value = null;
  isPlaying.value = false;
  currentTime.value = 0;
  duration.value = 0;
  playerError.value = "";

  if (audioRef.value) {
    audioRef.value.pause();
    audioRef.value.currentTime = 0;
  }
});
</script>

<template>
  <div class="min-h-screen bg-[#0B0B0F] text-white">

    <!-- TOAST -->
    <div v-if="toast.show" class="fixed top-4 right-4 z-[9999] px-4 py-3 rounded-2xl border shadow-lg backdrop-blur"
      :class="toast.type === 'err'
        ? 'bg-red-500/20 border-red-400/30 text-red-100'
        : 'bg-green-500/20 border-green-400/30 text-green-100'">
      {{ toast.text }}
    </div>

    <div class="flex h-screen overflow-hidden">

      <!-- OVERLAY -->
      <div v-if="sidebarOpen" class="fixed inset-0 z-20 bg-black/60 backdrop-blur-sm" @click="sidebarOpen = false">
      </div>

      <!-- SIDEBAR -->
      <aside
        class="fixed inset-y-0 left-0 z-30 w-64 bg-[#0F1016] border-r border-white/10 flex flex-col transition-transform duration-300 ease-out"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="p-5 border-b border-white/10 flex items-center justify-between">
          <div class="flex items-center gap-3 min-w-0">
            <div class="h-9 w-9 rounded-xl bg-[#F6C90E] flex items-center justify-center">
              <span class="text-black font-black">F</span>
            </div>

            <div class="min-w-0">
              <div class="font-bold leading-5 truncate">
                {{ isLogged ? userName : "Visitante" }}
              </div>
              <div class="text-xs text-white/60 truncate">Acervo: {{ tracks.length }} / 200</div>
            </div>
          </div>

          <button class="h-9 w-9 rounded-xl hover:bg-white/10 flex items-center justify-center transition"
            @click="sidebarOpen = false" title="Ocultar menu">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="none">
              <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
          </button>
        </div>

        <nav class="px-3 py-3 space-y-1">
          <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 cursor-pointer transition">
            <span class="h-2 w-2 rounded-full bg-[#F6C90E]"></span>
            <span class="text-sm">Início</span>
          </a>

          <button type="button" @click="showTermsModal = true"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 cursor-pointer transition text-left">

            <span class="h-2 w-2 rounded-full bg-white/40"></span>
            <span class="text-sm">Termos de Uso</span>

          </button>

          <button v-if="isLogged" type="button" @click="logout"
            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-white/5 cursor-pointer transition text-left">
            <span class="h-2 w-2 rounded-full bg-red-400"></span>
            <span class="text-sm">Sair</span>
          </button>
        </nav>

        <div class="p-4 border-t border-white/10 mt-auto">
          <button
            class="w-full mt-2 bg-white/5 border border-white/10 text-white font-semibold py-2 rounded-xl hover:bg-white/10 transition"
            @click="sidebarOpen = false">
            Fechar menu
          </button>
        </div>
      </aside>

      <!-- MAIN -->
      <main class="flex-1 flex flex-col overflow-hidden min-h-0">

        <!-- MODAL TERMOS -->
        <div v-if="showTermsModal"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm">

          <div class="bg-[#111827] border border-white/10 rounded-2xl w-[90%] max-w-lg p-6">

            <h2 class="text-lg font-bold mb-4">Termos de Uso</h2>

            <div
              class="text-xs text-white/70 space-y-3 leading-relaxed max-h-[60vh] overflow-y-auto w-[98%] mx-auto pr-2">

              <p>
                Ao utilizar este aplicativo, o usuário concorda que é o único responsável
                pelo conteúdo armazenado em seu acervo pessoal.
              </p>

              <p>
                O sistema funciona apenas como uma ferramenta de organização e reprodução
                de arquivos de áudio. A plataforma não hospeda, distribui ou incentiva
                a violação de direitos autorais.
              </p>

              <p>
                Todo conteúdo adicionado ao acervo é de responsabilidade exclusiva
                do usuário, incluindo a origem, legalidade e direitos de uso
                dos arquivos armazenados.
              </p>

              <p>
                Este aplicativo não possui qualquer responsabilidade sobre os
                arquivos de áudio armazenados pelos usuários, incluindo
                possíveis violações de direitos autorais ou uso indevido de conteúdo.
              </p>

              <p>
                O usuário concorda em utilizar a plataforma apenas para uso
                pessoal e privado, sendo proibida a redistribuição pública
                ou comercial de conteúdos protegidos por direitos autorais.
              </p>

              <p>
                A plataforma pode remover conteúdos ou limitar funcionalidades
                caso identifique uso indevido ou violação destes termos.
              </p>

              <p class="text-white/50">
                Ao continuar utilizando o aplicativo, você declara estar de acordo
                com estes termos e assume total responsabilidade pelos conteúdos
                armazenados em seu acervo.
              </p>

            </div>

            <div class="mt-6 flex justify-end">
              <button @click="showTermsModal = false"
                class="bg-[#F6C90E] text-black px-4 py-2 rounded-xl font-semibold hover:brightness-110">
                Fechar
              </button>
            </div>

          </div>

        </div>

        <!-- MODAL DELETE -->
        <div v-if="showDeleteModal"
          class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 backdrop-blur">
          <div class="w-[360px] bg-[#0F1016] border border-white/10 rounded-2xl p-6 shadow-xl">

            <div class="flex items-center gap-3 mb-4">
              <div class="h-10 w-10 rounded-xl bg-red-600 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-6 h-6 text-white"
                  fill="currentColor">
                  <path
                    d="M12 2L1 21h22L12 2zm0 6c.55 0 1 .45 1 1v5a1 1 0 11-2 0V9c0-.55.45-1 1-1zm0 10a1.25 1.25 0 110-2.5A1.25 1.25 0 0112 18z" />
                </svg>
              </div>

              <div>
                <div class="font-semibold">Excluir música</div>
                <div class="text-xs text-white/60">Essa ação não pode ser desfeita!</div>
              </div>
            </div>

            <div class="text-sm text-white/80 mb-5">
              Deseja remover do acervo:
              <div class="font-semibold mt-1 text-white">
                {{ current?.title }}
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button @click="cancelDelete"
                class="px-4 py-2 rounded-xl border border-white/10 hover:bg-white/5 text-sm">
                Cancelar
              </button>

              <button @click="confirmDelete"
                class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white text-sm font-semibold">
                Apagar
              </button>
            </div>

          </div>
        </div>

        <!-- TOPBAR -->
        <header class="sticky top-0 z-10 bg-[#0B0B0F]/90 backdrop-blur border-b border-white/10">

          <div
            class="w-full bg-gradient-to-b from-[#111827]/90 to-[#111827]/70 px-6 py-5 flex items-center gap-3 backdrop-blur-md border-b border-white/10 shadow-lg">

            <img src="/images/felpa_branco.png" alt="Logo" class="h-10 w-auto" />

            <div class="flex flex-col leading-tight">
              <span class="text-white font-extrabold tracking-widest text-lg md:text-xl"
                style="text-shadow: 0 0 12px rgba(246,201,14,0.35);">
                FELPANO
              </span>

              <span class="text-xs text-white/60 tracking-widest">
                Multiplayer
              </span>
            </div>

            <!-- MENU -->
            <button
              class="h-10 w-10 rounded-2xl bg-white/10 hover:bg-white/15 border border-white/10 flex items-center justify-center"
              @click="sidebarOpen = !sidebarOpen" title="Menu">

              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">

                <path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h16v2H4z" />
              </svg>

            </button>

          </div>

          <!-- BUSCAS: SÓ LOGADO -->
          <div v-if="isLogged" class="px-6 py-4 flex items-start gap-4">
            <div class="flex-1">

              <!-- PLANO PIX -->
              <div v-if="showPixPay" class="mb-4 rounded-2xl border border-white/10 bg-white/5 p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="text-sm font-semibold">Plano mensal — R$ 5,00 / mês</div>
                    <div class="text-xs text-white/70 mt-1">
                      Faça o pagamento via PIX para liberar ou renovar o acesso.
                    </div>
                  </div>
                </div>

                <div class="grid md:grid-cols-3 gap-3">
                  <div class="rounded-xl border border-white/10 bg-black/20 p-3">
                    <div class="text-[11px] text-white/60">Valor</div>
                    <div class="text-sm font-semibold mt-1">R$ {{ pixAmount.toFixed(2) }} / mês</div>
                  </div>

                  <div class="rounded-xl border border-white/10 bg-black/20 p-3 flex items-center gap-2 md:col-span-2">
                    <button type="button" @click="copyPixKey"
                      class="flex-1 px-3 py-2 rounded-xl bg-[#F6C90E] text-black font-semibold hover:opacity-95">
                      Copiar chave PIX
                    </button>

                    <button type="button" @click="confirmPaid" :disabled="confirmingPaid"
                      class="px-3 py-2 rounded-xl border border-white/10 hover:bg-white/5 text-xs text-white/80 disabled:opacity-50 disabled:cursor-not-allowed">
                      {{ confirmingPaid ? "Liberando..." : "Confirmar Pagamento" }}
                    </button>
                  </div>
                </div>

                <div class="text-xs text-white/60">Após o pagamento, seu acesso será renovado por 30 dias.</div>
              </div>

              <div class="relative mt-4" v-if="planActive">
                <input v-model="search" type="text" placeholder="Buscar músicas, artistas, arquivos..."
                  class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
              </div>
            </div>
          </div>

          <!-- CADASTRO: NÃO LOGADO -->
          <div v-else class="px-6 py-10">
            <div class="max-w-md mx-auto rounded-2xl border border-white/10 bg-white/5 p-6">
              <div class="flex items-center gap-2">
                <button class="flex-1 py-2 rounded-xl border text-sm font-semibold transition" :class="authTab === 'login'
                  ? 'bg-[#F6C90E] text-black border-[#F6C90E]'
                  : 'bg-transparent text-white border-white/10 hover:bg-white/5'" @click="authTab = 'login'"
                  type="button">
                  Entrar
                </button>

                <button class="flex-1 py-2 rounded-xl border text-sm font-semibold transition" :class="authTab === 'register'
                  ? 'bg-[#F6C90E] text-black border-[#F6C90E]'
                  : 'bg-transparent text-white border-white/10 hover:bg-white/5'" @click="authTab = 'register'"
                  type="button">
                  Cadastrar
                </button>
              </div>

              <!-- LOGIN -->
              <div v-if="authTab === 'login'" class="mt-5">
                <div class="text-xl font-semibold">Entrar</div>
                <div class="mt-1 text-sm text-white/60">Faça login para acessar o acervo.</div>

                <div v-if="authMsg" class="mt-3 text-sm text-green-300">
                  {{ authMsg }}
                </div>

                <form class="mt-5 space-y-3" @submit.prevent="submitLogin">
                  <input v-model="loginForm.email" type="email" placeholder="E-mail"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <div class="relative">
                    <input v-model="loginForm.password" :type="showPassword ? 'text' : 'password'" placeholder="Senha"
                      class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 pr-11 text-sm outline-none focus:border-[#F6C90E]/70" />

                    <button type="button" @click="showPassword = !showPassword"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-200 transition">
                      <!-- olho aberto -->
                      <svg v-if="!showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5
           c4.477 0 8.268 2.943 9.542 7
           -1.274 4.057-5.065 7-9.542 7
           -4.477 0-8.268-2.943-9.542-7z" />
                      </svg>

                      <!-- olho fechado -->
                      <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19
           c-4.478 0-8.27-2.943-9.544-7
           a9.956 9.956 0 012.042-3.368M6.223 6.223
           A9.953 9.953 0 0112 5c4.478 0 8.27 2.943 9.544 7
           a9.955 9.955 0 01-4.132 5.411M15 12a3 3 0 00-3-3
           m0 0a3 3 0 00-2.121.879M3 3l18 18" />
                      </svg>
                    </button>
                  </div>

                  <button type="submit" :disabled="loginLoading"
                    class="w-full px-4 py-2 rounded-2xl bg-[#F6C90E] text-black font-semibold hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ loginLoading ? "Entrando..." : "Entrar" }}
                  </button>

                  <div v-if="loginError" class="text-xs text-red-300">{{ loginError }}</div>
                </form>
              </div>

              <!-- REGISTRO -->
              <div v-else class="mt-5">
                <div class="text-xl font-semibold">Cadastrar</div>
                <div class="mt-1 text-sm text-white/60">Crie sua conta para acessar o acervo.</div>

                <form class="mt-5 space-y-3" @submit.prevent="submitRegister">
                  <input v-model="registerForm.name" type="text" placeholder="Nome"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <input v-model="registerForm.email" type="email" placeholder="E-mail"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <input v-model="registerForm.telefone" type="tel" inputmode="tel" maxlength="15"
                    placeholder="Telefone (ex: (11) 98765-4321)"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <input v-model="registerForm.cpf" type="text" inputmode="numeric" maxlength="14"
                    placeholder="CPF (ex: 000.000.000-00)"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <input v-model="registerForm.password" type="password" placeholder="Senha"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />
                  <input v-model="registerForm.password_confirmation" type="password" placeholder="Confirmar senha"
                    class="w-full bg-[#15151D] border border-white/10 rounded-2xl px-4 py-2 text-sm outline-none focus:border-[#F6C90E]/70" />

                  <button type="submit" :disabled="registerLoading"
                    class="w-full px-4 py-2 rounded-2xl bg-[#F6C90E] text-black font-semibold hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ registerLoading ? "Cadastrando..." : "Cadastrar" }}
                  </button>

                  <div v-if="registerError" class="text-xs text-red-300">{{ registerError }}</div>
                </form>
              </div>

            </div>
          </div>

        </header>

        <!-- LISTA COM SCROLL -->
        <div class="mt-2 border-white/10 overflow-hidden flex flex-col flex-1 min-h-0">

          <!-- BODY (SCROLL) -->
          <div ref="scrollBodyRef" class="overflow-y-auto flex-1 min-h-0">
            <div v-for="t in paginatedTracks" :key="t.id"
              class="w-full grid grid-cols-12 px-4 py-3 hover:bg-white/5 border-t border-white/5 items-center cursor-pointer select-none"
              :class="current?.id === t.id ? 'bg-white/5' : ''" @click="toggleRow(t)" title="Clique para tocar/pausar">

              <div class="col-span-1">
                <button @click.stop="toggleRow(t)"
                  class="h-9 w-9 rounded-xl flex items-center justify-center transition text-white"
                  :class="current?.id === t.id && isPlaying ? 'bg-red-500 hover:bg-red-400' : 'bg-green-500 hover:bg-green-400'">
                  <svg v-if="current?.id === t.id && isPlaying" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                    class="w-4 h-4" fill="currentColor">
                    <rect x="6" y="4" width="4" height="16" rx="1" />
                    <rect x="14" y="4" width="4" height="16" rx="1" />
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4 ml-[2px]"
                    fill="currentColor">
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </button>
              </div>

              <div class="col-span-11 text-left flex items-center gap-3">
                <div class="h-1 w-1 rounded-xl bg-white/10 border border-white/10"></div>
                <div class="min-w-0">
                  <div class="text-sm font-medium truncate">{{ t.title }}</div>
                  <div class="text-xs text-white/50 truncate">
                    {{ t.artist }} -- {{ t.duration }} mins
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- PLAYER -->
        <footer v-if="isLogged && planActive"
          class="fixed bottom-0 left-0 right-0 z-20 border-t border-white/10 bg-[#0B0B0F]/95 backdrop-blur">
          <div class="px-3 sm:px-6 py-2 sm:py-3 flex flex-col gap-2 sm:gap-3">
            <div class="flex items-center gap-2 sm:gap-4">
              <!-- ESQUERDA (encolhe no mobile) -->
              <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 sm:flex-none sm:min-w-[260px]">
                <div class="hidden sm:block h-12 w-12 rounded-2xl bg-white/10 border border-white/10"></div>

                <div class="leading-4 min-w-0">
                  <div class="text-xs sm:text-sm font-semibold truncate max-w-[160px] sm:max-w-[180px]">
                    {{ current?.title ?? "Nada tocando" }}
                  </div>
                  <div class="text-[10px] sm:text-xs text-white/60 truncate max-w-[160px] sm:max-w-[180px]">
                    {{ current?.artist ?? "-" }}
                  </div>
                </div>
              </div>

              <!-- CONTROLES (compacto no mobile) -->
              <div class="flex-1 flex items-center justify-center gap-2 sm:gap-3">
                <!-- ANTERIOR -->
                <button @click="prevTrack" :disabled="currentIndex <= 0"
                  class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl flex items-center justify-center transition text-white bg-white/10 hover:bg-white/15 border border-white/10 disabled:opacity-40 disabled:cursor-not-allowed"
                  title="Anterior">
                  ‹
                </button>

                <!-- SHUFFLE -->
                <button @click="toggleShuffle"
                  class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl flex items-center justify-center transition border border-white/10"
                  :class="shuffleOn ? 'bg-[#F6C90E] text-black' : 'bg-white/10 text-white hover:bg-white/15'"
                  title="Aleatório (Shuffle)">
                  <!-- ícone shuffle -->
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <path
                      d="M17 3h4v4h-2V5.41l-3.3 3.3-1.4-1.42L17.59 4H17V3zM3 7h4.59l3.7 3.7-1.42 1.42L6.76 9H3V7zm0 10v-2h3.76l3.11-3.11 1.42 1.42L7.59 17H3zm18-2v1.59l-2.29-2.29-1.42 1.42L19 19.41V18h2v4h-4v-2h1.59l-3.3-3.3 1.42-1.42L21 15z" />
                  </svg>
                </button>

                <!-- PLAY/PAUSE -->
                <button @click="togglePlay" :disabled="!current?.src"
                  class="h-9 w-9 sm:h-10 sm:w-10 rounded-xl flex items-center justify-center transition text-white"
                  :class="isPlaying ? 'bg-red-500 hover:bg-red-400' : 'bg-green-500 hover:bg-green-400'"
                  title="Tocar / Pausar">
                  <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                    class="w-4 h-4 sm:w-5 sm:h-5" fill="currentColor">
                    <rect x="6" y="4" width="4" height="16" rx="1" />
                    <rect x="14" y="4" width="4" height="16" rx="1" />
                  </svg>

                  <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                    class="w-4 h-4 sm:w-5 sm:h-5 ml-[2px]" fill="currentColor">
                    <path d="M8 5v14l11-7z" />
                  </svg>
                </button>

                <!-- STOP (some no mobile pra caber) -->
                <button @click="stop"
                  class="hidden sm:flex h-9 w-9 rounded-xl items-center justify-center transition text-white bg-gray-600 hover:bg-gray-500"
                  title="Parar">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <rect x="6" y="6" width="12" height="12" rx="2" />
                  </svg>
                </button>

                <!-- DELETE -->
                <button @click="confirmDeleteTrack" :disabled="!current?.file || deletingTrack" class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl flex items-center justify-center transition border border-white/10
         bg-red-600 text-white hover:bg-red-500" title="Deletar música">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM6 9h2v9H6V9z" />
                  </svg>
                </button>

                <!-- PRÓXIMA -->
                <button @click="nextTrack" :disabled="currentIndex === -1 || currentIndex >= playList.length - 1"
                  class="h-8 w-8 sm:h-9 sm:w-9 rounded-xl flex items-center justify-center transition text-white bg-white/10 hover:bg-white/15 border border-white/10 disabled:opacity-40 disabled:cursor-not-allowed"
                  title="Próxima">
                  ›
                </button>
              </div>

              <!-- VOLUME (esconde no mobile) -->
              <div class="hidden sm:flex min-w-[260px] items-center justify-end gap-3">
                <div class="text-xs text-white/50">Volume</div>
                <input type="range" min="0" max="1" step="0.01" value="1" @input="setVolume" />
              </div>
            </div>

            <!-- PROGRESSO (compacto no mobile) -->
            <div class="flex items-center gap-2 sm:gap-3">
              <div class="text-[10px] sm:text-xs text-white/60 w-[60px] sm:w-[90px]">
                {{ fmtTime(currentTime) }}
              </div>

              <input type="range" class="w-full pointer-events-none" min="0" :max="duration" step="0.25"
                :value="currentTime" />

              <div class="text-[10px] sm:text-xs text-white/60 w-[60px] sm:w-[90px] text-right">
                {{ fmtTime(duration) }}
              </div>
            </div>

            <div v-if="playerError" class="text-xs text-red-300">{{ playerError }}</div>

            <audio ref="audioRef" :src="current?.src || ''" preload="metadata" @loadedmetadata="onLoadedMeta"
              @loadeddata="onLoadedMeta" @durationchange="onLoadedMeta" @timeupdate="onTimeUpdate" @ended="onEnded"
              @play="isPlaying = true" @pause="isPlaying = false" />
          </div>
        </footer>

      </main>
    </div>
  </div>
</template>