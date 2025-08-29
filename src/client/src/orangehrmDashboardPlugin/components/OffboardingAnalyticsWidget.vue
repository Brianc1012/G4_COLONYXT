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
          <!-- BPO Turnover Alert Banner -->
          <div
            v-if="turnoverAlertLevel !== 'good'"
            class="orangehrm-turnover-alert"
            :class="`alert-${turnoverAlertLevel}`"
          >
            <oxd-icon :name="turnoverAlertIcon" class="orangehrm-alert-icon" />
            <span class="orangehrm-alert-text"
              >{{ turnoverAlertLevel.toUpperCase() }} TURNOVER</span
            >
          </div>

          <oxd-text
            tag="h3"
            class="orangehrm-offboarding-number orangehrm-turnover-rate"
            :style="{color: turnoverAlertColor}"
          >
            {{ analytics.turnoverRate || 0 }}%
          </oxd-text>
          <oxd-text tag="p" class="orangehrm-offboarding-label">
            Annual Turnover Rate
          </oxd-text>

          <!-- BPO Industry Benchmark -->
          <oxd-text tag="p" class="orangehrm-bpo-benchmark">
            BPO Industry Avg: 30-40%
          </oxd-text>

          <!-- Alert Message for BPO -->
          <oxd-text
            v-if="turnoverAlertLevel !== 'good'"
            tag="p"
            class="orangehrm-alert-message"
          >
            {{ turnoverAlertMessage }}
          </oxd-text>
        </div>
      </oxd-grid-item>
    </oxd-grid>

    <!-- BPO-Specific Recommendations Panel -->
    <div
      v-if="turnoverAlertLevel === 'high' || turnoverAlertLevel === 'critical'"
      class="orangehrm-bpo-recommendations"
    >
      <oxd-text tag="h6" class="orangehrm-recommendations-title">
        🎯 BPO Retention Strategies
      </oxd-text>
      <div class="orangehrm-recommendations-grid">
        <div
          v-for="(recommendation, index) in bpoRecommendations"
          :key="index"
          class="orangehrm-recommendation-item"
        >
          <oxd-icon name="check-circle" class="orangehrm-recommendation-icon" />
          <span>{{ recommendation }}</span>
        </div>
      </div>
    </div>

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
import BaseWidget from '@/orangehrmDashboardPlugin/components/BaseWidget.vue';
import {OxdPieChart, CHART_COLORS} from '@ohrm/oxd';

export default {
  name: 'OffboardingAnalyticsWidget',

  components: {
    'base-widget': BaseWidget,
    'oxd-pie-chart': OxdPieChart,
  },

  setup() {
    // Use direct fetch for test API since it's not a standard API endpoint
    const http = {
      getAll: async () => {
        // Construct the URL more reliably
        const baseUrl = window.appGlobal.baseUrl;
        // Remove index.php and any trailing slash, then add our endpoint
        const cleanBase = baseUrl.replace('/index.php', '').replace(/\/$/, '');
        const apiUrl = `${cleanBase}/test_offboarding_api.php`;

        console.log('Fetching from URL:', apiUrl);

        const response = await fetch(apiUrl);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();
        return {data: data.data}; // Extract the data array from the response
      },
    };

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
        totalActiveEmployees: 0,
        monthlyTrend: [],
        departmentBreakdown: [],
        jobTitleBreakdown: [],
        averageTenureMonths: 0,
      },
      recentDepartures: [],
      reasonDataset: [],
      bpoMetrics: {},
      isLoading: false,
      isEmpty: false,
    };
  },

  computed: {
    hasReasonData() {
      return this.reasonDataset.length > 0;
    },

    // Checks and sets the turnoverrate data
    turnoverAlertLevel() {
      return this.bpoMetrics.alertLevel || 'good';
    },

    // Sets the turnoverrate message in each rates
    turnoverAlertMessage() {
      const rate = this.analytics.turnoverRate;
      const level = this.turnoverAlertLevel;

      switch (level) {
        case 'critical':
          return `🚨 ${rate}% turnover requires immediate BPO retention intervention`;
        case 'high':
          return `⚠️ ${rate}% turnover is above BPO industry standards (${
            this.bpoMetrics.industryAverage || 35
          }%)`;
        case 'moderate':
          return `📊 ${rate}% turnover is within BPO industry range - monitor trends`;
        default:
          return `✅ ${rate}% turnover is excellent for BPO operations`;
      }
    },

    turnoverAlertColor() {
      switch (this.turnoverAlertLevel) {
        case 'critical':
          return '#dc3545'; // Red
        case 'high':
          return '#fd7e14'; // Orange
        case 'moderate':
          return '#ffc107'; // Yellow
        default:
          return '#28a745'; // Green
      }
    },

    turnoverAlertIcon() {
      switch (this.turnoverAlertLevel) {
        case 'critical':
          return 'exclamation-triangle';
        case 'high':
          return 'exclamation-circle';
        case 'moderate':
          return 'info-circle';
        default:
          return 'check-circle';
      }
    },

    bpoRecommendations() {
      return (
        this.bpoMetrics.recommendations || [
          'Implement employee recognition programs',
          'Enhance training and development opportunities',
          'Review workplace culture and management practices',
        ]
      );
    },
  },

  beforeMount() {
    this.loadAnalytics();
  },

  methods: {
    async loadAnalytics() {
      this.isLoading = true;

      try {
        // Use the actual API endpoint
        const response = await this.http.getAll();

        if (response.data && response.data.length > 0) {
          this.processAnalyticsData(response.data[0]);
        } else {
          // Fallback data if no API response
          this.processAnalyticsData({
            totalOffboarded: 0,
            turnoverRate: 0,
            totalActiveEmployees: 0,
            reasonBreakdown: [],
            recentDepartures: [],
            monthlyTrend: [],
            departmentBreakdown: [],
            jobTitleBreakdown: [],
            averageTenureMonths: 0,
            bpoMetrics: {
              alertLevel: 'good',
              benchmarks: {low: 25, moderate: 35, high: 50, critical: 50},
              recommendations: [],
              industryAverage: 35,
              riskFactors: [],
            },
          });
        }
      } catch (error) {
        console.error('Error loading offboarding analytics:', error);
        console.log('BaseURL:', window.appGlobal.baseUrl);

        // Fallback to hardcoded data on error

        // Fallback data in case of API error
        this.processAnalyticsData({
          totalOffboarded: 0,
          turnoverRate: 0,
          totalActiveEmployees: 0,
          reasonBreakdown: [],
          recentDepartures: [],
          monthlyTrend: [],
          departmentBreakdown: [],
          jobTitleBreakdown: [],
          averageTenureMonths: 0,
          bpoMetrics: {
            alertLevel: 'good',
            benchmarks: {low: 25, moderate: 35, high: 50, critical: 50},
            recommendations: [],
            industryAverage: 35,
            riskFactors: [],
          },
        });
      }

      this.isLoading = false;
    },

    processAnalyticsData(data) {
      this.analytics = {
        totalOffboarded: data.totalOffboarded || 0,
        turnoverRate: data.turnoverRate || 0,
        reasonBreakdown: data.reasonBreakdown || [],
        totalActiveEmployees: data.totalActiveEmployees || 0,
        monthlyTrend: data.monthlyTrend || [],
        departmentBreakdown: data.departmentBreakdown || [],
        jobTitleBreakdown: data.jobTitleBreakdown || [],
        averageTenureMonths: data.averageTenureMonths || 0,
      };

      this.recentDepartures = data.recentDepartures || [];
      this.bpoMetrics = data.bpoMetrics || {};
      this.isEmpty = this.analytics.totalOffboarded === 0;

      this.buildReasonChart();
    },

    buildReasonChart() {
      if (
        !this.analytics.reasonBreakdown ||
        this.analytics.reasonBreakdown.length === 0
      ) {
        this.reasonDataset = [];
        return;
      }

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
          value: item.count,
          color: colors[index % colors.length],
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
// Ensure the widget takes full width when used in full-width context
:deep(.orangehrm-widget) {
  width: 100% !important;
  max-width: none !important;
}

// Ensure the base widget container takes full width
:deep(.oxd-widget) {
  width: 100% !important;
  max-width: none !important;
}

// BPO Alert Styles
.orangehrm-turnover-alert {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.375rem 0.75rem;
  border-radius: 0.375rem;
  margin-bottom: 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;

  &.alert-moderate {
    background: rgba(255, 193, 7, 0.15);
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.4);
  }

  &.alert-high {
    background: rgba(253, 126, 20, 0.15);
    color: #8a4116;
    border: 1px solid rgba(253, 126, 20, 0.4);
  }

  &.alert-critical {
    background: rgba(220, 53, 69, 0.15);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.4);
  }
}

.orangehrm-alert-icon {
  margin-right: 0.375rem;
}

.orangehrm-bpo-benchmark {
  font-size: 0.75rem;
  color: #6c757d;
  font-style: italic;
  margin-top: 0.25rem;
}

.orangehrm-alert-message {
  font-size: 0.75rem;
  color: #495057;
  text-align: center;
  margin-top: 0.5rem;
  line-height: 1.4;
  font-weight: 500;
}

.orangehrm-bpo-recommendations {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.75rem;
  padding: 1.25rem;
  margin: 1.5rem 0;
}

.orangehrm-recommendations-title {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--oxd-primary-one-color, #0b6449);
  text-align: center;
}

.orangehrm-recommendations-grid {
  display: grid;
  grid-template-columns: repeat(
    auto-fit,
    minmax(280px, 1fr)
  ); // Wider minimum for better readability
  gap: 1rem; // More generous gap
}

.orangehrm-recommendation-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem; // More padding
  background: #f8f9fa;
  border-radius: 0.5rem;
  font-size: 0.875rem;
  color: #495057;
  border: 1px solid #e9ecef;
  transition: background-color 0.2s ease;
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );

  &:hover {
    background: #e9ecef;
  }
}

.orangehrm-recommendation-icon {
  color: #28a745;
  margin-right: 0.75rem; // More space after icon
  flex-shrink: 0; // Prevent icon from shrinking
}

.orangehrm-offboarding-summary {
  margin-bottom: 1.5rem;
  gap: 1rem; // Better spacing between cards
}

.orangehrm-offboarding-card {
  background: #f8f9fa;
  border-radius: 0.75rem;
  padding: 1.5rem; // More generous padding
  border: 1px solid #e9ecef;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); // Subtle shadow for depth
  transition: transform 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
}

.orangehrm-offboarding-metric {
  text-align: center;
}

.orangehrm-offboarding-number {
  font-size: 2.25rem; // Larger, more prominent numbers
  font-weight: 700;
  color: var(--oxd-primary-one-color, #0b6449);
  margin-bottom: 0.5rem; // More space below numbers
  line-height: 1.1; // Tighter line height for large numbers
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );

  &.orangehrm-turnover-rate {
    transition: color 0.3s ease;
  }
}

.orangehrm-offboarding-label {
  font-size: 0.875rem;
  color: #6c757d;
  font-weight: 500;
  line-height: 1.4;
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
}

.orangehrm-offboarding-chart {
  margin: 2rem 0; // More space around chart
  padding: 1rem;
  background: white;
  border-radius: 0.75rem;
  border: 1px solid #e9ecef;
}

.orangehrm-offboarding-chart-title {
  font-size: 1.125rem; // Slightly larger
  font-weight: 600;
  margin-bottom: 1.25rem; // More space below title
  color: var(--oxd-primary-one-color, #0b6449);
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
}

.orangehrm-offboarding-recent {
  margin-top: 2rem; // More space above section
}

.orangehrm-offboarding-section-title {
  font-size: 1.125rem; // Consistent with chart title
  font-weight: 600;
  margin-bottom: 1.25rem;
  color: var(--oxd-primary-one-color, #0b6449);
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
}

.orangehrm-offboarding-list {
  max-height: 180px; // Slightly taller
  overflow-y: auto;
  padding: 0.5rem 0; // Padding for better visual balance
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
  height: 300px; // Increased height to accommodate longer labels
  width: 100%;

  .oxd-pie-chart {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .oxd-pie-chart__legend {
    margin-top: 1rem;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;

    .oxd-pie-chart__legend-item {
      max-width: none; // Allow full text to show
      white-space: normal; // Allow text wrapping
      word-break: break-word; // Break long words if needed
      flex: 0 1 auto; // Allow flexible sizing
    }

    .oxd-pie-chart__legend-text {
      font-size: 0.875rem;
      line-height: 1.4;
      margin-left: 0.5rem;
    }
  }
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

.orangehrm-offboarding-chart-title,
.orangehrm-offboarding-section-title,
.orangehrm-recommendations-title {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 1.125rem; // Slightly larger for better hierarchy
  font-weight: 600;
  margin-bottom: 1rem;
  color: var(--oxd-primary-one-color, #0b6449);
  letter-spacing: -0.025em; // OrangeHRM's letter spacing
}

// Update all text elements to use consistent typography
.orangehrm-offboarding-number {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 2.25rem; // Larger number display
  font-weight: 700;
  color: var(--oxd-primary-one-color, #0b6449);
  margin-bottom: 0.5rem;
  letter-spacing: -0.05em;

  &.orangehrm-turnover-rate {
    transition: color 0.3s ease;
  }
}

.orangehrm-offboarding-label {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 0.875rem;
  color: var(--oxd-text-color-subtle, #6c757d);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

// Fix alert text typography
.orangehrm-alert-message,
.orangehrm-bpo-benchmark {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 0.8125rem;
  line-height: 1.4;
}

// Fix recommendations typography
.orangehrm-recommendation-item {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 0.875rem;
  font-weight: 500;
  line-height: 1.4;
}

// Fix employee list typography
.orangehrm-offboarding-name {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--oxd-text-color, #212529);
  margin-bottom: 0.25rem;
}

.orangehrm-offboarding-position,
.orangehrm-offboarding-date {
  font-family: var(
    --oxd-font-family,
    'Inter',
    -apple-system,
    BlinkMacSystemFont,
    'Segoe UI',
    sans-serif
  );
  font-size: 0.8125rem;
  color: var(--oxd-text-color-subtle, #6c757d);
  font-weight: 400;
}

// Better responsive behavior
@media (max-width: 768px) {
  .orangehrm-offboarding-summary {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .orangehrm-recommendations-grid {
    grid-template-columns: 1fr;
  }

  .orangehrm-offboarding-card {
    padding: 1.25rem;
  }

  .orangehrm-offboarding-number {
    font-size: 2rem;
  }

  .orangehrm-offboarding-chart {
    margin: 1.5rem 0;
    padding: 0.75rem;
  }
}

// Widget container improvements for better spacing
::v-deep(.orangehrm-dashboard-widget) {
  .orangehrm-widget-body {
    padding: 1.5rem;
  }

  // Risk Overview Cards
  .orangehrm-risk-overview {
    margin-bottom: 2rem;
  }

  .orangehrm-risk-card {
    text-align: center;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 2px solid transparent;
    transition: all 0.3s ease;

    &.risk-critical {
      background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
      border-color: #fc8181;

      .orangehrm-risk-icon {
        color: #e53e3e;
      }
    }

    &.risk-high {
      background: linear-gradient(135deg, #fffaf0 0%, #fbd38d 100%);
      border-color: #f6ad55;

      .orangehrm-risk-icon {
        color: #dd6b20;
      }
    }

    &.risk-low {
      background: linear-gradient(135deg, #f0fff4 0%, #9ae6b4 100%);
      border-color: #68d391;

      .orangehrm-risk-icon {
        color: #38a169;
      }
    }
  }

  .orangehrm-risk-metric {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
  }

  .orangehrm-risk-icon {
    font-size: 2rem;
  }

  .orangehrm-risk-count {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
  }

  .orangehrm-risk-label {
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
    color: #6b7280;
  }

  // High Risk Employee Alert
  .orangehrm-high-risk-alert {
    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
    border: 2px solid #fc8181;
    border-radius: 0.75rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
  }

  .orangehrm-alert-title {
    color: #c53030;
    margin-bottom: 1rem;
    font-weight: 700;
  }

  .orangehrm-high-risk-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .orangehrm-high-risk-employee {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    align-items: center;
    gap: 1rem;
    background: white;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;

    &:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }
  }

  .orangehrm-employee-info {
    .orangehrm-employee-name {
      font-weight: 600;
      color: #2d3748;
      margin: 0 0 0.25rem 0;
    }

    .orangehrm-employee-details {
      color: #718096;
      margin: 0;
      font-size: 0.875rem;
    }
  }

  .orangehrm-risk-details {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
  }

  .orangehrm-risk-score {
    font-size: 1.25rem;
    font-weight: 700;
    padding: 0.25rem 0.75rem;
    border-radius: 0.375rem;

    &.score-critical {
      background: #fed7d7;
      color: #c53030;
    }

    &.score-high {
      background: #fbd38d;
      color: #dd6b20;
    }
  }

  .orangehrm-risk-factors {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    justify-content: center;
  }

  .orangehrm-risk-factor {
    background: #edf2f7;
    color: #4a5568;
    padding: 0.125rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 500;
  }

  .orangehrm-risk-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
  }

  .orangehrm-action-btn {
    font-size: 0.875rem !important;
    padding: 0.375rem 0.75rem !important;
  }

  // Risk Trends
  .orangehrm-risk-trends {
    margin-bottom: 2rem;
  }

  .orangehrm-chart-title {
    margin-bottom: 1rem;
    color: #495057;
    font-weight: 600;
  }

  .orangehrm-trend-chart {
    background: white;
    padding: 1.5rem;
    border-radius: 0.5rem;
    border: 1px solid #e9ecef;
  }

  .orangehrm-trend-bars {
    display: flex;
    justify-content: space-between;
    align-items: end;
    height: 150px;
    gap: 1rem;
  }

  .orangehrm-trend-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
  }

  .orangehrm-bar-fill {
    width: 100%;
    background: #e2e8f0;
    border-radius: 0.25rem 0.25rem 0 0;
    transition: all 0.3s ease;

    &.trend-critical {
      background: linear-gradient(to top, #fc8181, #e53e3e);
    }

    &.trend-high {
      background: linear-gradient(to top, #f6ad55, #dd6b20);
    }

    &.trend-moderate {
      background: linear-gradient(to top, #fbd38d, #d69e2e);
    }
  }

  .orangehrm-trend-label {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: #6b7280;
    font-weight: 500;
  }

  // Predictive Insights
  .orangehrm-predictive-insights {
    margin-bottom: 1rem;
  }

  .orangehrm-insights-title {
    margin-bottom: 1rem;
    color: #495057;
    font-weight: 600;
  }

  .orangehrm-insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
  }

  .orangehrm-insight-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 2px solid transparent;
    transition: all 0.3s ease;

    &.insight-critical {
      background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
      border-color: #fc8181;
    }

    &.insight-high {
      background: linear-gradient(135deg, #fffaf0 0%, #fbd38d 100%);
      border-color: #f6ad55;
    }

    &.insight-good {
      background: linear-gradient(135deg, #f0fff4 0%, #9ae6b4 100%);
      border-color: #68d391;
    }
  }

  .orangehrm-insight-icon {
    font-size: 1.5rem;
    margin-top: 0.25rem;

    .insight-critical & {
      color: #e53e3e;
    }

    .insight-high & {
      color: #dd6b20;
    }

    .insight-good & {
      color: #38a169;
    }
  }

  .orangehrm-insight-content {
    flex: 1;
  }

  .orangehrm-insight-text {
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #2d3748;
  }

  .orangehrm-insight-action {
    margin: 0;
    font-size: 0.875rem;
    color: #718096;
    font-style: italic;
  }
}
</style>
