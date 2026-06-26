<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

interface EventCity { name: string; lat: number; lng: number }

interface EventCard {
    id: string;
    name: string;
    description: string;
    type: string;
    status: string;
    starts_at: number;
    ends_at: number;
    location: string;
    venue: string;
    price: number;
    currency: string;
    images: string[];
    attendee_count: number;
}

const props = defineProps<{
    cities: EventCity[];
    types: string[];
    filters: { type: string; city: string; date_from: string; date_to: string };
}>();

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

const form = ref({ ...props.filters });
const events = ref<EventCard[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoaded = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasActiveFilters = computed(() => Object.values(form.value).some(v => v !== ''));

// Attendee modal
const registeringFor = ref<EventCard | null>(null);
const attendeeForm = ref({ name: '', email: '' });
const registerLoading = ref(false);
const registerMessage = ref('');

// Image carousel index per event
const imageIndex = ref<Record<string, number>>({});

const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

function formatDate(ts: number) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short', month: 'short', day: 'numeric',
        year: 'numeric', hour: 'numeric', minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(ts * 1000));
}

function formatPrice(price: number, currency: string) {
    if (price === 0) return 'Free';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }).format(price);
}

function typeColor(type: string) {
    const map: Record<string, string> = {
        concert: 'bg-purple-500/90',
        conference: 'bg-blue-500/90',
        meetup: 'bg-teal-500/90',
        workshop: 'bg-orange-500/90',
        festival: 'bg-pink-500/90',
        sports: 'bg-green-500/90',
        networking: 'bg-indigo-500/90',
        exhibition: 'bg-slate-500/90',
    };
    return map[type] ?? 'bg-gray-500/90';
}

function nextImage(id: string, total: number) {
    imageIndex.value[id] = ((imageIndex.value[id] ?? 0) + 1) % total;
}
function prevImage(id: string, total: number) {
    imageIndex.value[id] = ((imageIndex.value[id] ?? 0) - 1 + total) % total;
}

async function load() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    const params = new URLSearchParams({ page: String(page.value + 1) });
    if (form.value.type) params.set('type', form.value.type);
    if (form.value.city) params.set('city', form.value.city);
    if (form.value.date_from) params.set('date_from', form.value.date_from);
    if (form.value.date_to) params.set('date_to', form.value.date_to);
    try {
        const res = await fetch(`/events/visual-data?${params}`, { headers: { Accept: 'application/json' } });
        const data = await res.json();
        events.value.push(...data.data);
        page.value = data.current_page;
        lastPage.value = data.last_page;
        total.value = data.total;
        hasLoaded.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    events.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoaded.value = false;
    load();
}

function openRegister(event: EventCard) {
    registeringFor.value = event;
    attendeeForm.value = { name: '', email: '' };
    registerMessage.value = '';
}

async function submitRegister() {
    if (!registeringFor.value) return;
    registerLoading.value = true;
    registerMessage.value = '';
    try {
        const res = await fetch(`/events/${registeringFor.value.id}/attendees`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' },
            body: JSON.stringify(attendeeForm.value),
        });
        const data = await res.json();
        registerMessage.value = data.message;
        if (res.ok && res.status === 201 && registeringFor.value) {
            registeringFor.value.attendee_count++;
        }
    } finally {
        registerLoading.value = false;
    }
}

watch(() => props.filters, (f) => { form.value = { ...f }; }, { deep: true });

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) load();
    }, { rootMargin: '600px' });
    if (sentinel.value) observer.observe(sentinel.value);
    load();
});
onUnmounted(() => observer?.disconnect());
</script>

<template>
    <Head title="Events Visual 1 — Card Grid" />

    <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-white">
        <!-- Header -->
        <div class="border-b border-gray-200 bg-white/90 px-6 py-5 backdrop-blur dark:border-white/10 dark:bg-gray-900/80">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Upcoming Events</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
                        </p>
                    </div>

                    <!-- Filters + theme toggle -->
                    <div class="flex flex-wrap items-end gap-2">
                        <select
                            v-model="form.type"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">All types</option>
                            <option v-for="t in types" :key="t" :value="t" class="bg-white capitalize dark:bg-gray-900">{{ t }}</option>
                        </select>

                        <select
                            v-model="form.city"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="" class="bg-white dark:bg-gray-900">All cities</option>
                            <option v-for="c in cities" :key="c.name" :value="c.name" class="bg-white dark:bg-gray-900">{{ c.name }}</option>
                        </select>

                        <input
                            v-model="form.date_from"
                            type="date"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-model="form.date_to"
                            type="date"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />

                        <button
                            class="h-9 rounded-lg bg-violet-600 px-4 text-sm font-medium text-white transition hover:bg-violet-500 active:scale-95"
                            @click="applyFilters"
                        >
                            Filter
                        </button>

                        <button
                            v-if="hasActiveFilters"
                            class="h-9 rounded-lg border border-gray-200 px-4 text-sm font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 active:scale-95 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                            @click="form = { type: '', city: '', date_from: '', date_to: '' }; applyFilters()"
                        >
                            Clear
                        </button>

                        <!-- Theme toggle -->
                        <button
                            class="h-9 w-9 rounded-lg border border-gray-200 text-lg transition hover:bg-gray-100 active:scale-95 dark:border-white/10 dark:hover:bg-white/5"
                            :title="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                            @click="toggleTheme"
                        >
                            {{ resolvedAppearance === 'dark' ? '☀️' : '🌙' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid -->
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(event, idx) in events"
                    :key="event.id"
                    class="group card-enter flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg dark:border-white/8 dark:bg-gray-900 dark:shadow-none dark:hover:shadow-violet-500/10 dark:hover:shadow-2xl"
                    :style="{ animationDelay: `${(idx % 24) * 45}ms` }"
                >
                    <!-- Image area -->
                    <Link :href="`/events/${event.id}`" class="relative block h-48 overflow-hidden bg-gray-100 dark:bg-gray-800">
                        <img
                            :src="event.images[imageIndex[event.id] ?? 0]"
                            :alt="event.name"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                        <!-- Type badge -->
                        <span
                            :class="typeColor(event.type)"
                            class="absolute left-3 top-3 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize text-white backdrop-blur"
                        >
                            {{ event.type }}
                        </span>
                        <!-- Price badge -->
                        <span class="absolute right-3 top-3 rounded-full bg-black/50 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur">
                            {{ formatPrice(event.price, event.currency) }}
                        </span>
                        <!-- Image carousel controls (only if >1 image) -->
                        <template v-if="event.images.length > 1">
                            <button
                                class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-1 text-white opacity-0 transition group-hover:opacity-100 hover:bg-black/70"
                                @click.stop="prevImage(event.id, event.images.length)"
                            >‹</button>
                            <button
                                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-black/40 p-1 text-white opacity-0 transition group-hover:opacity-100 hover:bg-black/70"
                                @click.stop="nextImage(event.id, event.images.length)"
                            >›</button>
                            <div class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-1">
                                <span
                                    v-for="(_, i) in event.images"
                                    :key="i"
                                    :class="(imageIndex[event.id] ?? 0) === i ? 'bg-white' : 'bg-white/40'"
                                    class="h-1.5 w-1.5 rounded-full transition"
                                />
                            </div>
                        </template>
                    </Link>

                    <!-- Content -->
                    <div class="flex flex-1 flex-col gap-3 p-4">
                        <Link :href="`/events/${event.id}`" class="line-clamp-2 text-base font-semibold leading-snug text-gray-900 transition-colors hover:text-violet-600 dark:text-white dark:hover:text-violet-300">{{ event.name }}</Link>

                        <div class="space-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex items-center gap-1.5">
                                <span>📅</span>
                                <span>{{ formatDate(event.starts_at) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span>📍</span>
                                <span>{{ event.venue ? `${event.venue} · ` : '' }}{{ event.location }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span>👥</span>
                                <span>{{ event.attendee_count }} interested</span>
                            </div>
                        </div>

                        <div class="mt-auto flex gap-2">
                            <button
                                class="flex-1 rounded-xl bg-violet-600/10 py-2 text-sm font-medium text-violet-600 ring-1 ring-violet-500/30 transition hover:bg-violet-600 hover:text-white hover:ring-violet-500 active:scale-95 dark:bg-violet-600/20 dark:text-violet-300"
                                @click="openRegister(event)"
                            >
                                Register
                            </button>
                            <Link
                                :href="`/events/${event.id}`"
                                class="rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                            >
                                Details →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="hasLoaded && events.length === 0" class="py-24 text-center text-gray-400 dark:text-gray-500">
                <p class="text-lg">No events found</p>
                <p class="mt-1 text-sm">Try adjusting the filters</p>
            </div>

            <!-- Sentinel for infinite scroll -->
            <div ref="sentinel" class="h-2" />

            <!-- Loading spinner -->
            <div v-if="loading" class="flex justify-center py-10">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-violet-500 border-t-transparent" />
            </div>
        </div>
    </div>

    <!-- Register modal -->
    <Teleport to="body">
        <div
            v-if="registeringFor"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="registeringFor = null"
        >
            <div class="w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 shadow-2xl">
                <h3 class="mb-1 text-lg font-semibold text-white">Register Interest</h3>
                <p class="mb-4 text-sm text-gray-400 line-clamp-1">{{ registeringFor.name }}</p>

                <div v-if="registerMessage" class="mb-4 rounded-lg bg-violet-500/10 px-4 py-3 text-sm text-violet-300 ring-1 ring-violet-500/20">
                    {{ registerMessage }}
                </div>

                <template v-if="!registerMessage">
                    <div class="space-y-3">
                        <input
                            v-model="attendeeForm.name"
                            type="text"
                            placeholder="Your name"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500"
                        />
                        <input
                            v-model="attendeeForm.email"
                            type="email"
                            placeholder="Your email"
                            class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-violet-500"
                        />
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button
                            class="flex-1 rounded-xl bg-violet-600 py-2 text-sm font-medium text-white transition hover:bg-violet-500 disabled:opacity-50 active:scale-95"
                            :disabled="registerLoading || !attendeeForm.name || !attendeeForm.email"
                            @click="submitRegister"
                        >
                            {{ registerLoading ? 'Sending…' : 'Confirm' }}
                        </button>
                        <button
                            class="rounded-xl border border-white/10 px-4 py-2 text-sm text-gray-400 transition hover:bg-white/5"
                            @click="registeringFor = null"
                        >
                            Cancel
                        </button>
                    </div>
                </template>
                <template v-else>
                    <button
                        class="mt-2 w-full rounded-xl border border-white/10 py-2 text-sm text-gray-400 transition hover:bg-white/5"
                        @click="registeringFor = null"
                    >
                        Close
                    </button>
                </template>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes cardEnter {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
.card-enter {
    animation: cardEnter 0.4s ease both;
}
</style>
