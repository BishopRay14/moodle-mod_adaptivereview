import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import { initApi } from './api'

// Look for the config injected by Moodle.
const rootElement = document.getElementById('v-app-mod-adaptivereview')

if (rootElement) {
    const config = JSON.parse(rootElement.getAttribute('data-config') || '{}')
    initApi(config)
    createApp(App, {
        masteryGoal: Number(config.masterygoal ?? 4.0),
    }).mount('#v-app-mod-adaptivereview')
} else {
    console.error("Mount point #v-app-mod-adaptivereview not found.")
}
