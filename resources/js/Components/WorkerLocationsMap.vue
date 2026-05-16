<script setup>
import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { nextTick, onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
    funcionarios: { type: Array, default: () => [] },
    heightClass: { type: String, default: 'h-[560px]' },
});

let mapContainer = null;
let mapInstance = null;
let markerLayer = null;

const activeWorkers = () => props.funcionarios.filter((funcionario) => (
    funcionario.has_location &&
    funcionario.latitude !== null &&
    funcionario.longitude !== null
));

const formatDate = (value) => {
    if (!value) return 'Sem registo';

    return new Intl.DateTimeFormat('pt-PT', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
};

const markerIcon = (funcionario) => L.divIcon({
    className: '',
    html: `
        <div style="display:flex;height:38px;width:38px;align-items:center;justify-content:center;border:3px solid white;border-radius:9999px;background:#047857;color:white;font-weight:800;box-shadow:0 14px 28px rgba(15,23,42,.28);">
            ${String(funcionario.nome ?? '?').trim().slice(0, 1).toUpperCase()}
        </div>
    `,
    iconSize: [38, 38],
    iconAnchor: [19, 38],
    popupAnchor: [0, -36],
});

const renderMarkers = () => {
    if (!mapInstance || !markerLayer) return;

    markerLayer.clearLayers();
    const bounds = [];

    activeWorkers().forEach((funcionario) => {
        const position = [Number(funcionario.latitude), Number(funcionario.longitude)];

        if (!Number.isFinite(position[0]) || !Number.isFinite(position[1])) return;

        const marker = L.marker(position, { icon: markerIcon(funcionario) }).bindPopup(`
            <div style="min-width:190px">
                <strong>${funcionario.nome}</strong>
                <div>${funcionario.cargo ?? 'Sem função'}</div>
                <div>Atualizado: ${formatDate(funcionario.location_shared_at)}</div>
                ${funcionario.accuracy ? `<div>Precisão: ${funcionario.accuracy} m</div>` : ''}
            </div>
        `);

        marker.addTo(markerLayer);
        bounds.push(position);
    });

    if (bounds.length) {
        mapInstance.fitBounds(bounds, { padding: [42, 42], maxZoom: 17 });
        return;
    }

    mapInstance.setView([39.5, -8.0], 7);
};

const initializeMap = async () => {
    if (mapInstance || !mapContainer) return;

    await nextTick();

    mapInstance = L.map(mapContainer, { zoomControl: true }).setView([39.5, -8.0], 7);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 19,
    }).addTo(mapInstance);

    L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Labels &copy; Esri',
        maxZoom: 19,
        opacity: 0.75,
    }).addTo(mapInstance);

    markerLayer = L.featureGroup().addTo(mapInstance);
    renderMarkers();
    setTimeout(() => mapInstance?.invalidateSize(), 150);
};

watch(
    () => props.funcionarios,
    async () => {
        if (!mapInstance) await initializeMap();

        renderMarkers();
        setTimeout(() => mapInstance?.invalidateSize(), 100);
    },
    { deep: true },
);

onMounted(async () => {
    await initializeMap();
    setTimeout(() => mapInstance?.invalidateSize(), 250);
});

onBeforeUnmount(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
        markerLayer = null;
        mapContainer = null;
    }
});
</script>

<template>
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-slate-100" :class="heightClass">
        <div :ref="(el) => mapContainer = el" class="h-full w-full" />
    </div>
</template>
