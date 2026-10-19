<template>
  <div class="rc-wrap">

   <!-- ═══ HEADER ═══ -->
    <header class="rc-header" role="banner">
      <div class="rc-header-text">
        <h1 class="rc-title" style="margin-bottom: 4px;">{{ getString('dashboardtitle') }}</h1>
        <p class="rc-subtitle">{{ getString('dashboardsbtitle') }}</p>
      </div>

      <div class="rc-header-actions">
        <button @click="showInfo = true" class="rc-btn rc-btn--ghost rc-btn--sm" :aria-label="getString('howitworks')">
          <svg aria-hidden="true" class="rc-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          {{ getString('howitworks') }}
        </button>
        <button @click="showReset = true" class="rc-btn-reset" :aria-label="getString('reset_progress')" :title="getString('reset_progress')">
          <span aria-hidden="true">🔄</span>
        </button>
      </div>
    </header>

    <section
      class="rc-mastery-summary"
      :aria-label="getString('progress_toward_goal')"
    >
      <div class="rc-mastery-values">
        <div>
          <div class="rc-summary-label">{{ getString('set_mastery') }}</div>
          <div class="rc-summary-value">{{ Number(summary.setmastery || 0).toFixed(1) }}</div>
        </div>
        <div class="rc-summary-goal">
          <div class="rc-summary-label">{{ getString('goal') }}</div>
          <div class="rc-summary-value">{{ Number(summary.masterygoal || masteryGoal).toFixed(1) }}</div>
        </div>
      </div>
      <div class="rc-progress-heading">
        <strong>{{ getString('progress_toward_goal') }}</strong>
        <span>{{ Number(summary.progresspercent || 0).toFixed(1) }}%</span>
      </div>
      <div class="rc-progress-bar">
        <div
          class="rc-progress-fill"
          :style="{ width: `${summary.progresspercent || 0}%` }"
        ></div>
      </div>
      <p class="rc-progress-label">
        {{ summary.cardsatgoal || 0 }} / {{ summary.totalcards || 0 }} {{ getString('cards_at_goal') }}
      </p>
    </section>

    <!-- ═══ BOX GRID ═══ -->
    <main>
      <div class="rc-grid" role="list">
        <div
          v-for="box in 4"
          :key="box"
          @click="startSession(box - 1)"
          @keydown.enter.space.prevent="startSession(box - 1)"
          class="rc-box"
          :class="[getBoxColorClass(box - 1), getBoxStateClass(box - 1)]"
          :role="isCategoryAvailable(box - 1) ? 'button' : 'listitem'"
          :tabindex="isCategoryAvailable(box - 1) ? 0 : -1"
          :aria-label="getBoxName(box - 1) + ': ' + (counts[box-1] || 0) + ' ' + getString('cards')"
          :aria-disabled="!isCategoryAvailable(box - 1)"
        >
          <!-- colour stripe -->
          <div class="rc-box-stripe" aria-hidden="true"></div>

          <!-- emoji -->
          <div class="rc-box-icon" aria-hidden="true" v-html="getBoxIcon(box - 1)"></div>

          <!-- name -->
          <h2 class="rc-box-name">{{ getBoxName(box - 1) }}</h2>

          <!-- badge -->
          <div v-if="isCategoryAvailable(box - 1)" class="rc-badge rc-badge--active" aria-live="polite">
            <span class="rc-badge-dot" aria-hidden="true"></span>
            {{ counts[box-1] }} {{ getString('cards') }}
          </div>
          <div v-else-if="box === 2 && counts[box-1] > 0" class="rc-badge rc-badge--locked">
            <span aria-hidden="true">🔒</span>
            {{ counts[box-1] }} {{ getString('cards') }}
          </div>
          <div v-else-if="counts[box-1] === 0" class="rc-badge rc-badge--empty">
            0 {{ getString('cards') }}
          </div>
          <div v-else class="rc-badge rc-badge--loading" role="status" :aria-label="getString('loadingcards')">
            <svg aria-hidden="true" class="rc-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>

          <!-- arrow -->
          <div v-if="isCategoryAvailable(box - 1)" class="rc-box-arrow" aria-hidden="true">→</div>
        </div>
      </div>
      <p class="rc-queue-note" aria-live="polite">
        {{ counts[0] > 0 ? getString('newcardaccessdue') : getString('newcardaccess') }}
      </p>
    </main>

    <!-- ═══ HOW-IT-WORKS MODAL ═══ -->
    <Teleport to="body">
      <Transition name="rc-fade">
        <div v-if="showInfo" class="rc-overlay" @click.self="showInfo = false" role="dialog" aria-modal="true" :aria-label="getString('systemtitle')">
          <div class="rc-modal rc-modal-flex">
            <button @click="showInfo = false" class="rc-modal-close" :aria-label="'Close'">×</button>
            <div class="rc-modal-head rc-modal-px rc-modal-pt" style="margin-bottom: 14px;">
              <span aria-hidden="true">🎓</span>
              <h2 style="margin: 0;">{{ getString('systemtitle') }}</h2>
            </div>
            <div class="rc-modal-scroll rc-modal-px">
              <p class="rc-modal-intro" v-html="getString('systemintro')"></p>
              <div class="rc-modal-rules">
                <div class="rc-rule">
                  <span class="rc-rule-dot rc-rule-dot--green" aria-hidden="true">✓</span>
                  <div><strong>{{ getString('known_btn') }}</strong><p v-html="getString('known_desc')"></p></div>
                </div>
                <div class="rc-rule">
                  <span class="rc-rule-dot rc-rule-dot--red" aria-hidden="true">↺</span>
                  <div><strong>{{ getString('notyet_btn') }}</strong><p v-html="getString('notyet_desc')"></p></div>
                </div>
              </div>
              <div class="rc-modal-tip" v-html="getString('systemtip')"></div>
            </div>
            <div class="rc-modal-footer rc-modal-px rc-modal-pb" style="padding-top: 14px;">
              <button @click="showInfo = false" class="rc-btn rc-btn--primary rc-btn--full">
                {{ getString('gotit') }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ RESET CONFIRMATION MODAL ═══ -->
    <Teleport to="body">
      <Transition name="rc-fade">
        <div v-if="showReset" class="rc-overlay" @click.self="showReset = false" role="alertdialog" aria-modal="true" :aria-label="getString('reset_progress_confirm_title')">
          <div class="rc-modal rc-modal-flex rc-modal--warning">
            <button @click="showReset = false" class="rc-modal-close" :aria-label="'Cancel'">×</button>
            <div class="rc-modal-scroll rc-modal-px rc-modal-pt rc-modal-pb">
              <div class="rc-modal-head" style="margin-bottom: 14px;">
                <span aria-hidden="true">⚠️</span>
                <h2 style="margin: 0;">{{ getString('reset_progress_confirm_title') }}</h2>
              </div>
              <p class="rc-modal-warning-text" v-html="getString('reset_progress_confirm_msg')"></p>
              <div v-if="resetError" class="rc-alert rc-alert--error" role="alert">
                ❌ {{ resetError }}
              </div>
              <div class="rc-modal-actions">
                <button @click="showReset = false" class="rc-btn rc-btn--ghost">
                  {{ getString('reset_progress_cancel') }}
                </button>
                <button @click="doReset" class="rc-btn rc-btn--danger" :disabled="resetting">
                  <svg v-if="resetting" aria-hidden="true" class="rc-spin rc-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  {{ getString('reset_progress_btn') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue';
import { getStudentSummary, resetProgress } from '../api';

const emit = defineEmits(['start-session']);
const props = defineProps({
    masteryGoal: {
        type: Number,
        default: 4.0,
    },
});
const counts = ref({});
const summary = ref({
    duecount: 0,
    newcount: 0,
    recentcount: 0,
    totalcards: 0,
    setmastery: 0,
    masterygoal: props.masteryGoal,
    progresspercent: 0,
    cardsatgoal: 0,
    newavailable: true,
});
const showInfo = ref(false);
const showReset = ref(false);
const resetting = ref(false);
const resetSuccess = ref(false);
const resetError = ref('');

const totalCards = computed(() => {
    if (typeof counts.value[3] === 'number') {
        return counts.value[3];
    }

    return Object.values(counts.value).reduce((s, v) => s + (v || 0), 0);
});

const loadSummary = async () => {
    try {
        summary.value = await getStudentSummary();
        counts.value = {
            0: summary.value.duecount,
            1: summary.value.newcount,
            2: summary.value.recentcount,
            3: summary.value.totalcards,
        };
    } catch (e) {
        console.error('Failed to load the student summary', e);
    }
};

const FALLBACKS = {
    // Header
    dashboardtitle: 'Today\'s Review',
    dashboardsbtitle: 'Select a category',
    howitworks: 'How does Adaptive Review work?',
    cards: 'Cards',
    loadingcards: 'Loading cards...',
    newcardaccess: 'Due reviews come first. Once they are complete, all new cards are available.',
    newcardaccessdue: 'Complete Due Today before starting new cards.',
    progress_label: 'Cards meeting the goal',
    progress_aria: 'Progress: {mastered} of {total} cards have reached the mastery goal',
    set_mastery: 'Set Mastery',
    progress_toward_goal: 'Progress Toward Goal',
    cards_at_goal: 'cards at goal',
    goal: 'Goal',
    // Box levels
    box0: 'New',
    box1: 'Beginner',
    box2: 'Learner',
    box3: 'Advanced',
    box4: 'Experienced',
    box5: 'Review',
    // Info modal
   systemtitle: 'Build Long-Term Mastery',
    systemintro: 'Adaptive Review helps learners retain knowledge through spaced repetition. New material is introduced gradually, while previously learned material is scheduled for review at the optimal time to strengthen long-term memory.',
    known_btn: 'I Know It',
    known_desc: 'Successful retrieval increases mastery based on the time elapsed.',
    notyet_btn: 'Not Yet',
    notyet_desc: 'Mastery is preserved and a shorter recovery review is scheduled.',
    systemtip: '<strong>Tip:</strong> Completing your Due Today review consistently leads to the strongest long-term retention.',
    gotit: 'Got it!',
    // Reset
    reset_progress: 'Reset progress',
    reset_progress_confirm_title: 'Reset progress?',
    reset_progress_confirm_msg: 'Are you sure you want to reset your progress? This cannot be undone.',
    reset_progress_btn: 'Yes, reset progress',
    reset_progress_cancel: 'Cancel',
    reset_progress_done: '✅ Progress was reset successfully!',
};

const getString = (key) => {
    // 1. Try Moodle's loaded strings
    const moodleStr = window.M?.str?.mod_adaptivereview?.[key];
    // 2. Only use Moodle string if it's valid (not a [[placeholder]])
    if (moodleStr && !moodleStr.startsWith('[[')) return moodleStr;
    // 3. Fall back to hardcoded defaults
    return FALLBACKS[key] ?? key;
};

onMounted(loadSummary);

const getBoxIcon = (box) => [
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M12 10c0-4 3-6 7-6 0 4-2 7-7 7"/><path d="M12 12c0-3-2-5-6-5 0 4 2 6 6 6"/></svg>',
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/></svg>',
][box] ?? '';
const getBoxName = (box) => {
    const names = [
        getString('queue_due'),
        getString('queue_new'),
        getString('queue_recent'),
        getString('queue_all'),
    ];

    return names[box] ?? getString('cards');
};

const getBoxColorClass = (box) => [
    'rc-box--blue',     // Due Today
    'rc-box--orange',   // New
    'rc-box--green',    // Recently Learned
    'rc-box--gray',     // All Cards
][box] ?? 'rc-box--blue';

const isCategoryAvailable = (box) => {
    if ((counts.value[box] ?? 0) <= 0) {
        return false;
    }
    return box !== 1 || summary.value.newavailable;
};

const getBoxStateClass = (box) => {
    if (isCategoryAvailable(box)) {
        return 'rc-box--active';
    }
    if ((counts.value[box] ?? 0) > 0) {
        return 'rc-box--locked';
    }
    return 'rc-box--empty';
};

const startSession = (boxnumber) => {
    if (isCategoryAvailable(boxnumber)) emit('start-session', boxnumber, totalCards.value);
};

const doReset = async () => {
    resetting.value = true;
    resetSuccess.value = false;
    resetError.value = '';
    try {
        const appConfig = JSON.parse(document.getElementById('v-app-mod-adaptivereview')?.dataset?.config || '{}');
        await resetProgress(appConfig.instanceid);
        await loadSummary();
        showReset.value = false;
    } catch (e) {
        resetError.value = 'Error resetting progress: ' + (e.message || 'Unknown error');
    } finally {
        resetting.value = false;
    }
};
</script>

<style scoped>
/* ─── tokens ──────────────── */
.rc-wrap, .rc-overlay {
  --teal: #00a693;
  --navy: #1d2757;
  --teal-pastel: #f0fdf9;
  --teal-mid: #ccf5ee;
}
.rc-wrap {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  max-width: 1080px;
  margin: 0 auto;
  padding: 0 24px 48px;
  color: var(--navy);
}

/* ─── header ──────────────── */
.rc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  padding: 28px 0 20px;
}
.rc-header-brand {
  display: flex;
  align-items: center;
  gap: 20px;
}
.rc-wordmark {
  font-size: 2.25rem;
  font-weight: 800;
  color: #1e2a44;
  line-height: 1;
  letter-spacing: -0.03em;
  flex-shrink: 0;
  margin-left: -4px;
  user-select: none;
}
.rc-title-row {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}
.rc-title {
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  font-weight: 800;
  color: var(--navy);
  margin: 0;
  letter-spacing: -0.3px;
  line-height: 1.1;
}
.rc-subtitle {
  font-size: 0.97rem;
  color: #64748b;
  margin: 0;
}
.rc-header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* ─── buttons ──────────────── */
.rc-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  min-height: 44px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  border: 2px solid transparent;
  cursor: pointer;
  transition: background 0.18s, border-color 0.18s, color 0.18s;
}
.rc-btn--sm {
  padding: 6px 14px;
  min-height: 36px;
  border-radius: 8px;
  font-size: 0.82rem;
}
.rc-btn--ghost {
  background: #f0f4f8;
  color: var(--navy);
  border-color: #dde3ec;
}
.rc-btn--ghost:hover { background: var(--teal-mid); border-color: var(--teal); color: #00766b; }
.rc-btn-reset {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 1.1rem;
}
.rc-btn-reset:hover {
  background: #f1f5f9;
  border-style: solid;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(100, 116, 139, 0.15);
}
.rc-btn-reset:focus-visible { outline: 2px solid #94a3b8; outline-offset: 2px; }

.rc-btn--danger-ghost {
  background: #fff5f5;
  color: #b91c1c;
  border-color: #fecaca;
}
.rc-btn--danger-ghost:hover { background: #fee2e2; border-color: #f87171; }
.rc-btn--danger {
  background: #dc2626;
  color: #fff;
  border-color: #dc2626;
}
.rc-btn--danger:hover { background: #b91c1c; border-color: #b91c1c; }
.rc-btn--danger:disabled { opacity: 0.6; cursor: not-allowed; }
.rc-btn--primary {
  background: linear-gradient(135deg, var(--teal), var(--navy));
  color: #fff;
  border-color: transparent;
}
.rc-btn--primary:hover { opacity: 0.88; }
.rc-btn--full { width: 100%; justify-content: center; }
.rc-btn:focus-visible { outline: 3px solid var(--teal); outline-offset: 3px; }
.rc-btn:focus:not(:focus-visible) { outline: none; }

/* ─── icon ──────────────── */
.rc-icon { width: 17px; height: 17px; flex-shrink: 0; }

/* ─── progress ──────────────── */
.rc-progress-bar {
  height: 7px;
  background: #e2e8f0;
  border-radius: 99px;
  overflow: hidden;
  margin-bottom: 6px;
}
.rc-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--teal), var(--navy));
  border-radius: 99px;
  transition: width 0.5s ease;
}
.rc-progress-label {
  font-size: 0.78rem;
  color: #94a3b8;
  margin: 0 0 28px;
}

.rc-mastery-summary {
  max-width: 900px;
  margin: 0 auto 24px;
  padding: 22px 24px 18px;
  border: 1px solid #a7f3d0;
  border-radius: 18px;
  background: linear-gradient(135deg, #ecfdf5, #f8fafc);
}
.rc-mastery-summary .rc-progress-label { margin-bottom: 0; }
.rc-mastery-values,
.rc-progress-heading {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
}
.rc-summary-goal { text-align: right; }
.rc-summary-label {
  color: #047857;
  font-size: 0.74rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.rc-summary-value {
  margin-top: 2px;
  color: #064e3b;
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
}
.rc-progress-heading {
  align-items: center;
  margin: 20px 0 8px;
  color: #334155;
  font-size: 0.84rem;
}

/* ─── grid ──────────────── */
.rc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(320px, 1fr));
  gap: 24px;
  max-width: 900px;
  margin: 0 auto;
}

.rc-queue-note {
  max-width: 900px;
  margin: 18px auto 0;
  color: #64748b;
  font-size: 0.88rem;
  text-align: center;
}

@media (max-width: 768px) {
  .rc-grid {
    grid-template-columns: 1fr;
  }
}
/* ─── box card ──────────────── */
.rc-box {
  position: relative;
  border-radius: 18px;
  padding: 28px 20px 22px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  border: 2px solid transparent;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  background: var(--teal-pastel);
}

/* ── category colour variants ── */
.rc-box--blue {
  background: #eff6ff;
  border-color: #bfdbfe;
}

.rc-box--orange {
  background: #fff7ed;
  border-color: #fed7aa;
}

.rc-box--green {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.rc-box--gray {
  background: #f8fafc;
  border-color: #cbd5e1;
}

/* stripe */
.rc-box-stripe {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  border-radius: 18px 18px 0 0;
}

.rc-box--blue .rc-box-stripe {
  background: #3b82f6;
}

.rc-box--orange .rc-box-stripe {
  background: #f97316;
}

.rc-box--green .rc-box-stripe {
  background: #22c55e;
}

.rc-box--gray .rc-box-stripe {
  background: #64748b;
}

/* active / empty states */
.rc-box--active:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 28px rgba(29,39,87,0.13);
  cursor: pointer;
}
.rc-box--empty {
  opacity: 0.45;
  border-style: dashed;
}
.rc-box--locked {
  opacity: 0.72;
  cursor: not-allowed;
}
.rc-box:focus-visible { outline: 3px solid var(--teal); outline-offset: 3px; }
.rc-box:focus:not(:focus-visible) { outline: none; }

.rc-box-icon {
  width: 40px;
  height: 40px;
  color: currentColor;
  transition: transform 0.2s;
  opacity: 0.9;
}

.rc-box-icon svg {
  width: 100%;
  height: 100%;
}

.rc-box--active:hover .rc-box-icon {
  transform: scale(1.12);
}

.rc-box-name {
  font-size: 0.87rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin: 0;
  text-align: center;
}

/* ─── badge ──────────────── */
.rc-badge {
  font-size: 0.77rem;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 99px;
  display: flex;
  align-items: center;
  gap: 5px;
  border: 1px solid transparent;
}
.rc-badge--active {
  background: rgba(0,166,147,0.18);
  color: #005f56;
  border-color: rgba(0,166,147,0.35);
}
.rc-box--gold .rc-badge--active {
  background: rgba(255,255,255,0.2);
  color: #fff;
  border-color: rgba(255,255,255,0.4);
}
.rc-badge--empty {
  background: #f1f5f9;
  color: #94a3b8;
  border-color: #e2e8f0;
  border-style: dashed;
}
.rc-badge--locked {
  background: #fff7ed;
  color: #9a3412;
  border-color: #fed7aa;
}
.rc-badge--loading { color: #94a3b8; }
.rc-badge-dot {
  width: 6px; height: 6px; border-radius: 50%; background: var(--teal);
  animation: rc-pulse 1.5s infinite;
}
.rc-box--gold .rc-badge-dot { background: #fff; }

.rc-box-arrow {
  position: absolute; bottom: 12px; right: 16px;
  font-size: 1rem; color: #94a3b8;
  opacity: 0; transition: opacity 0.2s, transform 0.2s;
}
.rc-box--gold .rc-box-arrow { color: rgba(255,255,255,0.6); }
.rc-box--active:hover .rc-box-arrow { opacity: 1; transform: translateX(3px); }

/* ─── spin ──────────────── */
.rc-spin { width: 16px; height: 16px; animation: rc-spin 1s linear infinite; }

/* ─── modals ──────────────── */
.rc-overlay {
  position: fixed; inset: 0; z-index: 100;
  display: flex; align-items: center; justify-content: center; padding: 16px;
  background: rgba(15,23,42,0.5);
  backdrop-filter: blur(4px);
}
.rc-modal {
  position: relative;
  background: #fff;
  border-radius: 24px;
  max-width: 540px; width: 100%;
  box-shadow: 0 24px 64px rgba(29,39,87,0.22);
  max-height: 90vh; /* standard modals */
}
.rc-modal-flex {
  display: flex !important;
  flex-direction: column !important;
  padding: 0 !important;
  overflow: hidden !important;
}
.rc-modal-scroll {
  overflow-y: auto;
  flex: 1 1 auto;
  overscroll-behavior: contain;
}
.rc-modal-footer {
  flex-shrink: 0;
}
.rc-modal-px { padding-left: 30px; padding-right: 30px; }
.rc-modal-pt { padding-top: 32px; flex-shrink: 0; }
.rc-modal-pb { padding-bottom: 32px; flex-shrink: 0; }

.rc-modal--warning { border-top: 5px solid #dc2626; }
.rc-modal-close {
  position: absolute; top: 14px; right: 14px;
  background: #f1f5f9; border: none; border-radius: 50%;
  width: 32px; height: 32px; font-size: 1.3rem; line-height: 1;
  cursor: pointer; color: #64748b;
  z-index: 10;
}
.rc-modal-close:hover { background: #e2e8f0; }
.rc-modal-close:focus-visible { outline: 3px solid var(--teal); outline-offset: 2px; }
.rc-modal-head {
  display: flex; align-items: center; gap: 12px;
}
.rc-modal-head span { font-size: 1.8rem; }
.rc-modal-head h2 { font-size: 1.25rem; font-weight: 800; color: var(--navy); margin: 0; }
.rc-modal-intro { color: #475569; line-height: 1.65; margin-bottom: 18px; }
.rc-modal-rules {
  background: #f8fafc; border-radius: 14px; padding: 18px;
  display: flex; flex-direction: column; gap: 14px; margin-bottom: 18px;
}
.rc-rule { display: flex; align-items: flex-start; gap: 12px; }
.rc-rule strong { display: block; color: var(--navy); font-weight: 700; margin-bottom: 2px; }
.rc-rule p { color: #64748b; font-size: 0.88rem; margin: 0; line-height: 1.5; }
.rc-rule-dot {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 800; flex-shrink: 0; margin-top: 1px;
}
.rc-rule-dot--green { background: #dcfce7; color: #15803d; }
.rc-rule-dot--yellow { background: #fef9c3; color: #a16207; }
.rc-rule-dot--red   { background: #fee2e2; color: #b91c1c; }
.rc-modal-tip {
  background: #edfdf8; border-left: 3px solid var(--teal);
  border-radius: 10px; padding: 12px 16px;
  font-size: 0.88rem; color: #0f766e; margin-bottom: 18px;
}
.rc-modal-warning-text {
  color: #374151; line-height: 1.65;
  background: #fff5f5; border: 1px solid #fecaca;
  border-radius: 10px; padding: 14px 16px;
  margin-bottom: 18px; font-size: 0.93rem;
}
.rc-modal-actions {
  display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap;
}
.rc-alert {
  border-radius: 10px; padding: 10px 16px;
  font-size: 0.9rem; font-weight: 600; margin-bottom: 14px;
}
.rc-alert--success { background: #dcfce7; color: #15803d; }
.rc-alert--error   { background: #fee2e2; color: #b91c1c; }

/* ─── transitions ──────────────── */
.rc-fade-enter-active, .rc-fade-leave-active { transition: opacity 0.22s ease; }
.rc-fade-enter-from, .rc-fade-leave-to { opacity: 0; }

/* ─── keyframes ──────────────── */
@keyframes rc-pulse { 0%,100% { opacity:1; } 50% { opacity:0.35; } }
@keyframes rc-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
