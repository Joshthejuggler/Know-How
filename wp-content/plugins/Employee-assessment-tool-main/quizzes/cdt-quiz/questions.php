<?php
if (!defined('ABSPATH'))
    exit;

// Category slugs => display names
$cdt_categories = [
    'ambiguity-tolerance' => 'Ambiguity Tolerance',
    'value-conflict-navigation' => 'Value Conflict Navigation',
    'self-confrontation-capacity' => 'Self-Confrontation Capacity',
    'discomfort-regulation' => 'Discomfort Regulation',
    'conflict-resolution-tolerance' => 'Conflict Resolution Tolerance',
    'si-rumination' => 'Decision Dynamics (R)',
    'si-avoidance' => 'Decision Dynamics (A)',
    'si-emotional-flood' => 'Decision Dynamics (F)',
];

// Since questions are the same for all age groups, we define them once.
// Each question is an array with 'text' and a 'reverse' scoring flag.
$cdt_questions_base = [
    'ambiguity-tolerance' => [
        ['text' => 'I feel anxious when I don’t know which side is \'right\' in a disagreement.', 'reverse' => true],
        ['text' => 'I can sit with uncertainty without rushing to resolve it.', 'reverse' => false],
        ['text' => 'I enjoy hearing perspectives that contradict my own.', 'reverse' => false],
        ['text' => 'I like puzzles or problems with more than one possible answer.', 'reverse' => false],
        ['text' => 'I get frustrated when instructions are vague.', 'reverse' => true],
        ['text' => 'I can make progress on something even if the outcome is unclear.', 'reverse' => false],
        ['text' => 'When I don’t know all the facts, I still try to stay calm.', 'reverse' => false],
        ['text' => 'I’m okay not knowing what others think of me.', 'reverse' => false],
        ['text' => 'Uncertainty makes me curious, not stressed.', 'reverse' => false],
        ['text' => 'I often delay decisions if I feel more learning is needed.', 'reverse' => false],
    ],
    'value-conflict-navigation' => [
        ['text' => 'I reflect when someone points out hypocrisy in my beliefs.', 'reverse' => false],
        ['text' => 'I feel attacked when someone challenges my core values.', 'reverse' => true],
        ['text' => 'I’ve changed a deeply held belief after hearing a convincing argument.', 'reverse' => false],
        ['text' => 'I can understand people who think very differently from me.', 'reverse' => false],
        ['text' => 'I try to avoid people who think differently from me.', 'reverse' => true],
        ['text' => 'I appreciate when others question my assumptions.', 'reverse' => false],
        ['text' => 'I’m willing to explore both sides of a heated topic.', 'reverse' => false],
        ['text' => 'I’ve changed my opinion on something important after a conversation.', 'reverse' => false],
        ['text' => 'I can explain beliefs I don’t agree with, fairly.', 'reverse' => false],
        ['text' => 'I’m not easily offended when someone critiques my views.', 'reverse' => false],
    ],
    'self-confrontation-capacity' => [
        ['text' => 'I find it hard to admit when I’m being inconsistent.', 'reverse' => true],
        ['text' => 'I journal or reflect when I feel conflicted about a decision.', 'reverse' => false],
        ['text' => 'I’m comfortable realizing that I’ve outgrown some past beliefs.', 'reverse' => false],
        ['text' => 'I’ve caught myself doing something I said I’d never do—and learned from it.', 'reverse' => false],
        ['text' => 'I avoid thinking about situations where I messed up.', 'reverse' => true],
        ['text' => 'I ask others for honest feedback, even if it’s hard to hear.', 'reverse' => false],
        ['text' => 'I can say \'I was wrong\' without too much discomfort.', 'reverse' => false],
        ['text' => 'I think about how my behavior matches my values.', 'reverse' => false],
        ['text' => 'I’m open to realizing I might have been wrong about something big.', 'reverse' => false],
        ['text' => 'I reflect on what my reactions say about me.', 'reverse' => false],
    ],
    'discomfort-regulation' => [
        ['text' => 'I avoid tough conversations because they make me feel bad.', 'reverse' => true],
        ['text' => 'I can stay grounded even when I feel conflicted inside.', 'reverse' => false],
        ['text' => 'I try to resolve tension quickly, even if it means ignoring part of the truth.', 'reverse' => true],
        ['text' => 'I stay calm when people strongly disagree with me.', 'reverse' => false],
        ['text' => 'I continue important conversations even when they feel awkward.', 'reverse' => false],
        ['text' => 'I tend to shut down when things get emotionally intense.', 'reverse' => true],
        ['text' => 'I’ve been told I’m steady under pressure.', 'reverse' => false],
        ['text' => 'I work through discomfort instead of avoiding it.', 'reverse' => false],
        ['text' => 'I can disagree without needing to win or be right.', 'reverse' => false],
        ['text' => 'I don’t panic when people expect different things from me.', 'reverse' => false],
    ],
    'conflict-resolution-tolerance' => [
        ['text' => 'I see inner tension as a sign that I’m learning.', 'reverse' => false],
        ['text' => 'I seek out ideas that challenge my assumptions.', 'reverse' => false],
        ['text' => 'I view cognitive dissonance as a sign to pay attention—not shut down.', 'reverse' => false],
        ['text' => 'I grow the most when things feel difficult at first.', 'reverse' => false],
        ['text' => 'I like learning things that make me rethink old habits.', 'reverse' => false],
        ['text' => 'I believe discomfort is part of becoming wiser.', 'reverse' => false],
        ['text' => 'I’m drawn to experiences that stretch me.', 'reverse' => false],
        ['text' => 'I trust the process of learning, even when it feels messy.', 'reverse' => false],
        ['text' => 'I prefer honest feedback over praise.', 'reverse' => false],
        ['text' => 'I believe real growth takes time and discomfort.', 'reverse' => false],
    ],
    'si-rumination' => [
        ['text' => 'When things don’t make sense yet, I keep turning the problem over in my mind.', 'reverse' => false],
        ['text' => 'I often try to mentally \'solve\' confusion before I move forward.', 'reverse' => false],
        ['text' => 'Even after making a decision, I sometimes revisit it in my mind repeatedly.', 'reverse' => false],
    ],
    'si-avoidance' => [
        ['text' => 'When I face two good options, I sometimes freeze and struggle to choose.', 'reverse' => false],
        ['text' => 'When I’m unsure what’s right, I tend to delay taking action.', 'reverse' => false],
        ['text' => 'When things feel uncertain, I prefer to step back rather than push ahead.', 'reverse' => false],
        ['text' => 'If something feels ambiguous, I may wait for clarity instead of acting right away.', 'reverse' => false],
    ],
    'si-emotional-flood' => [
        ['text' => 'Unexpected changes can hit me emotionally before I can think them through.', 'reverse' => false],
        ['text' => 'Contradictions or mixed signals can feel emotionally intense for me.', 'reverse' => false],
        ['text' => 'Confusing situations can create a rush of feelings that make clarity hard.', 'reverse' => false],
    ],
];

// Assign the same set of questions to each age group for this quiz.
$cdt_questions = [
    'teen' => $cdt_questions_base,
    'graduate' => $cdt_questions_base,
    'adult' => $cdt_questions_base,
];