<template>
  <div class="matches-section">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white px-6 py-4">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
              <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
            </svg>
            Week {{ currentWeek }} Matches
          </h2>
          
          <!-- Week Navigation -->
          <div class="flex items-center space-x-2">
            <button 
              @click="changeWeek(currentWeek - 1)"
              :disabled="currentWeek <= 1"
              class="p-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg disabled:opacity-50 transition-colors"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path>
              </svg>
            </button>
            
            <span class="px-3 py-1 bg-white bg-opacity-20 rounded-lg text-sm">
              {{ currentWeek }} / {{ totalWeeks }}
            </span>
            
            <button 
              @click="changeWeek(currentWeek + 1)"
              :disabled="currentWeek >= totalWeeks"
              class="p-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg disabled:opacity-50 transition-colors"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-8 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600 mx-auto mb-4"></div>
        <p class="text-gray-500">Loading matches...</p>
      </div>

      <!-- Matches Content -->
      <div v-else-if="matches.length" class="p-6">
        <div class="space-y-4">
          <div 
            v-for="match in matches" 
            :key="match.id"
            class="match-card border rounded-lg p-4 hover:shadow-md transition-all duration-200"
            :class="getMatchCardClass(match)"
          >
            <div class="flex items-center justify-between">
              <!-- Teams -->
              <div class="flex items-center space-x-4 flex-1">
                <!-- Home Team -->
                <div class="flex items-center space-x-3 flex-1">
                  <div 
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                    :style="{ backgroundColor: match.home_team.primary_color }"
                  >
                    {{ getTeamInitials(match.home_team.name) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 truncate">{{ match.home_team.name }}</p>
                    <p class="text-sm text-gray-500">Home</p>
                  </div>
                </div>

                <!-- Score/Result -->
                <div class="flex-shrink-0 px-4">
                  <div v-if="match.status === 'completed'" class="text-center">
                    <div class="text-2xl font-bold text-gray-900 mb-1">
                      {{ match.home_goals }} - {{ match.away_goals }}
                    </div>
                    <div class="text-xs text-gray-500">Final</div>
                  </div>
                  <div v-else class="text-center">
                    <div class="text-lg text-gray-400 mb-1">VS</div>
                    <div class="text-xs text-gray-500">Scheduled</div>
                  </div>
                </div>

                <!-- Away Team -->
                <div class="flex items-center space-x-3 flex-1 flex-row-reverse">
                  <div 
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm"
                    :style="{ backgroundColor: match.away_team.primary_color }"
                  >
                    {{ getTeamInitials(match.away_team.name) }}
                  </div>
                  <div class="flex-1 min-w-0 text-right">
                    <p class="font-medium text-gray-900 truncate">{{ match.away_team.name }}</p>
                    <p class="text-sm text-gray-500">Away</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Match Actions -->
            <div class="mt-4 flex items-center justify-between">
              <!-- Match Info -->
              <div class="text-sm text-gray-500">
                <span>Week {{ match.week }}</span>
                <span class="mx-2">•</span>
                <span class="capitalize">{{ match.status }}</span>
                <span v-if="match.updated_at" class="mx-2">•</span>
                <span v-if="match.updated_at" class="text-xs">
                  {{ formatDate(match.updated_at) }}
                </span>
              </div>

              <!-- Action Buttons -->
              <div class="flex items-center space-x-2">
                <!-- Edit Result Button -->
                <button 
                  v-if="match.status === 'completed'"
                  @click="openEditModal(match)"
                  class="px-3 py-1 text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-800 rounded-lg transition-colors"
                >
                  <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                  </svg>
                  Edit
                </button>

                <!-- Simulate Button -->
                <button 
                  v-if="match.status === 'scheduled'"
                  @click="$emit('simulate-match', match.id)"
                  class="px-3 py-1 text-sm bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-lg transition-colors"
                >
                  <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                  </svg>
                  Simulate
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="p-8 text-center">
        <div class="text-gray-400 mb-4">
          <svg class="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
            <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
          </svg>
        </div>
        <p class="text-gray-500">No matches scheduled for week {{ currentWeek }}</p>
      </div>
    </div>

    <!-- Edit Result Modal -->
    <div v-if="editingMatch" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeEditModal">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4" @click.stop>
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-gray-900">Edit Match Result</h3>
            <button @click="closeEditModal" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
              </svg>
            </button>
          </div>

          <!-- Teams Display -->
          <div class="space-y-4 mb-6">
            <!-- Home Team -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <div 
                  class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
                  :style="{ backgroundColor: editingMatch.home_team.primary_color }"
                >
                  {{ getTeamInitials(editingMatch.home_team.name) }}
                </div>
                <span class="font-medium">{{ editingMatch.home_team.name }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Goals:</label>
                <input 
                  v-model.number="editForm.homeGoals"
                  type="number" 
                  min="0" 
                  max="10"
                  class="w-16 px-2 py-1 border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
              </div>
            </div>

            <!-- Away Team -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <div 
                  class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
                  :style="{ backgroundColor: editingMatch.away_team.primary_color }"
                >
                  {{ getTeamInitials(editingMatch.away_team.name) }}
                </div>
                <span class="font-medium">{{ editingMatch.away_team.name }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Goals:</label>
                <input 
                  v-model.number="editForm.awayGoals"
                  type="number" 
                  min="0" 
                  max="10"
                  class="w-16 px-2 py-1 border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
              </div>
            </div>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end space-x-3">
            <button 
              @click="closeEditModal"
              class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            >
              Cancel
            </button>
            <button 
              @click="saveMatchResult"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
            >
              Save Result
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MatchesSection',
  props: {
    matches: {
      type: Array,
      default: () => []
    },
    currentWeek: {
      type: Number,
      default: 1
    },
    totalWeeks: {
      type: Number,
      default: 12
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      editingMatch: null,
      editForm: {
        homeGoals: 0,
        awayGoals: 0
      }
    };
  },
  methods: {
    getMatchCardClass(match) {
      if (match.status === 'completed') {
        return 'border-green-200 bg-green-50';
      } else {
        return 'border-gray-200 bg-white';
      }
    },
    
    getTeamInitials(teamName) {
      return teamName
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .substring(0, 2);
    },
    
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    },
    
    changeWeek(week) {
      this.$emit('week-changed', week);
    },
    
    openEditModal(match) {
      this.editingMatch = match;
      this.editForm.homeGoals = match.home_goals || 0;
      this.editForm.awayGoals = match.away_goals || 0;
    },
    
    closeEditModal() {
      this.editingMatch = null;
      this.editForm = {
        homeGoals: 0,
        awayGoals: 0
      };
    },
    
    saveMatchResult() {
      if (this.editingMatch) {
        this.$emit('update-result', this.editingMatch.id, this.editForm.homeGoals, this.editForm.awayGoals);
        this.closeEditModal();
      }
    }
  }
};
</script>

<style scoped>
.match-card {
  @apply transition-all duration-200;
}

.match-card:hover {
  transform: translateY(-2px);
}
</style> 