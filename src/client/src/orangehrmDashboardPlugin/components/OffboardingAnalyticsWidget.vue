<!--
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */
 -->

<template>
  <base-widget
    icon="graph-down-arrow"
    :loading="isLoading"
    title="Offboarding Analytics"
    :empty="isEmpty"
  >
    <!-- Summary Cards -->
    <oxd-grid :cols="2" class="orangehrm-offboarding-summary">
      <oxd-grid-item class="orangehrm-offboarding-card">
        <div class="orangehrm-offboarding-metric">
          <oxd-text tag="h3" class="orangehrm-offboarding-number">
            {{ analytics.totalOffboarded || 0 }}
          </oxd-text>
          <oxd-text tag="p" class="orangehrm-offboarding-label">
            Total Offboarded (YTD)
          </oxd-text>
        </div>
      </oxd-grid-item>

      <oxd-grid-item class="orangehrm-offboarding-card">
        <div class="orangehrm-offboarding-metric">
          <oxd-text
            tag="h3"
            class="orangehrm-offboarding-number orangehrm-turnover-rate"
          >
            {{ analytics.turnoverRate || 0 }}%
          </oxd-text>
          <oxd-text tag="p" class="orangehrm-offboarding-label">
            Turnover Rate
          </oxd-text>
        </div>
      </oxd-grid-item>
    </oxd-grid>

    <!-- Reason Breakdown Chart -->
    <div v-if="hasReasonData" class="orangehrm-offboarding-chart">
      <oxd-text tag="h6" class="orangehrm-offboarding-chart-title">
        Termination Reasons
      </oxd-text>
      <oxd-pie-chart
        :data="reasonDataset"
        :aspect-ratio="false"
        :custom-legend="true"
        :custom-tooltip="true"
        wrapper-classes="offboarding-reason-chart"
      ></oxd-pie-chart>
    </div>

    <!-- Recent Departures -->
    <div
      v-if="recentDepartures.length > 0"
      class="orangehrm-offboarding-recent"
    >
      <oxd-text tag="h6" class="orangehrm-offboarding-section-title">
        Recent Departures
      </oxd-text>
      <div class="orangehrm-offboarding-list">
        <div
          v-for="departure in recentDepartures"
          :key="departure.empNumber"
          class="orangehrm-offboarding-item"
        >
          <div class="orangehrm-offboarding-employee">
            <oxd-text tag="p" class="orangehrm-offboarding-name">
              {{ departure.firstName }} {{ departure.lastName }}
            </oxd-text>
            <oxd-text tag="p" class="orangehrm-offboarding-position">
              {{ departure.jobTitle }}
            </oxd-text>
          </div>
          <div class="orangehrm-offboarding-date">
            <oxd-text tag="p">
              {{ formatDate(departure.terminationDate) }}
            </oxd-text>
          </div>
        </div>
      </div>
    </div>
  </base-widget>
</template>

<script>
import {APIService} from '@/core/util/services/api.service';
import BaseWidget from '@/orangehrmDashboardPlugin/components/BaseWidget.vue';
import {OxdPieChart, CHART_COLORS} from '@ohrm/oxd';

export default {
  name: 'OffboardingAnalyticsWidget',

  components: {
    'base-widget': BaseWidget,
    'oxd-pie-chart': OxdPieChart,
  },

  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/dashboard/offboarding',
    );

    return {
      http,
    };
  },

  data() {
    return {
      analytics: {
        totalOffboarded: 0,
        turnoverRate: 0,
        reasonBreakdown: [],
      },
      recentDepartures: [],
      reasonDataset: [],
      isLoading: false,
      isEmpty: false,
    };
  },

  computed: {
    hasReasonData() {
      return this.reasonDataset.length > 0;
    },
  },

  beforeMount() {
    this.loadAnalytics();
  },

  methods: {
    async loadAnalytics() {
      this.isLoading = true;
      try {
        // For demo purposes, we'll simulate the API call
        // In a real implementation, you would call the actual API
        await this.loadDemoData();

        // Real API call would be:
        // const response = await this.http.getAll();
        // this.processAnalyticsData(response.data);
      } catch (error) {
        // Handle error appropriately - could log to a service or show user message
        this.isEmpty = true;
      } finally {
        this.isLoading = false;
      }
    },

    async loadDemoData() {
      // Simulate API delay
      await new Promise((resolve) => setTimeout(resolve, 1000));

      // Demo data - replace with real API data
      this.analytics = {
        totalOffboarded: 23,
        turnoverRate: 8.5,
        reasonBreakdown: [
          {reason: 'Voluntary Resignation', count: 12},
          {reason: 'Performance Issues', count: 5},
          {reason: 'Redundancy', count: 4},
          {reason: 'End of Contract', count: 2},
        ],
      };

      this.recentDepartures = [
        {
          empNumber: 1,
          firstName: 'John',
          lastName: 'Smith',
          jobTitle: 'Software Engineer',
          terminationDate: '2024-08-05',
        },
        {
          empNumber: 2,
          firstName: 'Sarah',
          lastName: 'Johnson',
          jobTitle: 'Marketing Manager',
          terminationDate: '2024-08-03',
        },
        {
          empNumber: 3,
          firstName: 'Mike',
          lastName: 'Davis',
          jobTitle: 'Sales Representative',
          terminationDate: '2024-07-28',
        },
      ];

      this.buildReasonChart();
    },

    buildReasonChart() {
      const colors = [
        CHART_COLORS.COLOR_HEAT_WAVE,
        CHART_COLORS.COLOR_CHROME_YELLOW,
        CHART_COLORS.COLOR_MOUNTAIN_MEADOW,
        CHART_COLORS.COLOR_PACIFIC_BLUE,
        CHART_COLORS.COLOR_BLEU_DE_FRANCE,
      ];

      this.reasonDataset = this.analytics.reasonBreakdown.map(
        (item, index) => ({
          label: item.reason,
          data: item.count,
          backgroundColor: colors[index % colors.length],
        }),
      );
    },

    formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.orangehrm-offboarding-summary {
  margin-bottom: 1rem;
}

.orangehrm-offboarding-card {
  background: #f8f9fa;
  border-radius: 0.75rem;
  padding: 1rem;
  border: 1px solid #e9ecef;
}

.orangehrm-offboarding-metric {
  text-align: center;
}

.orangehrm-offboarding-number {
  font-size: 2rem;
  font-weight: 700;
  color: var(--oxd-primary-one-color, #ff7b1d);
  margin-bottom: 0.25rem;

  &.orangehrm-turnover-rate {
    color: #dc3545;
  }
}

.orangehrm-offboarding-label {
  font-size: 0.875rem;
  color: #6c757d;
  font-weight: 500;
}

.orangehrm-offboarding-chart {
  margin: 1.5rem 0;
}

.orangehrm-offboarding-chart-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--oxd-primary-one-color, #ff7b1d);
}

.orangehrm-offboarding-recent {
  margin-top: 1.5rem;
}

.orangehrm-offboarding-section-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--oxd-primary-one-color, #ff7b1d);
}

.orangehrm-offboarding-list {
  max-height: 150px;
  overflow-y: auto;
}

.orangehrm-offboarding-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  border-bottom: 1px solid #e9ecef;
  transition: background-color 0.2s;

  &:hover {
    background-color: #f8f9fa;
  }

  &:last-child {
    border-bottom: none;
  }
}

.orangehrm-offboarding-employee {
  flex: 1;
}

.orangehrm-offboarding-name {
  font-weight: 600;
  color: #212529;
  margin-bottom: 0.25rem;
}

.orangehrm-offboarding-position {
  font-size: 0.875rem;
  color: #6c757d;
}

.orangehrm-offboarding-date {
  font-size: 0.875rem;
  color: #6c757d;
  font-weight: 500;
}

// Chart wrapper styling
:deep(.offboarding-reason-chart) {
  height: 200px;
}

// Theme-specific overrides
:root {
  &[data-theme='blue'] {
    .orangehrm-offboarding-number {
      color: var(--oxd-primary-one-color, #2196f3);
    }

    .orangehrm-offboarding-chart-title,
    .orangehrm-offboarding-section-title {
      color: var(--oxd-primary-one-color, #2196f3);
    }
  }

  &[data-theme='green'] {
    .orangehrm-offboarding-number {
      color: var(--oxd-primary-one-color, #4caf50);
    }

    .orangehrm-offboarding-chart-title,
    .orangehrm-offboarding-section-title {
      color: var(--oxd-primary-one-color, #4caf50);
    }
  }

  &[data-theme='purple'] {
    .orangehrm-offboarding-number {
      color: var(--oxd-primary-one-color, #9c27b0);
    }

    .orangehrm-offboarding-chart-title,
    .orangehrm-offboarding-section-title {
      color: var(--oxd-primary-one-color, #9c27b0);
    }
  }

  &[data-theme='red'] {
    .orangehrm-offboarding-number {
      color: var(--oxd-primary-one-color, #f44336);
    }

    .orangehrm-offboarding-chart-title,
    .orangehrm-offboarding-section-title {
      color: var(--oxd-primary-one-color, #f44336);
    }
  }
}
</style>
