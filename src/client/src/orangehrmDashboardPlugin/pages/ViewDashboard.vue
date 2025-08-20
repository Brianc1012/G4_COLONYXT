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
  <oxd-grid class="orangehrm-dashboard-grid" :cols="3">
    <!-- Birthday Widget - Shows when user has birthday today -->
    <oxd-grid-item
      v-if="showBirthdayWidget"
      class="orangehrm-dashboard-widget orangehrm-birthday-widget-container"
    >
      <birthday-greetings-widget
        @birthday-status="handleBirthdayStatus"
      ></birthday-greetings-widget>
    </oxd-grid-item>

    <oxd-grid-item
      v-if="$can.read('dashboard_time_widget')"
      class="orangehrm-dashboard-widget"
    >
      <employee-attendance-widget></employee-attendance-widget>
    </oxd-grid-item>
    <oxd-grid-item class="orangehrm-dashboard-widget">
      <my-action-summary-widget></my-action-summary-widget>
    </oxd-grid-item>
    <oxd-grid-item class="orangehrm-dashboard-widget">
      <quick-launch-widget></quick-launch-widget>
    </oxd-grid-item>
    <oxd-grid-item
      v-if="$can.read('dashboard_buzz_widget')"
      class="orangehrm-dashboard-widget"
    >
      <buzz-latest-post-widget></buzz-latest-post-widget>
    </oxd-grid-item>
    <oxd-grid-item
      v-if="$can.read('dashboard_leave_widget')"
      class="orangehrm-dashboard-widget"
    >
      <employees-on-leave-widget></employees-on-leave-widget>
    </oxd-grid-item>
    <oxd-grid-item
      v-if="$can.read('dashboard_subunit_widget')"
      class="orangehrm-dashboard-widget"
    >
      <employee-subunit-widget></employee-subunit-widget>
    </oxd-grid-item>
    <oxd-grid-item
      v-if="$can.read('dashboard_location_widget')"
      class="orangehrm-dashboard-widget"
    >
      <employee-location-widget></employee-location-widget>
    </oxd-grid-item>

    <!-- Full-width Offboarding Analytics Widget spanning all 3 columns -->
    <oxd-grid-item
      class="orangehrm-dashboard-widget orangehrm-full-width-widget"
      :span="3"
    >
      <offboarding-analytics-widget></offboarding-analytics-widget>
    </oxd-grid-item>
  </oxd-grid>
</template>

<script>
import {APIService} from '@/core/util/services/api.service';
import QuickLaunchWidget from '@/orangehrmDashboardPlugin/components/QuickLaunchWidget.vue';
import BuzzLatestPostWidget from '@/orangehrmDashboardPlugin/components/BuzzLatestPostWidget.vue';
import EmployeeSubunitWidget from '@/orangehrmDashboardPlugin/components/EmployeeSubunitWidget.vue';
import MyActionSummaryWidget from '@/orangehrmDashboardPlugin/components/MyActionSummaryWidget.vue';
import EmployeeLocationWidget from '@/orangehrmDashboardPlugin/components/EmployeeLocationWidget.vue';
import EmployeesOnLeaveWidget from '@/orangehrmDashboardPlugin/components/EmployeesOnLeaveWidget.vue';
import EmployeeAttendanceWidget from '@/orangehrmDashboardPlugin/components/EmployeeAttendanceWidget.vue';
import BirthdayGreetingsWidget from '@/orangehrmDashboardPlugin/components/BirthdayGreetingsWidget.vue';
import OffboardingAnalyticsWidget from '@/orangehrmDashboardPlugin/components/OffboardingAnalyticsWidget.vue';

export default {
  components: {
    'quick-launch-widget': QuickLaunchWidget,
    'buzz-latest-post-widget': BuzzLatestPostWidget,
    'employee-subunit-widget': EmployeeSubunitWidget,
    'my-action-summary-widget': MyActionSummaryWidget,
    'employee-location-widget': EmployeeLocationWidget,
    'employees-on-leave-widget': EmployeesOnLeaveWidget,
    'employee-attendance-widget': EmployeeAttendanceWidget,
    'birthday-greetings-widget': BirthdayGreetingsWidget,
    'offboarding-analytics-widget': OffboardingAnalyticsWidget,
  },

  data() {
    return {
      showBirthdayWidget: true,
    };
  },

  mounted() {
    const http = new APIService(window.appGlobal.baseUrl, '/events/push');
    http.create();
  },

  methods: {
    handleBirthdayStatus(isBirthday) {
      this.showBirthdayWidget = isBirthday;
    },
  },
};
</script>

<style lang="scss" scoped>
.orangehrm-dashboard-grid {
  margin: 0 auto;
  box-sizing: border-box;
  max-width: calc(350px * 3);
  grid-template-columns: repeat(
    3,
    1fr
  ); // Fixed 3 columns for proper span behavior
  gap: 1rem;
}

// Full-width widget styling - spans all 3 columns
.orangehrm-full-width-widget {
  grid-column: 1 / -1; // Span from first to last column
  margin-top: 1rem;

  // Override BaseWidget max-width constraint
  ::v-deep(.orangehrm-dashboard-widget) {
    max-width: none !important;
    width: 100% !important;
    height: auto !important;
  }

  // Ensure the widget content takes full width
  ::v-deep(.orangehrm-widget) {
    width: 100%;
  }

  // Allow the widget body to expand
  ::v-deep(.orangehrm-dashboard-widget-body) {
    height: auto !important;
    overflow: visible !important;
  }

  @media (max-width: 768px) {
    margin-top: 0.75rem;
  }
}

// Remove grid lines/borders for birthday widget container
.orangehrm-birthday-widget-container {
  border: none !important;
  border-left: none !important;
  border-right: none !important;
  box-shadow: none !important;

  &::before,
  &::after {
    display: none !important;
  }
}
</style>
