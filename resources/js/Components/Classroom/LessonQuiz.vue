<template>
    <!-- Quiz section -->
    <div v-if="lesson?.quiz" class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-gray-900 text-base">📝 {{ lesson.quiz.title }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Pass score: {{ lesson.quiz.pass_score }}%</p>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="currentAttempt?.passed"
                    class="text-xs font-semibold text-green-700 bg-green-50 px-2.5 py-1 rounded-full">
                    ✓ Passed {{ currentAttempt.score }}%
                </span>
                <span v-else-if="currentAttempt"
                    class="text-xs font-semibold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">
                    {{ currentAttempt.score }}% – Retake available
                </span>
                <button v-if="isOwner" @click="$emit('delete-quiz')"
                    class="text-xs text-red-400 hover:text-red-600 border border-red-200 px-2.5 py-1 rounded-lg">
                    Delete Quiz
                </button>
            </div>
        </div>

        <!-- Quiz result banner -->
        <div v-if="quizResult" class="mx-6 mt-4 p-4 rounded-xl border"
            :class="quizResult.passed ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
            <p class="font-bold text-sm" :class="quizResult.passed ? 'text-green-800' : 'text-red-800'">
                {{ quizResult.passed ? '🎉 You passed!' : '😕 Not quite' }}
            </p>
            <p class="text-xs mt-0.5" :class="quizResult.passed ? 'text-green-600' : 'text-red-600'">
                Score: {{ quizResult.score }}% ({{ quizResult.correct }}/{{ quizResult.total }} correct)
            </p>
        </div>

        <!-- Take quiz -->
        <div v-if="!quizResult || !quizResult.passed" class="p-6 space-y-6">
            <div
                v-for="(question, qi) in lesson.quiz.questions"
                :key="question.id"
                class="space-y-2"
            >
                <p class="text-sm font-semibold text-gray-800">{{ qi + 1 }}. {{ question.question }}</p>
                <div class="space-y-1.5">
                    <label
                        v-for="option in question.options"
                        :key="option.id"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                        :class="quizAnswers[question.id] === option.id
                            ? 'border-indigo-400 bg-indigo-50'
                            : 'border-gray-200 hover:bg-gray-50'"
                    >
                        <input
                            type="radio"
                            :name="`q_${question.id}`"
                            :value="option.id"
                            v-model="quizAnswers[question.id]"
                            class="accent-indigo-600"
                        />
                        <span class="text-sm text-gray-700">{{ option.label }}</span>
                    </label>
                </div>
            </div>

            <button
                @click="$emit('submit-quiz')"
                :disabled="quizFormProcessing || !allAnswered"
                class="w-full py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
                {{ quizFormProcessing ? 'Submitting...' : 'Submit Quiz' }}
            </button>
        </div>

        <!-- Retake button -->
        <div v-if="quizResult && !quizResult.passed" class="px-6 pb-6">
            <button @click="$emit('retake-quiz')" class="text-sm text-indigo-600 font-medium hover:text-indigo-800">
                ↺ Retake Quiz
            </button>
        </div>
    </div>

    <!-- Owner: Add quiz (when no quiz exists) -->
    <div v-if="isOwner && lesson && !lesson.quiz" class="bg-white border border-dashed border-gray-300 rounded-2xl p-5">
        <div v-if="!showQuizBuilder">
            <button @click="showQuizBuilder = true"
                class="w-full text-sm text-gray-400 hover:text-indigo-600 text-center font-medium">
                + Add Quiz to this Lesson
            </button>
        </div>

        <!-- Quiz builder -->
        <div v-else class="space-y-4">
            <h4 class="text-sm font-bold text-gray-800">Quiz Builder</h4>

            <input v-model="quizBuilderForm.title" type="text" placeholder="Quiz title"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />

            <div class="flex items-center gap-3">
                <label class="text-xs text-gray-500 shrink-0">Pass score (%)</label>
                <input v-model.number="quizBuilderForm.pass_score" type="number" min="1" max="100"
                    class="w-24 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div v-for="(q, qi) in quizBuilderForm.questions" :key="qi" class="p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-500">Q{{ qi + 1 }}</span>
                    <input v-model="q.question" type="text" placeholder="Question text"
                        class="flex-1 px-2.5 py-1.5 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button @click="quizBuilderForm.questions.splice(qi, 1)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                </div>

                <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2 pl-6">
                    <input type="radio" :name="`builder_q_${qi}`" @change="setCorrect(qi, oi)"
                        :checked="opt.is_correct" class="accent-indigo-600" title="Mark as correct" />
                    <input v-model="opt.label" type="text" :placeholder="`Option ${oi + 1}`"
                        class="flex-1 px-2.5 py-1 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button v-if="q.options.length > 2" @click="q.options.splice(oi, 1)" class="text-red-400 text-xs">✕</button>
                </div>
                <button @click="q.options.push({ label: '', is_correct: false })"
                    class="text-xs text-indigo-500 hover:text-indigo-700 pl-6 font-medium">+ Add Option</button>
            </div>

            <button @click="addQuestion"
                class="w-full py-2 border border-dashed border-indigo-300 text-sm text-indigo-500 hover:text-indigo-700 rounded-xl">
                + Add Question
            </button>

            <div class="flex gap-2 justify-end">
                <button @click="showQuizBuilder = false; resetQuizBuilder()" class="px-4 py-2 text-sm text-gray-500">Cancel</button>
                <button @click="$emit('save-quiz')" :disabled="quizBuilderForm.processing"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 disabled:opacity-50">
                    {{ quizBuilderForm.processing ? 'Saving...' : 'Save Quiz' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    lesson:             { type: Object, default: null },
    isOwner:            { type: Boolean, default: false },
    quizAnswers:        { type: Object, required: true },
    quizResult:         { type: Object, default: null },
    quizFormProcessing: { type: Boolean, default: false },
    currentAttempt:     { type: Object, default: null },
    quizBuilderForm:    { type: Object, required: true },
});

defineEmits(['submit-quiz', 'retake-quiz', 'delete-quiz', 'save-quiz']);

const showQuizBuilder = ref(false);

const allAnswered = computed(() => {
    const quiz = props.lesson?.quiz;
    if (!quiz) return false;
    return quiz.questions.every((q) => props.quizAnswers[q.id] != null);
});

function addQuestion() {
    props.quizBuilderForm.questions.push({
        question: '',
        type:     'multiple_choice',
        options:  [
            { label: '', is_correct: true },
            { label: '', is_correct: false },
        ],
    });
}

function setCorrect(qi, oi) {
    props.quizBuilderForm.questions[qi].options.forEach((o, i) => {
        o.is_correct = i === oi;
    });
}

function resetQuizBuilder() {
    props.quizBuilderForm.reset();
    props.quizBuilderForm.questions = [];
}

defineExpose({ showQuizBuilder, resetQuizBuilder });
</script>
