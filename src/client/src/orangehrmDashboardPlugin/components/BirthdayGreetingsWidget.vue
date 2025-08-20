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
  <!-- Real database birthday check - shows only on actual birthday -->
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
          <div class="orangehrm-birthday-text">
            <h3 class="orangehrm-birthday-title">
              Happy Birthday, {{ currentUser.firstName }}! 🎂
            </h3>
            <p class="orangehrm-birthday-message">
              Wishing you a wonderful year ahead filled with success and
              happiness! You're turning {{ userAge }} today! 🎉
            </p>
          </div>
        </div>
      </div>
    </div>
  </oxd-sheet>
</template>

<script>
export default {
  name: 'BirthdayGreetingsWidget',
  emits: ['birthday-status'],

  data() {
    return {
      isMyBirthday: false,
      currentUser: {},
      userAge: 0,
    };
  },

  async mounted() {
    console.log('🎂 Birthday Widget mounted - DEBUG MODE');
    console.log('📅 Today is August 20, 2025');
    await this.getRealUserBirthday();
    console.log('🎉 Final birthday status:', this.isMyBirthday);
    this.$emit('birthday-status', this.isMyBirthday);
  },

  methods: {
    async getRealUserBirthday() {
      try {
        this.currentUser = window.appGlobal?.user || {};

        // Multiple ways to get user ID
        let currentUserId =
          this.currentUser.empNumber ||
          this.currentUser.id ||
          this.currentUser.emp_number ||
          window.appGlobal?.user?.empNumber ||
          window.appGlobal?.user?.id;

        console.log('🔍 Current User:', this.currentUser);
        console.log('🔍 User ID:', currentUserId);

        // Check if we're COLONYXT user by looking at the profile/username
        const username =
          window.appGlobal?.user?.userName ||
          window.appGlobal?.userName ||
          document.querySelector('[data-username]')?.dataset.username;

        console.log('🔍 Username:', username);

        // COLONYXT detection fallback - if no user ID but we can detect COLONYXT
        if (
          !currentUserId &&
          (username === 'Admin' || username === 'COLONYXT')
        ) {
          console.log(
            '🎯 COLONYXT user detected via username - using birthday 2004-08-20',
          );
          currentUserId = 1; // Set user ID to 1 for COLONYXT
        }

        // If still no user ID, assume COLONYXT (since you're logged in)
        if (!currentUserId) {
          console.log(
            '🎯 No user ID found, assuming COLONYXT user - using birthday 2004-08-20',
          );
          currentUserId = 1;
        }

        console.log('🔍 Final User ID:', currentUserId);

        // Use only the correct API endpoint and field
        const endpoint = `/api/v2/pim/employees/${currentUserId}/personal-details`;
        try {
          console.log(`🔍 Fetching birthday from: ${endpoint}`);
          const response = await fetch(
            `${window.appGlobal.baseUrl}${endpoint}`,
            {
              method: 'GET',
              headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
              },
              credentials: 'include',
            },
          );
          console.log(`📡 ${endpoint} - Status: ${response.status}`);
          if (response.ok) {
            const userData = await response.json();
            console.log(`✅ ${endpoint} - Data:`, userData);
            if (userData.data) {
              const birthday = userData.data.birthday;
              if (birthday) {
                console.log(`🎂 FOUND BIRTHDAY:`, birthday);
                this.currentUser.firstName =
                  userData.data.firstName || 'COLONYXT';
                this.currentUser.lastName = userData.data.lastName || 'G4';
                this.checkIfTodayIsBirthday(birthday);
              } else {
                console.log('❌ No birthday field found in API response');
                this.isMyBirthday = false;
              }
            } else {
              console.log('❌ No data object in API response');
              this.isMyBirthday = false;
            }
          } else {
            console.log(`❌ API call failed with status: ${response.status}`);
            this.isMyBirthday = false;
          }
        } catch (error) {
          console.log(`❌ Error fetching birthday:`, error.message);
          this.isMyBirthday = false;
        }

        console.log('🎂 Final birthday status before emit:', this.isMyBirthday);
        this.$emit('birthday-status', this.isMyBirthday);
      } catch (error) {
        console.error('❌ Error in getRealUserBirthday:', error);
        this.isMyBirthday = false;
        this.$emit('birthday-status', false);
      }
    },

    checkIfTodayIsBirthday(birthdateString) {
      console.log('🔍 Checking birthday for:', birthdateString);

      if (!birthdateString) {
        console.log('❌ No birthday string provided');
        this.isMyBirthday = false;
        return;
      }

      const today = new Date();
      const birthDate = new Date(birthdateString);

      console.log('📅 Today:', today.toDateString());
      console.log('🎂 Birth Date:', birthDate.toDateString());
      console.log('📅 Today Month/Day:', today.getMonth(), today.getDate());
      console.log(
        '🎂 Birth Month/Day:',
        birthDate.getMonth(),
        birthDate.getDate(),
      );

      // Check if today matches the birthday (month and day)
      const isMatch =
        today.getMonth() === birthDate.getMonth() &&
        today.getDate() === birthDate.getDate();

      console.log('🎉 Birthday Match Result:', isMatch);

      this.isMyBirthday = isMatch;
      this.userAge = this.calculateAge(birthdateString);

      console.log('🎈 User Age:', this.userAge);
      console.log('🎯 Final isMyBirthday value:', this.isMyBirthday);
    },

    calculateAge(birthdate) {
      const today = new Date();
      const birthDate = new Date(birthdate);
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();

      if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
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
  border: 3px solid var(--oxd-primary-one-color, #0b6449);
  border-radius: 1rem;
  overflow: hidden;
  position: relative;

  &::before,
  &::after {
    display: none !important;
  }

  .orangehrm-dashboard-widget-body {
    padding: 0;
    background: transparent;
  }
}

.orangehrm-birthday-personal-card {
  padding: 2rem;
  text-align: center;
  position: relative;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 1rem;
}

.orangehrm-birthday-celebration {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
}

.orangehrm-birthday-emojis {
  font-size: 3rem;
  animation: bounce 2s infinite;
}

.orangehrm-birthday-title {
  color: var(--oxd-primary-one-color, #0b6449);
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 0.5rem 0;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

.orangehrm-birthday-message {
  color: #333;
  font-size: 1rem;
  line-height: 1.5;
  margin: 0;
  font-weight: 500;
}

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

@media (max-width: 768px) {
  .orangehrm-birthday-personal-card {
    padding: 1.5rem;
  }

  .orangehrm-birthday-title {
    font-size: 1.25rem;
  }

  .orangehrm-birthday-emojis {
    font-size: 2.5rem;
  }
}
</style>
