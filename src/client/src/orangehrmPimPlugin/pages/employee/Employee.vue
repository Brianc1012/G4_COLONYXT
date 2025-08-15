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
    <oxd-table-filter :filter-title="$t('pim.employee_information')">
      <oxd-form @submit-valid="filterItems" @reset="filterItems">
        <oxd-form-row>
          <oxd-grid :cols="4" class="orangehrm-full-width-grid">
            <oxd-grid-item>
              <employee-autocomplete
                v-model="filters.employee"
                :rules="rules.employee"
                :params="{includeEmployees: filters.includeEmployees?.param}"
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <oxd-input-field
                v-model="filters.employeeId"
                :label="$t('general.employee_id')"
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <employment-status-dropdown v-model="filters.empStatusId" />
            </oxd-grid-item>
            <oxd-grid-item>
              <include-employee-dropdown
                v-model="filters.includeEmployees"
              ></include-employee-dropdown>
            </oxd-grid-item>
            <oxd-grid-item>
              <employee-autocomplete
                v-model="filters.supervisor"
                :rules="rules.supervisor"
                :label="$t('pim.supervisor_name')"
              />
            </oxd-grid-item>
            <oxd-grid-item>
              <jobtitle-dropdown v-model="filters.jobTitleId" />
            </oxd-grid-item>
            <oxd-grid-item>
              <subunit-dropdown v-model="filters.subunitId" />
            </oxd-grid-item>
          </oxd-grid>
        </oxd-form-row>

        <oxd-divider />

        <oxd-form-actions>
          <oxd-button
            display-type="ghost"
            :label="$t('general.reset')"
            type="reset"
          />
          <oxd-button
            class="orangehrm-left-space"
            display-type="secondary"
            :label="$t('general.search')"
            type="submit"
          />
        </oxd-form-actions>
      </oxd-form>
    </oxd-table-filter>
    <br />
    <div class="orangehrm-paper-container">
      <div
        v-if="$can.create('employee_list')"
        class="orangehrm-header-container"
      >
        <oxd-button
          :label="$t('general.add')"
          icon-name="plus"
          display-type="secondary"
          @click="onClickAdd"
        />

        <!-- EXPORT DROPDOWN -->
        <div ref="exportDropdown" class="export-dropdown-container">
          <oxd-button
            display-type="success"
            :label="'Export'"
            icon-name="download"
            :loading="isExporting"
            @click="toggleExportDropdown"
          />
          <div v-if="showExportDropdown" class="export-dropdown-menu">
            <div class="export-dropdown-item" @click="onClickExport('csv')">
              <oxd-icon name="file-text" />
              <span>Export as CSV</span>
            </div>
            <div class="export-dropdown-item" @click="onClickExport('pdf')">
              <oxd-icon name="file-pdf" />
              <span>Export as PDF</span>
            </div>
          </div>
        </div>
      </div>
      <table-header
        :selected="checkedItems.length"
        :total="total"
        :loading="isLoading"
        @delete="onClickDeleteSelected"
      ></table-header>
      <div class="orangehrm-container">
        <oxd-card-table
          ref="cardTable"
          v-model:selected="checkedItems"
          v-model:order="sortDefinition"
          :headers="headers"
          :items="items?.data"
          :selectable="$can.delete('employee_list')"
          :clickable="true"
          :loading="isLoading"
          class="orangehrm-employee-list"
          row-decorator="oxd-table-decorator-card"
          @click="onClickEdit"
        />
      </div>
      <div class="orangehrm-bottom-container">
        <oxd-pagination
          v-if="showPaginator"
          v-model:current="currentPage"
          :length="pages"
        />
      </div>
    </div>
    <delete-confirmation ref="deleteDialog"></delete-confirmation>
  </div>
</template>

<script>
import {computed, ref} from 'vue';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import DeleteConfirmationDialog from '@ohrm/components/dialogs/DeleteConfirmationDialog';
import usePaginate from '@ohrm/core/util/composable/usePaginate';
import {navigate} from '@ohrm/core/util/helper/navigation';
import {APIService} from '@/core/util/services/api.service';
import EmployeeAutocomplete from '@/core/components/inputs/EmployeeAutocomplete';
import JobtitleDropdown from '@/orangehrmPimPlugin/components/JobtitleDropdown';
import SubunitDropdown from '@/orangehrmPimPlugin/components/SubunitDropdown';
import EmploymentStatusDropdown from '@/orangehrmPimPlugin/components/EmploymentStatusDropdown';
import IncludeEmployeeDropdown from '@/core/components/dropdown/IncludeEmployeeDropdown';
import useSort from '@ohrm/core/util/composable/useSort';
import {
  shouldNotExceedCharLength,
  validSelection,
} from '@/core/util/validation/rules';
import usei18n from '@/core/util/composable/usei18n';

const defaultSortOrder = {
  'employee.employeeId': 'DEFAULT',
  'employee.firstName': 'ASC',
  'employee.lastName': 'DEFAULT',
  'jobTitle.jobTitleName': 'DEFAULT',
  'empStatus.name': 'DEFAULT',
  'subunit.name': 'DEFAULT',
  'supervisor.firstName': 'DEFAULT',
};

export default {
  components: {
    'delete-confirmation': DeleteConfirmationDialog,
    'employee-autocomplete': EmployeeAutocomplete,
    'jobtitle-dropdown': JobtitleDropdown,
    'subunit-dropdown': SubunitDropdown,
    'employment-status-dropdown': EmploymentStatusDropdown,
    'include-employee-dropdown': IncludeEmployeeDropdown,
  },

  props: {
    unselectableEmpNumbers: {
      type: Array,
      default: () => [],
    },
  },

  setup(props) {
    const {$t} = usei18n();
    const dataNormalizer = (data) => {
      return data.map((item) => {
        const selectable = props.unselectableEmpNumbers.findIndex(
          (empNumber) => empNumber == item.empNumber,
        );
        return {
          id: item.empNumber,
          employeeId: item.employeeId,
          firstAndMiddleName: `${item.firstName} ${item.middleName}`,
          lastName:
            item.lastName +
            (item.terminationId ? ` ${$t('general.past_employee')}` : ''),
          jobTitle: item.jobTitle?.isDeleted
            ? item.jobTitle.title + $t('general.deleted')
            : item.jobTitle?.title,
          empStatus: item.empStatus?.name,
          subunit: item.subunit?.name,
          supervisor: item.supervisors
            ? item.supervisors
                .map(
                  (supervisor) =>
                    `${supervisor.firstName} ${supervisor.lastName}`,
                )
                .join(',')
            : '',
          isSelectable: selectable === -1,
        };
      });
    };

    const filters = ref({
      employee: null,
      employeeId: '',
      empStatusId: null,
      supervisor: null,
      jobTitleId: null,
      subunitId: null,
      includeEmployees: {
        id: 1,
        param: 'onlyCurrent',
        label: $t('general.current_employees_only'),
      },
    });
    const {sortDefinition, sortField, sortOrder, onSort} = useSort({
      sortDefinition: defaultSortOrder,
    });
    const serializedFilters = computed(() => {
      return {
        model: 'detailed',
        nameOrId:
          typeof filters.value.employee === 'string'
            ? filters.value.employee
            : undefined,
        empNumber: filters.value.employee?.id,
        employeeId: filters.value.employeeId,
        empStatusId: filters.value.empStatusId?.id,
        includeEmployees: filters.value.includeEmployees?.param,
        supervisorEmpNumbers: filters.value.supervisor
          ? [filters.value.supervisor.id]
          : undefined,
        jobTitleId: filters.value.jobTitleId?.id,
        subunitId: filters.value.subunitId?.id,
        sortField: sortField.value,
        sortOrder: sortOrder.value,
      };
    });

    const http = new APIService(
      window.appGlobal.baseUrl,
      '/api/v2/pim/employees',
    );
    const {
      showPaginator,
      currentPage,
      total,
      pages,
      pageSize,
      response,
      isLoading,
      execQuery,
    } = usePaginate(http, {
      query: serializedFilters,
      normalizer: dataNormalizer,
    });

    onSort(execQuery);

    return {
      http,
      showPaginator,
      currentPage,
      isLoading,
      total,
      pages,
      pageSize,
      execQuery,
      items: response,
      filters,
      sortDefinition,
    };
  },
  data() {
    return {
      checkedItems: [],
      isExporting: false,
      showExportDropdown: false,
      rules: {
        employee: [shouldNotExceedCharLength(100)],
        supervisor: [shouldNotExceedCharLength(100), validSelection],
      },
    };
  },
  computed: {
    headers() {
      return [
        {
          name: 'employeeId',
          slot: 'title',
          title: this.$t('general.id'),
          sortField: 'employee.employeeId',
          style: {flex: 1},
        },
        {
          name: 'firstAndMiddleName',
          title: this.$t('pim.first_middle_name'),
          sortField: 'employee.firstName',
          style: {flex: 1},
        },
        {
          name: 'lastName',
          title: this.$t('general.last_name'),
          sortField: 'employee.lastName',
          style: {flex: 1},
        },
        {
          name: 'jobTitle',
          title: this.$t('general.job_title'),
          sortField: 'jobTitle.jobTitleName',
          style: {flex: 1},
        },
        {
          name: 'empStatus',
          title: this.$t('general.employment_status'),
          sortField: 'empStatus.name',
          style: {flex: 1},
        },
        {
          name: 'subunit',
          title: this.$t('general.sub_unit'),
          sortField: 'subunit.name',
          style: {flex: 1},
        },
        {
          name: 'supervisor',
          title: this.$t('pim.supervisor'),
          sortField: 'supervisor.firstName',
          style: {flex: 1},
        },
        {
          name: 'actions',
          slot: 'action',
          title: this.$t('general.actions'),
          style: {flex: 1},
          cellType: 'oxd-table-cell-actions',
          cellRenderer: this.cellRenderer,
        },
      ];
    },
  },

  mounted() {
    // Close dropdown when clicking outside
    document.addEventListener('click', this.handleClickOutside);
  },

  beforeUnmount() {
    document.removeEventListener('click', this.handleClickOutside);
  },

  methods: {
    toggleExportDropdown() {
      if (this.isExporting) return; // Don't open dropdown when exporting
      this.showExportDropdown = !this.showExportDropdown;
    },

    handleClickOutside(event) {
      if (
        this.$refs.exportDropdown &&
        !this.$refs.exportDropdown.contains(event.target)
      ) {
        this.showExportDropdown = false;
      }
    },

    async onClickExport(format = 'csv') {
      this.showExportDropdown = false; // Close dropdown
      this.isExporting = true;

      try {
        if (!this.items?.data || this.items.data.length === 0) {
          this.$toast.error({
            title: 'Error',
            message: 'No employees to export',
          });
          return;
        }

        if (format === 'csv') {
          this.downloadCSV(this.items.data);
        } else if (format === 'pdf') {
          this.downloadPDF(this.items.data);
        }

        this.$toast.success({
          title: 'Success',
          message: `${
            this.items.data.length
          } employees exported as ${format.toUpperCase()} successfully`,
        });
      } catch (error) {
        console.error('Export error:', error);
        this.$toast.error({
          title: 'Error',
          message: 'Failed to export employees',
        });
      } finally {
        this.isExporting = false;
      }
    },

    // Method for dropdown selection
    onSelectExportOption(option) {
      this.onClickExport(option.id);
    },

    // Existing CSV download method
    downloadCSV(employees) {
      const headers = [
        'Employee ID',
        'First & Middle Name',
        'Last Name',
        'Job Title',
        'Employment Status',
        'Sub Unit',
        'Supervisor',
      ];

      const rows = employees.map((emp) => [
        emp.employeeId || '',
        emp.firstAndMiddleName || '',
        emp.lastName || '',
        emp.jobTitle || '',
        emp.empStatus || '',
        emp.subunit || '',
        emp.supervisor || '',
      ]);

      const csvContent = [headers, ...rows]
        .map((row) => row.map((field) => `"${field}"`).join(','))
        .join('\n');

      const blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `employees_${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    },

    downloadPDF(employees) {
      const doc = new jsPDF();

      // Add title
      doc.setFontSize(18);
      doc.text('Employee List Report', 14, 22);

      // Add date
      doc.setFontSize(12);
      doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 14, 32);
      doc.text(`Total Employees: ${employees.length}`, 14, 42);

      // Prepare table data
      const headers = [
        'Employee ID',
        'First & Middle Name',
        'Last Name',
        'Job Title',
        'Employment Status',
        'Sub Unit',
        'Supervisor',
      ];

      const data = employees.map((emp) => [
        emp.employeeId || '',
        emp.firstAndMiddleName || '',
        emp.lastName || '',
        emp.jobTitle || '',
        emp.empStatus || '',
        emp.subunit || '',
        emp.supervisor || '',
      ]);

      // Add table using autoTable function
      autoTable(doc, {
        head: [headers],
        body: data,
        startY: 50,
        styles: {
          fontSize: 8,
          cellPadding: 2,
        },
        headStyles: {
          fillColor: [41, 128, 185],
          textColor: 255,
          fontStyle: 'bold',
        },
        alternateRowStyles: {
          fillColor: [245, 245, 245],
        },
        margin: {top: 50},
      });

      // Save the PDF
      doc.save(`employees_${new Date().toISOString().split('T')[0]}.pdf`);
    },

    onClickAdd() {
      navigate('/pim/addEmployee');
    },
    onClickEdit($event) {
      const id = $event.id ? $event.id : $event.item?.id;
      navigate('/pim/viewPersonalDetails/empNumber/{id}', {id});
    },
    onClickDeleteSelected() {
      const ids = this.checkedItems.map((index) => {
        return this.items?.data[index].id;
      });
      this.$refs.deleteDialog.showDialog().then((confirmation) => {
        if (confirmation === 'ok') {
          this.deleteItems(ids);
        }
      });
    },

    onClickDelete(item, $event) {
      $event.stopImmediatePropagation();
      const isSelectable = this.unselectableEmpNumbers.findIndex(
        (empNumber) => empNumber == item.id,
      );
      if (isSelectable > -1) {
        return this.$toast.cannotDelete();
      }
      this.$refs.deleteDialog.showDialog().then((confirmation) => {
        if (confirmation === 'ok') {
          this.deleteItems([item.id]);
        }
      });
    },
    deleteItems(items) {
      if (items instanceof Array) {
        this.isLoading = true;
        this.http
          .deleteAll({
            ids: items,
          })
          .then(() => {
            return this.$toast.deleteSuccess();
          })
          .then(() => {
            this.isLoading = false;
            this.resetDataTable();
          });
      }
    },
    async resetDataTable() {
      this.checkedItems = [];
      await this.execQuery();
    },
    async filterItems() {
      await this.execQuery();
    },
    cellRenderer(...[, , , row]) {
      const cellConfig = {
        edit: {
          onClick: this.onClickEdit,
          props: {
            name: 'pencil-fill',
          },
        },
      };

      if (
        this.$can.delete('employee_list') &&
        !this.unselectableEmpNumbers.includes(row.id)
      ) {
        cellConfig.delete = {
          onClick: this.onClickDelete,
          component: 'oxd-icon-button',
          props: {
            name: 'trash',
          },
        };
      }

      return {
        props: {
          header: {
            cellConfig,
          },
        },
      };
    },
  },
};
</script>

<style src="./employee.scss" lang="scss" scoped></style>

<style scoped>
.orangehrm-header-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.export-dropdown-container {
  position: relative;
  display: inline-block;
}

.export-dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: white;
  border: 1px solid #d6d6d6;
  border-radius: 6px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  min-width: 180px;
  margin-top: 4px;
}

.export-dropdown-item {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  cursor: pointer;
  transition: background-color 0.2s;
  border-bottom: 1px solid #f0f0f0;
  font-size: 14px;
}

.export-dropdown-item:last-child {
  border-bottom: none;
}

.export-dropdown-item:hover {
  background-color: #f8f9fa;
}

.export-dropdown-item span {
  margin-left: 8px;
  color: #333;
}

.export-dropdown-item oxd-icon {
  color: #666;
  width: 16px;
  height: 16px;
}
</style>
