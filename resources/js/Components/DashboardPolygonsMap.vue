<script setup>
import 'leaflet/dist/leaflet.css';

import L from 'leaflet';
import { nextTick, onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
    polygons: {
        type: Array,
        default: () => [],
    },
    heightClass: {
        type: String,
        default: 'h-[520px]',
    },
});

let mapContainer = null;
let mapInstance = null;
let polygonLayer = null;

const normalizePolygon = (polygon) => {
    const points = Array.isArray(polygon) ? polygon : [];

    return points
        .filter((point) => Array.isArray(point) && point.length === 2)
        .map(([lat, lng]) => [Number(lat), Number(lng)])
        .filter(([lat, lng]) => Number.isFinite(lat) && Number.isFinite(lng));
};

const buildPopup = (item) => `
    <div style="min-width: 180px">
        <strong>${item.nome ?? 'Sem nome'}</strong>
        <div>${item.tipo === 'parcela' ? 'Parcela' : 'Terreno'}</div>
        ${item.area_total ? `<div>Area: ${item.area_total} ha</div>` : ''}
        ${item.extra ? `<div>${item.extra}</div>` : ''}
    </div>
`;

const renderPolygons = () => {
    if (!mapInstance || !polygonLayer) {
        return;
    }

    polygonLayer.clearLayers();

    const bounds = [];

    props.polygons.forEach((item) => {
        const points = normalizePolygon(item.poligono);

        if (points.length < 3) {
            return;
        }

        const layer = L.polygon(points, {
            color: item.tipo === 'parcela' ? '#0284c7' : '#15803d',
            weight: item.tipo === 'parcela' ? 2 : 3,
            fillColor: item.tipo === 'parcela' ? '#38bdf8' : '#22c55e',
            fillOpacity: item.tipo === 'parcela' ? 0.22 : 0.14,
        });

        layer.bindPopup(buildPopup(item));
        layer.addTo(polygonLayer);
        bounds.push(...points);
    });

    if (bounds.length) {
        mapInstance.fitBounds(bounds, { padding: [28, 28] });
        return;
    }

    mapInstance.setView([39.5, -8.0], 7);
};

const initializeMap = async () => {
    if (mapInstance || !mapContainer) {
        return;
    }

    await nextTick();

    mapInstance = L.map(mapContainer, {
        zoomControl: true,
    }).setView([39.5, -8.0], 7);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 19,
    }).addTo(mapInstance);

    L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Labels &copy; Esri',
        maxZoom: 19,
        opacity: 0.75,
    }).addTo(mapInstance);

    polygonLayer = L.featureGroup().addTo(mapInstance);
    renderPolygons();
    setTimeout(() => mapInstance?.invalidateSize(), 150);
};

watch(
    () => props.polygons,
    async () => {
        if (!mapInstance) {
            await initializeMap();
        }

        renderPolygons();
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
        polygonLayer = null;
        mapContainer = null;
    }
});
</script>

<template>
    <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-slate-100" :class="heightClass">
        <div :ref="(el) => mapContainer = el" class="h-full w-full" />
    </div>
</template>
