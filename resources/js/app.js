import './bootstrap';
import Vue from 'vue';

// Import components
import ExampleComponent from './components/ExampleComponent.vue';

// Register components
Vue.component('example-component', ExampleComponent);

// Create Vue instance
const app = new Vue({
    el: '#app',
});
