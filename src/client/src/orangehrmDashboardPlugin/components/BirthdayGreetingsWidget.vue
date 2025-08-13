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
  <!-- Only show the widget if it's the current user's birthday -->
  <oxd-sheet
    v-if="isMyBirthday"
    class="orangehrm-dashboard-widget orangehrm-birthday-special"
    style="
      border: none !important;
      border-left: none !important;
      border-right: none !important;
      box-shadow: none !important;
    "
  >
    <div class="orangehrm-dashboard-widget-body">
      <div class="orangehrm-birthday-personal-card">
        <div class="orangehrm-birthday-celebration">
          <div class="orangehrm-birthday-emojis">🎂🎉🎈🎁</div>
          <div class="orangehrm-birthday-message">
            <oxd-text tag="h3" class="orangehrm-birthday-greeting">
              Happy Birthday, {{ currentUser.firstName }}!
            </oxd-text>
            <oxd-text tag="p" class="orangehrm-birthday-wish">
              Wishing you a wonderful year ahead! 🌟
            </oxd-text>
            <oxd-text v-if="userAge" tag="p" class="orangehrm-birthday-age">
              You're turning {{ userAge }} today! �
            </oxd-text>
          </div>
        </div>
        <div class="orangehrm-birthday-confetti">
          <div class="confetti"></div>
          <div class="confetti"></div>
          <div class="confetti"></div>
          <div class="confetti"></div>
          <div class="confetti"></div>
        </div>
      </div>
    </div>
  </oxd-sheet>
</template>

<script>
export default {
  name: 'BirthdayGreetingsWidget',

  data() {
    return {
      currentUser: {},
      isMyBirthday: false,
      userAge: null,
    };
  },

  mounted() {
    this.checkIfMyBirthday();
  },

  methods: {
    checkIfMyBirthday() {
      // Get current user data from global app state
      this.currentUser = window.appGlobal?.user || {};

      // For demo purposes - you can replace this with real user birthday data
      const today = new Date();
      const todayMonth = today.getMonth() + 1; // getMonth() returns 0-11
      const todayDate = today.getDate();

      // Demo: Show birthday widget on August 9th
      // In real implementation, check against user's actual birthday from profile
      if (todayMonth === 8 && todayDate === 10) {
        this.isMyBirthday = true;
        this.userAge = this.calculateAge('1996-08-10'); // Demo birth year
      }

      // Real implementation would look like:
      // const userBirthday = this.currentUser.dateOfBirth;
      // if (userBirthday) {
      //   const birthDate = new Date(userBirthday);
      //   const birthMonth = birthDate.getMonth() + 1;
      //   const birthDay = birthDate.getDate();
      //
      //   if (todayMonth === birthMonth && todayDate === birthDay) {
      //     this.isMyBirthday = true;
      //     this.userAge = this.calculateAge(userBirthday);
      //   }
      // }
    },

    calculateAge(dateOfBirth) {
      const today = new Date();
      const birth = new Date(dateOfBirth);
      let age = today.getFullYear() - birth.getFullYear();
      const monthDiff = today.getMonth() - birth.getMonth();

      if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birth.getDate())
      ) {
        age--;
      }

      return age;
    },
  },
};
</script>

<style lang="scss" scoped>
.orangehrm-birthday-special {
  background: linear-gradient(135deg, #fff8e1 0%, #fff3c4 100%);
  border: 3px solid var(--oxd-primary-one-color, #ff7b1d);
  border-radius: 1rem;
  overflow: hidden;
  position: relative;

  // Remove all inherited borders and lines
  &::before,
  &::after {
    display: none !important;
  }

  // Remove any oxd-sheet borders
  :deep(.oxd-sheet) {
    border: none !important;
    border-left: none !important;
    border-right: none !important;
    box-shadow: none !important;
  }

  // Remove any dashboard widget borders
  :deep(.orangehrm-dashboard-widget) {
    border: none !important;
    border-left: none !important;
    border-right: none !important;
  }
}

.orangehrm-dashboard-widget {
  height: 100%;

  &-body {
    padding: 1.5rem;
    text-align: center;
    position: relative;
  }
}

.orangehrm-birthday-personal-card {
  position: relative;
  z-index: 2;
}

.orangehrm-birthday-celebration {
  background: white;
  border-radius: 1rem;
  padding: 2rem 1rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 2px solid var(--oxd-primary-one-color, #ff7b1d);
}

.orangehrm-birthday-emojis {
  font-size: 3rem;
  margin-bottom: 1rem;
  animation: bounce 2s infinite;
}

.orangehrm-birthday-message {
  .orangehrm-birthday-greeting {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--oxd-primary-one-color, #ff7b1d);
    margin-bottom: 0.5rem;
  }

  .orangehrm-birthday-wish {
    font-size: 1rem;
    color: var(--oxd-primary-one-darken-color, #d35400);
    margin-bottom: 0.5rem;
    font-weight: 500;
  }

  .orangehrm-birthday-age {
    font-size: 1.1rem;
    color: var(--oxd-secondary-one-color, #8e44ad);
    font-weight: 600;
  }
}

// Confetti animation
.orangehrm-birthday-confetti {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  z-index: 1;
}

.confetti {
  position: absolute;
  width: 10px;
  height: 10px;
  background: var(--oxd-primary-one-color, #ff7b1d);
  animation: confetti-fall 3s infinite linear;

  &:nth-child(1) {
    left: 10%;
    animation-delay: 0s;
    background: #ff6b6b;
  }

  &:nth-child(2) {
    left: 30%;
    animation-delay: 0.5s;
    background: #4ecdc4;
  }

  &:nth-child(3) {
    left: 50%;
    animation-delay: 1s;
    background: #45b7d1;
  }

  &:nth-child(4) {
    left: 70%;
    animation-delay: 1.5s;
    background: #f39c12;
  }

  &:nth-child(5) {
    left: 90%;
    animation-delay: 2s;
    background: #e74c3c;
  }
}

// Animations
@keyframes bounce {
  0%,
  20%,
  50%,
  80%,
  100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-10px);
  }
  60% {
    transform: translateY(-5px);
  }
}

@keyframes confetti-fall {
  0% {
    transform: translateY(-100px) rotateZ(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(400px) rotateZ(720deg);
    opacity: 0;
  }
}

// Theme-specific overrides
:root {
  // Blue theme
  &[data-theme='blue'] {
    .orangehrm-birthday-special {
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
      border-color: var(--oxd-primary-one-color, #2196f3);
    }
  }

  // Green theme
  &[data-theme='green'] {
    .orangehrm-birthday-special {
      background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
      border-color: var(--oxd-primary-one-color, #4caf50);
    }
  }

  // Purple theme
  &[data-theme='purple'] {
    .orangehrm-birthday-special {
      background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
      border-color: var(--oxd-primary-one-color, #9c27b0);
    }
  }

  // Red theme
  &[data-theme='red'] {
    .orangehrm-birthday-special {
      background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
      border-color: var(--oxd-primary-one-color, #f44336);
    }
  }
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .orangehrm-birthday-celebration {
    background: rgba(255, 255, 255, 0.95);
  }
}
</style>
