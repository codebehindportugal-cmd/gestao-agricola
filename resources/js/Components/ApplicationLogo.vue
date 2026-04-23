<template>
    <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" fill="none">
        <defs>
            <linearGradient id="fieldGradient" x1="24" y1="24" x2="136" y2="136" gradientUnits="userSpaceOnUse">
                <stop stop-color="#34D399" />
                <stop offset="1" stop-color="#14532D" />
            </linearGradient>
            <linearGradient id="sunGradient" x1="80" y1="26" x2="80" y2="74" gradientUnits="userSpaceOnUse">
                <stop stop-color="#FCD34D" />
                <stop offset="1" stop-color="#F59E0B" />
            </linearGradient>
        </defs>

        <rect x="10" y="10" width="140" height="140" rx="36" fill="url(#fieldGradient)" />
        <circle cx="112" cy="48" r="16" fill="url(#sunGradient)" />
        <path
            d="M42 111C52.5 101.5 63.5 97 75 97C88.5 97 99.5 102.5 114 114"
            stroke="white"
            stroke-width="8"
            stroke-linecap="round"
            opacity="0.9"
        />
        <path
            d="M37 129C50.5 117.5 65 112 81 112C96.5 112 110 116.5 123 126"
            stroke="white"
            stroke-width="8"
            stroke-linecap="round"
            opacity="0.7"
        />
        <path
            d="M79 101V57"
            stroke="white"
            stroke-width="8"
            stroke-linecap="round"
        />
        <path
            d="M79 58C79 47 87 37 98 34C98 45 90 55 79 58Z"
            fill="white"
        />
        <path
            d="M79 74C79 62.5 71 53 60 50C60 61 68 71 79 74Z"
            fill="white"
            opacity="0.92"
        />
    </svg>
</template>
