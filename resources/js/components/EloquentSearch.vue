<script setup>
import { computed, onMounted, ref } from 'vue';
import { useSearch } from '../composables/useSearch';
import Pager from './Pager.vue';

/**
 * Search or browse the Article model through Laravel Scout, optionally within one category.
 * An empty query lists every article, so the page has content before the first keystroke.
 */
const query = ref('');
const category = ref('');
const categories = ref([]);
const search = useSearch('/api/articles');
const result = computed(() => search.state.result);

const examples = ['sleepy pets', 'budget dining', 'where to see the aurora', 'passwords without passwords'];

async function submit(page = 1) {
    await search.run({ q: query.value.trim(), category: category.value, page });
    if (result.value?.categories?.length) {
        categories.value = result.value.categories;
    }
    window.scrollTo({ top: 0 });
}

function use(text) {
    query.value = text;
    submit();
}

onMounted(() => submit());
</script>

<template>
    <section class="search">
        <form class="search-form" @submit.prevent="submit()">
            <input
                v-model="query"
                type="search"
                name="q"
                placeholder="Search articles"
                maxlength="200"
                autocomplete="off"
                autofocus
            >
            <select v-model="category" name="category" @change="submit()">
                <option value="">All categories</option>
                <option v-for="name in categories" :key="name" :value="name">{{ name }}</option>
            </select>
            <button type="submit" class="btn btn-primary" :disabled="search.state.loading">Search</button>
        </form>

        <div class="search-options">
            <span class="examples">
                Try:
                <button v-for="example in examples" :key="example" type="button" class="link" @click="use(example)">{{ example }}</button>
            </span>
        </div>

        <div v-if="search.state.error" class="panel error">{{ search.state.error }}</div>

        <template v-if="result">
            <p class="meta">
                <strong>{{ result.total }}</strong>
                <template v-if="result.query">results for “{{ result.query }}”</template>
                <template v-else>articles</template>
                <span v-if="result.category">in {{ result.category }}</span>
            </p>

            <ol class="results" :start="(result.page - 1) * 10 + 1">
                <li v-for="doc in result.docs" :key="doc.id" class="result">
                    <div class="result-body">
                        <h3 class="result-title">{{ doc.title }}</h3>
                        <p class="source">
                            <span class="pill">{{ doc.category }}</span>
                            <span>{{ doc.author }}</span>
                            <span>{{ doc.date }}</span>
                        </p>
                        <p class="snippet">{{ doc.excerpt }}</p>
                    </div>
                </li>
            </ol>

            <p v-if="!result.docs.length" class="panel muted">
                No articles matched. If the list is empty for every query, the articles have not been imported yet:
                <code>php artisan scout:import "App\Models\Article"</code>.
            </p>

            <Pager :page="result.page" :pages="result.pages" @change="submit" />
        </template>

        <p v-else-if="search.state.loading" class="panel muted">Loading…</p>
    </section>
</template>
