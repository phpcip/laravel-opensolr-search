<script setup>
import { computed, onMounted, ref } from 'vue';
import { useSearch } from '../composables/useSearch';
import HighlightText from './HighlightText.vue';
import Pager from './Pager.vue';

/**
 * Hybrid search over the whole index, with highlights, a spelling suggestion, a recency
 * toggle and a grounded AI answer. The query lives in the address bar so results can be
 * linked to.
 */
const props = defineProps({
    index: { type: String, default: '' },
});

const query = ref('');
const fresh = ref(false);
const search = useSearch('/api/search');
const answer = useSearch('/api/answer');
const result = computed(() => search.state.result);

const examples = [
    'how am I supposed to save money?',
    'what is happening with interest rates',
    'electric cars',
];

function submit(page = 1) {
    const q = query.value.trim();
    if (q.length < 2) {
        return;
    }
    answer.reset();
    search.run({ q, page, fresh: fresh.value ? 1 : '' });
    const url = new URL(window.location.href);
    url.searchParams.set('q', q);
    if (page > 1) {
        url.searchParams.set('page', page);
    } else {
        url.searchParams.delete('page');
    }
    window.history.replaceState({}, '', url);
    window.scrollTo({ top: 0 });
}

function ask() {
    const q = query.value.trim();
    if (q.length < 3) {
        return;
    }
    answer.run({ q });
}

function use(text) {
    query.value = text;
    submit();
}

onMounted(() => {
    const url = new URL(window.location.href);
    const q = url.searchParams.get('q');
    if (q) {
        query.value = q;
        submit(parseInt(url.searchParams.get('page') || '1', 10) || 1);
    }
});
</script>

<template>
    <section class="search">
        <form class="search-form" @submit.prevent="submit()">
            <input
                v-model="query"
                type="search"
                name="q"
                placeholder="Ask anything, in any language"
                maxlength="200"
                autocomplete="off"
                autofocus
            >
            <button type="submit" class="btn btn-primary" :disabled="search.state.loading">Search</button>
            <button type="button" class="btn" :disabled="answer.state.loading || query.trim().length < 3" @click="ask">Ask AI</button>
        </form>

        <div class="search-options">
            <label class="toggle">
                <input v-model="fresh" type="checkbox" @change="result && submit()">
                Fresh results first
            </label>
            <span class="examples">
                Try:
                <button v-for="example in examples" :key="example" type="button" class="link" @click="use(example)">{{ example }}</button>
            </span>
        </div>

        <div v-if="answer.state.loading" class="panel muted">Reading the top results and writing an answer…</div>
        <div v-else-if="answer.state.error" class="panel error">{{ answer.state.error }}</div>
        <div v-else-if="answer.state.result" class="panel answer">
            <h2>Answer</h2>
            <p class="answer-text">{{ answer.state.result.answer }}</p>
            <p class="small muted">Written by the Opensolr LLM from the top hybrid hits for this query. The sources are the results below.</p>
        </div>

        <div v-if="search.state.error" class="panel error">{{ search.state.error }}</div>

        <template v-if="result">
            <p class="meta">
                <strong>{{ result.total.toLocaleString('en-US') }}</strong> results for “{{ result.query }}”
                <span class="muted">in {{ result.qtime }} ms</span>
                <span v-if="result.suggestion">
                    · Did you mean <button type="button" class="link" @click="use(result.suggestion)">{{ result.suggestion }}</button>?
                </span>
            </p>

            <p v-if="result.facets.length" class="facets">
                <span v-for="facet in result.facets" :key="facet.field" class="facet">
                    <span class="facet-name">{{ facet.field }}</span>
                    <span v-for="value in facet.values.slice(0, 6)" :key="value.value" class="pill">{{ value.value }} <b>{{ value.count.toLocaleString('en-US') }}</b></span>
                </span>
            </p>

            <ol class="results" :start="(result.page - 1) * 10 + 1">
                <li v-for="doc in result.docs" :key="doc.id" class="result">
                    <img v-if="doc.image" :src="doc.image" alt="" loading="lazy" referrerpolicy="no-referrer" class="thumb">
                    <div class="result-body">
                        <h3 class="result-title">
                            <a v-if="doc.url" :href="doc.url" target="_blank" rel="noopener noreferrer"><HighlightText :segments="doc.title_segments" /></a>
                            <HighlightText v-else :segments="doc.title_segments" />
                        </h3>
                        <p class="source">
                            <span v-if="doc.domain">{{ doc.domain }}</span>
                            <span v-if="doc.section">{{ doc.section }}</span>
                            <span v-if="doc.date">{{ doc.date }}</span>
                            <span v-if="doc.language">{{ doc.language }}</span>
                        </p>
                        <p class="snippet"><HighlightText :segments="doc.snippet_segments" /></p>
                    </div>
                </li>
            </ol>

            <p v-if="!result.docs.length" class="panel muted">Nothing matched. Try fewer words, or a question in plain language.</p>

            <Pager :page="result.page" :pages="result.pages" @change="submit" />
        </template>

        <p v-else-if="!search.state.loading" class="panel muted">
            Searching <code>{{ props.index }}</code>. Type a few words or a whole question and press Enter.
        </p>
        <p v-else class="panel muted">Searching…</p>
    </section>
</template>
