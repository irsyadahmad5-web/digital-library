<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import type { SeoPayload } from '@/types/seo';

const props = defineProps<{
    seo: SeoPayload;
}>();

const jsonLd = computed(() =>
    props.seo.json_ld.map((item) => JSON.stringify(item)),
);
</script>

<template>
    <Head>
        <title>{{ seo.title }}</title>
        <meta head-key="description" name="description" :content="seo.description">
        <meta head-key="robots" name="robots" :content="seo.robots">
        <link head-key="canonical" rel="canonical" :href="seo.canonical">

        <meta
            v-if="seo.google_site_verification"
            head-key="google-site-verification"
            name="google-site-verification"
            :content="seo.google_site_verification"
        >

        <meta head-key="og:type" property="og:type" :content="seo.open_graph.type || 'website'">
        <meta
            v-if="seo.open_graph.locale"
            head-key="og:locale"
            property="og:locale"
            :content="seo.open_graph.locale"
        >
        <meta
            v-if="seo.open_graph.site_name"
            head-key="og:site_name"
            property="og:site_name"
            :content="seo.open_graph.site_name"
        >
        <meta head-key="og:title" property="og:title" :content="seo.open_graph.title">
        <meta head-key="og:description" property="og:description" :content="seo.open_graph.description">
        <meta
            v-if="seo.open_graph.url"
            head-key="og:url"
            property="og:url"
            :content="seo.open_graph.url"
        >
        <meta
            v-if="seo.open_graph.image"
            head-key="og:image"
            property="og:image"
            :content="seo.open_graph.image"
        >
        <meta
            v-if="seo.open_graph.image && seo.open_graph.image_alt"
            head-key="og:image:alt"
            property="og:image:alt"
            :content="seo.open_graph.image_alt"
        >

        <meta head-key="twitter:card" name="twitter:card" :content="seo.twitter.card">
        <meta head-key="twitter:title" name="twitter:title" :content="seo.twitter.title">
        <meta head-key="twitter:description" name="twitter:description" :content="seo.twitter.description">
        <meta
            v-if="seo.twitter.image"
            head-key="twitter:image"
            name="twitter:image"
            :content="seo.twitter.image"
        >
        <meta
            v-if="seo.twitter.image && seo.twitter.image_alt"
            head-key="twitter:image:alt"
            name="twitter:image:alt"
            :content="seo.twitter.image_alt"
        >

        <script
            v-for="(item, index) in jsonLd"
            :key="index"
            :head-key="`jsonld-${index}`"
            type="application/ld+json"
            v-text="item"
        />
    </Head>
</template>
