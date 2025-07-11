<template>
  <div class="final-predictions">
    <div class="bg-white rounded-lg shadow-xl p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-bold text-gray-800 flex items-center">
          <svg class="w-6 h-6 mr-2 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"></path>
          </svg>
          Final Predictions
        </h3>
        
        <button 
          @click="$emit('generate-predictions')"
          :disabled="loading"
          class="px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white text-sm font-medium rounded-lg transition-colors duration-200 flex items-center"
        >
          <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M9.504 1.132a1 1 0 01.992 0l1.75 1a1 1 0 11-.992 1.736L10 3.152l-1.254.716a1 1 0 11-.992-1.736l1.75-1zM5.618 4.504a1 1 0 01-.372 1.364L5.016 6l.23.132a1 1 0 11-.992 1.736L3 7.347V8a1 1 0 01-2 0V6a.996.996 0 01.52-.878l1.734-.99a1 1 0 011.364.372zm8.764 0a1 1 0 011.364-.372l1.734.99A.996.996 0 0118 6v2a1 1 0 11-2 0v-.653l-1.254.721a1 1 0 11-.992-1.736L14.984 6l-.23-.132a1 1 0 01-.372-1.364z" clip-rule="evenodd"></path>
          </svg>
          {{ loading ? 'Generating...' : 'Generate' }}
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600 mx-auto mb-4"></div>
        <p class="text-gray-500">Generating predictions...</p>
      </div>

      <!-- Predictions Table -->
      <div v-else-if="predictions.length" class="space-y-4">
        <!-- Info Banner -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
          <div class="flex items-start">
            <svg class="w-5 h-5 text-purple-500 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div>
              <p class="text-sm text-purple-800 font-medium">AI-Generated Predictions</p>
              <p class="text-xs text-purple-600 mt-1">
                Based on team strength and current form. Results may vary from actual simulation.
              </p>
            </div>
          </div>
        </div>

        <!-- Compact Predictions Table -->
        <div class="space-y-2">
          <div 
            v-for="(team, index) in predictions" 
            :key="team.team.id"
            class="flex items-center justify-between p-3 rounded-lg border hover:bg-gray-50 transition-colors"
            :class="getPredictionRowClass(team.position)"
          >
            <!-- Position & Team -->
            <div class="flex items-center space-x-3 flex-1 min-w-0">
              <span 
                :class="getPositionClass(team.position)"
                class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold flex-shrink-0"
              >
                {{ team.position }}
              </span>
              
              <div 
                class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                :style="{ backgroundColor: team.team.primary_color }"
              >
                {{ getTeamInitials(team.team.name) }}
              </div>
              
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900 truncate">{{ team.team.name }}</p>
              </div>
            </div>

            <!-- Stats -->
            <div class="flex items-center space-x-4 text-xs text-gray-600">
              <div class="text-center">
                <div class="font-medium text-gray-900">{{ team.points }}</div>
                <div>Pts</div>
              </div>
              <div class="text-center">
                <div class="font-medium" :class="team.goal_difference >= 0 ? 'text-green-600' : 'text-red-600'">
                  {{ team.goal_difference >= 0 ? '+' : '' }}{{ team.goal_difference }}
                </div>
                <div>GD</div>
              </div>
              <div class="text-center">
                <div class="font-medium text-gray-900">{{ team.played }}</div>
                <div>P</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Prediction Legend -->
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
          <div class="flex items-center justify-center space-x-4 text-xs">
            <div class="flex items-center space-x-1">
              <span class="w-2 h-2 bg-yellow-500 rounded-full"></span>
              <span class="text-gray-600">Champion</span>
            </div>
            <div class="flex items-center space-x-1">
              <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
              <span class="text-gray-600">Top 2</span>
            </div>
            <div class="flex items-center space-x-1">
              <span class="w-2 h-2 bg-red-500 rounded-full"></span>
              <span class="text-gray-600">Bottom</span>
            </div>
          </div>
        </div>

        <!-- Summary Stats -->
        <div class="mt-4 grid grid-cols-2 gap-4 text-center">
          <div class="p-3 bg-green-50 rounded-lg">
            <div class="text-lg font-bold text-green-700">{{ getProjectedChampion() }}</div>
            <div class="text-xs text-green-600">Projected Champion</div>
          </div>
          <div class="p-3 bg-blue-50 rounded-lg">
            <div class="text-lg font-bold text-blue-700">{{ getMaxPoints() }} pts</div>
            <div class="text-xs text-blue-600">Winning Points</div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8">
        <div class="text-gray-400 mb-4">
          <svg class="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4A1 1 0 0116 13H6a1 1 0 00-1 1v3a1 1 0 11-2 0V6z" clip-rule="evenodd"></path>
          </svg>
        </div>
        <p class="text-gray-500 mb-4">No predictions generated yet</p>
        <button 
          @click="$emit('generate-predictions')"
          class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors"
        >
          Generate Predictions
        </button>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FinalPredictions',
  props: {
    predictions: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  methods: {
    getPredictionRowClass(position) {
      if (position === 1) {
        return 'border-yellow-200 bg-yellow-50';
      } else if (position === 2) {
        return 'border-blue-200 bg-blue-50';
      } else if (position >= this.predictions.length - 1) {
        return 'border-red-200 bg-red-50';
      }
      return 'border-gray-200';
    },
    
    getPositionClass(position) {
      if (position === 1) {
        return 'bg-yellow-500 text-white';
      } else if (position === 2) {
        return 'bg-gray-400 text-white';
      } else if (position === 3) {
        return 'bg-yellow-600 text-white';
      } else if (position >= this.predictions.length - 1) {
        return 'bg-red-500 text-white';
      }
      return 'bg-gray-200 text-gray-700';
    },
    
    getTeamInitials(teamName) {
      return teamName
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
    },
    
    getProjectedChampion() {
      if (this.predictions.length > 0) {
        return this.predictions[0].team.name;
      }
      return '-';
    },
    
    getMaxPoints() {
      if (this.predictions.length > 0) {
        return this.predictions[0].points;
      }
      return 0;
    }
  }
};
</script>

<style scoped>
.final-predictions {
  @apply transition-all duration-300;
}
</style> 