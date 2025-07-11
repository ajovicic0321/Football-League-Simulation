import './bootstrap';
import Vue from 'vue';

// Import components
import ExampleComponent from './components/ExampleComponent.vue';
import LeagueSimulation from './components/LeagueSimulation.vue';
import LeagueTable from './components/LeagueTable.vue';
import SeasonControls from './components/SeasonControls.vue';
import FinalPredictions from './components/FinalPredictions.vue';
import MatchesSection from './components/MatchesSection.vue';

// Register components
Vue.component('example-component', ExampleComponent);
Vue.component('league-simulation', LeagueSimulation);
Vue.component('league-table', LeagueTable);
Vue.component('season-controls', SeasonControls);
Vue.component('final-predictions', FinalPredictions);
Vue.component('matches-section', MatchesSection);

// Create Vue instance
const app = new Vue({
    el: '#app',
});
