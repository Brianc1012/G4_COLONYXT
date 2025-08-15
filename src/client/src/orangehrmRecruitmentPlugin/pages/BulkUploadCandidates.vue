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
  <div class="orangehrm-background-container">
    <div class="orangehrm-card-container">
      <oxd-text tag="h6" class="orangehrm-main-title">
        {{ $t('recruitment.bulk_upload_candidates') }}
      </oxd-text>
      <oxd-divider />

      <!-- Step 1: Download Template -->
      <div class="orangehrm-bulk-upload-step">
        <oxd-text tag="h6" class="orangehrm-step-title">
          Step 1: Download CSV Template
        </oxd-text>
        <oxd-text tag="p" class="orangehrm-step-description">
          Download the CSV template with the required format and fill in your
          candidate data.
        </oxd-text>
        <oxd-button
          display-type="secondary"
          label="Download Template"
          @click="downloadTemplate"
        >
          <oxd-icon name="download" />
        </oxd-button>
      </div>

      <oxd-divider />

      <!-- Step 2: Upload CSV -->
      <div class="orangehrm-bulk-upload-step">
        <oxd-text tag="h6" class="orangehrm-step-title">
          Step 2: Upload Completed CSV
        </oxd-text>
        <oxd-text tag="p" class="orangehrm-step-description">
          Upload your completed CSV file. The system will validate and preview
          the data before importing.
        </oxd-text>

        <oxd-form @submit-valid="processUpload">
          <oxd-form-row>
            <oxd-grid :cols="2" class="orangehrm-full-width-grid">
              <oxd-grid-item>
                <file-upload-input
                  v-model:newFile="csvFile"
                  v-model:method="fileMethod"
                  label="CSV File"
                  button-label="Browse"
                  :rules="csvRules"
                  hint="Upload a CSV file with candidate data (max 5MB)"
                  required
                />
              </oxd-grid-item>
              <oxd-grid-item>
                <oxd-input-field
                  v-model="defaultVacancy"
                  label="Default Vacancy (Optional)"
                  type="select"
                  :options="vacancyOptions"
                  placeholder="Select a vacancy"
                />
              </oxd-grid-item>
            </oxd-grid>
          </oxd-form-row>

          <oxd-form-actions>
            <oxd-button display-type="ghost" label="Cancel" @click="onCancel" />
            <oxd-button
              type="submit"
              display-type="secondary"
              label="Validate & Preview"
              :disabled="!csvFile"
            />
          </oxd-form-actions>
        </oxd-form>
      </div>

      <!-- Step 3: Preview & Validation -->
      <div v-if="previewData.length > 0" class="orangehrm-bulk-upload-step">
        <oxd-divider />
        <oxd-text tag="h6" class="orangehrm-step-title">
          Step 3: Preview & Validation Results
        </oxd-text>

        <!-- Validation Summary -->
        <div class="orangehrm-validation-summary">
          <div class="orangehrm-summary-cards">
            <div class="orangehrm-summary-card orangehrm-success">
              <oxd-text tag="h4">{{ validCount }}</oxd-text>
              <oxd-text>Valid Records</oxd-text>
            </div>
            <div class="orangehrm-summary-card orangehrm-warning">
              <oxd-text tag="h4">{{ warningCount }}</oxd-text>
              <oxd-text>Warnings</oxd-text>
            </div>
            <div class="orangehrm-summary-card orangehrm-error">
              <oxd-text tag="h4">{{ errorCount }}</oxd-text>
              <oxd-text>Errors</oxd-text>
            </div>
          </div>
        </div>

        <!-- Data Preview Table -->
        <div class="orangehrm-preview-table">
          <oxd-table
            :headers="previewHeaders"
            :items="previewData"
            :loading="isValidating"
          >
            <template #cell-status="{item}">
              <div class="orangehrm-status-cell">
                <oxd-icon
                  :name="getStatusIcon(item.status)"
                  :class="getStatusClass(item.status)"
                />
                <oxd-text :class="getStatusClass(item.status)">
                  {{ item.status }}
                </oxd-text>
              </div>
            </template>

            <template #cell-errors="{item}">
              <div v-if="item.errors.length > 0" class="orangehrm-error-list">
                <oxd-text
                  v-for="error in item.errors"
                  :key="error"
                  tag="small"
                  class="orangehrm-error-text"
                >
                  • {{ error }}
                </oxd-text>
              </div>
            </template>

            <template #cell-warnings="{item}">
              <div
                v-if="item.warnings.length > 0"
                class="orangehrm-warning-list"
              >
                <oxd-text
                  v-for="warning in item.warnings"
                  :key="warning"
                  tag="small"
                  class="orangehrm-warning-text"
                >
                  • {{ warning }}
                </oxd-text>
              </div>
            </template>
          </oxd-table>
        </div>

        <!-- Import Actions -->
        <div class="orangehrm-import-actions">
          <oxd-text tag="p" class="orangehrm-import-note">
            Only valid records will be imported. Records with errors will be
            skipped.
          </oxd-text>

          <oxd-form-actions>
            <oxd-button
              display-type="ghost"
              label="Back"
              @click="resetUpload"
            />
            <oxd-button
              display-type="secondary"
              label="Download Errors"
              :disabled="errorCount === 0"
              @click="downloadErrors"
            />
            <oxd-button
              display-type="success"
              :label="`Import ${validCount} Candidates`"
              :disabled="validCount === 0 || isImporting"
              :loading="isImporting"
              @click="importCandidates"
            />
          </oxd-form-actions>
        </div>
      </div>

      <!-- Import Progress -->
      <div v-if="isImporting" class="orangehrm-import-progress">
        <oxd-divider />
        <oxd-text tag="h6">Importing Candidates...</oxd-text>
        <oxd-progress-bar
          :percentage="importProgress"
          :label="`${importedCount} of ${validCount} imported`"
        />
      </div>

      <!-- Import Results -->
      <div v-if="importResults" class="orangehrm-import-results">
        <oxd-divider />
        <oxd-text tag="h6" class="orangehrm-step-title">
          Import Complete!
        </oxd-text>

        <div class="orangehrm-results-summary">
          <oxd-alert
            v-if="importResults.success > 0"
            type="success"
            :message="`Successfully imported ${importResults.success} candidates`"
          />
          <oxd-alert
            v-if="importResults.failed > 0"
            type="error"
            :message="`Failed to import ${importResults.failed} candidates`"
          />
        </div>

        <oxd-form-actions>
          <oxd-button
            display-type="secondary"
            label="View Candidates"
            @click="viewCandidates"
          />
          <oxd-button
            display-type="ghost"
            label="Import More"
            @click="resetAll"
          />
        </oxd-form-actions>
      </div>
    </div>
  </div>
</template>

<script>
import {navigate} from '@/core/util/helper/navigation';
import {APIService} from '@/core/util/services/api.service';
import FileUploadInput from '@/core/components/inputs/FileUploadInput';
import {required, validFileTypes} from '@/core/util/validation/rules';

export default {
  name: 'BulkUploadCandidates',

  components: {
    'file-upload-input': FileUploadInput,
  },

  setup() {
    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/recruitment/candidates',
    );
    const vacancyHttp = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/recruitment/vacancies',
    );

    return {
      http,
      vacancyHttp,
    };
  },

  data() {
    return {
      csvFile: null,
      fileMethod: 'keepCurrent',
      defaultVacancy: null,
      vacancyOptions: [],
      previewData: [],
      isValidating: false,
      isImporting: false,
      importProgress: 0,
      importedCount: 0,
      importResults: null,

      csvRules: [
        required,
        validFileTypes(['text/csv', 'application/vnd.ms-excel']),
      ],

      previewHeaders: [
        {name: 'rowNumber', title: 'Row #', style: {flex: '0 0 60px'}},
        {name: 'firstName', title: 'First Name', style: {flex: 1}},
        {name: 'lastName', title: 'Last Name', style: {flex: 1}},
        {name: 'email', title: 'Email', style: {flex: 1}},
        {name: 'contactNumber', title: 'Contact', style: {flex: 1}},
        {name: 'vacancy', title: 'Vacancy', style: {flex: 1}},
        {
          name: 'status',
          title: 'Status',
          cellType: 'oxd-table-cell-slot',
          style: {flex: '0 0 100px'},
        },
        {
          name: 'errors',
          title: 'Errors',
          cellType: 'oxd-table-cell-slot',
          style: {flex: 1},
        },
        {
          name: 'warnings',
          title: 'Warnings',
          cellType: 'oxd-table-cell-slot',
          style: {flex: 1},
        },
      ],
    };
  },

  computed: {
    validCount() {
      return this.previewData.filter((item) => item.status === 'Valid').length;
    },
    warningCount() {
      return this.previewData.filter((item) => item.status === 'Warning')
        .length;
    },
    errorCount() {
      return this.previewData.filter((item) => item.status === 'Error').length;
    },
  },

  mounted() {
    this.loadVacancies();
  },

  methods: {
    async loadVacancies() {
      try {
        const response = await this.vacancyHttp.getAll({
          limit: 0,
          sortField: 'vacancy.name',
          sortOrder: 'ASC',
        });

        this.vacancyOptions = response.data.data.map((vacancy) => ({
          id: vacancy.id,
          label: vacancy.name,
        }));
      } catch (error) {
        this.$toast.error('Failed to load vacancies');
      }
    },

    downloadTemplate() {
      const csvContent = [
        'firstName,lastName,email,contactNumber,vacancy,keywords,notes,dateOfApplication',
        'John,Doe,john.doe@email.com,1234567890,Software Engineer,"JavaScript,Vue.js,Node.js","Experienced developer",2024-08-09',
        'Jane,Smith,jane.smith@email.com,0987654321,Marketing Manager,"Digital Marketing,SEO","Marketing specialist",2024-08-09',
        'Mike,Johnson,mike.j@email.com,5551234567,Sales Representative,"Sales,CRM,B2B","Great communication skills",2024-08-09',
      ].join('\n');

      const blob = new Blob([csvContent], {type: 'text/csv'});
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'candidate_bulk_upload_template.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    },

    async processUpload() {
      if (!this.csvFile) return;

      this.isValidating = true;
      this.previewData = [];

      try {
        const text = await this.csvFile.text();
        const lines = text.split('\n').filter((line) => line.trim());

        if (lines.length < 2) {
          this.$toast.error('CSV file must contain at least one data row');
          return;
        }

        const headers = lines[0]
          .split(',')
          .map((h) => h.trim().replace(/"/g, ''));
        const requiredHeaders = ['firstName', 'lastName', 'email'];

        // Validate headers
        const missingHeaders = requiredHeaders.filter(
          (h) => !headers.includes(h),
        );
        if (missingHeaders.length > 0) {
          this.$toast.error(
            `Missing required columns: ${missingHeaders.join(', ')}`,
          );
          return;
        }

        // Process data rows
        for (let i = 1; i < lines.length; i++) {
          const values = this.parseCSVLine(lines[i]);
          const rowData = this.createRowData(headers, values, i + 1);
          this.previewData.push(rowData);
        }

        // Check for duplicates
        this.checkDuplicates();
      } catch (error) {
        this.$toast.error('Failed to parse CSV file');
      } finally {
        this.isValidating = false;
      }
    },

    parseCSVLine(line) {
      const values = [];
      let current = '';
      let inQuotes = false;

      for (let i = 0; i < line.length; i++) {
        const char = line[i];
        if (char === '"') {
          inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
          values.push(current.trim());
          current = '';
        } else {
          current += char;
        }
      }
      values.push(current.trim());

      return values;
    },

    createRowData(headers, values, rowNumber) {
      const rowData = {
        rowNumber,
        errors: [],
        warnings: [],
        status: 'Valid',
      };

      // Map values to headers
      headers.forEach((header, index) => {
        rowData[header] = values[index]?.replace(/"/g, '') || '';
      });

      // Validate required fields
      if (!rowData.firstName) {
        rowData.errors.push('First name is required');
      }
      if (!rowData.lastName) {
        rowData.errors.push('Last name is required');
      }
      if (!rowData.email) {
        rowData.errors.push('Email is required');
      } else if (!this.isValidEmail(rowData.email)) {
        rowData.errors.push('Invalid email format');
      }

      // Validate vacancy
      if (!rowData.vacancy && !this.defaultVacancy) {
        rowData.warnings.push('No vacancy specified, will use default if set');
      }

      // Validate field lengths
      if (rowData.firstName && rowData.firstName.length > 30) {
        rowData.errors.push('First name too long (max 30 characters)');
      }
      if (rowData.lastName && rowData.lastName.length > 30) {
        rowData.errors.push('Last name too long (max 30 characters)');
      }
      if (rowData.email && rowData.email.length > 50) {
        rowData.errors.push('Email too long (max 50 characters)');
      }

      // Set status based on validation
      if (rowData.errors.length > 0) {
        rowData.status = 'Error';
      } else if (rowData.warnings.length > 0) {
        rowData.status = 'Warning';
      }

      return rowData;
    },

    checkDuplicates() {
      const emailMap = new Map();

      this.previewData.forEach((row) => {
        if (row.email) {
          if (emailMap.has(row.email)) {
            row.warnings.push(
              `Duplicate email (also in row ${emailMap.get(row.email)})`,
            );
            if (row.status === 'Valid') row.status = 'Warning';
          } else {
            emailMap.set(row.email, row.rowNumber);
          }
        }
      });
    },

    isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    },

    getStatusIcon(status) {
      switch (status) {
        case 'Valid':
          return 'check-circle';
        case 'Warning':
          return 'alert-triangle';
        case 'Error':
          return 'x-circle';
        default:
          return 'help-circle';
      }
    },

    getStatusClass(status) {
      switch (status) {
        case 'Valid':
          return 'orangehrm-status-success';
        case 'Warning':
          return 'orangehrm-status-warning';
        case 'Error':
          return 'orangehrm-status-error';
        default:
          return '';
      }
    },

    async importCandidates() {
      this.isImporting = true;
      this.importProgress = 0;
      this.importedCount = 0;

      const validCandidates = this.previewData.filter(
        (row) => row.status === 'Valid' || row.status === 'Warning',
      );

      const results = {
        success: 0,
        failed: 0,
      };

      for (let i = 0; i < validCandidates.length; i++) {
        const candidate = validCandidates[i];

        try {
          const candidateData = {
            firstName: candidate.firstName,
            lastName: candidate.lastName,
            middleName: candidate.middleName || null,
            email: candidate.email,
            contactNumber: candidate.contactNumber || null,
            keywords: candidate.keywords || null,
            comment: candidate.notes || null,
            dateOfApplication:
              candidate.dateOfApplication ||
              new Date().toISOString().split('T')[0],
            consentToKeepData: false,
            vacancyId: this.getVacancyId(candidate.vacancy),
          };

          await this.http.create(candidateData);
          results.success++;
        } catch (error) {
          results.failed++;
        }

        this.importedCount = i + 1;
        this.importProgress = ((i + 1) / validCandidates.length) * 100;
      }

      this.importResults = results;
      this.isImporting = false;

      if (results.success > 0) {
        this.$toast.success(
          `Successfully imported ${results.success} candidates`,
        );
      }
      if (results.failed > 0) {
        this.$toast.error(`Failed to import ${results.failed} candidates`);
      }
    },

    getVacancyId(vacancyName) {
      if (!vacancyName && this.defaultVacancy) {
        return this.defaultVacancy.id;
      }

      const vacancy = this.vacancyOptions.find(
        (v) => v.label.toLowerCase() === vacancyName?.toLowerCase(),
      );

      return vacancy?.id || this.defaultVacancy?.id || null;
    },

    downloadErrors() {
      const errorRows = this.previewData.filter((row) => row.errors.length > 0);

      if (errorRows.length === 0) return;

      const csvContent = [
        'Row,First Name,Last Name,Email,Contact Number,Vacancy,Errors',
        ...errorRows.map(
          (row) =>
            `${row.rowNumber},"${row.firstName}","${row.lastName}","${
              row.email
            }","${row.contactNumber}","${row.vacancy}","${row.errors.join(
              '; ',
            )}"`,
        ),
      ].join('\n');

      const blob = new Blob([csvContent], {type: 'text/csv'});
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'candidate_import_errors.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    },

    resetUpload() {
      this.previewData = [];
      this.csvFile = null;
    },

    resetAll() {
      this.resetUpload();
      this.importResults = null;
      this.importProgress = 0;
      this.importedCount = 0;
    },

    viewCandidates() {
      navigate('/recruitment/viewCandidates');
    },

    onCancel() {
      navigate('/recruitment/viewCandidates');
    },
  },
};
</script>

<style scoped lang="scss">
.orangehrm-bulk-upload-step {
  margin-bottom: 2rem;

  .orangehrm-step-title {
    color: var(--oxd-primary-one-color);
    margin-bottom: 0.5rem;
  }

  .orangehrm-step-description {
    color: var(--oxd-secondary-four-color);
    margin-bottom: 1rem;
  }
}

.orangehrm-validation-summary {
  margin-bottom: 1.5rem;

  .orangehrm-summary-cards {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
  }

  .orangehrm-summary-card {
    flex: 1;
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;

    &.orangehrm-success {
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
    }

    &.orangehrm-warning {
      background-color: #fff3cd;
      border: 1px solid #ffeaa7;
    }

    &.orangehrm-error {
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
    }

    h4 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: bold;
    }
  }
}

.orangehrm-preview-table {
  margin-bottom: 1.5rem;
}

.orangehrm-status-cell {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.orangehrm-status-success {
  color: #28a745;
}

.orangehrm-status-warning {
  color: #ffc107;
}

.orangehrm-status-error {
  color: #dc3545;
}

.orangehrm-error-list,
.orangehrm-warning-list {
  .orangehrm-error-text {
    color: #dc3545;
    display: block;
  }

  .orangehrm-warning-text {
    color: #ffc107;
    display: block;
  }
}

.orangehrm-import-note {
  color: var(--oxd-secondary-four-color);
  font-style: italic;
  margin-bottom: 1rem;
}

.orangehrm-import-progress,
.orangehrm-import-results {
  margin-top: 2rem;
}

.orangehrm-results-summary {
  margin-bottom: 1rem;
}
</style>
