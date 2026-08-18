<template>
  <div class="min-h-[600px] w-full bg-slate-50 font-sans text-slate-900 py-6 px-4 rounded-3xl" style="font-family: 'Inter', system-ui, sans-serif;">
    <Dashboard
      v-if="!sessionActive && !allCardsMode && !recentChoiceMode"
      @start-session="handleStartSession"
    />

    <div v-else-if="recentChoiceMode" class="max-w-2xl mx-auto">
      <div class="mb-5">
        <button
          @click="endSession"
          class="text-slate-500 hover:text-[#4F46E5] font-medium flex items-center gap-2 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          {{ getString('backtodashboard') }}
        </button>
      </div>

      <div class="bg-white rounded-3xl shadow-md border border-slate-100 p-8">
        <div class="text-center mb-8">
          <div class="mx-auto mb-4 w-14 h-14 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700">
            <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 20V10"/>
              <path d="M12 10c0-4 3-6 7-6 0 4-2 7-7 7"/>
              <path d="M12 12c0-3-2-5-6-5 0 4 2 6 6 6"/>
            </svg>
          </div>
          <h2 class="text-3xl font-extrabold text-slate-800">Recently Learned</h2>
          <p class="text-slate-500 mt-2">
            Browse these cards without changing mastery, or practice them early.
          </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <button
            type="button"
            @click="startRecentBrowse"
            class="rounded-3xl border border-slate-200 bg-slate-50 hover:bg-white hover:border-emerald-200 p-6 text-left transition-all shadow-sm hover:shadow-md"
          >
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"></path>
              </svg>
            </div>
            <div class="text-xl font-extrabold text-slate-800">Browse Cards</div>
            <p class="text-sm text-slate-500 mt-2">
              Flip through recently learned cards with previous and next. No mastery update.
            </p>
          </button>

          <button
            type="button"
            @click="startRecentPractice"
            class="rounded-3xl bg-emerald-500 hover:bg-emerald-600 p-6 text-left transition-all shadow-md hover:shadow-lg text-white"
          >
            <div class="w-11 h-11 rounded-2xl bg-white/20 flex items-center justify-center mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
              </svg>
            </div>
            <div class="text-xl font-extrabold">Practice Review</div>
            <p class="text-sm text-emerald-50 mt-2">
              Quiz yourself early with I Know It / Not Yet.
            </p>
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="allCardsMode" class="max-w-5xl mx-auto">
      <div class="mb-4">
        <button
          @click="endSession"
          class="text-slate-500 hover:text-[#4F46E5] font-medium flex items-center gap-2 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          {{ getString('backtodashboard') }}
        </button>
      </div>

      <CardList
        :cards="allCards"
        :mastery-goal="masteryGoal"
        @review-card="handleLibraryCard"
      />
    </div>

    <div v-else class="max-w-2xl mx-auto">
      <div class="flex flex-col sm:flex-row justify-between items-center mb-5 gap-3">
        <button
          @click="endSession"
          class="text-slate-500 hover:text-[#4F46E5] font-medium flex items-center gap-2 transition-colors"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          {{ getString('backtodashboard') }}
        </button>

        <div class="text-sm font-bold px-4 py-1.5 rounded-full shadow-sm border flex items-center gap-2" :class="getBadgeClass(activeBox)">
          <span class="w-2 h-2 rounded-full" :class="getDotClass(activeBox)"></span>
          {{ getBoxName(activeBox) }}
          <span class="opacity-40 mx-1 font-normal">|</span>
          {{ getString('cardxofy_x') }}
          {{ Math.min(currentCardIndex + 1, Math.max(sessionCards.length, 1)) }}
          {{ getString('cardxofy_y') }}
          <span class="font-extrabold">{{ sessionCards.length }}</span>
        </div>
      </div>

      <div v-if="loading" class="flex flex-col items-center justify-center py-24 text-slate-500">
        <svg class="animate-spin h-10 w-10 text-indigo-500 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="font-medium text-lg">{{ getString('loadingcards') }}</p>
      </div>

      <div v-else-if="sessionFinished" class="text-center py-10 bg-white rounded-3xl shadow-md border border-slate-100 p-8 transform transition-all max-w-lg mx-auto">
        <div class="text-5xl mb-4">{{ reviewMode === 'browse' ? '🌱' : getSessionFeedback().emoji }}</div>
        <h3 class="text-2xl font-extrabold text-slate-800 mb-2 tracking-tight">
          {{ reviewMode === 'browse' ? 'Finished Browsing' : getSessionFeedback().title }}
        </h3>
        <p class="text-slate-500 mb-8">
          {{ reviewMode === 'browse' ? 'Return to the dashboard when you are ready.' : getSessionFeedback().desc }}
        </p>

        <button
          @click="endSession"
          class="bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white font-bold py-3.5 px-8 rounded-full shadow-lg hover:shadow-xl transition-all hover:-translate-y-0.5 w-full"
        >
          {{ getString('backtodashboard') }}
        </button>
      </div>

      <div v-else-if="currentCard" class="flex flex-col items-center w-full perspective-1000">
        <Transition name="fade-slide" mode="out-in">
          <Card
            :key="`${currentCard.id}-${currentCardIndex}-${reviewMode}`"
            :card="currentCard"
            :mode="reviewMode"
            :current-index="currentCardIndex"
            :total-cards="sessionCards.length"
            :mastery-goal="masteryGoal"
            :has-previous="reviewMode === 'browse' && currentCardIndex > 0"
            :has-next="reviewMode === 'browse' && currentCardIndex < sessionCards.length - 1"
            @answer="handleAnswer"
            @continue="handleContinue"
            @previous="goPrevious"
            @next="goNext"
          />
        </Transition>
      </div>

      <div v-if="!loading && !sessionFinished && sessionCards.length > 0" class="mt-8 max-w-md mx-auto relative group">
        <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden shadow-inner">
          <div
            class="bg-gradient-to-r from-emerald-400 to-emerald-500 h-2 rounded-full transition-all duration-500 ease-out"
            :style="{ width: `${progressPercentage}%` }"
          ></div>
        </div>
        <div class="absolute -top-6 text-center w-full text-xs font-bold text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity">
          {{ Math.round(progressPercentage) }}% {{ getString('completed') }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Dashboard from './components/Dashboard.vue';
import Card from './components/Card.vue';
import CardList from './components/CardList.vue';
import {
    completeReviewSession,
    getCardsByBox,
    startReviewSession,
    submitAnswer,
} from './api';

const FALLBACKS = {
    feedback_perfect_title: 'Strong result',
    feedback_perfect_desc: 'You recalled every card correctly. The material is sticking.',
    feedback_good_title: 'Solid performance',
    feedback_good_desc: 'You have a good grasp of this material. The remaining gaps will close with review.',
    feedback_okay_title: 'On the right track',
    feedback_okay_desc: 'You are building a foundation. Another review will help strengthen recall.',
    feedback_learn_title: 'Review recommended',
    feedback_learn_desc: 'Some answers were difficult. Use the next round to strengthen those cards.',
    completed: 'completed',
    loadingcards: 'Loading cards...',
    backtodashboard: 'Back to Dashboard',
    cardxofy_x: 'Card',
    cardxofy_y: 'of',
    error_loading_cards: 'Error loading cards.',
    error_saving_answer: 'Your answer could not be saved. Please try again.',
    box0: 'Due Today',
    box1: 'New',
    box2: 'Recently Learned',
    box3: 'All Cards',
};

const getString = (key) => {
    const moodleStr = window.M?.str?.mod_adaptivereview?.[key];
    if (moodleStr && !moodleStr.startsWith('[[')) return moodleStr;
    return FALLBACKS[key] ?? key;
};

const masteryGoal = ref(4.0);
const sessionActive = ref(false);
const sessionFinished = ref(false);
const allCardsMode = ref(false);
const recentChoiceMode = ref(false);
const activeBox = ref(null);
const loading = ref(false);
const sessionCards = ref([]);
const recentCards = ref([]);
const allCards = ref([]);
const currentCardIndex = ref(0);
const sessionStats = ref({ known: 0, again: 0, hard: 0 });
const reviewMode = ref('review');
const firstSessionResponses = ref({});
const reviewSessionKey = ref(null);
const pendingAnswerSave = ref(null);

const currentCard = computed(() => sessionCards.value[currentCardIndex.value]);

const progressPercentage = computed(() => {
    if (sessionCards.value.length === 0) return 100;
    return (currentCardIndex.value / sessionCards.value.length) * 100;
});

const getSessionFeedback = () => {
    const total = Math.max(1, sessionStats.value.known + sessionStats.value.hard);
    const score = sessionStats.value.known / total;

    if (score >= 0.9) {
        return { emoji: '🏆', title: getString('feedback_perfect_title'), desc: getString('feedback_perfect_desc') };
    } else if (score >= 0.7) {
        return { emoji: '🌟', title: getString('feedback_good_title'), desc: getString('feedback_good_desc') };
    } else if (score >= 0.4) {
        return { emoji: '👍', title: getString('feedback_okay_title'), desc: getString('feedback_okay_desc') };
    }

    return { emoji: '💪', title: getString('feedback_learn_title'), desc: getString('feedback_learn_desc') };
};

const getBadgeClass = (box) => {
    const classes = {
        0: 'bg-blue-50 text-blue-700 border-blue-200',
        1: 'bg-orange-50 text-orange-800 border-orange-200',
        2: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        3: 'bg-violet-50 text-violet-800 border-violet-200',
    };
    return classes[box] || 'bg-slate-100 text-slate-700 border-slate-200';
};

const getDotClass = (box) => {
    const classes = {
        0: 'bg-blue-500',
        1: 'bg-orange-500',
        2: 'bg-emerald-500',
        3: 'bg-violet-500',
    };
    return classes[box] || 'bg-slate-500';
};

const getBoxName = (box) => getString('box' + box);

const resetSessionState = () => {
    sessionFinished.value = false;
    currentCardIndex.value = 0;
    sessionStats.value = { known: 0, again: 0, hard: 0 };
    firstSessionResponses.value = {};
    pendingAnswerSave.value = null;
};

const handleStartSession = async (boxnumber) => {
    activeBox.value = boxnumber;
    sessionActive.value = false;
    sessionFinished.value = false;
    allCardsMode.value = false;
    recentChoiceMode.value = false;
    loading.value = true;
    resetSessionState();
    reviewMode.value = 'review';

    try {
        const cards = await getCardsByBox(boxnumber);

        if (boxnumber === 3) {
            allCards.value = cards;
            allCardsMode.value = true;
            return;
        }

        if (boxnumber === 2) {
            recentCards.value = cards;
            recentChoiceMode.value = true;
            return;
        }

        reviewSessionKey.value = await startReviewSession(boxnumber === 0 ? 'due' : 'new');
        sessionCards.value = cards;
        sessionActive.value = true;
    } catch (e) {
        console.error('Error loading session:', e);
        alert(getString('error_loading_cards'));
        sessionActive.value = false;
        allCardsMode.value = false;
        recentChoiceMode.value = false;
    } finally {
        loading.value = false;
    }
};

const startRecentBrowse = () => {
    sessionCards.value = [...recentCards.value];
    recentChoiceMode.value = false;
    sessionActive.value = true;
    reviewMode.value = 'browse';
    resetSessionState();
};

const startRecentPractice = async () => {
    loading.value = true;
    try {
        reviewSessionKey.value = await startReviewSession('early');
        sessionCards.value = [...recentCards.value];
        recentChoiceMode.value = false;
        sessionActive.value = true;
        reviewMode.value = 'review';
        resetSessionState();
    } catch (e) {
        console.error('Error starting early practice:', e);
        alert(getString('error_loading_cards'));
    } finally {
        loading.value = false;
    }
};

const handleLibraryCard = (card) => {
    allCardsMode.value = false;
    sessionActive.value = true;
    sessionFinished.value = false;
    activeBox.value = 3;
    reviewMode.value = 'browse';
    sessionCards.value = [card];
    currentCardIndex.value = 0;
};

const handleAnswer = ({ card, result }) => {
    if (!card || reviewMode.value !== 'review' || !reviewSessionKey.value) {
        return;
    }

    const cardid = String(card.id);

    if (!firstSessionResponses.value[cardid]) {
        firstSessionResponses.value[cardid] = result;

        if (result === 'know') {
            sessionStats.value.known++;
        } else {
            sessionStats.value.hard++;
        }
    }

    pendingAnswerSave.value = submitAnswer(card.id, result, reviewSessionKey.value);
};

const handleContinue = async ({ result }) => {
    if (reviewMode.value === 'browse') {
        goNextOrFinish();
        return;
    }

    if (pendingAnswerSave.value) {
        try {
            await pendingAnswerSave.value;
            pendingAnswerSave.value = null;
        } catch (e) {
            console.error('Failed to save answer', e);
            if (currentCard.value && reviewSessionKey.value) {
                pendingAnswerSave.value = submitAnswer(
                    currentCard.value.id,
                    result,
                    reviewSessionKey.value
                );
            }
            alert(getString('error_saving_answer'));
            return;
        }
    }

    if (result === 'notyet' && currentCard.value) {
        const cardid = String(currentCard.value.id);
        if (firstSessionResponses.value[cardid] === 'notyet') {
            sessionCards.value.push({ ...currentCard.value });
        }
    }

    goNextOrFinish();
};

const goNextOrFinish = () => {
    if (currentCardIndex.value < sessionCards.value.length - 1) {
        currentCardIndex.value++;
    } else {
        currentCardIndex.value++;
        setTimeout(() => {
            sessionFinished.value = true;
        }, 300);
    }
};

const goPrevious = () => {
    if (currentCardIndex.value > 0) {
        currentCardIndex.value--;
    }
};

const goNext = () => {
    goNextOrFinish();
};

const endSession = async () => {
    if (pendingAnswerSave.value) {
        try {
            await pendingAnswerSave.value;
        } catch (e) {
            console.error('Failed to save answer before ending the session', e);
            alert(getString('error_saving_answer'));
            return;
        }
    }

    const sessionkey = reviewSessionKey.value;

    sessionActive.value = false;
    sessionFinished.value = false;
    allCardsMode.value = false;
    recentChoiceMode.value = false;
    sessionCards.value = [];
    recentCards.value = [];
    allCards.value = [];
    activeBox.value = null;
    currentCardIndex.value = 0;
    sessionStats.value = { known: 0, again: 0, hard: 0 };
    firstSessionResponses.value = {};
    reviewMode.value = 'review';
    reviewSessionKey.value = null;
    pendingAnswerSave.value = null;

    if (sessionkey) {
        completeReviewSession(sessionkey).catch(e => {
            console.error('Failed to complete review session', e);
        });
    }
};
</script>

<style>
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

.perspective-1000 {
  perspective: 1000px;
}
</style>
