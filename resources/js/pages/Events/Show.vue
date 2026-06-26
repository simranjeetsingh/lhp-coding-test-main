<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface EventDetail {
    id: string;
    name: string;
    description: string;
    type: string;
    status: string;
    starts_at: number;
    ends_at: number;
    location: string;
    venue: string;
    venue_capacity: number;
    price: number;
    currency: string;
    organizer: string;
    tags: string[];
    images: string[];
    attendee_count: number;
}

const props = defineProps<{ event: EventDetail }>();

const imageIdx = ref(0);
const registerOpen = ref(false);
const attendeeForm = ref({ name: '', email: '' });
const registerLoading = ref(false);
const registerMessage = ref('');
const attendeeCount = ref(props.event.attendee_count);

function formatDate(ts: number) {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
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

function statusLabel(status: string) {
    return status.replace('_', ' ');
}

function openRegister() {
    registerOpen.value = true;
    registerMessage.value = '';
    attendeeForm.value = { name: '', email: '' };
}

async function submitRegister() {
    registerLoading.value = true;
    registerMessage.value = '';
    try {
        const res = await fetch(`/events/${props.event.id}/attendees`, {
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
        if (res.status === 201) attendeeCount.value++;
    } finally {
        registerLoading.value = false;
    }
}
</script>

<template>
    <Head :title="event.name" />

    <div class="min-h-screen bg-gray-950 text-white">
        <!-- Breadcrumb -->
        <div class="px-6 pt-5">
            <div class="mx-auto max-w-5xl">
                <Link
                    href="/events-visual-1"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-400 transition hover:text-white"
                >
                    ← Back to events
                </Link>
            </div>
        </div>

        <!-- Hero image carousel -->
        <div class="mx-auto mt-4 max-w-5xl px-6">
            <div class="relative h-64 overflow-hidden rounded-2xl sm:h-80 lg:h-96">
                <Transition name="img-fade" mode="out-in">
                    <img
                        :key="imageIdx"
                        :src="event.images[imageIdx]"
                        :alt="event.name"
                        class="hero-img h-full w-full object-cover"
                    />
                </Transition>

                <!-- Gradient overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-gray-950/70 via-transparent to-transparent" />

                <!-- Type badge -->
                <span
                    :class="typeColor(event.type)"
                    class="absolute left-4 top-4 rounded-full px-3 py-1 text-xs font-semibold capitalize text-white backdrop-blur"
                >
                    {{ event.type }}
                </span>

                <!-- Status badge -->
                <span class="absolute right-4 top-4 rounded-full bg-black/50 px-3 py-1 text-xs font-semibold capitalize text-white backdrop-blur">
                    {{ statusLabel(event.status) }}
                </span>

                <!-- Carousel controls -->
                <template v-if="event.images.length > 1">
                    <button
                        class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70 active:scale-95"
                        @click="imageIdx = (imageIdx - 1 + event.images.length) % event.images.length"
                    >
                        ‹
                    </button>
                    <button
                        class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70 active:scale-95"
                        @click="imageIdx = (imageIdx + 1) % event.images.length"
                    >
                        ›
                    </button>
                    <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5">
                        <button
                            v-for="(_, i) in event.images"
                            :key="i"
                            :class="imageIdx === i ? 'w-5 bg-white' : 'w-1.5 bg-white/40'"
                            class="h-1.5 rounded-full transition-all duration-300"
                            @click="imageIdx = i"
                        />
                    </div>
                </template>
            </div>
        </div>

        <!-- Content -->
        <div class="mx-auto max-w-5xl px-6 py-8">
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left: Main content -->
                <div class="space-y-6 lg:col-span-2 detail-content">
                    <div>
                        <h1 class="text-3xl font-bold leading-tight text-white sm:text-4xl">
                            {{ event.name }}
                        </h1>
                        <p v-if="event.organizer" class="mt-2 text-sm text-gray-500">
                            Organized by
                            <span class="text-gray-300">{{ event.organizer }}</span>
                        </p>
                    </div>

                    <p class="text-base leading-relaxed text-gray-400">{{ event.description }}</p>

                    <!-- Tags -->
                    <div v-if="event.tags?.length" class="flex flex-wrap gap-2">
                        <span
                            v-for="tag in event.tags"
                            :key="tag"
                            class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-gray-300"
                        >
                            {{ tag }}
                        </span>
                    </div>
                </div>

                <!-- Right: Booking card -->
                <div class="booking-card">
                    <div class="rounded-2xl border border-white/10 bg-gray-900 p-5">
                        <div class="space-y-4 text-sm">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-gray-500">📅</span>
                                <div>
                                    <div class="font-medium text-white">{{ formatDate(event.starts_at) }}</div>
                                    <div v-if="event.ends_at" class="mt-0.5 text-xs text-gray-500">
                                        Ends {{ formatDate(event.ends_at) }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-gray-500">📍</span>
                                <div>
                                    <div class="font-medium text-white">{{ event.venue || event.location }}</div>
                                    <div v-if="event.venue" class="mt-0.5 text-xs text-gray-500">{{ event.location }}</div>
                                    <div v-if="event.venue_capacity" class="mt-0.5 text-xs text-gray-500">
                                        Capacity: {{ event.venue_capacity.toLocaleString() }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-gray-500">💰</span>
                                <span class="text-lg font-bold text-white">{{ formatPrice(event.price, event.currency) }}</span>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-gray-500">👥</span>
                                <span class="text-gray-300">{{ attendeeCount.toLocaleString() }} interested</span>
                            </div>
                        </div>

                        <button
                            class="mt-5 w-full rounded-xl bg-violet-600 py-3 text-sm font-semibold text-white transition hover:bg-violet-500 active:scale-95"
                            @click="openRegister"
                        >
                            Register Interest
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register modal -->
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="registerOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="registerOpen = false"
            >
                <div class="modal-panel w-full max-w-md rounded-2xl border border-white/10 bg-gray-900 p-6 shadow-2xl">
                    <h3 class="mb-1 text-lg font-semibold text-white">Register Interest</h3>
                    <p class="mb-4 line-clamp-1 text-sm text-gray-400">{{ event.name }}</p>

                    <div
                        v-if="registerMessage"
                        class="mb-4 rounded-lg bg-violet-500/10 px-4 py-3 text-sm text-violet-300 ring-1 ring-violet-500/20"
                    >
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
                                @click="registerOpen = false"
                            >
                                Cancel
                            </button>
                        </div>
                    </template>
                    <template v-else>
                        <button
                            class="mt-2 w-full rounded-xl border border-white/10 py-2 text-sm text-gray-400 transition hover:bg-white/5"
                            @click="registerOpen = false"
                        >
                            Close
                        </button>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@keyframes heroIn {
    from { opacity: 0; transform: scale(1.03); }
    to   { opacity: 1; transform: scale(1); }
}
.hero-img { animation: heroIn 0.5s ease forwards; }

@keyframes slideUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
.detail-content { animation: slideUp 0.45s ease 0.1s both; }
.booking-card   { animation: slideUp 0.45s ease 0.2s both; }

.img-fade-enter-active,
.img-fade-leave-active { transition: opacity 0.25s ease; }
.img-fade-enter-from,
.img-fade-leave-to { opacity: 0; }

.modal-enter-active,
.modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from,
.modal-leave-to { opacity: 0; }
.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel { transition: transform 0.2s ease, opacity 0.2s ease; }
.modal-enter-from .modal-panel,
.modal-leave-to .modal-panel { transform: translateY(8px); opacity: 0; }
</style>
