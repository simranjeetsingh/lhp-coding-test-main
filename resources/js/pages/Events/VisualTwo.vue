<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useAppearance } from '@/composables/useAppearance';

interface EventCity { name: string; lat: number; lng: number }

interface EventItem {
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

interface DayGroup {
    label: string;
    date: string;
    events: EventItem[];
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
const events = ref<EventItem[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoaded = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasActiveFilters = computed(() => Object.values(form.value).some(v => v !== ''));

// Attendee modal
const registeringFor = ref<EventItem | null>(null);
const attendeeForm = ref({ name: '', email: '' });
const registerLoading = ref(false);
const registerMessage = ref('');

const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

const dayGroups = computed<DayGroup[]>(() => {
    const map = new Map<string, DayGroup>();
    for (const ev of events.value) {
        const d = new Date(ev.starts_at * 1000);
        const key = d.toISOString().slice(0, 10);
        if (!map.has(key)) {
            map.set(key, {
                date: key,
                label: new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(d),
                events: [],
            });
        }
        map.get(key)!.events.push(ev);
    }
    return [...map.values()];
});

function formatTime(ts: number) {
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }).format(new Date(ts * 1000));
}

function formatPrice(price: number, currency: string) {
    if (price === 0) return 'Free';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency, maximumFractionDigits: 0 }).format(price);
}

function typeDot(type: string) {
    const map: Record<string, string> = {
        concert: 'bg-purple-500',
        conference: 'bg-blue-500',
        meetup: 'bg-teal-500',
        workshop: 'bg-orange-500',
        festival: 'bg-pink-500',
        sports: 'bg-green-500',
        networking: 'bg-indigo-500',
        exhibition: 'bg-slate-500',
    };
    return map[type] ?? 'bg-gray-500';
}

async function load() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    const params = new URLSearchParams({ page: String(page.value + 1), sort: 'asc' });
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

function openRegister(event: EventItem) {
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
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify(attendeeForm.value),
        });
        const data = await res.json();
        registerMessage.value = data.message;
        if (res.status === 201 && registeringFor.value) {
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
    <Head title="Events Visual 2 — Timeline" />

    <div class="min-h-screen bg-white dark:bg-gray-950">
        <!-- Header -->
        <div class="sticky top-0 z-10 border-b border-gray-200 bg-white/90 px-6 py-4 backdrop-blur dark:border-white/10 dark:bg-gray-950/90">
            <div class="mx-auto max-w-4xl">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Event Timeline</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
                        </p>
                    </div>

                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="form.type"
                            class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">All types</option>
                            <option v-for="t in types" :key="t" :value="t" class="dark:bg-gray-900 capitalize">{{ t }}</option>
                        </select>

                        <select
                            v-model="form.city"
                            class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="" class="dark:bg-gray-900">All cities</option>
                            <option v-for="c in cities" :key="c.name" :value="c.name" class="dark:bg-gray-900">{{ c.name }}</option>
                        </select>

                        <input
                            v-model="form.date_from"
                            type="date"
                            class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <input
                            v-model="form.date_to"
                            type="date"
                            class="h-8 rounded-lg border border-gray-200 bg-white px-2.5 text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />

                        <button
                            class="h-8 rounded-lg bg-indigo-600 px-3 text-xs font-medium text-white transition hover:bg-indigo-500 active:scale-95"
                            @click="applyFilters"
                        >
                            Apply
                        </button>

                        <button
                            v-if="hasActiveFilters"
                            class="h-8 rounded-lg border border-gray-200 px-3 text-xs font-medium text-gray-500 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5 active:scale-95"
                            @click="form = { type: '', city: '', date_from: '', date_to: '' }; applyFilters()"
                        >
                            Clear
                        </button>

                        <!-- Theme toggle -->
                        <button
                            class="h-8 w-8 rounded-lg border border-gray-200 text-sm transition hover:bg-gray-50 active:scale-95 dark:border-white/10 dark:hover:bg-white/5"
                            :title="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                            @click="toggleTheme"
                        >
                            {{ resolvedAppearance === 'dark' ? '☀️' : '🌙' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline -->
        <div class="mx-auto max-w-4xl px-4 py-10">
            <div v-for="group in dayGroups" :key="group.date" class="mb-10">
                <!-- Day label -->
                <div class="mb-4 flex items-center gap-3">
                    <div class="shrink-0 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white shadow">
                        {{ group.label }}
                    </div>
                    <div class="h-px flex-1 bg-gray-200 dark:bg-white/10" />
                    <span class="text-xs text-gray-400">{{ group.events.length }} event{{ group.events.length > 1 ? 's' : '' }}</span>
                </div>

                <!-- Events for the day -->
                <div class="relative ml-4 border-l-2 border-gray-200 pl-6 dark:border-white/10">
                    <div
                        v-for="(event, idx) in group.events"
                        :key="event.id"
                        class="row-enter relative mb-4 flex gap-4 rounded-xl border border-gray-100 bg-white p-4 shadow-sm transition-all duration-200 hover:border-indigo-200 hover:shadow-md dark:border-white/8 dark:bg-gray-900 dark:hover:border-indigo-500/30"
                        :style="{ animationDelay: `${idx * 45}ms` }"
                    >
                        <!-- Timeline dot -->
                        <div :class="typeDot(event.type)" class="absolute -left-7.25 top-5 h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-950" />

                        <!-- Event image thumbnail -->
                        <Link :href="`/events/${event.id}`" class="block h-20 w-20 shrink-0 overflow-hidden rounded-lg">
                            <img :src="event.images[0]" :alt="event.name" class="h-full w-full object-cover transition duration-300 hover:scale-105" />
                        </Link>

                        <!-- Content -->
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <div class="flex items-start justify-between gap-2">
                                <Link :href="`/events/${event.id}`" class="line-clamp-1 text-sm font-semibold text-gray-900 transition hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ event.name }}</Link>
                                <span class="shrink-0 text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ formatPrice(event.price, event.currency) }}
                                </span>
                            </div>

                            <p v-if="event.description" class="line-clamp-1 text-xs text-gray-400 dark:text-gray-500">{{ event.description }}</p>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span>🕐 {{ formatTime(event.starts_at) }}</span>
                                <span>📍 {{ event.venue ? `${event.venue} · ` : '' }}{{ event.location }}</span>
                                <span>👥 {{ event.attendee_count }}</span>
                            </div>

                            <div class="mt-1 flex items-center gap-2">
                                <span
                                    :class="typeDot(event.type)"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium capitalize text-white"
                                >
                                    {{ event.type }}
                                </span>
                                <div class="ml-auto flex gap-1.5">
                                    <button
                                        class="rounded-lg border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-600 transition hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-400 dark:hover:bg-indigo-500/10"
                                        @click="openRegister(event)"
                                    >
                                        Register
                                    </button>
                                    <Link
                                        :href="`/events/${event.id}`"
                                        class="rounded-lg border border-gray-200 px-3 py-1 text-xs text-gray-500 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5"
                                    >
                                        Details →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="hasLoaded && events.length === 0" class="py-24 text-center text-gray-400">
                <p class="text-lg font-medium">No events found</p>
                <p class="mt-1 text-sm">Try adjusting the filters above</p>
            </div>

            <div ref="sentinel" class="h-2" />

            <div v-if="loading" class="flex justify-center py-8">
                <div class="h-6 w-6 animate-spin rounded-full border-2 border-indigo-500 border-t-transparent" />
            </div>
        </div>
    </div>

    <!-- Register modal -->
    <Teleport to="body">
        <div
            v-if="registeringFor"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
            @click.self="registeringFor = null"
        >
            <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-6 shadow-2xl dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-1 text-lg font-semibold text-gray-900 dark:text-white">Register Interest</h3>
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400 line-clamp-1">{{ registeringFor.name }}</p>

                <div v-if="registerMessage" class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                    {{ registerMessage }}
                </div>

                <template v-if="!registerMessage">
                    <div class="space-y-3">
                        <input
                            v-model="attendeeForm.name"
                            type="text"
                            placeholder="Your name"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-gray-500"
                        />
                        <input
                            v-model="attendeeForm.email"
                            type="email"
                            placeholder="Your email"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder-gray-500"
                        />
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button
                            class="flex-1 rounded-xl bg-indigo-600 py-2 text-sm font-medium text-white transition hover:bg-indigo-500 disabled:opacity-50 active:scale-95"
                            :disabled="registerLoading || !attendeeForm.name || !attendeeForm.email"
                            @click="submitRegister"
                        >
                            {{ registerLoading ? 'Sending…' : 'Confirm' }}
                        </button>
                        <button
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm text-gray-500 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5"
                            @click="registeringFor = null"
                        >
                            Cancel
                        </button>
                    </div>
                </template>
                <template v-else>
                    <button
                        class="mt-2 w-full rounded-xl border border-gray-200 py-2 text-sm text-gray-500 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-400 dark:hover:bg-white/5"
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
@keyframes rowEnter {
    from { opacity: 0; transform: translateX(-12px); }
    to   { opacity: 1; transform: translateX(0); }
}
.row-enter {
    animation: rowEnter 0.35s ease both;
}
</style>
