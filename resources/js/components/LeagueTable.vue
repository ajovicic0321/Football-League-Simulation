<template>
  <div class="league-table">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden">
      <!-- Table Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-4">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-bold">League Table</h2>
          <button 
            @click="$emit('refresh')"
            :disabled="loading"
            class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition-all duration-200 disabled:opacity-50"
          >
            <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
            </svg>
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="p-8 text-center">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-500">Loading league table...</p>
      </div>

      <!-- Table Content -->
      <div v-else-if="standings.length" class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pos</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">P</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">W</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">D</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">L</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">GF</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">GA</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">GD</th>
              <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Pts</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr 
              v-for="(team, index) in standings" 
              :key="team.team.id"
              :class="getRowClass(team.position)"
              class="hover:bg-gray-50 transition-colors duration-150"
            >
              <!-- Position -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <span 
                    :class="getPositionClass(team.position)"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold"
                  >
                    {{ team.position }}
                  </span>
                </div>
              </td>

              <!-- Team -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div 
                    class="w-8 h-8 rounded-full mr-3 flex items-center justify-center text-white text-xs font-bold"
                    :style="{ backgroundColor: team.team.primary_color }"
                  >
                    {{ getTeamInitials(team.team.name) }}
                  </div>
                  <div>
                    <div class="text-sm font-medium text-gray-900">
                      {{ team.team.name }}
                    </div>
                    <div class="text-sm text-gray-500">
                      {{ team.team.city }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Stats -->
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                {{ team.played }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600 font-medium">
                {{ team.won }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-yellow-600 font-medium">
                {{ team.drawn }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-red-600 font-medium">
                {{ team.lost }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                {{ team.goals_for }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                {{ team.goals_against }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium"
                  :class="team.goal_difference >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ team.goal_difference >= 0 ? '+' : '' }}{{ team.goal_difference }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-bold text-gray-900">
                {{ team.points }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div v-else class="p-8 text-center">
        <div class="text-gray-400 mb-4">
          <svg class="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
          </svg>
        </div>
        <p class="text-gray-500">No league data available</p>
      </div>

      <!-- Table Legend -->
      <div class="bg-gray-50 px-6 py-3 border-t">
        <div class="flex flex-wrap items-center justify-center space-x-6 text-xs text-gray-600">
          <div class="flex items-center space-x-2">
            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
            <span>Champions League</span>
          </div>
          <div class="flex items-center space-x-2">
            <span class="w-3 h-3 bg-blue-500 rounded-full"></span>
            <span>Europa League</span>
          </div>
          <div class="flex items-center space-x-2">
            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
            <span>Relegation</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'LeagueTable',
  props: {
    standings: {
      type: Array,
      default: () => []
    },
    loading: {
      type: Boolean,
      default: false
    }
  },
  methods: {
    getRowClass(position) {
      if (position === 1) {
        return 'bg-green-50 border-l-4 border-green-500';
      } else if (position === 2) {
        return 'bg-blue-50 border-l-4 border-blue-500';
      } else if (position >= this.standings.length - 1) {
        return 'bg-red-50 border-l-4 border-red-500';
      }
      return '';
    },
    
    getPositionClass(position) {
      if (position === 1) {
        return 'bg-yellow-500 text-white'; // Gold for 1st
      } else if (position === 2) {
        return 'bg-gray-400 text-white'; // Silver for 2nd
      } else if (position === 3) {
        return 'bg-yellow-600 text-white'; // Bronze for 3rd
      } else if (position >= this.standings.length - 1) {
        return 'bg-red-500 text-white'; // Red for relegation
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
    }
  }
};
</script>

<style scoped>
.league-table {
  @apply transition-all duration-300;
}

tbody tr:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style> 