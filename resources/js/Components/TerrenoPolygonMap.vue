<script setup>
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw/dist/leaflet.draw.css';

import L from 'leaflet';
import 'leaflet-draw';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    polygon: {
        type: Array,
        default: () => [],
    },
    contextPolygon: {
        type: Array,
        default: () => [],
    },
    latitude: {
        type: [String, Number, null],
        default: null,
    },
    longitude: {
        type: [String, Number, null],
        default: null,
    },
    contextLatitude: {
        type: [String, Number, null],
        default: null,
    },
    contextLongitude: {
        type: [String, Number, null],
        default: null,
    },
    heightClass: {
        type: String,
        default: 'h-[320px]',
    },
    selectableContextPolygon: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:polygon', 'update:center', 'update:area', 'select:context-polygon']);

let mapContainer = null;
let mapInstance = null;
let drawnItems = null;
let currentPolygon = null;
let contextPolygonLayer = null;
const polygonArea = ref(null);

const formattedArea = computed(() => {
    if (!polygonArea.value) {
        return null;
    }

    return new Intl.NumberFormat('pt-PT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    }).format(polygonArea.value);
});

const normalizePolygon = (polygon) =>
    (polygon ?? [])
        .filter((point) => Array.isArray(point) && point.length === 2)
        .map(([lat, lng]) => [Number(lat), Number(lng)]);

const getNormalizedPolygon = () => normalizePolygon(props.polygon);
const getNormalizedContextPolygon = () => normalizePolygon(props.contextPolygon);

const getMapCenter = () => {
    const polygon = getNormalizedPolygon();

    if (polygon.length) {
        return polygon[0];
    }

    const contextPolygon = getNormalizedContextPolygon();

    if (contextPolygon.length) {
        return contextPolygon[0];
    }

    if (props.latitude && props.longitude) {
        return [Number(props.latitude), Number(props.longitude)];
    }

    if (props.contextLatitude && props.contextLongitude) {
        return [Number(props.contextLatitude), Number(props.contextLongitude)];
    }

    return [39.5, -8.0];
};

const calculateCenter = (latLngs) => {
    if (!latLngs.length) {
        return { latitude: null, longitude: null };
    }

    const totals = latLngs.reduce(
        (acc, point) => ({
            lat: acc.lat + point.lat,
            lng: acc.lng + point.lng,
        }),
        { lat: 0, lng: 0 },
    );

    return {
        latitude: Number((totals.lat / latLngs.length).toFixed(8)),
        longitude: Number((totals.lng / latLngs.length).toFixed(8)),
    };
};

const calculateArea = (latLngs) => {
    if (latLngs.length < 3) {
        return null;
    }

    const earthRadius = 6378137;
    const toRadians = (degrees) => degrees * Math.PI / 180;
    const area = latLngs.reduce((total, point, index) => {
        const nextPoint = latLngs[(index + 1) % latLngs.length];

        return total + toRadians(nextPoint.lng - point.lng) * (2 + Math.sin(toRadians(point.lat)) + Math.sin(toRadians(nextPoint.lat)));
    }, 0);

    return Number((Math.abs(area * earthRadius * earthRadius / 2) / 10000).toFixed(4));
};

const updateArea = (latLngs) => {
    polygonArea.value = calculateArea(latLngs);
    emit('update:area', polygonArea.value);
};

const emitPolygonData = (layer) => {
    const latLngs = layer.getLatLngs()[0] ?? [];
    const polygon = latLngs.map((point) => [
        Number(point.lat.toFixed(8)),
        Number(point.lng.toFixed(8)),
    ]);

    emit('update:polygon', polygon);
    emit('update:center', calculateCenter(latLngs));
    updateArea(latLngs);
};

const clearCurrentPolygon = () => {
    if (currentPolygon && drawnItems) {
        drawnItems.removeLayer(currentPolygon);
    }

    currentPolygon = null;
};

const clearContextPolygon = () => {
    if (contextPolygonLayer && mapInstance) {
        mapInstance.removeLayer(contextPolygonLayer);
    }

    contextPolygonLayer = null;
};

const renderPolygon = () => {
    if (!mapInstance || !drawnItems) {
        return;
    }

    clearCurrentPolygon();
    clearContextPolygon();

    const polygon = getNormalizedPolygon();
    const contextPolygon = getNormalizedContextPolygon();

    if (contextPolygon.length) {
        contextPolygonLayer = L.polygon(contextPolygon, {
            color: '#0f766e',
            weight: 2,
            dashArray: '6 6',
            fillColor: '#14b8a6',
            fillOpacity: props.selectableContextPolygon ? 0.14 : 0.08,
            interactive: props.selectableContextPolygon,
        }).addTo(mapInstance);

        if (props.selectableContextPolygon) {
            contextPolygonLayer.on('click', () => emit('select:context-polygon'));
        }
    }

    if (!polygon.length) {
        polygonArea.value = null;

        if (contextPolygonLayer) {
            mapInstance.fitBounds(contextPolygonLayer.getBounds(), { padding: [20, 20] });
            return;
        }

        mapInstance.setView(getMapCenter(), 13);
        return;
    }

    currentPolygon = L.polygon(polygon, {
        color: '#15803d',
        weight: 3,
        fillColor: '#22c55e',
        fillOpacity: 0.18,
    });

    drawnItems.addLayer(currentPolygon);
    updateArea(currentPolygon.getLatLngs()[0] ?? []);
    mapInstance.fitBounds(currentPolygon.getBounds(), { padding: [20, 20] });
};

const initializeMap = async () => {
    if (mapInstance || !mapContainer) {
        return;
    }

    await nextTick();

    mapInstance = L.map(mapContainer, {
        zoomControl: true,
    }).setView(getMapCenter(), 13);

    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri',
        maxZoom: 19,
    }).addTo(mapInstance);

    L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Labels &copy; Esri',
        maxZoom: 19,
        opacity: 0.75,
    }).addTo(mapInstance);

    drawnItems = new L.FeatureGroup();
    mapInstance.addLayer(drawnItems);

    mapInstance.addControl(new L.Control.Draw({
        draw: {
            polygon: {
                allowIntersection: false,
                showArea: false,
                shapeOptions: {
                    color: '#15803d',
                    weight: 3,
                    fillColor: '#22c55e',
                    fillOpacity: 0.18,
                },
            },
            polyline: false,
            rectangle: false,
            circle: false,
            marker: false,
            circlemarker: false,
        },
        edit: {
            featureGroup: drawnItems,
            remove: true,
        },
    }));

    mapInstance.on(L.Draw.Event.CREATED, (event) => {
        clearCurrentPolygon();
        currentPolygon = event.layer;
        drawnItems.addLayer(currentPolygon);
        emitPolygonData(currentPolygon);
    });

    mapInstance.on(L.Draw.Event.EDITED, (event) => {
        event.layers.eachLayer((layer) => {
            currentPolygon = layer;
            emitPolygonData(layer);
        });
    });

    mapInstance.on(L.Draw.Event.DELETED, () => {
        currentPolygon = null;
        emit('update:polygon', []);
        emit('update:center', { latitude: null, longitude: null });
        polygonArea.value = null;
        emit('update:area', null);
    });

    renderPolygon();
    setTimeout(() => mapInstance?.invalidateSize(), 150);
};

watch(
    () => [props.polygon, props.contextPolygon, props.latitude, props.longitude, props.contextLatitude, props.contextLongitude, props.selectableContextPolygon],
    async () => {
        if (!mapInstance) {
            await initializeMap();
        }

        renderPolygon();
        setTimeout(() => mapInstance?.invalidateSize(), 100);
    },
    { deep: true, immediate: true },
);

onMounted(async () => {
    await initializeMap();
    setTimeout(() => mapInstance?.invalidateSize(), 200);
});

onBeforeUnmount(() => {
    if (mapInstance) {
        mapInstance.remove();
    }
});
</script>

<template>
    <div class="space-y-3">
        <div class="rounded-3xl border border-emerald-100 bg-emerald-50/60 p-4 text-sm leading-6 text-slate-700">
            Desenha o perímetro no mapa. Podes editar ou apagar o polígono e o centro será atualizado automaticamente.
            <span v-if="selectableContextPolygon" class="block text-slate-600">
                Também podes clicar no contorno do terreno para o usar como polígono da parcela.
            </span>
            <span v-if="formattedArea" class="mt-2 block font-semibold text-emerald-800">
                Area calculada: {{ formattedArea }} ha
            </span>
        </div>
        <div :ref="(el) => mapContainer = el" class="w-full overflow-hidden rounded-3xl border border-slate-200" :class="heightClass" />
    </div>
</template>
