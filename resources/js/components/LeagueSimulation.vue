<template>
  <div class="league-simulation">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-8">
      <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold text-center mb-2">
          Insider Champions League
        </h1>
        <p class="text-center text-blue-100">
          Football League Simulation - {{ currentSeason.name }}
        </p>
      </div>
    </header>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- League Table (Left Column) -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-lg shadow-xl p-4 mb-4 text-center text-gray-500" v-if="leagueTable.length === 0 && !loading.table">
            <p>No league data yet. Click "Simulate Week 1" to get started!</p>
          </div>
          <league-table 
            :standings="leagueTable" 
            :loading="loading.table"
            @refresh="loadLeagueTable"
          ></league-table>
        </div>

        <!-- Controls & Info (Right Column) -->
        <div class="space-y-6">
          <!-- Season Controls -->
          <season-controls
            :current-week="currentWeek"
            :total-weeks="totalWeeks"
            :loading="loading.simulation"
            @simulate-week="simulateWeek"
            @simulate-season="simulateSeason"
            @auto-play="startAutoPlay"
            @previous-week="changeWeek($event)"
            @next-week="changeWeek($event)"
          ></season-controls>

          <!-- Final Table Prediction -->
          <final-predictions
            :predictions="predictions"
            :loading="loading.predictions"
            @generate-predictions="generatePredictions"
          ></final-predictions>
        </div>
      </div>

      <!-- Matches Section -->
      <div class="mt-12">
        <matches-section
          :matches="weekMatches"
          :current-week="currentWeek"
          :loading="loading.matches"
          @simulate-match="simulateMatch"
          @update-result="updateMatchResult"
          @week-changed="changeWeek"
        ></matches-section>
      </div>
    </div>

    <!-- Loading Overlay -->
    <div v-if="loading.app" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg shadow-xl">
        <div class="flex items-center space-x-3">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
          <span class="text-gray-700">Loading...</span>
        </div>
      </div>
    </div>

    <!-- Toast Notifications -->
    <div class="fixed top-4 right-4 z-50 space-y-2">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="[
          'px-6 py-3 rounded-lg shadow-lg text-white transform transition-all duration-300',
          notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'
        ]"
      >
        {{ notification.message }}
      </div>
    </div>
  </div>
</template>

<script>
import LeagueTable from './LeagueTable.vue';
import SeasonControls from './SeasonControls.vue';
import FinalPredictions from './FinalPredictions.vue';
import MatchesSection from './MatchesSection.vue';

export default {
  name: 'LeagueSimulation',
  components: {
    LeagueTable,
    SeasonControls,
    FinalPredictions,
    MatchesSection
  },
  data() {
    return {
      currentSeason: {},
      leagueTable: [],
      weekMatches: [],
      predictions: [],
      currentWeek: 1,
      totalWeeks: 12,
      loading: {
        app: true,
        table: false,
        matches: false,
        simulation: false,
        predictions: false
      },
      notifications: []
    };
  },
  async mounted() {
    await this.loadInitialData();
  },
  methods: {
    async loadInitialData() {
      this.loading.app = true;
      try {
        await Promise.all([
          this.loadCurrentSeason(),
          this.loadLeagueTable(),
          this.loadWeekMatches(this.currentWeek)
        ]);
      } catch (error) {
        this.showNotification('Failed to load initial data', 'error');
      } finally {
        this.loading.app = false;
      }
    },

    async loadCurrentSeason() {
      try {
        const response = await fetch('/api/seasons/current');
        const data = await response.json();
        if (data.success) {
          this.currentSeason = data.data;
        }
      } catch (error) {
        console.error('Error loading current season:', error);
      }
    },

    async loadLeagueTable() {
      this.loading.table = true;
      try {
        const response = await fetch('/api/seasons/1/table');
        const data = await response.json();
        console.log('League table response:', data);
        if (data.success) {
          this.leagueTable = data.data.table;
          console.log('League table loaded:', this.leagueTable);
        } else {
          console.error('League table API error:', data);
        }
      } catch (error) {
        console.error('League table fetch error:', error);
        this.showNotification('Failed to load league table', 'error');
      } finally {
        this.loading.table = false;
      }
    },

    async loadWeekMatches(week) {
      this.loading.matches = true;
      try {
        const response = await fetch(`/api/seasons/1/games/week/${week}`);
        const data = await response.json();
        if (data.success) {
          this.weekMatches = data.data.games;
        }
      } catch (error) {
        this.showNotification(`Failed to load week ${week} matches`, 'error');
      } finally {
        this.loading.matches = false;
      }
    },

    async simulateWeek(week) {
      this.loading.simulation = true;
      try {
        const response = await fetch(`/api/advanced/seasons/1/simulate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            week: week,
            mode: 'realistic' // Enhanced simulation with form and momentum
          })
        });
        const data = await response.json();
        
        if (data.success) {
          this.showNotification(`Week ${week} enhanced simulation complete! (${data.data.games_simulated} games)`, 'success');
          await Promise.all([
            this.loadLeagueTable(),
            this.loadWeekMatches(week)
          ]);
        } else {
          this.showNotification(data.message, 'error');
        }
      } catch (error) {
        this.showNotification('Failed to simulate week', 'error');
      } finally {
        this.loading.simulation = false;
      }
    },

    async simulateSeason() {
      this.loading.simulation = true;
      try {
        const response = await fetch('/api/seasons/1/simulate', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });
        const data = await response.json();
        
        if (data.success) {
          this.showNotification('Season simulated successfully!', 'success');
          await Promise.all([
            this.loadLeagueTable(),
            this.loadWeekMatches(this.currentWeek)
          ]);
        } else {
          this.showNotification(data.message, 'error');
        }
      } catch (error) {
        this.showNotification('Failed to simulate season', 'error');
      } finally {
        this.loading.simulation = false;
      }
    },

    async simulateMatch(gameId) {
      try {
        const response = await fetch(`/api/games/${gameId}/simulate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });
        const data = await response.json();
        
        if (data.success) {
          this.showNotification('Match simulated successfully!', 'success');
          await Promise.all([
            this.loadLeagueTable(),
            this.loadWeekMatches(this.currentWeek)
          ]);
        } else {
          this.showNotification(data.message, 'error');
        }
      } catch (error) {
        this.showNotification('Failed to simulate match', 'error');
      }
    },

    async updateMatchResult(gameId, homeGoals, awayGoals) {
      try {
        const response = await fetch(`/api/games/${gameId}/result`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            home_goals: homeGoals,
            away_goals: awayGoals
          })
        });
        const data = await response.json();
        
        if (data.success) {
          this.showNotification('Match result updated successfully!', 'success');
          await Promise.all([
            this.loadLeagueTable(),
            this.loadWeekMatches(this.currentWeek)
          ]);
        } else {
          this.showNotification(data.message, 'error');
        }
      } catch (error) {
        this.showNotification('Failed to update match result', 'error');
      }
    },

    async generatePredictions() {
      this.loading.predictions = true;
      try {
        const response = await fetch('/api/advanced/seasons/1/predictions');
        const data = await response.json();
        if (data.success) {
          // Use consensus predictions (combines all 5 algorithms)
          this.predictions = data.data.predictions.consensus;
          this.showNotification('Advanced AI predictions generated! (5 algorithms combined)', 'success');
        }
      } catch (error) {
        this.showNotification('Failed to generate predictions', 'error');
      } finally {
        this.loading.predictions = false;
      }
    },

    async startAutoPlay() {
      this.loading.simulation = true;
      try {
        const response = await fetch('/api/autoplay/seasons/1/start', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            speed: 'normal',
            mode: 'realistic'
          })
        });
        const data = await response.json();
        
        if (data.success) {
          this.showNotification(`🤖 Auto-play started! Simulated ${data.data.games_simulated} games with analytics`, 'success');
          await Promise.all([
            this.loadLeagueTable(),
            this.loadWeekMatches(this.currentWeek)
          ]);
        } else {
          this.showNotification(data.message, 'error');
        }
      } catch (error) {
        this.showNotification('Failed to start auto-play', 'error');
      } finally {
        this.loading.simulation = false;
      }
    },

    async changeWeek(week) {
      if (week >= 1 && week <= this.totalWeeks) {
        this.currentWeek = week;
        await this.loadWeekMatches(week);
      }
    },

    showNotification(message, type = 'success') {
      const notification = {
        id: Date.now(),
        message,
        type
      };
      this.notifications.push(notification);
      
      setTimeout(() => {
        const index = this.notifications.findIndex(n => n.id === notification.id);
        if (index > -1) {
          this.notifications.splice(index, 1);
        }
      }, 4000);
    }
  }
};
</script>

<style scoped>
.league-simulation {
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  background-attachment: fixed;
}

.container {
  max-width: 1200px;
}
</style> 