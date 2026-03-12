import { createApp } from 'vue'
import App from './AdminApp.vue'
import GroupMappings from './components/GroupMappings.vue'

const app = createApp(App)
app.component('GroupMappings', GroupMappings)
app.mount('#webdavimpersonate-admin-app')
