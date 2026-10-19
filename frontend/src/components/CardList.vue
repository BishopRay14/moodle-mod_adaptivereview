<template>
  <div class="bg-white rounded-3xl shadow-md border border-slate-100 overflow-hidden">
    <div class="p-5 sm:p-6 border-b border-slate-100">
      <div class="flex flex-col gap-5">
        <div>
          <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">All Cards</h2>
          <p class="text-sm text-slate-500 mt-1">
            Browse every card in this activity. Select a card to preview it.
          </p>
        </div>

        <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-xs uppercase tracking-wide font-extrabold text-emerald-700">
                Set Mastery
              </div>
              <div class="text-3xl font-extrabold text-emerald-950 mt-1">
                {{ Number(setMastery).toFixed(1) }}
              </div>
            </div>

            <div class="text-right">
              <div class="text-xs uppercase tracking-wide font-extrabold text-emerald-700">
                Goal
              </div>
              <div class="text-3xl font-extrabold text-emerald-950 mt-1">
                {{ masteryGoal.toFixed(1) }}
              </div>
            </div>
          </div>

          <div class="mt-5 mb-1 flex items-center gap-3">
            <div class="relative flex-1 h-2.5 rounded-full bg-emerald-100 overflow-hidden">
              <div
                class="h-full rounded-full bg-emerald-500 transition-all"
                :style="{ width: progressPercent + '%' }"
              ></div>
            </div>

            <div class="shrink-0 text-amber-400 text-2xl leading-none" title="Goal">
              ★
            </div>
          </div>
          <div class="mt-2 flex items-center justify-between text-xs font-bold text-emerald-800">
            <span>Progress Toward Goal: {{ Number(progressPercent).toFixed(1) }}%</span>
            <span>{{ cardsAtGoal }} / {{ totalCards }} cards at goal</span>
          </div>
        </div>

        <div class="relative">
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0z"/>
          </svg>
          <input
            v-model="search"
            type="search"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3.5 pl-12 pr-4 text-base font-medium text-slate-700 outline-none transition focus:border-emerald-300 focus:bg-white focus:ring-4 focus:ring-emerald-50"
            placeholder="Search cards..."
          />
        </div>
      </div>
    </div>

    <div v-if="filteredCards.length === 0" class="p-10 text-center text-slate-500">
      No cards match your search.
    </div>

    <div v-else class="divide-y divide-slate-100">
      <button
        v-for="card in filteredCards"
        :key="card.id"
        type="button"
        class="w-full text-left p-4 hover:bg-slate-50 transition-colors focus:outline-none focus:bg-emerald-50"
        @click="$emit('review-card', card)"
      >
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="min-w-0">
            <div class="text-base font-bold text-slate-800 truncate" v-html="card.question"></div>
            <div class="mt-1 text-sm text-slate-500 line-clamp-2" v-html="card.answer"></div>
          </div>

          <div class="w-full md:w-52 shrink-0">
            <div class="flex items-end justify-between mb-2">
              <div>
                <div class="text-xs uppercase tracking-wide font-bold text-slate-400">
                  Mastery
                </div>
                <div class="text-lg font-extrabold text-emerald-700">
                  {{ masteryLabel(card) }}
                </div>
              </div>

              <div class="text-xs font-bold text-slate-400">
                Goal: {{ masteryGoal.toFixed(1) }}
              </div>
            </div>

            <div class="flex items-center gap-2">
              <div class="relative flex-1 h-2 rounded-full bg-emerald-100 overflow-hidden">
                <div
                  class="h-full rounded-full bg-emerald-500 transition-all"
                  :style="{ width: masteryPercent(card) + '%' }"
                ></div>
              </div>

              <div class="shrink-0 text-amber-400 text-base leading-none" title="Goal">
                ★
              </div>
            </div>

            <div class="mt-1 text-xs font-medium text-slate-400 text-right">
              {{ masteryStatus(card) }}
            </div>
          </div>
        </div>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  cards: {
    type: Array,
    default: () => [],
  },
  masteryGoal: {
    type: Number,
    default: 4.0,
  },
  setMastery: {
    type: Number,
    default: 0,
  },
  progressPercent: {
    type: Number,
    default: 0,
  },
  cardsAtGoal: {
    type: Number,
    default: 0,
  },
  totalCards: {
    type: Number,
    default: 0,
  },
});

defineEmits(['review-card']);

const search = ref('');

const stripHtml = (value) => {
  const div = document.createElement('div');
  div.innerHTML = value || '';
  return div.textContent || div.innerText || '';
};

const masteryScore = (card) => {
  const value = Number(card.masteryscore ?? 0);
  if (Number.isNaN(value)) {
    return 0;
  }
  return Math.max(0, value);
};

const masteryLabel = (card) => masteryScore(card).toFixed(1);

const masteryPercent = (card) => {
  return Math.min(100, Math.max(0, Number(card.progresspercent ?? 0)));
};

const masteryStatus = (card) => {
  const score = masteryScore(card);

  if (score <= 0) {
    return 'Not started';
  }

  if (card.atgoal) {
    return 'At goal';
  }

  return 'Developing';
};

const filteredCards = computed(() => {
  const term = search.value.trim().toLowerCase();

  if (!term) {
    return props.cards;
  }

  return props.cards.filter((card) => {
    const haystack = [
      stripHtml(card.question),
      stripHtml(card.answer),
      card.category || '',
    ].join(' ').toLowerCase();

    return haystack.includes(term);
  });
});

</script>
