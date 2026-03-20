<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    config: Object,
    tables: Array,
    assignMode: {
        type: Boolean,
        default: false,
    },
    selectedSeat: {
        type: Object, // { tableId: 1, seatNum: 1 }
        default: null
    },
    embedded: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['seat-selected']);

// DOM refs for fit-to-screen calculation
const mapContainer = ref(null);
const transformLayer = ref(null);

const fitToScreen = () => {
    if (!mapContainer.value || !transformLayer.value) return;
    const containerW = mapContainer.value.clientWidth;
    const containerH = mapContainer.value.clientHeight;
    const contentW = transformLayer.value.scrollWidth;
    const contentH = transformLayer.value.scrollHeight;
    const fitScale = Math.min(
        (containerW * 0.92) / contentW,
        (containerH * 0.92) / contentH,
        1
    );
    scale.value = Math.max(fitScale, 0.3);
    // origin-center: translate so the element's center aligns with the container's center
    translateX.value = containerW / 2 - contentW / 2;
    translateY.value = containerH / 2 - contentH / 2;
};

// Refresh data every 10 seconds (only if not in assign mode, so assigning doesn't annoyingly reload)
let refreshInterval;
onMounted(async () => {
    await nextTick();
    fitToScreen();
    if (!props.assignMode) {
        refreshInterval = setInterval(() => {
            router.reload({ only: ['tables'] });
        }, 10000);
    }
});
onUnmounted(() => {
    clearInterval(refreshInterval);
});

// Pan & Zoom logic
const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);
let isDragging = false;
let startX = 0;
let startY = 0;

const handleWheel = (e) => {
    e.preventDefault();
    const zoomIntensity = 0.05;
    if (e.deltaY < 0) {
        scale.value = Math.min(scale.value + zoomIntensity, 3);
    } else {
        scale.value = Math.max(scale.value - zoomIntensity, 0.3);
    }
};

const handleMouseDown = (e) => {
    if (e.button !== 0 && e.button !== 1) return;
    isDragging = true;
    startX = e.clientX - translateX.value;
    startY = e.clientY - translateY.value;
};

const handleMouseMove = (e) => {
    if (!isDragging) return;
    translateX.value = e.clientX - startX;
    translateY.value = e.clientY - startY;
};

const handleMouseUp = () => {
    isDragging = false;
};

// Zoom buttons
const zoomIn = () => { scale.value = Math.min(scale.value + 0.2, 3); };
const zoomOut = () => { scale.value = Math.max(scale.value - 0.2, 0.3); };
const resetView = () => fitToScreen();

// Touch support
let touchStartX = 0;
let touchStartY = 0;
let touchStartDist = 0;
let touchStartScale = 1;
let isTouching = false;

const getTouchDist = (touches) =>
    Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);

const handleTouchStart = (e) => {
    if (e.touches.length === 1) {
        isTouching = true;
        touchStartX = e.touches[0].clientX - translateX.value;
        touchStartY = e.touches[0].clientY - translateY.value;
    } else if (e.touches.length === 2) {
        isTouching = false;
        touchStartDist = getTouchDist(e.touches);
        touchStartScale = scale.value;
    }
};

const handleTouchMove = (e) => {
    e.preventDefault();
    if (e.touches.length === 1 && isTouching) {
        translateX.value = e.touches[0].clientX - touchStartX;
        translateY.value = e.touches[0].clientY - touchStartY;
    } else if (e.touches.length === 2) {
        const dist = getTouchDist(e.touches);
        scale.value = Math.min(Math.max(touchStartScale * (dist / touchStartDist), 0.3), 3);
    }
};

const handleTouchEnd = () => {
    isTouching = false;
};

// Seat popover logic
const selectedGuest = ref(null);
const popoverStyle = ref({ top: '0px', left: '0px' });

const openPopover = (guest, table, e) => {
    if (!guest) {
        selectedGuest.value = null;
        return;
    }
    selectedGuest.value = { ...guest, table_name: table.name };
    const rect = e.target.getBoundingClientRect();
    popoverStyle.value = {
        top: `${rect.top + window.scrollY - 10}px`,
        left: `${rect.right + window.scrollX + 10}px`,
    };
};

const closePopover = () => {
    selectedGuest.value = null;
};

const handleSeatClick = (table, seatNum, e) => {
    const guest = getGuestForSeat(table, seatNum);
    if (props.assignMode) {
        emit('seat-selected', { table, seatNum, guest });
        return;
    }
    openPopover(guest, table, e);
};

// Map tables to a 2D grid based on row_label
const grid = computed(() => {
    if (!props.tables.length) return [];
    
    // Group by row_label
    const rowsMap = {};
    props.tables.forEach(t => {
        if (!rowsMap[t.row_label]) rowsMap[t.row_label] = [];
        rowsMap[t.row_label].push(t);
    });

    const sortedRows = Object.keys(rowsMap).sort().map(rk => {
        return rowsMap[rk].sort((a, b) => a.position_in_row - b.position_in_row);
    });

    return sortedRows;
});

const getSeatColor = (table, seatNum) => {
    if (props.selectedSeat && props.selectedSeat.tableId === table.id && props.selectedSeat.seatNum === seatNum) {
        return 'bg-blue-500 border-blue-600 shadow-[0_0_15px_rgba(59,130,246,0.9)] z-30 scale-125';
    }
    const guest = table.guests.find(g => g.seat_number === seatNum);
    if (!guest) return 'bg-white border-gray-300';
    if (guest.checked_in) return 'bg-green-500 border-green-600 shadow-[0_0_10px_rgba(34,197,94,0.6)]';
    if (guest.ticket_issued) return 'bg-yellow-400 border-yellow-500 shadow-[0_0_10px_rgba(250,204,21,0.6)]';
    return 'bg-gray-400 border-gray-500';
};

const getGuestForSeat = (table, seatNum) => {
    return table.guests.find(g => g.seat_number === seatNum);
};
</script>

<template>
    <Head v-if="!embedded" title="Mapa Sály" />

    <component :is="embedded ? 'div' : AuthenticatedLayout" class="h-full w-full">
        <template v-if="!embedded" #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Interaktívna Mapa Sály</h2>
                <div class="flex items-center space-x-6 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-white border border-gray-300 mr-2 display-block"></span> Voľné</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-gray-400 border border-gray-500 mr-2 display-block"></span> Obsadené (bez lístka)</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-yellow-400 border border-yellow-500 mr-2 display-block"></span> Lístok vydaný (neprišiel)</div>
                    <div class="flex items-center"><span class="w-4 h-4 rounded-full bg-green-500 border border-green-600 mr-2 display-block"></span> Na mieste (Check-in)</div>
                </div>
            </div>
        </template>

        <!-- Fullscreen Map Container -->
        <div
            ref="mapContainer"
            class="map-container relative overflow-hidden bg-gray-50 dark:bg-gray-900"
            :style="embedded ? 'height: 100%; min-height: 600px; cursor: grab;' : 'height: calc(100vh - 130px); min-height: 80vh; cursor: grab;'"
            :class="{'!cursor-grabbing': isDragging}"
            @wheel.passive="handleWheel"
            @mousedown="handleMouseDown"
            @mousemove="handleMouseMove"
            @mouseup="handleMouseUp"
            @mouseleave="handleMouseUp"
            @click="closePopover"
            @touchstart.passive="handleTouchStart"
            @touchmove.prevent="handleTouchMove"
            @touchend="handleTouchEnd"
        >
            <!-- Transform Layer -->
            <div
                ref="transformLayer"
                class="absolute origin-center transition-transform duration-75 ease-out"
                :style="{ transform: `translate(${translateX}px, ${translateY}px) scale(${scale})` }"
            >
                <div class="p-20 flex flex-col items-center justify-center space-y-24 min-w-[2000px] min-h-[1000px]">
                    
                    <!-- Stage/Podium at top -->
                    <div class="w-96 h-20 bg-gray-800 dark:bg-gray-950 rounded-b-3xl border-4 border-t-0 border-gray-600 dark:border-gray-800 flex items-center justify-center text-gray-400 font-bold uppercase tracking-[0.3em]">
                        Parket
                    </div>

                    <!-- Row iterations -->
                    <div v-for="(rowTables, rIndex) in grid" :key="'R'+rIndex" class="flex items-center justify-center space-x-20 w-full relative">
                        <!-- Left Row Label -->
                        <div class="absolute left-10 text-6xl font-black text-gray-200 dark:text-gray-800 select-none">
                            {{ rowTables[0].row_label }}
                        </div>
                        
                        <div v-for="table in rowTables" :key="table.id" class="relative">
                            
                            <!-- The Table Circle/Rect -->
                            <div class="w-40 h-40 bg-orange-100 dark:bg-orange-900/50 rounded-full border-4 border-orange-300 dark:border-orange-800 flex items-center justify-center shadow-lg relative z-10 transition-transform hover:scale-105">
                                <span class="text-3xl font-black text-orange-800 dark:text-orange-200 select-none">{{ table.name }}</span>
                            </div>

                            <!-- Seats distributed around the table -->
                            <div v-for="s in table.capacity" :key="s">
                                <!-- Place seats radially around the table -->
                                <button 
                                    @click.stop="handleSeatClick(table, s, $event)"
                                    class="absolute w-10 h-10 -ml-5 -mt-5 rounded-full border-2 transition-transform hover:scale-125 hover:z-20 cursor-pointer shadow-sm flex items-center justify-center"
                                    :class="getSeatColor(table, s)"
                                    :style="{
                                        left: `calc(50% + ${Math.cos((s * (360 / table.capacity) - 90) * Math.PI / 180) * 95}px)`,
                                        top: `calc(50% + ${Math.sin((s * (360 / table.capacity) - 90) * Math.PI / 180) * 95}px)`
                                    }"
                                >
                                    <!-- Use conditional class binding in the text span to ensure contrast:
                                         White text for blue (selected) and green (checked_in) backgrounds.
                                         Black/dark-gray text for white (empty), gray (occupied), and yellow (ticket issued).
                                    -->
                                    <span class="text-[14px] font-bold pointer-events-none select-none"
                                          :class="['bg-blue-500', 'bg-green-500'].some(c => getSeatColor(table, s).includes(c)) ? 'text-white' : 'text-gray-900'">
                                        {{ s }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Right Row Label -->
                        <div class="absolute right-10 text-6xl font-black text-gray-200 dark:text-gray-800 select-none">
                            {{ rowTables[0].row_label }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zoom Controls -->
        <div class="absolute bottom-4 left-4 z-40 flex flex-col gap-1">
            <button @click.stop="zoomIn" class="w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow text-gray-700 dark:text-gray-200 text-xl font-bold hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-center">+</button>
            <button @click.stop="zoomOut" class="w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow text-gray-700 dark:text-gray-200 text-xl font-bold hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-center">−</button>
            <button @click.stop="resetView" class="w-10 h-10 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg shadow text-gray-700 dark:text-gray-200 text-xs font-semibold hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center justify-center" title="Resetovať pohľad">⊙</button>
        </div>

        <!-- Popover -->
        <div 
            v-if="selectedGuest"
            class="fixed bg-white dark:bg-gray-800 p-5 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 z-50 w-72 pointer-events-none transform -translate-y-1/2"
            :style="popoverStyle"
        >
            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-1 flex items-center gap-2">
                {{ selectedGuest.name }}
                <span v-if="selectedGuest.is_teacher" class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">Učiteľ</span>
            </h4>
            <div class="text-sm text-gray-500 dark:text-gray-400 mb-3 space-y-1">
                <p><strong>Stôl:</strong> {{ selectedGuest.table_name }}, <strong>Miesto:</strong> {{ selectedGuest.seat_number }}</p>
                <p><strong>Rezervácia:</strong> {{ selectedGuest.registration?.reservation_number }}</p>
            </div>
            
            <div v-if="selectedGuest.allergen_ids?.length || selectedGuest.is_vegan || selectedGuest.is_vegetarian || selectedGuest.allergen_note" class="mb-3 px-3 py-2 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm font-medium">
                <span class="block text-xs uppercase opacity-70 mb-0.5">Alergény</span>
                <span v-if="selectedGuest.allergen_ids?.length">{{ selectedGuest.allergen_ids.join(', ') }}</span>
                <span v-if="selectedGuest.is_vegan"> · Vegán</span>
                <span v-if="selectedGuest.is_vegetarian"> · Vegetarián</span>
                <span v-if="selectedGuest.allergen_note"> · {{ selectedGuest.allergen_note }}</span>
            </div>

            <div v-if="selectedGuest.note" class="mb-3 px-3 py-2 bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 rounded-lg text-sm">
                <span class="block text-xs uppercase opacity-70 mb-0.5">Poznámka</span>
                {{ selectedGuest.note }}
            </div>

            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 font-medium text-sm text-center rounded">
                <span v-if="selectedGuest.checked_in" class="block w-full text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-900/20 py-1 rounded">
                    ✓ Check-in: {{ new Date(selectedGuest.checked_in_at).toLocaleTimeString() }}
                </span>
                <span v-else-if="selectedGuest.ticket_issued" class="block w-full text-yellow-600 bg-yellow-50 dark:text-yellow-400 dark:bg-yellow-900/20 py-1 rounded">
                    Lístok vydaný (neprišiel)
                </span>
                <span v-else class="block w-full text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-700 p-1 rounded">
                    Lístok nebol vydaný
                </span>
            </div>
        </div>
        
    </component>
</template>
