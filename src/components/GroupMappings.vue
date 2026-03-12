<template>
  <div class="section">
    <div class="section-header">
      <h3>{{ t('webdavimpersonate', 'Group Mappings') }}</h3>
      <NcButton @click="addRow" type="primary">
        <template #icon><IconPlus /></template>
        {{ t('webdavimpersonate', 'Add Mapping') }}
      </NcButton>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>{{ t('webdavimpersonate', 'Impersonator Group') }}</th>
          <th>{{ t('webdavimpersonate', 'Imitatee Group') }}</th>
          <th>{{ t('webdavimpersonate', 'Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="row.id">
          <td>
            <NcSelect
              v-model="row.impersonator"
              :options="getAvailableGroupsForImpersonator(row)"
              :input-label="t('webdavimpersonate', 'Impersonator Group')"
              label="displayName"
              :placeholder="t('webdavimpersonate', 'Select group...')"
            />
          </td>
          <td>
            <NcSelect
              v-model="row.imitatee"
              :options="getAvailableGroupsForImitatee(row)"
              :input-label="t('webdavimpersonate', 'Imitatee Group')"
              label="displayName"
              :placeholder="t('webdavimpersonate', 'Select group...')"
            />
          </td>
          <td>
            <NcButton
              @click="deleteRow(index)"
              type="tertiary-no-background"
              :aria-label="t('webdavimpersonate', 'Delete')"
            >
              <template #icon><IconDelete /></template>
            </NcButton>
          </td>
        </tr>

        <tr v-if="rows.length === 0">
          <td colspan="3" class="empty-state">
            {{ t('webdavimpersonate', 'No mappings configured yet.') }}
          </td>
        </tr>
      </tbody>
    </table>

    <div class="section-footer">
      <NcButton @click="saveConfig" type="primary" :disabled="saving || !isValid">
        {{ saving ? t('webdavimpersonate', 'Saving...') : t('webdavimpersonate', 'Save') }}
      </NcButton>
    </div>
  </div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
  name: 'GroupMappings',
  components: { NcButton, NcSelect, IconDelete, IconPlus },

  data() {
    return {
      rows: [],             // [{ id, impersonator: {id, displayName}, imitatee: {id, displayName} }]
      availableGroups: [],  // alle NC-Gruppen
      saving: false,
      nextId: 0,
    }
  },

  computed: {
    isValid() {
      return this.rows.length === 0 || this.rows.every(row => row.impersonator && row.imitatee)
    }
  },

  async mounted() {
    await this.loadGroups()
    await this.loadConfig()
  },

  methods: {
    async loadGroups() {
      try {
        const { data } = await axios.get(generateUrl('/apps/webdavimpersonate/api/groups'))
        this.availableGroups = data.groups
      } catch (error) {
        showError(this.t('webdavimpersonate', 'Failed to load groups'))
      }
    },

    async loadConfig() {
      try {
        const { data } = await axios.get(generateUrl('/apps/webdavimpersonate/api/mappings'))
        // Backend gibt [{impersonator: 'admin', imitatee: 'users'}, ...] zurück
        // → Objekte aus availableGroups raussuchen
        this.rows = data.mappings.map(m => ({
          id: this.nextId++,
          impersonator: this.availableGroups.find(g => g.id === m.impersonator) ?? null,
          imitatee: this.availableGroups.find(g => g.id === m.imitatee) ?? null,
        })).filter(row => row.impersonator && row.imitatee) // Nur gültige Mappings behalten
      } catch (error) {
        showError(this.t('webdavimpersonate', 'Failed to load mappings'))
      }
    },

    getAvailableGroupsForImpersonator(currentRow) {
      // Verhindere doppelte Impersonator-Gruppen (1:1 Beziehung)
      const usedImpersonators = this.rows
        .filter(row => row.id !== currentRow.id && row.impersonator)
        .map(row => row.impersonator.id)
      
      return this.availableGroups.filter(group => !usedImpersonators.includes(group.id))
    },

    getAvailableGroupsForImitatee(currentRow) {
      // Verhindere doppelte Imitatee-Gruppen (1:1 Beziehung)
      const usedImitatees = this.rows
        .filter(row => row.id !== currentRow.id && row.imitatee)
        .map(row => row.imitatee.id)
      
      return this.availableGroups.filter(group => !usedImitatees.includes(group.id))
    },

    addRow() {
      this.rows.push({ id: this.nextId++, impersonator: null, imitatee: null })
    },

    deleteRow(index) {
      this.rows.splice(index, 1)
    },

    async saveConfig() {
      // Validierung: beide Felder müssen gesetzt sein
      if (!this.isValid) {
        showError(this.t('webdavimpersonate', 'Please fill out all group fields'))
        return
      }

      this.saving = true
      try {
        const response = await axios.post(generateUrl('/apps/webdavimpersonate/api/mappings'), {
          mappings: this.rows.map(r => ({
            impersonator: r.impersonator.id,
            imitatee: r.imitatee.id,
          }))
        })
        
        showSuccess(this.t('webdavimpersonate', 'Mappings saved successfully'))
      } catch (error) {
        console.error('Save error:', error)
        showError(this.t('webdavimpersonate', 'Failed to save mappings'))
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.section-footer {
  margin-top: 1rem;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table td {
  vertical-align: middle;
  padding: 8px;
  width: 45%;
}

.table td:last-child {
  width: 10%;
  text-align: center;
}

.empty-state {
  text-align: center;
  color: var(--color-text-lighter);
  padding: 2rem;
  font-style: italic;
}

/* NcSelect styling */
.nc-select {
  min-width: 200px;
}
</style>
