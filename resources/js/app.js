import { createApp } from 'vue';
import IndexSearch from './components/IndexSearch.vue';
import EloquentSearch from './components/EloquentSearch.vue';

/**
 * Each page declares one mount point; its data-* attributes become the component's props.
 */
const mounts = {
    '#index-search': IndexSearch,
    '#eloquent-search': EloquentSearch,
};

for (const [selector, component] of Object.entries(mounts)) {
    const element = document.querySelector(selector);
    if (element) {
        createApp(component, { ...element.dataset }).mount(element);
    }
}
