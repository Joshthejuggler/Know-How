<?php
if (!defined('ABSPATH'))
  exit;

// Category slugs => display names
$mi_categories = [
  'logical-mathematical' => 'Logical–Mathematical Intelligence',
  'linguistic' => 'Linguistic Intelligence',
  'spatial' => 'Spatial Intelligence',
  'bodily-kinesthetic' => 'Bodily–Kinesthetic Intelligence',
  'musical' => 'Musical Intelligence',
  'interpersonal' => 'Interpersonal Intelligence',
  'intrapersonal' => 'Intrapersonal Intelligence',
  'naturalistic' => 'Naturalistic Intelligence',
  'si-rumination' => 'Processing Style (R)',
  'si-avoidance' => 'Processing Style (A)',
  'si-emotional-flood' => 'Processing Style (F)',
];

// Part 1 questions: age_group => category => [items...]
$mi_questions = [
  'adult' => [
    'logical-mathematical' => ['I enjoy solving puzzles or brain teasers.', 'I see patterns in numbers or information easily.', 'I often use logical reasoning to make decisions.', 'I like working with numbers and formulas.', 'I enjoy strategy games like chess or Sudoku.', 'I can estimate quantities or calculate quickly in my head.', 'I like to analyze problems and find structured solutions.', 'I’m curious about how things work mechanically or logically.',],
    'linguistic' => ['I enjoy reading books, articles, or stories.', 'I often find the right words to express myself clearly.', 'I enjoy writing, whether it’s journaling, essays, or creative writing.', 'I like playing with language—puns, rhymes, or word games.', 'I learn best through reading or listening to spoken words.', 'I enjoy learning new vocabulary and languages.', 'I often notice grammatical or spelling mistakes.', 'I express myself more confidently in writing than in visuals or numbers.',],
    'spatial' => ['I can easily visualize objects in my mind.', 'I enjoy drawing, designing, or creating visual art.', 'I often notice patterns, colors, or design details others miss.', 'I can navigate well, even in unfamiliar places.', 'I enjoy working with maps, charts, or blueprints.', 'I like puzzles that involve shapes and space, like tangrams or Tetris.', 'I imagine things in 3D before building or assembling them.', 'I prefer learning through visual tools like diagrams or videos.',],
    'bodily-kinesthetic' => ['I learn best by doing, rather than just watching or listening.', 'I enjoy physical activities like sports, dance, or building things.', 'I’m good at using my hands to make or fix things.', 'I feel comfortable expressing myself physically.', 'I have good balance, coordination, and body awareness.', 'I often use gestures while speaking.', 'I get restless if I sit too long without moving.', 'I enjoy learning new physical skills or techniques.',],
    'musical' => ['I often have songs or melodies running through my head.', 'I can recognize off-key notes or rhythms easily.', 'I enjoy listening to a variety of music genres.', 'I remember information better when it’s set to music or rhythm.', 'I’m drawn to the emotional tone of music.', 'I can play an instrument or want to learn one.', 'I’m sensitive to sound, pitch, and rhythm.', 'I find patterns and meaning in music naturally.',],
    'interpersonal' => ['I understand how others are feeling, even when they don’t say anything.', 'I enjoy group activities and team collaboration.', 'I’m good at resolving conflicts between people.', 'I often find myself giving advice or support to others.', 'I can easily see things from another person’s perspective.', 'I’m energized by social interactions.', 'I communicate well in a variety of social settings.', 'I’m often the one who organizes or brings people together.',],
    'intrapersonal' => ['I spend time reflecting on my thoughts and feelings.', 'I’m aware of my personal strengths and limitations.', 'I value solitude and alone time.', 'I set meaningful personal goals and track my progress.', 'I have a strong sense of my personal values and beliefs.', 'I understand what motivates me internally.', 'I enjoy journaling or inner self-exploration-activities.', 'I often analyze why I react a certain way in different situations.',],
    'naturalistic' => ['I enjoy spending time in nature.', 'I’m good at identifying plants, animals, or natural patterns.', 'I care deeply about environmental issues.', 'I feel rejuvenated after being outside.', 'I notice changes in weather, seasons, or natural surroundings.', 'I enjoy organizing or categorizing natural things.', 'I often incorporate nature into my creative work.', 'I feel connected to the earth or natural world in a meaningful way.',],
    'si-rumination' => [
      'When I face a difficult decision, I need extra time to figure out the \'right\' move.',
      'I replay conversations or choices in my head to see what I could have done differently.',
      'I sometimes feel mentally \'stuck\' when two of my thoughts or values clash.',
      'I often need to think through many possibilities before I can focus.',
    ],
    'si-avoidance' => [
      'I often feel a strong urge to step away from a problem when it becomes confusing.',
      'If a task feels uncertain, I tend to put it off until I feel more ready.',
      'I prefer learning environments where I can step away and come back when it feels right.',
    ],
    'si-emotional-flood' => [
      'When trying something new, my emotions can shift suddenly depending on how it goes.',
      'When too much is happening at once, I feel overwhelmed before I can think clearly.',
      'Strong feelings can suddenly disrupt my ability to stay engaged with a task.',
    ],
  ],
  'teen' => [
    'logical-mathematical' => ['I like figuring out brain teasers or logic puzzles.', 'I can spot patterns in information or data pretty easily.', 'I use logical reasoning to think through my choices.', 'I’m comfortable working with numbers in subjects like math or science.', 'I enjoy strategy games, whether they’re video games or board games.', 'I can guess how many things are in a jar or do quick math in my head.', 'I prefer to break down big school projects into smaller, organized steps.', 'I’m curious about how things like my phone or a car engine work.',],
    'linguistic' => ['I enjoy reading, whether it’s for school or for fun.', 'I can usually find the right words to say what I mean.', 'I like to write, whether it’s stories, for a blog, or just in a journal.', 'I enjoy wordplay, like puns, rhymes, or word games.', 'I learn best by reading the textbook or listening to the teacher.', 'I like learning new words or am interested in other languages.', 'I’m the person who usually spots typos or grammar mistakes in a text.', 'I’m better at explaining myself in writing than by drawing or using numbers.',],
    'spatial' => ['I can easily picture things in my head.', 'I enjoy drawing, doodling, or using apps to create visual art.', 'I’m good at spotting details in a picture or a scene that others might miss.', 'I have a good sense of direction, even in new places.', 'I find it easy to read maps, charts, or diagrams for school.', 'I like games that involve shapes and spaces, like Tetris or building in Minecraft.', 'I can imagine how something will look in 3D before I build or assemble it.', 'I learn a new concept faster if I see a diagram or watch a video about it.',],
    'bodily-kinesthetic' => ['I learn things better by actually doing them, not just watching a tutorial.', 'I like being physically active, like playing sports, dancing, or working out.', 'I’m good with my hands, whether it’s for a hobby, a craft, or fixing something.', 'I’m comfortable moving around and expressing myself with my body.', 'I have good coordination and balance.', 'I talk with my hands a lot.', 'I find it hard to sit still for a long time.', 'I enjoy learning new physical skills, like a new sport or a dance move.',],
    'musical' => ['I often have a song stuck in my head.', 'I can tell when someone sings off-key or a beat is off.', 'I like listening to many different kinds of music.', 'I find it easier to remember things if they are set to a beat or a jingle.', 'Music can easily affect my mood.', 'I play a musical instrument or would like to learn how.', 'I’m sensitive to background noises, sounds, and rhythms.', 'I can hear different instruments or layers in a song.',],
    'interpersonal' => ['I can usually tell how my friends are feeling, even if they don’t say it.', 'I like doing group projects more than working alone.', 'I’m good at helping my friends sort out arguments.', 'My friends often come to me for advice or just to talk.', 'I can easily understand where someone else is coming from.', 'Being around my friends gives me energy.', 'I can talk to different types of people pretty easily.', 'I’m often the one who plans hangouts or gets the group together.',],
    'intrapersonal' => ['I often think about my own thoughts and feelings.', 'I have a good sense of my own strengths and weaknesses.', 'I need my alone time to recharge.', 'I like to set goals for myself and work towards them.', 'I know what’s really important to me and what my values are.', 'I understand what truly motivates me to do things.', 'I find journaling or just thinking by myself helpful.', 'I often think about why I reacted a certain way to something.',],
    'naturalistic' => ['I like spending time outside in nature.', 'I’m good at recognizing different types of plants, trees, or animals.', 'I’m concerned about things like pollution and climate change.', 'I feel more relaxed and focused after spending time outdoors.', 'I notice changes in the weather or seasons before others do.', 'I enjoy collecting or organizing things from nature, like rocks or leaves.', 'Nature often inspires my creative work or ideas.', 'I feel a real connection to the planet or the outdoors.',],
    'si-rumination' => [
      'When I face a difficult decision, I need extra time to figure out the \'right\' move.',
      'I replay conversations or choices in my head to see what I could have done differently.',
      'I sometimes feel mentally \'stuck\' when two of my thoughts or values clash.',
      'I often need to think through many possibilities before I can focus.',
    ],
    'si-avoidance' => [
      'I often feel a strong urge to step away from a problem when it becomes confusing.',
      'If a task feels uncertain, I tend to put it off until I feel more ready.',
      'I prefer learning environments where I can step away and come back when it feels right.',
    ],
    'si-emotional-flood' => [
      'When trying something new, my emotions can shift suddenly depending on how it goes.',
      'When too much is happening at once, I feel overwhelmed before I can think clearly.',
      'Strong feelings can suddenly disrupt my ability to stay engaged with a task.',
    ],
  ],
  'graduate' => [
    'logical-mathematical' => ['I enjoy tackling complex problems or strategic puzzles.', 'I can quickly identify patterns and inconsistencies in data or arguments.', 'I rely on logical reasoning to inform my major decisions.', 'I’m comfortable working with spreadsheets, analytics, or financial models.', 'I excel at strategy games that require long-term planning.', 'I can make quick mental calculations or estimations with confidence.', 'I prefer to approach projects with a clear, structured methodology.', 'I have a deep curiosity about the underlying principles of systems or technologies.',],
    'linguistic' => ['I enjoy reading a wide range of materials, from industry articles to novels.', 'I can articulate complex ideas clearly and persuasively.', 'I enjoy writing, be it reports, presentations, or personal reflections.', 'I have an appreciation for clever language, like a good pun or a well-turned phrase.', 'I learn best by reading documentation or listening to lectures and podcasts.', 'I actively seek to expand my vocabulary or learn new languages.', 'I have a keen eye for errors in written communication.', 'I find writing to be my most effective and confident mode of expression.',],
    'spatial' => ['I can easily visualize concepts and systems in my mind.', 'I have a strong sense of design, whether in presentations, spaces, or products.', 'I often notice aesthetic details or design flaws that others overlook.', 'I can orient myself and navigate effectively in new cities or buildings.', 'I am adept at interpreting maps, blueprints, or data visualizations.', 'I enjoy challenges that involve spatial reasoning, like design problems or puzzles.', 'I mentally model things in 3D before creating or building them.', 'I prefer learning from visual aids like charts, diagrams, or video tutorials.',],
    'bodily-kinesthetic' => ['I learn new skills best through hands-on practice, not just theory.', 'I prioritize physical activity, such as sports, fitness, or hands-on projects.', 'I am skilled at working with my hands, whether for repairs, crafts, or technical tasks.', 'I use physical presence and body language to communicate effectively.', 'I have a strong sense of physical balance, coordination, and proprioception.', 'I often use gestures to emphasize points when I speak.', 'I feel the need to move or take physical breaks to stay focused during long periods of sitting.', 'I am motivated by the process of mastering new physical skills and techniques.',],
    'musical' => ['I often think in terms of melodies or rhythmic patterns.', 'I have a discerning ear for musical quality, pitch, and rhythm.', 'I appreciate a diverse range of musical styles and structures.', 'I use music or rhythm to help me focus, learn, or memorize information.', 'I am strongly affected by the emotional undertones and composition of music.', 'I have experience playing an instrument or a strong desire to learn.', 'I am highly sensitive to the soundscape around me, including pitch and rhythm.', 'I can deconstruct music and appreciate its underlying patterns and theory.',],
    'interpersonal' => ['I am adept at reading non-verbal cues and understanding others’ emotional states.', 'I thrive in collaborative team environments and partnerships.', 'I am skilled at mediating disagreements and facilitating consensus.', 'People often seek me out for professional advice or personal guidance.', 'I can intuitively grasp different perspectives in a negotiation or discussion.', 'I draw energy from professional networking and social interaction.', 'I can adapt my communication style to a wide variety of audiences.', 'I naturally take on roles that involve organizing, leading, or connecting people.',],
    'intrapersonal' => ['I regularly set aside time for self-reflection on my career and personal life.', 'I have a clear and realistic understanding of my professional strengths and limitations.', 'I recognize the importance of time alone to process information and recharge.', 'I establish meaningful long-term goals and regularly review my progress.', 'My personal values and ethics are a primary driver in my decision-making.', 'I have a strong grasp of my own intrinsic motivations.', 'I engage in practices like journaling or mindfulness to foster self-awareness.', 'I often analyze my own reactions to understand my professional and personal triggers.',],
    'naturalistic' => ['I make a point to spend time in natural settings to de-stress and refocus.', 'I can easily identify and classify elements of the natural world.', 'I am actively concerned with environmental sustainability and conservation issues.', 'I feel a measurable sense of well-being after being in nature.', 'I am highly attuned to subtle changes in my natural surroundings.', 'I enjoy systems thinking and categorizing complex information, similar to natural ecosystems.', 'I often draw inspiration from natural forms, systems, or patterns in my work.', 'I feel a profound sense of connection to the broader ecosystem.',],
    'si-rumination' => [
      'When I face a difficult decision, I need extra time to figure out the \'right\' move.',
      'I replay conversations or choices in my head to see what I could have done differently.',
      'I sometimes feel mentally \'stuck\' when two of my thoughts or values clash.',
      'I often need to think through many possibilities before I can focus.',
    ],
    'si-avoidance' => [
      'I often feel a strong urge to step away from a problem when it becomes confusing.',
      'If a task feels uncertain, I tend to put it off until I feel more ready.',
      'I prefer learning environments where I can step away and come back when it feels right.',
    ],
    'si-emotional-flood' => [
      'When trying something new, my emotions can shift suddenly depending on how it goes.',
      'When too much is happening at once, I feel overwhelmed before I can think clearly.',
      'Strong feelings can suddenly disrupt my ability to stay engaged with a task.',
    ],
  ],
];

// Part 2 questions: age_group => category => sub-skill => [items...]
$mi_part_two_questions = [
  'adult' => [
    'logical-mathematical' => [
      'Analytical Thinking' => ['I enjoy breaking down complex problems into smaller, more manageable parts.', 'I often spot patterns, inconsistencies, or logical flaws that others miss.', 'I feel energized when solving puzzles, riddles, or logic-based challenges.',],
      'Quantitative Reasoning' => ['I feel comfortable working with numbers, percentages, and formulas.', 'I often think in terms of metrics or data when analyzing situations.', 'I enjoy solving real-world problems using math (e.g., budgeting, measurements, probabilities).',],
      'Strategic Problem-Solving' => ['I like creating step-by-step plans or systems to achieve a goal.', 'I enjoy thinking several steps ahead when making decisions.', 'I often ask “What’s the most efficient way to solve this?” before diving in.',],
      'Coding & Algorithmic Logic' => ['I enjoy activities where I need to follow or design step-by-step instructions or rules.', 'I can often see how to structure a process, workflow, or formula for better results.', 'I am interested in how systems (like software or machines) work at a logical level.',],
      'Experimental Design' => ['I enjoy testing hypotheses or trying different variables to see what works best.', 'I often reflect on outcomes and ask what could be improved in the process.', 'I like experimenting in a structured way, whether in cooking, tech, or everyday life.',],
    ],
    'linguistic' => [
      'Reading & Comprehension' => ['I easily absorb information when reading, even from dense or complex texts.', 'I often find myself analyzing what an author really means beneath the surface of their words.', 'I enjoy reading books, articles, or essays in my free time more than watching videos or listening to podcasts.',],
      'Creative Writing' => ['I often get ideas for stories, essays, or posts that I feel compelled to write down.', 'I enjoy crafting original language to express ideas, feelings, or arguments.', 'Writing helps me organize my thoughts more clearly than speaking does.',],
      'Public Speaking' => ['I feel confident and comfortable expressing ideas clearly when speaking to a group.', 'People often tell me I explain things in a way that is easy to understand.', 'I enjoy opportunities to speak, present, or share ideas verbally with others.',],
      'Editing & Precision' => ['I naturally notice spelling, grammar, or sentence structure errors when reading.', 'I enjoy refining or rewriting text to make it clearer or more effective.', 'I often help others improve their writing or communication for clarity and impact.',],
      'Language Acquisition' => ['I pick up new words, phrases, or languages quickly — especially when immersed or exposed regularly.', 'I notice patterns in how different languages are structured and how they relate to each other.', 'I enjoy learning new languages or dialects, even if I’m not fluent yet.',],
    ],
    'spatial' => [
      'Visual Imagination' => ['I can easily picture things in my mind, even when I’ve only read or heard about them.', 'I enjoy imagining or visualizing how something will look before it’s made or built.', 'I often think in images rather than words when planning or solving problems.',],
      'Map & Layout Reading' => ['I find it easy to understand maps, blueprints, or diagrams.', 'I can quickly figure out how to navigate new spaces or cities.', 'I enjoy activities that involve reading or creating layouts (e.g., event planning, organizing spaces).',],
      'Design & Aesthetics' => ['I have a strong sense of what looks visually balanced or pleasing.', 'I enjoy arranging objects or designing spaces to look their best.', 'I often notice small design details in products, interiors, or artwork that others overlook.',],
      'Mechanical Visualization' => ['I can easily understand how things fit together or move, even without instructions.', 'I enjoy figuring out how mechanical systems or physical objects work.', 'I often find myself mentally taking apart and reassembling objects in my head.',],
      'Artistic Representation' => ['I enjoy drawing, sketching, or modeling ideas to communicate them clearly.', 'I use visual tools (like mind maps or sketches) to organize my thinking.', 'I feel most expressive when creating or modifying something visual.',],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => ['I pick up new physical activities or sports quickly.', 'I feel confident in my balance, timing, and overall coordination.', 'I often find myself moving or fidgeting rather than sitting still for long periods.',],
      'Hands-On Building' => ['I enjoy assembling, repairing, or crafting things with my hands.', 'I feel satisfied when I create or fix something tangible.', 'I learn best when I can touch or physically work with materials.',],
      'Expressive Movement' => ['I enjoy expressing myself through movement (dance, gestures, acting, etc.).', 'I naturally use my body language to emphasize or communicate ideas.', 'I feel energized and confident when performing or moving in front of others.',],
      'Athletic Performance' => ['I enjoy pushing my body’s physical limits and tracking improvements.', 'I feel motivated by physical challenges and competitive activities.', 'I often think about technique or form when doing physical activities.',],
      'Somatic Awareness' => ['I am highly aware of how my body feels and can quickly notice small changes or discomforts.', 'I adjust my posture or movements instinctively to stay comfortable or efficient.', 'I feel a strong connection between my mind and body, especially during activities like stretching, yoga, or breathing exercises.',],
    ],
    'musical' => [
      'Musical Perception' => ['I can easily recognize when a note or chord is off-key or out of tune.', 'I notice subtle differences in rhythm, melody, or sound textures when listening to music.', 'I find myself mentally analyzing music instead of just hearing it passively.',],
      'Instrumental Skill' => ['I enjoy learning and playing musical instruments.', 'I feel comfortable coordinating my hands, fingers, or breath when playing an instrument.', 'I often look for ways to improve my technique or learn new pieces.',],
      'Vocal Ability' => ['I enjoy singing, whether alone or with others.', 'I can control my voice to match pitch, volume, or emotional expression.', 'People often tell me I have a pleasant or strong singing voice.',],
      'Composition & Arrangement' => ['I enjoy creating my own music, melodies, or arrangements.', 'I often think about how different sounds or instruments can be layered together.', 'I like experimenting with new sounds or musical ideas to create something original.',],
      'Sound Engineering' => ['I enjoy adjusting audio settings or mixing sounds to achieve a specific effect.', 'I pay close attention to the technical quality of recordings and live performances.', 'I am interested in how technology can be used to shape or enhance sound.',],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => ['I can usually tell how someone is feeling, even if they don’t say it directly.', 'I often notice subtle changes in people’s tone of voice or body language.', 'Others come to me for support or to talk about their emotions.',],
      'Team Facilitation' => ['I enjoy bringing people together to work toward a shared goal.', 'I often take on a coordinating or organizing role in group settings.', 'I feel fulfilled when I help a group function smoothly and effectively.',],
      'Conflict Resolution' => ['I am comfortable stepping in to mediate or resolve disagreements.', 'I can usually see different sides of an argument and find common ground.', 'I feel motivated to help others understand each other and move forward.',],
      'Persuasion & Influence' => ['I enjoy convincing others of new ideas or inspiring them to take action.', 'I feel confident presenting arguments in a way that resonates with different people.', 'I often motivate friends, family, or colleagues to try something new.',],
      'Mentoring & Support' => ['I enjoy guiding others to develop new skills or gain confidence.', 'I feel satisfied when I see someone grow because of my encouragement or feedback.', 'People often seek my advice or mentorship on personal or professional challenges.',],
    ],
    'intrapersonal' => [
      'Self-Reflection' => ['I regularly take time to think deeply about my thoughts, feelings, or behaviors.', 'I find value in journaling, meditation, or other reflective practices.', 'I often analyze past experiences to better understand myself.',],
      'Goal Orientation' => ['I set personal or professional goals for myself and actively track my progress.', 'I feel motivated when I have a clear plan or objective to work toward.', 'I often break big goals into smaller steps to make them achievable.',],
      'Values Clarity' => ['I have a clear understanding of my core values and what matters most to me.', 'I use my values as a guide when making important life decisions.', 'I feel confident expressing my beliefs and standing by them, even under pressure.',],
      'Emotional Regulation' => ['I can usually manage stress and bounce back from setbacks effectively.', 'I recognize my emotional triggers and work to handle them constructively.', 'I actively practice strategies to stay calm and centered in challenging situations.',],
      'Decision-Making Autonomy' => ['I feel comfortable making decisions on my own without needing constant reassurance.', 'I trust my intuition or reasoning when facing tough choices.', 'I enjoy having control over my own path and being self-directed.',],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => ['I often notice small changes or patterns in plants, animals, or weather.', 'I enjoy observing natural systems and figuring out how they work together.', 'I feel curious and energized when I can explore or study nature closely.',],
      'Animal Interaction' => ['I feel comfortable and confident caring for or working with animals.', 'I easily pick up on animal behavior and understand their needs or moods.', 'I enjoy spending time with animals more than I do with many human activities.',],
      'Environmental Stewardship' => ['I feel strongly about protecting the environment and natural resources.', 'I actively look for ways to reduce my ecological impact (e.g., recycling, reducing waste).', 'I often encourage others to take actions that benefit the environment.',],
      'Plant & Ecosystem Knowledge' => ['I enjoy learning about different plants, trees, or ecosystems.', 'I feel confident growing or caring for plants.', 'I find joy in activities like gardening, foraging, or studying habitats.',],
      'Outdoor Navigation' => ['I feel comfortable navigating in natural settings without heavy reliance on technology.', 'I enjoy activities like hiking, camping, or exploring new outdoor areas.', 'I feel a strong sense of orientation and can usually find my way easily outdoors.',],
    ],
  ],
  'teen' => [
    'logical-mathematical' => [
      'Analytical Thinking' => ['I like to break big school assignments or problems into smaller, easier steps.', 'I’m good at spotting when something doesn’t make sense in an argument or a set of instructions.', 'I enjoy challenges like escape rooms, logic puzzles, or Sudoku.',],
      'Quantitative Reasoning' => ['I feel comfortable working with numbers in my math and science classes.', 'I tend to think about things in terms of numbers, like stats in a video game or my own grades.', 'I like using math to solve real-life problems, like figuring out a discount or splitting a bill.',],
      'Strategic Problem-Solving' => ['I like to make a plan before starting a big project.', 'I’m good at thinking a few moves ahead in a game.', 'I often try to find the most efficient way to get my homework or chores done.',],
      'Coding & Algorithmic Logic' => ['I enjoy activities that have clear rules and steps, like coding or following a complex recipe.', 'I can easily see how to organize a process or a set of instructions to make it better.', 'I’m interested in how things like apps or video games are programmed.',],
      'Experimental Design' => ['I like to try different approaches to see what works best when studying or playing a game.', 'After a test or a project, I think about what I could have done better.', 'I enjoy experimenting in a structured way, like in a science lab or trying a new workout routine.',],
    ],
    'linguistic' => [
      'Reading & Comprehension' => ['I can understand my reading assignments, even when the topic is difficult.', 'I often think about the hidden meanings or themes in a book or movie.', 'I’d rather read a book or an interesting article than watch a video about the topic.',],
      'Creative Writing' => ['I often get ideas for stories or poems that I want to write down.', 'I enjoy using my words to express my ideas and feelings.', 'Writing things down helps me make sense of my thoughts.',],
      'Public Speaking' => ['I feel confident giving a presentation in front of the class.', 'My friends tell me I’m good at explaining things clearly.', 'I like opportunities to share my ideas out loud with a group.',],
      'Editing & Precision' => ['I’m the friend who always catches typos in text messages.', 'I like to revise my essays and assignments to make them as clear as possible.', 'I often help my friends with their writing to make it better.',],
      'Language Acquisition' => ['I pick up new slang or phrases pretty quickly.', 'I can see how different languages have similarities or patterns.', 'I enjoy my foreign language classes at school.',],
    ],
    'spatial' => [
      'Visual Imagination' => ['I can easily picture scenes from a book I’m reading.', 'I enjoy imagining how I could redecorate my room or design something new.', 'I often think in pictures instead of just words.',],
      'Map & Layout Reading' => ['I’m good at reading maps, whether for a game or for directions.', 'I can find my way around a new school or neighborhood pretty easily.', 'I enjoy activities that involve planning a layout, like for a school project or a video game base.',],
      'Design & Aesthetics' => ['I have a good eye for what looks good together, like in an outfit or a drawing.', 'I enjoy making my school projects or my room look organized and visually appealing.', 'I notice small details in art, design, or even in movies that others might miss.',],
      'Mechanical Visualization' => ['I can usually figure out how to assemble things without looking at the instructions.', 'I like to understand how things work, like taking apart a pen just to see the parts.', 'I can picture how an object’s moving parts work together in my head.',],
      'Artistic Representation' => ['I like to communicate my ideas by drawing or sketching them out.', 'I use tools like mind maps or visual notes to help me study.', 'I feel like I can express myself best when I’m creating something visual.',],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => ['I learn new sports or physical activities faster than most of my friends.', 'I feel confident in my physical abilities, like my balance and timing.', 'I’d rather be moving around than sitting at a desk.',],
      'Hands-On Building' => ['I enjoy hands-on classes like woodshop, art, or science labs.', 'I get a lot of satisfaction from building or fixing something myself.', 'I learn best when I can physically interact with what I’m studying.',],
      'Expressive Movement' => ['I enjoy activities like dance, drama, or sports where I can express myself with my body.', 'I naturally use gestures and body language to help get my point across.', 'I feel confident and energized when I’m physically performing or active.',],
      'Athletic Performance' => ['I like to challenge myself physically and see how much I can improve.', 'I’m motivated by competition and physical challenges.', 'I pay attention to my technique and form when I play a sport or exercise.',],
      'Somatic Awareness' => ['I’m very in tune with what my body is telling me, like if I’m tired or need to stretch.', 'I can make small adjustments to how I’m sitting or standing to be more comfortable.', 'I feel a strong connection between my thoughts and my physical feelings.',],
    ],
    'musical' => [
      'Musical Perception' => ['I can easily tell if a singer is off-key or an instrument is out of tune.', 'I notice the little details in a song, like the bass line or a background harmony.', 'I find myself thinking about the structure of a song while I listen to it.',],
      'Instrumental Skill' => ['I enjoy practicing and getting better at my musical instrument.', 'I feel coordinated when I play music, whether it’s with my hands, my breath, or my feet.', 'I’m always looking for new songs to learn or ways to improve my playing.',],
      'Vocal Ability' => ['I like to sing, even if it’s just in the shower or with friends.', 'I can change my voice to match a song’s pitch or emotion.', 'People have told me I have a good singing voice.',],
      'Composition & Arrangement' => ['I like to come up with my own melodies or beats.', 'I often think about how different instruments could sound cool together in a song.', 'I enjoy using apps or software to create or mix my own music.',],
      'Sound Engineering' => ['I like to play with the sound settings on my phone or computer to make music sound better.', 'I can tell the difference between a high-quality and a low-quality audio recording.', 'I’m interested in the technology used to record and produce music.',],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => ['I can usually sense when a friend is upset, even if they’re trying to hide it.', 'I pay attention to how people say things, not just what they say.', 'My friends know they can come to me when they need to talk about their feelings.',],
      'Team Facilitation' => ['I like bringing my friends together and making sure everyone is included.', 'I often end up taking the lead in group projects to keep us organized.', 'I feel good when I can help my group of friends work together and have fun.',],
      'Conflict Resolution' => ['I’m comfortable being the mediator when my friends have a disagreement.', 'I can usually see both sides of an argument.', 'I feel motivated to help people understand each other better.',],
      'Persuasion & Influence' => ['I enjoy trying to get my friends excited about a new idea, game, or activity.', 'I’m good at explaining my point of view in a way that makes sense to others.', 'I can often convince my friends or family to try something new.',],
      'Mentoring & Support' => ['I enjoy helping younger students or my friends learn something new.', 'It feels good to see a friend succeed because of my help or encouragement.', 'People often ask me for help with their schoolwork or personal problems.',],
    ],
    'intrapersonal' => [
      'Self-Reflection' => ['I spend time thinking about who I am and what I want to be.', 'I find it helpful to write in a journal or just think quietly by myself.', 'I often try to understand why I felt a certain way after something happens.',],
      'Goal Orientation' => ['I set goals for myself, like improving my grades or learning a new skill.', 'Having a clear goal helps me stay motivated.', 'I like to break down big goals into a to-do list to make them less overwhelming.',],
      'Values Clarity' => ['I have a strong sense of what’s right and wrong for me.', 'I try to make choices that line up with what I believe in.', 'I’m not afraid to stand up for what I think is right, even if it’s not popular.',],
      'Emotional Regulation' => ['I can usually handle stress from school or friends without getting overwhelmed.', 'I know what my emotional triggers are and try to manage them.', 'I have strategies, like listening to music or going for a walk, to help me stay calm.',],
      'Decision-Making Autonomy' => ['I’m comfortable making decisions for myself without asking everyone for their opinion.', 'I trust my own judgment when I have to make a tough choice.', 'I like being in charge of my own schedule and making my own plans.',],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => ['I often notice things in nature that my friends don’t, like a certain type of bird or how the clouds are changing.', 'I like to understand how natural systems work, like a food web or the water cycle.', 'I get excited when I can explore and learn about the outdoors.',],
      'Animal Interaction' => ['I feel confident and happy when I’m taking care of my pets or other animals.', 'I’m good at sensing what an animal needs or how it’s feeling.', 'I’d rather spend time with animals than do a lot of other activities.',],
      'Environmental Stewardship' => ['I’m passionate about protecting the environment.', 'I try to do my part, like recycling or conserving water.', 'I often talk to my friends and family about why it’s important to care for the planet.',],
      'Plant & Ecosystem Knowledge' => ['I enjoy learning the names of different plants, trees, or flowers.', 'I feel confident in my ability to take care of plants.', 'I like activities like gardening or learning about different habitats in science class.',],
      'Outdoor Navigation' => ['I’m comfortable finding my way around in a park or on a hiking trail.', 'I enjoy activities like camping or hiking in new places.', 'I have a good sense of direction when I’m outside.',],
    ],
  ],
  'graduate' => [
    'logical-mathematical' => [
      'Analytical Thinking' => ['I excel at deconstructing complex work projects or personal goals into a clear action plan.', 'I have a knack for spotting logical fallacies or weaknesses in a business proposal or plan.', 'I feel engaged and motivated when faced with a complex problem that requires strategic thinking.',],
      'Quantitative Reasoning' => ['I am comfortable working with data, metrics, and spreadsheets to track performance or a budget.', 'I naturally frame problems in terms of quantifiable outcomes and data-driven insights.', 'I enjoy applying mathematical concepts to solve practical problems, like financial planning or market analysis.',],
      'Strategic Problem-Solving' => ['I am skilled at developing systems and long-range plans to achieve career or project goals.', 'I have a forward-thinking approach to decision-making, often considering the second and third-order consequences.', 'I instinctively ask "How can we optimize this?" before committing to a course of action.',],
      'Coding & Algorithmic Logic' => ['I thrive in environments that require structured, rule-based thinking, such as programming or process optimization.', 'I can effectively design and document workflows or standard operating procedures.', 'I am interested in how technology and automated systems can be leveraged to solve problems.',],
      'Experimental Design' => ['I enjoy testing different approaches in my work or personal life to find the most effective methods.', 'I make a habit of conducting "post-mortems" on projects to identify lessons learned.', 'I am comfortable with iterative processes, like developing a minimum viable product or testing a new strategy.',],
    ],
    'linguistic' => [
      'Reading & Comprehension' => ['I can quickly absorb and synthesize information from dense materials like academic papers or technical reports.', 'I am skilled at interpreting subtext and discerning the primary message in complex communications.', 'I prefer to get my information from well-written articles and books over summaries or video content.',],
      'Creative Writing' => ['I frequently get ideas for articles, presentations, or other forms of content I feel compelled to develop.', 'I enjoy the challenge of crafting precise and persuasive language to convey a specific message.', 'The act of writing is a primary tool I use for organizing and clarifying my strategic thinking.',],
      'Public Speaking' => ['I am confident and effective when presenting ideas to colleagues, clients, or stakeholders.', 'Peers and mentors have told me that I have a talent for making complex topics understandable.', 'I actively seek opportunities to share my expertise through public speaking, workshops, or presentations.',],
      'Editing & Precision' => ['I have a strong natural ability to spot errors in documents, presentations, or reports.', 'I enjoy the process of refining a piece of writing to enhance its clarity, tone, and impact.', 'I am often the person colleagues ask to review important communications before they are sent.',],
      'Language Acquisition' => ['I can quickly pick up industry-specific jargon, new terminology, or foreign languages.', 'I notice the underlying structure and patterns in different communication styles.', 'I see value in learning new languages for career development or personal enrichment.',],
    ],
    'spatial' => [
      'Visual Imagination' => ['I can mentally rehearse a presentation or visualize a complex system before building it.', 'I enjoy brainstorming and conceptualizing new products, services, or designs.', 'I often use mental images and models to solve problems.',],
      'Map & Layout Reading' => ['I can easily understand floor plans, process flows, or organizational charts.', 'I am confident in my ability to navigate new professional or urban environments.', 'I enjoy tasks that involve organizing physical or digital spaces for maximum efficiency.',],
      'Design & Aesthetics' => ['I have a strong instinct for what is visually appealing and well-designed in a product or presentation.', 'I enjoy curating my personal and professional environments to be both functional and aesthetically pleasing.', 'I often notice and appreciate fine details in design that others may not.',],
      'Mechanical Visualization' => ['I can intuitively understand how a mechanical system or piece of software works.', 'I enjoy troubleshooting and figuring out why a system or process is not working correctly.', 'I often build a mental model of how different parts of a system interact.',],
      'Artistic Representation' => ['I often use diagrams, sketches, or wireframes to communicate my ideas to others.', 'I use visual tools like mind maps or whiteboards to structure my thoughts and plans.', 'I feel that creating a visual representation is the most effective way for me to express a complex idea.',],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => ['I quickly adapt to new physical tasks that require precision and motor control.', 'I am confident in my physical poise and body awareness in professional and social settings.', 'I prefer an active role or career over one that is entirely sedentary.',],
      'Hands-On Building' => ['I enjoy work that has a tangible, physical outcome, from building a presentation deck to a physical product.', 'I get a sense of accomplishment from assembling, repairing, or improving things.', 'I learn best when I can apply theoretical knowledge in a practical, hands-on way.',],
      'Expressive Movement' => ['I am a dynamic and engaging presenter, partly due to my effective use of body language.', 'I naturally use gestures to add emphasis and clarity to my communication.', 'I feel confident and energized when I am "on my feet" and physically engaged in my work.',],
      'Athletic Performance' => ['I am driven to set and achieve physical fitness goals.', 'I am motivated by performance metrics and tangible improvements in my physical skills.', 'I consciously work on my form and technique to improve in my chosen physical activities.',],
      'Somatic Awareness' => ['I am highly attuned to my body’s signals for stress or fatigue and know when to take a break.', 'I instinctively make ergonomic adjustments to my workspace to improve comfort and productivity.', 'I believe a strong mind-body connection is essential for peak performance.',],
    ],
    'musical' => [
      'Musical Perception' => ['I can easily discern the quality of audio production in a podcast or presentation.', 'I notice the rhythm and cadence of a person’s speech and how it affects their message.', 'I often analyze the structure and composition of music rather than just listening passively.',],
      'Instrumental Skill' => ['I enjoy the discipline of practicing and mastering a skill, musical or otherwise.', 'I feel a sense of flow and coordination when deeply engaged in a complex, hands-on task.', 'I am always looking for ways to refine my technique and improve my performance in my chosen skills.',],
      'Vocal Ability' => ['I am confident in my ability to control my vocal tone and pitch to be a more effective communicator.', 'I enjoy using my voice in different ways, from presenting to negotiating to singing.', 'Colleagues have commented that I have a clear and pleasant speaking voice.',],
      'Composition & Arrangement' => ['I enjoy creating new structures, systems, or ideas from existing components.', 'I often think about how different elements of a project can be combined for a better outcome.', 'I like to experiment with different combinations of ideas to create something original and innovative.',],
      'Sound Engineering' => ['I enjoy the technical process of refining a project to achieve a high-quality result.', 'I have a high standard for the quality of my work, paying close attention to small details.', 'I am interested in how technology can be used to enhance communication and creative expression.',],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => ['I am skilled at perceiving the underlying mood or dynamics in a meeting or group setting.', 'I am effective in client-facing or stakeholder management roles due to my ability to read people.', 'Colleagues often confide in me or seek my perspective on interpersonal issues.',],
      'Team Facilitation' => ['I enjoy creating a collaborative environment where all team members feel empowered to contribute.', 'I often naturally step into a leadership or organizational role in group projects.', 'I feel a great sense of accomplishment when I help a team achieve its goals.',],
      'Conflict Resolution' => ['I am comfortable navigating difficult conversations and mediating different points of view.', 'I can usually find a win-win solution or common ground in a disagreement.', 'I am motivated to help teams overcome interpersonal roadblocks to move forward.',],
      'Persuasion & Influence' => ['I enjoy the challenge of building a case for a new idea and getting buy-in from others.', 'I am skilled at tailoring my message to resonate with different audiences.', 'I am often able to motivate and inspire my colleagues to take action.',],
      'Mentoring & Support' => ['I find it rewarding to help junior colleagues develop new skills and grow in their careers.', 'I feel a sense of satisfaction when my advice or guidance helps someone succeed.', 'People often seek me out for my mentorship and support.',],
    ],
    'intrapersonal' => [
      'Self-Reflection' => ['I regularly evaluate my own performance to identify areas for improvement and growth.', 'I find value in practices like journaling or mindfulness to maintain clarity and focus.', 'I can articulate my own thought process and the "why" behind my decisions.',],
      'Goal Orientation' => ['I am highly effective at setting clear, actionable goals for myself and my projects.', 'I am intrinsically motivated by having a clear objective to work towards.', 'I am skilled at breaking down large, long-term goals into a manageable series of steps.',],
      'Values Clarity' => ['I have a well-defined set of personal and professional values that guide my actions.', 'I use my core values as a framework for making important career and life decisions.', 'I am confident in my principles and can articulate them clearly when necessary.',],
      'Emotional Regulation' => ['I am able to remain productive and clear-headed in high-pressure situations.', 'I am aware of my own emotional triggers and have strategies for managing them effectively.', 'I actively work to maintain a balanced and constructive mindset, even during challenging times.',],
      'Decision-Making Autonomy' => ['I am comfortable taking ownership of my decisions and their outcomes.', 'I trust my own analysis and judgment when faced with complex choices.', 'I thrive in roles that offer a high degree of autonomy and self-direction.',],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => ['I am skilled at identifying trends and patterns in complex systems, whether natural or man-made.', 'I enjoy observing and analyzing how different parts of a system interact and affect one another.', 'I feel energized by opportunities to explore and understand complex environments.',],
      'Animal Interaction' => ['I have a knack for building trust and rapport with others, similar to how one might with animals.', 'I am good at observing non-verbal behavior to understand the needs or intentions of others.', 'I find that interacting with animals or nature helps me to de-stress and maintain perspective.',],
      'Environmental Stewardship' => ['I believe in the importance of sustainable and ethical practices in business and life.', 'I actively seek ways to improve efficiency and reduce waste in my work and personal life.', 'I am motivated to contribute to projects or causes that have a positive long-term impact.',],
      'Plant & Ecosystem Knowledge' => ['I enjoy learning about how different components of a system (like a market or an organization) grow and interact.', 'I am skilled at cultivating projects or relationships over time to help them succeed.', 'I find joy in nurturing growth, whether it’s a plant, a project, or a person’s skills.',],
      'Outdoor Navigation' => ['I am comfortable navigating ambiguity and finding my way through new or unfamiliar challenges.', 'I enjoy activities that require a strong sense of direction and planning, like project management or travel.', 'I am confident in my ability to orient myself and chart a course, both literally and figuratively.',],
    ],
  ],
];

// NEW: Career suggestions now have age-group keys
$mi_career_suggestions = [
  'adult' => [
    'logical-mathematical' => ['careers' => ['Data Scientist', 'Engineer', 'Accountant', 'Software Developer', 'Statistician'], 'hobbies' => ['Chess or Go', 'Sudoku', 'Budgeting/Investing', 'Coding Projects', 'Logic Puzzles']],
    'linguistic' => ['careers' => ['Author', 'Journalist', 'Lawyer', 'Copywriter', 'Public Speaker', 'Translator'], 'hobbies' => ['Creative Writing', 'Blogging or Journaling', 'Joining a Book Club', 'Learning a New Language', 'Doing Crossword Puzzles']],
    'spatial' => ['careers' => ['Architect', 'Graphic Designer', 'Engineer', 'Cartographer', 'Urban Planner', 'Animator'], 'hobbies' => ['Drawing or Painting', 'Photography', '3D Modeling', 'Interior Design', 'Jigsaw Puzzles']],
    'bodily-kinesthetic' => ['careers' => ['Athlete', 'Dancer', 'Surgeon', 'Carpenter', 'Physical Therapist', 'Mechanic'], 'hobbies' => ['Sports', 'Dancing', 'Woodworking', 'Yoga or Martial Arts', 'Building Models']],
    'musical' => ['careers' => ['Musician', 'Composer', 'DJ', 'Sound Engineer', 'Music Teacher', 'Conductor'], 'hobbies' => ['Playing an Instrument', 'Singing', 'Composing Music', 'Attending Concerts', 'Mixing Music']],
    'interpersonal' => ['careers' => ['Therapist', 'Teacher', 'Salesperson', 'HR Manager', 'Politician', 'Team Leader'], 'hobbies' => ['Team Sports', 'Volunteering', 'Hosting Events', 'Joining Clubs', 'Mentoring Others']],
    'intrapersonal' => ['careers' => ['Counselor', 'Writer', 'Researcher', 'Entrepreneur', 'Life Coach', 'Philosopher'], 'hobbies' => ['Journaling', 'Meditation', 'Reading Self-Help Books', 'Solo Travel', 'Setting Personal Goals']],
    'naturalistic' => ['careers' => ['Biologist', 'Veterinarian', 'Landscape Architect', 'Geologist', 'Environmental Scientist'], 'hobbies' => ['Gardening', 'Hiking or Camping', 'Bird Watching', 'Stargazing', 'Documenting Nature']],
  ],
  'teen' => [
    'logical-mathematical' => ['careers' => ['Game Developer', 'App Creator', 'Scientist', 'Math Teacher', 'Data Analyst'], 'hobbies' => ['Coding clubs', 'Competitive video games', 'Building things with LEGOs', 'Robotics club', 'Solving Rubik\'s cubes']],
    'linguistic' => ['careers' => ['Novelist', 'YouTuber/Streamer', 'School Newspaper Editor', 'Debate Club Captain', 'Podcaster'], 'hobbies' => ['Writing fan fiction', 'Participating in online forums', 'Playing word games on a phone', 'Reading series', 'Starting a blog']],
    'spatial' => ['careers' => ['Graphic Designer', 'Architect', 'Video Game Level Designer', 'Filmmaker', 'Fashion Designer'], 'hobbies' => ['Skateboarding', 'Drone photography', 'Building scale models', 'Creating graphic art for social media', 'Geocaching']],
    'bodily-kinesthetic' => ['careers' => ['Athlete', 'Dance Choreographer', 'Coach', 'PE Teacher', 'Video Game Tester'], 'hobbies' => ['Playing a team sport', 'Learning TikTok dances', 'Working out', 'Drama club or acting', 'Juggling']],
    'musical' => ['careers' => ['Band Member', 'Music Producer', 'DJ', 'Sound Tech for school plays', 'TikTok music creator'], 'hobbies' => ['Making playlists', 'Learning an instrument on YouTube', 'GarageBand or FL Studio', 'Joining a choir or band', 'Analyzing song lyrics']],
    'interpersonal' => ['careers' => ['Club President', 'Event Organizer', 'Peer Tutor', 'Community Volunteer Leader', 'Sales Associate'], 'hobbies' => ['Playing multiplayer online games', 'Organizing study groups', 'Volunteering for a cause', 'Being a camp counselor', 'Joining school clubs']],
    'intrapersonal' => ['careers' => ['Artist', 'Writer', 'School Counselor', 'Social Media Manager (for a cause you believe in)', 'Entrepreneur'], 'hobbies' => ['Journaling', 'Listening to podcasts', 'Going for walks alone', 'Learning a new skill independently', 'Reading about psychology']],
    'naturalistic' => ['careers' => ['Veterinarian', 'Park Ranger', 'Environmental Club Leader', 'Dog Walker', 'Camp Counselor'], 'hobbies' => ['Hiking', 'Taking care of a pet', 'Gardening', 'Stargazing', 'Joining a school environmental club']],
  ],
  'graduate' => [
    'logical-mathematical' => ['careers' => ['Financial Analyst', 'Software Engineer', 'UX/UI Data Analyst', 'Management Consultant', 'Research Assistant'], 'hobbies' => ['Stock market simulations', 'Learning a new programming language', 'Advanced board games', 'Personal finance planning', 'Optimizing daily routines']],
    'linguistic' => ['careers' => ['Content Strategist', 'Paralegal', 'Marketing Copywriter', 'Grant Writer', 'PR Coordinator'], 'hobbies' => ['Freelance writing', 'Starting a niche podcast', 'Joining Toastmasters', 'Writing a screenplay', 'Networking through thoughtful emails']],
    'spatial' => ['careers' => ['Junior Architect', 'UI/UX Designer', 'Product Designer', '3D Modeler for startups', 'GIS Analyst'], 'hobbies' => ['Urban exploration photography', 'Furniture restoration/DIY projects', 'Learning CAD software', 'Creating data visualizations', 'Joining a maker space']],
    'bodily-kinesthetic' => ['careers' => ['Personal Trainer', 'Craft Brewer/Artisan', 'Events Coordinator', 'Physical Therapist Aide', 'Surgical Technologist'], 'hobbies' => ['Joining a recreational sports league', 'Taking a dance or martial arts class', 'Pottery or other crafts', 'Rock climbing', 'Learning to cook complex meals']],
    'musical' => ['careers' => ['Audiobook Producer', 'Podcast Editor', 'Music Blogger/Critic', 'A/V Technician', 'Social Media Manager for a band'], 'hobbies' => ['DJing local events', 'Creating video essays about music', 'Joining a local choir or band', 'Learning music theory online', 'Producing music for friends']],
    'interpersonal' => ['careers' => ['HR Coordinator', 'Community Manager', 'Sales Development Rep', 'Non-profit Organizer', 'Recruiter'], 'hobbies' => ['Organizing alumni events', 'Joining professional networking groups', 'Volunteering on a committee', 'Coaching a youth sports team', 'Hosting dinner parties or game nights']],
    'intrapersonal' => ['careers' => ['Freelancer', 'Junior Researcher', 'Therapist (in training)', 'Founder of a small business/side-hustle', 'Policy Analyst'], 'hobbies' => ['Creating a personal development plan', 'Blogging about personal growth', 'Mindfulness or meditation apps', 'Traveling solo', 'Reading biographies and philosophy']],
    'naturalistic' => ['careers' => ['Urban Gardener', 'Sustainability Coordinator', 'Environmental Educator', 'Zookeeper or Vet Tech', 'Geology Field Assistant'], 'hobbies' => ['Volunteering for a community garden', 'Joining a hiking or conservation group', 'Amateur astronomy', 'Documenting local wildlife with an app like iNaturalist', 'Home brewing or fermenting']],
  ]
];

// NEW: Leverage Tips with age-group keys
$mi_leverage_tips = [
  'adult' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        "Own a recurring analysis (weekly/quarterly) and brief your team on 1–2 insights + actions.",
        "Create a decision tree or checklist for a messy recurring choice to speed it up.",
        "Host a 20-min 'how I think' teardown on a recent problem for your team."
      ],
      'Quantitative Reasoning' => [
        "Build a lightweight KPI dashboard or spreadsheet that updates automatically.",
        "Translate a fuzzy goal into 2–3 measurable metrics and set baselines.",
        "Price/forecast a small initiative and share the trade-offs with stakeholders."
      ],
      'Strategic Problem-Solving' => [
        "Run a pre-mortem on an upcoming project and propose safeguards.",
        "Map a simple roadmap (Now/Next/Later) and get alignment in 15 minutes.",
        "Prototype the riskiest assumption first; publish the learning."
      ],
      'Coding & Algorithmic Logic' => [
        "Automate one annoying task this month (e.g., data cleanup, email merge).",
        "Publish a tiny internal utility or template others can reuse.",
        "Write pseudo-code for a complex workflow so non-coders can follow it."
      ],
      'Experimental Design' => [
        "Set up a low-risk A/B or pilot; define success upfront and ship a one-page readout.",
        "Change one variable at a time; keep a lab log for quick learning transfer.",
        "Invite a skeptic to review your design before launch."
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        "Do a 10-minute digest of a dense article and share 3 bullet takeaways + 1 implication.",
        "Create an annotated reading list for your team’s next quarter.",
        "Summarize meeting notes into action-oriented briefs within 24 hours."
      ],
      'Creative Writing' => [
        "Draft a narrative memo to align stakeholders on a proposal before slides.",
        "Maintain a swipe-file of phrasing/headlines; reuse to speed up writing.",
        "Turn a success story into a short case study with problem → approach → result."
      ],
      'Public Speaking' => [
        "Volunteer to kick off meetings with clear purpose and outcomes.",
        "Use the 'rule of three' and a single call-to-action for every talk.",
        "Record one talk, review timing/fillers, and tighten your opener/closer."
      ],
      'Editing & Precision' => [
        "Offer 15-minute 'edit clinics' for teammates’ docs—focus on clarity and structure.",
        "Build style guidelines (voice, headings, tense) and link them in templates.",
        "Create before/after examples to teach concise writing."
      ],
      'Language Acquisition' => [
        "Translate key artifacts (FAQs, captions) for a new audience; note terms of art.",
        "Shadow a colleague who speaks the target language and capture phrases that work.",
        "Use spaced repetition (flashcards) for vocabulary tied to your projects."
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        "Sketch ideas first; run 'paper demos' to align quickly.",
        "Convert complex concepts into one visual model you reuse.",
        "Prototype in low-fidelity to invite feedback early."
      ],
      'Map & Layout Reading' => [
        "Own floor-plan/flow-chart responsibilities for events or processes.",
        "Create a user journey map with pain points and quick fixes.",
        "Audit a workspace or page layout and propose a one-week improvement."
      ],
      'Design & Aesthetics' => [
        "Ship a shared design kit (colors/type/spacing) for internal use.",
        "Run a 5-second test on key screens and swap what's not instantly clear.",
        "Pair with a non-designer to teach 'one hierarchy per view'."
      ],
      'Mechanical Visualization' => [
        "Whiteboard how a system works end-to-end; mark failure modes.",
        "Create assembly/ops guides with exploded views or GIFs.",
        "Lead root-cause analyses using cause-and-effect diagrams."
      ],
      'Artistic Representation' => [
        "Use storyboards to secure buy-in before production.",
        "Convert long docs into one-pager visuals for execs.",
        "Maintain a visual changelog of iterations and rationale."
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        "Facilitate standing/active workshops; keep energy high and on-time.",
        "Demonstrate physical workflows (ergonomics, safety) as live micro-lessons.",
        "Volunteer for roles that require presence (demos, tours, MC)."
      ],
      'Hands-On Building' => [
        "Create a 'try table'—mockups/samples people can touch and compare.",
        "Document a build in steps so others can replicate it.",
        "Run a monthly fix-it or maker session to spread know-how."
      ],
      'Expressive Movement' => [
        "Use intentional gestures/space when presenting; rehearse camera framing.",
        "Lead warm-ups/energizers for long meetings.",
        "Coach teammates on posture and delivery for key pitches."
      ],
      'Athletic Performance' => [
        "Track a simple performance metric (time, reps) and gamify team challenges.",
        "Offer to organize an activity that builds morale (walk-and-talks, step goals).",
        "Apply periodization: intense focus sprints followed by recovery blocks."
      ],
      'Somatic Awareness' => [
        "Design meeting cadences that include micro-breaks and resets.",
        "Notice stress signals; introduce a 30-second breathing cue before tough topics.",
        "Advise on workspace ergonomics; create a quick setup checklist."
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        "Polish audio for company content; spot harshness/noise quickly.",
        "Tune meeting cadence—openers, beats, and closers—to keep flow.",
        "Curate playlists to shape energy for events or deep-work blocks."
      ],
      'Instrumental Skill' => [
        "Provide live or recorded stingers for events/videos.",
        "Teach a short 'practice like a pro' routine to the team (deliberate reps).",
        "Use rhythm drills to improve team timing (handoffs, sprint rituals)."
      ],
      'Vocal Ability' => [
        "Be the voice of key explainers or onboarding; record clean narration.",
        "Coach colleagues on projection, pacing, and mic technique.",
        "Create quick voice-over versions of docs for on-the-go listening."
      ],
      'Composition & Arrangement' => [
        "Arrange cross-discipline 'parts' into one coherent release plan.",
        "Remix existing assets into new formats (clip, reel, carousel).",
        "Write intros/outros that make content feel finished and branded."
      ],
      'Sound Engineering' => [
        "Set up reusable audio presets and checklists for the team.",
        "Own sound at events—test mics, levels, and record a clean backup.",
        "Reduce friction: a shared booth or kit that’s grab-and-go."
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        "Be the 'feelings radar'—surface what’s unsaid and ask one clarifying question.",
        "Open/close meetings with check-ins and appreciations.",
        "Share patterns you notice so leaders can intervene early."
      ],
      'Team Facilitation' => [
        "Design agendas with clear outcomes and timeboxes; guard the process.",
        "Use round-robins and visual queues so every voice shows up.",
        "Capture decisions and owners in-room to avoid drift."
      ],
      'Conflict Resolution' => [
        "Mediates with 'both-and' reframes; write neutral summaries of each side.",
        "Set ground rules and ask for small test agreements.",
        "Follow up with a joint success metric to keep commitments real."
      ],
      'Persuasion & Influence' => [
        "Tell value-forward stories: problem → cost → better future → small ask.",
        "Social-proof your ideas—pilot with allies and share results.",
        "Make the next step tiny and calendar-ready."
      ],
      'Mentoring & Support' => [
        "Run office hours; share templates and feedback in the open.",
        "Co-create a 30-60-90 plan with a mentee and celebrate micro-wins.",
        "Sponsor someone—introduce them in rooms they’re not in yet."
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        "Hold a weekly 20-minute personal retro (Start/Stop/Continue).",
        "Name your 3 operating principles and publish them to your team.",
        "Track energy patterns; schedule hard work in your best window."
      ],
      'Goal Orientation' => [
        "Turn goals into 2-week deliverables with a visible checklist.",
        "Use 'one metric that matters' per quarter and report progress.",
        "Pre-commit by sharing your goal + deadline with a peer."
      ],
      'Values Clarity' => [
        "Decision filter: if it violates a core value, say no fast and explain why.",
        "Choose one value to spotlight monthly and design a ritual around it.",
        "Align a stretch project with a value you care about to stay motivated."
      ],
      'Emotional Regulation' => [
        "Build a reset routine (breath, walk, reframe) and model it publicly.",
        "Pre-write responses for hot triggers; buy time with 'Let me reflect.'",
        "Track stressors and reduce one structural cause each month."
      ],
      'Decision-Making Autonomy' => [
        "Use 'disagree & commit' when stakes are low—ship and learn.",
        "Set decision rights upfront (Who decides? By when? What input?).",
        "Document trade-offs so future you can reuse the logic."
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        "Apply systems thinking to work: map loops, delays, and leverage points.",
        "Create early-warning dashboards (leading indicators, not just lagging).",
        "Host a 'patterns hour' to compare anecdotes with data."
      ],
      'Animal Interaction' => [
        "Use calm presence to de-escalate tense rooms; mind tone and pace.",
        "Design roles and habitats—give people environments where they thrive.",
        "Lead volunteer days with animal orgs; build employer brand."
      ],
      'Environmental Stewardship' => [
        "Own a sustainability quick-win (waste, energy, travel policy) and measure it.",
        "Green your events: vendor checklist, re-use plan, carbon-light choices.",
        "Tell the impact story internally—cost saved and footprint reduced."
      ],
      'Plant & Ecosystem Knowledge' => [
        "Run growth experiments like a gardener: seed, tend, prune initiatives.",
        "Create onboarding 'soil'—resources that help newcomers root fast.",
        "Maintain a living map of stakeholders and nutrient flows (who feeds whom)."
      ],
      'Outdoor Navigation' => [
        "Plan offsites/fieldwork with clear routes, risks, and contingencies.",
        "Use wayfinding metaphors (milestones, markers) to simplify strategy.",
        "Offer outdoor walking 1:1s to unlock better thinking."
      ],
    ],
  ],

  'teen' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        "Lead puzzle/logic warm-ups in a club or class once a week.",
        "Break group projects into steps and assign deadlines with checklists.",
        "Explain your reasoning out loud—teach a friend your method."
      ],
      'Quantitative Reasoning' => [
        "Track a stat you care about (grades, sports, money) and graph it weekly.",
        "Build a simple spreadsheet to compare options (phone plans, bikes).",
        "Estimate before you calculate—play the 'guess then check' game."
      ],
      'Strategic Problem-Solving' => [
        "Plan a mini-event (study session, tournament) with a Now/Next/Later board.",
        "Choose the highest-impact task first; time-box to 25 minutes.",
        "Write a mini pre-mortem: 'What could go wrong and how will we handle it?'"
      ],
      'Coding & Algorithmic Logic' => [
        "Automate homework chores (flashcards, timers, file sorters).",
        "Join or start a coding circle; build one tiny tool for your school.",
        "Write pseudo-code before touching the keyboard."
      ],
      'Experimental Design' => [
        "Run a small experiment (study technique, workout) and keep a log.",
        "Change one variable at a time; share your results with your class or team.",
        "Ask a teacher to mentor your next test plan."
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        "Share a 3-bullet book/article summary with your group chat weekly.",
        "Annotate key paragraphs and trade notes with a friend.",
        "Choose one hard text and read 10 minutes a day."
      ],
      'Creative Writing' => [
        "Enter a short writing challenge; publish to a class blog or zine.",
        "Keep a 'lines I like' notebook to steal good phrasing (ethically).",
        "Rewrite a scene from a different point of view."
      ],
      'Public Speaking' => [
        "Volunteer to open team meetings with the plan for the day.",
        "Practice with video—watch once for posture, once for pacing.",
        "Use a clear call-to-action at the end of every talk."
      ],
      'Editing & Precision' => [
        "Be the 'typo scout' for your group—swap drafts and fix clarity.",
        "Build a one-page style guide for your club or class project.",
        "Cut 10% of words from a draft to make it stronger."
      ],
      'Language Acquisition' => [
        "Label things in your target language around your room.",
        "Do 5 minutes of spaced-repetition vocab daily.",
        "Find a language buddy and trade voice notes twice a week."
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        "Storyboard your video or project before building it.",
        "Turn notes into doodles/diagrams and share with your class.",
        "Build paper prototypes to test ideas fast."
      ],
      'Map & Layout Reading' => [
        "Be the map captain on trips/events; plan routes and timing.",
        "Create a seating chart or room layout that improves flow.",
        "Draft a game level or base plan and explain your choices."
      ],
      'Design & Aesthetics' => [
        "Make a simple style kit (fonts/colors) for your club.",
        "Run a 5-second test: can others tell what your poster is about?",
        "Redesign one school form or page for clarity."
      ],
      'Mechanical Visualization' => [
        "Take apart a broken gadget and sketch how it works.",
        "Explain a machine with a labeled drawing or short video.",
        "Build a step-by-step guide another student can follow."
      ],
      'Artistic Representation' => [
        "Turn a report into an infographic or comic strip.",
        "Create thumbnails (tiny sketches) to explore ideas quickly.",
        "Join a design or yearbook role to make real artifacts."
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        "Lead warm-ups for your team or class.",
        "Demonstrate skills slowly, then at full speed; let others mirror you.",
        "Teach a friend a move—coaching sharpens your own form."
      ],
      'Hands-On Building' => [
        "Join shop, robotics, or art; document builds step by step.",
        "Fix or upgrade something at school and show the before/after.",
        "Start a maker hour and share tools safely."
      ],
      'Expressive Movement' => [
        "Choreograph a short routine or blocking for a play.",
        "Use body language when presenting—practice eye contact and stance.",
        "Record and review to polish timing and clarity."
      ],
      'Athletic Performance' => [
        "Pick one metric (time, accuracy) and track weekly improvement.",
        "Design a mini-challenge for friends; celebrate progress.",
        "Plan rest and recovery—sleep and fuel matter."
      ],
      'Somatic Awareness' => [
        "Notice early stress signals and try a 60-second reset.",
        "Set up your desk for comfort; stretch between classes.",
        "Take a 'walk and think' break before tough conversations."
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        "Be sound check lead for performances or assemblies.",
        "Create playlists to set study or event mood.",
        "Write quick feedback for friends’ tracks—what you hear + one suggestion."
      ],
      'Instrumental Skill' => [
        "Record practice; pick one bar to perfect each day.",
        "Start a small ensemble or accompany school events.",
        "Teach a beginner—nothing sharpens fundamentals like coaching."
      ],
      'Vocal Ability' => [
        "Offer voice-overs for school videos or announcements.",
        "Warm up before talks; use breath to control pace.",
        "Join choir or start a jam session tradition."
      ],
      'Composition & Arrangement' => [
        "Remix school chants or themes for events.",
        "Score a short film or podcast episode for a friend.",
        "Layer parts in a DAW and explain your arrangement."
      ],
      'Sound Engineering' => [
        "Create a simple recording kit and checklist for clubs.",
        "Learn mic placement; run sound at a school event.",
        "Teach noise reduction and level matching basics."
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        "Be the check-in lead—ask one caring question at meetings.",
        "Notice who’s quiet and invite them in.",
        "Share concerns early with a teacher/coach to protect the group."
      ],
      'Team Facilitation' => [
        "Write agendas with outcomes and keep time.",
        "Use turn-taking so everyone contributes once per round.",
        "Capture decisions and owners on a shared board."
      ],
      'Conflict Resolution' => [
        "Name the goal you all share; list options without judging.",
        "Summarize each side fairly before proposing a middle path.",
        "End with a small test and a date to review."
      ],
      'Persuasion & Influence' => [
        "Tell a quick story + one clear ask when pitching ideas.",
        "Pilot your idea with a small group and show results.",
        "Make the next step easy (signup link, date, materials)."
      ],
      'Mentoring & Support' => [
        "Tutor a younger student; bring a simple plan and encouragement.",
        "Share templates/notes with your class.",
        "Give specific praise that points to effort and strategy."
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        "Keep a 3-line daily log: win, learn, next.",
        "Notice when you do your best work and protect that time.",
        "Ask 'What did this teach me?' after setbacks."
      ],
      'Goal Orientation' => [
        "Set one weekly goal and track it in public with a friend.",
        "Turn big goals into tiny daily actions (10-minute rule).",
        "Reward consistency, not perfection."
      ],
      'Values Clarity' => [
        "Pick a value (e.g., kindness) and plan one action for it this week.",
        "Say no to one thing that doesn’t match your values.",
        "Join a club or cause that expresses what you care about."
      ],
      'Emotional Regulation' => [
        "Name the feeling → normalize it → choose a next step.",
        "Use movement or music to reset before studying.",
        "Create a calm-down plan you can do anywhere."
      ],
      'Decision-Making Autonomy' => [
        "Write pros/cons and sleep on big choices.",
        "Ask for input, then decide and own the result.",
        "Start a small project you control end-to-end."
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        "Track patterns (sleep, study, mood) and adjust habits.",
        "Design a mini-ecosystem project (garden, compost) at school or home.",
        "Explain a natural cycle using your own visuals."
      ],
      'Animal Interaction' => [
        "Volunteer with a shelter or pet program; log what you learn.",
        "Teach calm, safe handling to peers.",
        "Create a plan for responsible care of class pets."
      ],
      'Environmental Stewardship' => [
        "Lead a simple school sustainability win (recycling station, refill water).",
        "Run an awareness campaign with measurable goals.",
        "Share your impact with before/after photos or data."
      ],
      'Plant & Ecosystem Knowledge' => [
        "Start seedlings or a micro-garden and journal growth.",
        "Identify local plants with an app and make a mini-field guide.",
        "Host a 'nature show-and-tell' for your class."
      ],
      'Outdoor Navigation' => [
        "Plan a hike with route, timing, and safety notes.",
        "Learn basic orienteering; teach friends to use a map/compass.",
        "Lead a local clean-up and map who covers which area."
      ],
    ],
  ],

  'graduate' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        "Own a research/analytics thread for a lab, club, or internship and publish short briefs.",
        "Design decision frameworks your team can reuse.",
        "Host a mini-workshop on problem-framing with examples from your field."
      ],
      'Quantitative Reasoning' => [
        "Build a small live dashboard for a student org or side project.",
        "Convert a thesis or capstone into 2–3 testable metrics.",
        "Estimate impact and confidence; recommend next experiments."
      ],
      'Strategic Problem-Solving' => [
        "Create a semester roadmap with risks and triggers.",
        "Pilot a 'minimum viable' version of an idea and report outcomes.",
        "Facilitate prioritization using impact vs. effort matrices."
      ],
      'Coding & Algorithmic Logic' => [
        "Automate repetitive course/research tasks; document for others.",
        "Ship a tiny open-source script—not perfect, but useful.",
        "Write pseudo-code specs to align teammates before building."
      ],
      'Experimental Design' => [
        "Pre-register a simple experiment or define success thresholds in advance.",
        "Run small N tests ethically and share learnings quickly.",
        "Maintain a lessons-learned log others can search."
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        "Summarize papers in a shared repository using a fixed template.",
        "Write 'too long; read this' briefs for busy partners.",
        "Lead a journal club where you translate jargon for newcomers."
      ],
      'Creative Writing' => [
        "Draft narrative proposals before slides to align advisors.",
        "Turn projects into Medium/portfolio posts with outcomes and tips.",
        "Maintain a hook bank—openers/closers that land."
      ],
      'Public Speaking' => [
        "Pitch your project in 90 seconds—iterate until crisp.",
        "Emcee a meetup or panel; practice hearing the room.",
        "End every talk with one action you want from listeners."
      ],
      'Editing & Precision' => [
        "Offer fast edits for peers; create a checklist for clarity.",
        "Build a lab/club style guide and templates.",
        "Run a 'cut 15%' clinic to tighten drafts."
      ],
      'Language Acquisition' => [
        "Translate a poster/README; harvest domain vocabulary.",
        "Pair with a native speaker for weekly voice notes.",
        "Use spaced repetition tied to your research topics."
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        "Sketch architectures before tools; win feedback early.",
        "Produce a single diagram that explains your project to outsiders.",
        "Prototype interfaces on paper during team jams."
      ],
      'Map & Layout Reading' => [
        "Design event or lab layouts to improve flow and safety.",
        "Map stakeholder journeys for your capstone sponsor.",
        "Audit a website/app screen and propose a 1-week fix."
      ],
      'Design & Aesthetics' => [
        "Ship a small design system for your team’s docs and slides.",
        "Run 5-second tests on key visuals to validate hierarchy.",
        "Teach a 'less but better' session to peers."
      ],
      'Mechanical Visualization' => [
        "Explain a mechanism with exploded views/animations.",
        "Lead a failure analysis and capture the learnings.",
        "Create assembly SOPs with visual steps."
      ],
      'Artistic Representation' => [
        "Turn complex methods into infographics or storyboards.",
        "Maintain a visual portfolio of iterations and results.",
        "Offer to visualize another team’s project for exposure."
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        "Facilitate active workshops; keep groups moving and focused.",
        "Demonstrate lab/shop techniques with safe, clear choreography.",
        "Volunteer for campus tours or demo days."
      ],
      'Hands-On Building' => [
        "Prototype early and often; host show-and-tell sessions.",
        "Document builds so first-years can replicate them.",
        "Run maker nights that solve real campus problems."
      ],
      'Expressive Movement' => [
        "Use presence and gesture to elevate pitches; practice on camera.",
        "Choreograph stage flow for events (entrances, mic handoffs).",
        "Coach peers on body language for interviews."
      ],
      'Athletic Performance' => [
        "Organize low-friction movement breaks during study marathons.",
        "Set a measurable fitness goal and recruit friends for accountability.",
        "Apply progressive overload to academic sprints as well."
      ],
      'Somatic Awareness' => [
        "Design a personal reset ritual before exams/talks.",
        "Help your team set up ergonomic study/maker spaces.",
        "Normalize micro-pauses to prevent burnout."
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        "Own audio quality for team media; build quick EQ/NR presets.",
        "Design meeting rhythms that keep groups engaged.",
        "Curate playlists for focus or events."
      ],
      'Instrumental Skill' => [
        "Offer live cues or stings at campus events.",
        "Teach deliberate-practice methods to your club.",
        "Record parts for peers’ projects and credit collaborators."
      ],
      'Vocal Ability' => [
        "Narrate explainers or tutorials; keep a clean vocal chain.",
        "Coach classmates on pacing, breathing, and mic use.",
        "Create short voice memos to summarize decisions."
      ],
      'Composition & Arrangement' => [
        "Arrange multi-team deliverables into a single coherent release.",
        "Remix raw footage/assets into multi-format content.",
        "Score a short film/promo for a student org."
      ],
      'Sound Engineering' => [
        "Assemble a portable recording kit with a setup checklist.",
        "Standardize audio settings for podcasts and lectures.",
        "Archive and tag raw audio so it’s reusable."
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        "Run check-ins and debriefs that surface feelings and needs.",
        "Name tensions early; propose small experiments to relieve them.",
        "Be the confidential sounding board for teammates."
      ],
      'Team Facilitation' => [
        "Design agendas with roles and clear outcomes.",
        "Use participatory methods (rounds, dots, 1-2-4-All).",
        "End every meeting with owners, deadlines, and a recap."
      ],
      'Conflict Resolution' => [
        "Reframe debates into shared goals and options.",
        "Draft joint statements that integrate both views.",
        "Follow through with a review date and success metric."
      ],
      'Persuasion & Influence' => [
        "Lead with value and a crisp ask; show a quick win.",
        "Line up social proof (advisor quotes, pilot data).",
        "Make the commitment tiny and calendar-ready."
      ],
      'Mentoring & Support' => [
        "Run weekly office hours; collect FAQs in public docs.",
        "Sponsor a junior by opening doors and giving context.",
        "Celebrate process, not just outcomes—model growth mindsets."
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        "Hold a weekly review (wins, frictions, experiments).",
        "Write 'operating notes' for future you after big pushes.",
        "Track when you do your best work and guard that time."
      ],
      'Goal Orientation' => [
        "Translate goals into two-week sprints with demo days.",
        "Pick one metric that matters and report it publicly.",
        "Pre-commit with peers to create friendly pressure."
      ],
      'Values Clarity' => [
        "Choose projects that express a value; say no faster to misfits.",
        "Publish your principles for collaborators.",
        "Design rituals that keep values visible (gratitude, shout-outs)."
      ],
      'Emotional Regulation' => [
        "Build a stress reset (breathe, move, reframe) before exams/talks.",
        "Name triggers and pre-plan responses that buy you time.",
        "Protect sleep and daylight—performance multipliers."
      ],
      'Decision-Making Autonomy' => [
        "Define decision rights upfront; avoid consensus traps.",
        "Ship small, learn fast—document trade-offs and results.",
        "Use 'disagree & commit' when the cost of delay is higher."
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        "Model systems (feedback loops, delays) in your projects.",
        "Detect leading indicators and propose early moves.",
        "Run 'pattern club' sessions to compare signals vs. noise."
      ],
      'Animal Interaction' => [
        "Bring calm, grounded presence to intense rooms.",
        "Design environments that fit different 'temperaments'.",
        "Lead service days with animal/nature orgs; measure impact."
      ],
      'Environmental Stewardship' => [
        "Own a sustainability initiative on campus (waste, events, labs).",
        "Report cost and footprint savings to stakeholders.",
        "Tell the before/after story to recruit allies."
      ],
      'Plant & Ecosystem Knowledge' => [
        "Grow projects like gardens—seed, thin, prune, harvest learnings.",
        "Map stakeholder ecosystems and resource flows.",
        "Create a 'care schedule' for critical relationships or systems."
      ],
      'Outdoor Navigation' => [
        "Plan fieldwork/offsites with routes, risks, contingencies.",
        "Use wayfinding metaphors to make strategy tangible.",
        "Host walking 1:1s to unlock better thinking."
      ],
    ],
  ],
];


// NEW: Growth Tips with age-group keys
$mi_growth_tips = [
  'adult' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        'Write the problem as a single sentence, then list 3 root causes (5 Whys).',
        'Do one 10-minute logic puzzle daily; explain your reasoning to a colleague.',
        'Turn a current decision into a quick decision tree and choose based on expected value.',
      ],
      'Quantitative Reasoning' => [
        'Pick 1 work metric to track weekly; chart a 4-week moving average and set a threshold.',
        'Recreate a chart from an article in a spreadsheet to check if the numbers tell the same story.',
        'Practice “Fermi” estimates (e.g., market size) and compare your guess to a quick Google answer.',
      ],
      'Strategic Problem-Solving' => [
        'Do a 15-minute pre-mortem: “If this fails in 3 months, why?” Turn top 3 risks into mitigations.',
        'Define “success” as one measurable outcome, then outline 3 milestones and a first domino task.',
        'Adopt a cadence: weekly review → next best move → what to stop doing.',
      ],
      'Coding & Algorithmic Logic' => [
        'Automate one repetitive task with spreadsheet formulas or a tiny script (if/then, loops).',
        'Write pseudocode for a workflow you do often; tighten it until another person can run it.',
        'Learn one control-flow idea (IF, loop, map/filter) and apply it to a real list this week.',
      ],
      'Experimental Design' => [
        'Run a micro A/B: change one variable, log the metric, decide to keep/revert in 7 days.',
        'State a hypothesis in IF/THEN/BECAUSE form; choose a success metric before you start.',
        'Do a 10-minute after-action review: what worked, what didn’t, what to change next time.',
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        'Use the SQ3R pass (skim, question, read, recite, review) for dense docs.',
        'Annotate one article per week; summarize it in 5 bullet points and one counterpoint.',
        'Teach a paragraph’s idea to someone in 60 seconds—if it’s hard, reread and simplify.',
      ],
      'Creative Writing' => [
        'Keep a 10-minute daily “rough page”; don’t edit—just ship words.',
        'Collect a swipe file of 5 favorite openings and mimic one each week.',
        'Revise with a pass for verbs, a pass for structure, then a pass for cuts (−20%).',
      ],
      'Public Speaking' => [
        'Storyboard talks in 5 tiles: Hook → Problem → Insight → Example → Ask.',
        'Record a 90-second voice note weekly; listen for filler words to cut.',
        'Rehearse standing; practice pauses and eye contact on every slide change.',
      ],
      'Editing & Precision' => [
        'Edit in layers: structure → clarity → concision → tone → typos.',
        'Use a “one-idea-per-sentence” test; split any sentence with 2+ ideas.',
        'Read aloud; anywhere you stumble, rewrite.',
      ],
      'Language Acquisition' => [
        'Daily micro-drill (10 min): 8 new words + one sentence using each.',
        'Shadow a native speaker video for rhythm and pronunciation twice a week.',
        'Set a real-world moment (coffee order, email) to use the language weekly.',
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        'Before building, sketch 3 variations; choose using a simple scorecard (clarity, cost, time).',
        'Translate a written idea into a diagram (boxes/arrows) once per project.',
        'Practice “before/after” mental models: picture end state, then steps backwards.',
      ],
      'Map & Layout Reading' => [
        'Do an orienteering walk: navigate by landmarks, not GPS, once a week.',
        'Redesign a room or slide using a grid; remove one element to reduce clutter.',
        'Convert a process into a flowchart; test it with a teammate.',
      ],
      'Design & Aesthetics' => [
        'Learn one design constraint (grid, spacing, contrast); apply it to a slide or doc today.',
        'Create a moodboard for your next presentation; reuse that style.',
        'Run a “remove to improve” pass: delete 10% of visual elements.',
      ],
      'Mechanical Visualization' => [
        'Disassemble a household item (or app workflow) on paper, label parts, reassemble logically.',
        'Watch a 3D animation of a mechanism and sketch the motion path.',
        'Predict failure points on something you use; check them after a week of use.',
      ],
      'Artistic Representation' => [
        'Keep a tiny sketch log: 3 objects, 3 minutes each, three times a week.',
        'Replace one meeting note set with a visual map (icons + arrows).',
        'Trace (not copy) an illustration to learn proportions, then redraw from memory.',
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        'Micro-practice: 5 minutes a day on one skill (balance, footwork, or timing).',
        'Film a movement weekly; compare to a model and fix one cue.',
        'Warm up with mobility before work; add one new drill per month.',
      ],
      'Hands-On Building' => [
        'Do one weekend fix/build; write a 3-step plan before starting.',
        'Learn a tool properly (e.g., torque wrench, soldering iron) via a 20-minute tutorial.',
        'End sessions by labeling what you’d change next time.',
      ],
      'Expressive Movement' => [
        'Pick a short routine; practice with a metronome and then with music.',
        'Use gestures intentionally: one hand for structure, the other for emphasis in presentations.',
        'Perform for a friend; request one “more of / less of” note.',
      ],
      'Athletic Performance' => [
        'Train one capacity per session (strength, speed, skill) and log it.',
        'Set a 6-week goal (e.g., 5K time, push-ups) and work backwards.',
        'Sleep and protein are performance multipliers—treat them as training.',
      ],
      'Somatic Awareness' => [
        'Schedule posture checks (phone buzz each hour → reset and breathe).',
        'Scan head-to-toe for tension; relax one area for 60 seconds.',
        'Use a “movement snack” when stuck: walk 2 minutes, return with one next step.',
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        'Active-listen to one track daily: call out form (intro/verse/chorus/bridge).',
        'Clap or tap subdivisions; switch between straight and swung feels.',
        'Identify 3 instruments in a mix and follow each through the song.',
      ],
      'Instrumental Skill' => [
        'Slow it down: 80% tempo clean first, then +5% per day.',
        'One technical drill + one repertoire piece per session.',
        'Record a weekly take; tag one flaw and one win.',
      ],
      'Vocal Ability' => [
        'Warm up gently (lip trills, sirens) for 5 minutes before singing.',
        'Practice pitch with a drone or piano; hold intervals steadily.',
        'Mark breaths and consonant releases on your lyrics sheet.',
      ],
      'Composition & Arrangement' => [
        'Write 8 bars a day; limit yourself to 2 chords and one motif.',
        'Steal like a musician: reharmonize a melody with a new groove.',
        'Arrange for contrast—change texture or register every 8 bars.',
      ],
      'Sound Engineering' => [
        'Gain-stage tracks so peaks sit around −12 dBFS before mixing.',
        'Practice EQ by sweeping to find “ugly,” then cut gently.',
        'Reference commercial mixes at matched loudness as you work.',
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        'Name what you notice (“I’m hearing frustration…”) and ask if you’ve got it right.',
        'Mirror tone and pace—then shift to calm to lead the conversation down.',
        'Log emotional bids from teammates and respond within 24 hours.',
      ],
      'Team Facilitation' => [
        'Use rounds: everyone speaks for 30 seconds before open discussion.',
        'End meetings with action owner + deadline on each decision.',
        'Rotate roles (facilitator, scribe, timekeeper) to build shared ownership.',
      ],
      'Conflict Resolution' => [
        'Separate people from problem: list interests, not positions.',
        'Try “Yes, and…” to acknowledge before offering an alternate path.',
        'Offer two acceptable options; let them choose the path.',
      ],
      'Persuasion & Influence' => [
        'Start with their words: reflect goals back before proposing anything.',
        'Tell a 3-act micro-story: Situation → Tension → Resolution (your ask).',
        'Show the smallest testable step instead of the whole plan.',
      ],
      'Mentoring & Support' => [
        'Ask, “What would make this 10% easier?” then co-design just that.',
        'Share a personal failure + lesson to normalize learning.',
        'Set a cadence (bi-weekly) and track one tiny goal per session.',
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        'Daily 3-line journal: what happened, how I felt, what I’ll do tomorrow.',
        'Name the story you’re telling yourself; write an alternative story.',
        'Schedule a monthly solo walk without headphones to think.',
      ],
      'Goal Orientation' => [
        'Use a 6-week sprint: one outcome, weekly lead measures, Friday review.',
        'Make “done” visible: checkbox, graph, or habit tracker.',
        'Cut goals in half and double the cadence to build momentum.',
      ],
      'Values Clarity' => [
        'List top 5 values; for each, write one “do more” and one “do less.”',
        'When stuck, ask: “Which choice honors my values more?”',
        'Do a weekly values check on your calendar—what didn’t fit?',
      ],
      'Emotional Regulation' => [
        'Use the 90-second rule: breathe and let the initial surge pass before replying.',
        'Name the emotion + intensity (0–10); choose a tool (walk, water, reframe).',
        'Create a micro-ritual before stressful tasks (exhale, posture, first step).',
      ],
      'Decision-Making Autonomy' => [
        'Write the decision, constraints, and the smallest reversible step.',
        'Time-box: 20 minutes to research, then decide.',
        'Own outcomes with a brief post-decision note: keep/adjust/stop.',
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        'Track one daily pattern (light, temperature, birds) for 2 weeks.',
        'Compare two similar environments and list 3 differences you observe.',
        'Sketch a mini food web for your yard or a local park.',
      ],
      'Animal Interaction' => [
        'Learn one species’ body-language signals and practice spotting them.',
        'Volunteer one hour a month at a shelter or conservation group.',
        'Train a simple behavior with a pet using shaping and rewards.',
      ],
      'Environmental Stewardship' => [
        'Choose one habit (waste, water, transport) and improve it 10% this month.',
        'Audit home or office energy use; change one device or setting.',
        'Join one local cleanup or tree-planting each quarter.',
      ],
      'Plant & Ecosystem Knowledge' => [
        'Grow one easy plant; log watering, light, and growth.',
        'Identify 5 local plants with an app; learn one fact about each.',
        'Visit two habitats (wetland/forest) and note contrasts.',
      ],
      'Outdoor Navigation' => [
        'Practice map + compass on an easy trail; check yourself every 10 minutes.',
        'Plan a simple route, estimate time, and compare to actual.',
        'Learn 3 natural navigation cues (sun path, moss, wind).',
      ],
    ],
  ],

  'teen' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        'Break school tasks into “must/should/could” and do must-dos first.',
        'Solve one brain-teaser a day and explain the steps to a friend.',
        'Turn a tough homework question into a flowchart of choices.',
      ],
      'Quantitative Reasoning' => [
        'Track a stat you care about (games, workouts, grades) weekly and graph it.',
        'Estimate before you calculate; see how close you were.',
        'Redo one math problem two ways (algebraic and numeric check).',
      ],
      'Strategic Problem-Solving' => [
        'Make a mini-plan for projects: today, this week, final checkpoint.',
        'Do a “pre-mortem” for group work: list 3 ways it could go wrong and fixes.',
        'Choose one easiest next step and do it in 10 minutes.',
      ],
      'Coding & Algorithmic Logic' => [
        'Write pseudocode for a game idea or routine before coding.',
        'Practice one concept (loops, conditionals) with 3 short exercises.',
        'Automate a homework task in Sheets (IF, COUNTIF, VLOOKUP).',
      ],
      'Experimental Design' => [
        'Run a simple experiment (sleep vs. test score): change one thing, track one metric.',
        'Write your hypothesis in one line and check it after a week.',
        'After a lab, list one improvement for next time.',
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        'Preview a chapter: headings, bold words, pictures—then read.',
        'After reading, write 3 bullet takeaways in your own words.',
        'Teach a friend one key idea in under a minute.',
      ],
      'Creative Writing' => [
        'Free-write for 7 minutes; no backspace allowed.',
        'Copy your favorite opening paragraph by hand, then write your own version.',
        'Swap drafts with a friend and ask for one thing to add and one to cut.',
      ],
      'Public Speaking' => [
        'Practice out loud with your phone camera; watch once and fix one habit.',
        'Use the “point-reason-example” pattern for answers.',
        'Pause after important lines; breathe instead of saying “um.”',
      ],
      'Editing & Precision' => [
        'Edit in two passes: ideas first, grammar second.',
        'Read your work aloud and circle any tangled sentence.',
        'Cut 10% of words—keep the meaning.',
      ],
      'Language Acquisition' => [
        'Do a 10-minute app lesson daily; speak every answer, don’t just tap.',
        'Shadow a short video and try to match the rhythm.',
        'Label 5 things in your room with the new words.',
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        'Sketch your idea for a project before you start building it.',
        'Try drawing the same object from 3 angles.',
        'Turn a paragraph of notes into a diagram with arrows.',
      ],
      'Map & Layout Reading' => [
        'Navigate your neighborhood without GPS; predict turns ahead.',
        'Design a new layout for your desk or room using a grid sketch.',
        'Build a simple level in a game and test the flow.',
      ],
      'Design & Aesthetics' => [
        'Use the rule “one font for titles, one for text.”',
        'Pick a color palette (3 colors) and stick to it for a project.',
        'Before submitting, remove one decoration that doesn’t help.',
      ],
      'Mechanical Visualization' => [
        'Take apart a pen or small toy and draw how the parts fit.',
        'Watch a short “how it works” video and pause to predict the next step.',
        'Explain a mechanism to someone else using only drawings.',
      ],
      'Artistic Representation' => [
        'Keep a mini sketchbook; draw 3 small things a day.',
        'Replace one set of written notes with a visual mind map.',
        'Trace a picture to learn proportions, then draw it without tracing.',
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        'Practice footwork or balance for 5 minutes daily.',
        'Film a move and compare to a tutorial; fix one thing.',
        'Warm up and cool down every session—make it a habit.',
      ],
      'Hands-On Building' => [
        'Plan builds in 3 steps: sketch → list parts → build.',
        'Learn one tool safely each month (soldering, knife skills, glue gun).',
        'Write one “what I’d change” note after each project.',
      ],
      'Expressive Movement' => [
        'Learn a short routine and practice with a metronome.',
        'Use bigger gestures when you present; match your words.',
        'Perform for a friend and ask for one tip.',
      ],
      'Athletic Performance' => [
        'Pick a 6-week goal (pull-ups, mile time) and track it.',
        'Focus each practice: skill day, strength day, or conditioning day.',
        'Sleep 8 hours—your training depends on it.',
      ],
      'Somatic Awareness' => [
        'Do a quick body scan before tests or games and relax one tight area.',
        'Stand up and stretch every class change.',
        'Drink water and take 5 deep breaths before big moments.',
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        'Clap the beat of your favorite song; find the downbeat.',
        'Name the song form (verse/chorus/bridge).',
        'Listen for 3 different instruments and follow each.',
      ],
      'Instrumental Skill' => [
        'Practice slow and clean; speed comes later.',
        'Do one technical drill and one fun song each practice.',
        'Record a weekly clip and notice improvements.',
      ],
      'Vocal Ability' => [
        'Warm up with lip trills and sirens for 5 minutes.',
        'Match pitch with a keyboard app; hold notes steady.',
        'Mark breathing spots on your lyrics.',
      ],
      'Composition & Arrangement' => [
        'Write 4 bars a day; keep it super simple.',
        'Change a song’s chord or rhythm to learn arranging.',
        'Alternate sections: loud/soft or high/low for contrast.',
      ],
      'Sound Engineering' => [
        'Keep input levels below clipping; watch the meters.',
        'Try EQ: remove muddiness before adding effects.',
        'Compare your mix to a favorite track at the same volume.',
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        'Ask a friend how they’re really doing, then listen without fixing.',
        'Paraphrase what you heard before you reply.',
        'Notice tone and body language; check if your guess is right.',
      ],
      'Team Facilitation' => [
        'Start group work with roles (leader, note-taker, timekeeper).',
        'End with “who does what by when.”',
        'Make sure quiet voices go first once in a while.',
      ],
      'Conflict Resolution' => [
        'Use “I” statements: “I felt… when…”',
        'Find one thing you both want before arguing about solutions.',
        'Offer two fair options and let the other person choose.',
      ],
      'Persuasion & Influence' => [
        'Start with what they care about, then show how your idea helps.',
        'Tell a quick story (problem → attempt → fix).',
        'Ask for the smallest yes (try for one day).',
      ],
      'Mentoring & Support' => [
        'Help a younger student with one skill you know.',
        'Share one mistake you made and what you learned.',
        'Check in weekly: “What’s one win and one stuck point?”',
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        'Write 3 lines a day: what happened, how I felt, what I’ll try tomorrow.',
        'Name the story in your head; write a kinder version.',
        'Take a 10-minute walk without your phone to think.',
      ],
      'Goal Orientation' => [
        'Pick one goal for 2 weeks; track it on a paper chart.',
        'Break it into tiny actions you can finish in 10 minutes.',
        'Review on Friday and choose the next tiny step.',
      ],
      'Values Clarity' => [
        'List your top 5 values; circle where school/life already matches.',
        'Say no to one thing this week that doesn’t fit your values.',
        'Choose friends and projects that line up with what matters to you.',
      ],
      'Emotional Regulation' => [
        'Use a name + number: “I feel stress at 6/10.”',
        'Breathe 4-7-8 before tests or talks.',
        'Move your body for 2 minutes when emotions spike.',
      ],
      'Decision-Making Autonomy' => [
        'Write the choice, pros/cons, and the smallest safe test.',
        'Give yourself a timer to decide and accept “good enough.”',
        'Note what happened after; keep/adjust/stop.',
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        'Notice one change outdoors daily (sound, light, animals) and jot it down.',
        'Compare two places (schoolyard vs. park) and list differences.',
        'Draw a simple food chain from your area.',
      ],
      'Animal Interaction' => [
        'Learn three calm-animal signals and watch for them.',
        'Volunteer at a shelter or help a neighbor with a pet.',
        'Teach a pet one trick using treats and patience.',
      ],
      'Environmental Stewardship' => [
        'Choose one habit (water bottle, lights, recycling) to improve for 2 weeks.',
        'Join a school clean-up or tree-planting day.',
        'Calculate your footprint with a free tool and change one thing.',
      ],
      'Plant & Ecosystem Knowledge' => [
        'Grow an easy plant and track care in a note.',
        'Use an ID app to learn 5 local plants/trees.',
        'Visit two habitats and write what’s unique in each.',
      ],
      'Outdoor Navigation' => [
        'Plan a short walk on a paper map; predict time and check after.',
        'Learn to use a compass (N/E/S/W) on a field or park.',
        'Find the sun’s path at your home morning/noon/evening.',
      ],
    ],
  ],

  'graduate' => [
    'logical-mathematical' => [
      'Analytical Thinking' => [
        'Summarize a messy problem as a 1-sentence hypothesis and 3 assumptions.',
        'Pressure-test with a peer: ask them to poke holes for 5 minutes.',
        'Translate insights into a simple decision rule you can reuse.',
      ],
      'Quantitative Reasoning' => [
        'Rebuild a figure from a paper in a spreadsheet and sanity-check the math.',
        'Track one personal or project metric weekly; try a simple regression or trendline.',
        'Do a weekly Fermi estimate relevant to your field and compare to a published number.',
      ],
      'Strategic Problem-Solving' => [
        'Use OKR-lite: one objective, 2–3 key results, weekly check-ins.',
        'Write a pre-mortem for your capstone/internship project.',
        'Choose a “first domino” task you can finish in 30 minutes.',
      ],
      'Coding & Algorithmic Logic' => [
        'Solve small problems with pseudocode first, then implement.',
        'Automate a lab/report step with a script or notebook cell.',
        'Refactor one function per week for clarity and reuse.',
      ],
      'Experimental Design' => [
        'Define success metrics before collecting data.',
        'Change one variable at a time; log conditions carefully.',
        'Always do an after-action note: insight, surprise, next test.',
      ],
    ],
    'linguistic' => [
      'Reading & Comprehension' => [
        'Skim methods/results first; then read full text.',
        'Write a 150-word abstract in your own words for each dense reading.',
        'Add one counter-argument to every summary.',
      ],
      'Creative Writing' => [
        'Draft fast, revise slow: separate creation from editing.',
        'Swap sections with a peer and trade one actionable note each.',
        'Practice headline → outline → draft to speed up structure.',
      ],
      'Public Speaking' => [
        'Design slides for speaking, not reading: one idea per slide.',
        'Rehearse with a peer and ask for one clarity and one pacing note.',
        'Open with a problem your audience actually has.',
      ],
      'Editing & Precision' => [
        'Use checklists: figures labeled, claims sourced, acronyms defined.',
        'Replace passive voice in a separate pass.',
        'Trim 15% of words without losing meaning.',
      ],
      'Language Acquisition' => [
        '10-minute daily drill + one weekly live conversation (in person or online).',
        'Shadow technical talks to copy phrasing for your field.',
        'Create a phrase bank for interviews or networking.',
      ],
    ],
    'spatial' => [
      'Visual Imagination' => [
        'Storyboard complex ideas with 6 panels before you code/build.',
        'Translate one abstract idea into a diagram for your report.',
        'Sketch three UI/diagram alternatives and pick with a rubric.',
      ],
      'Map & Layout Reading' => [
        'Read process maps and org charts; redraw a cleaner version.',
        'Practice campus/orienteering routes without phones weekly.',
        'Re-layout your resume/portfolio using a strict grid.',
      ],
      'Design & Aesthetics' => [
        'Build a personal slide template (grid, colors, type) and reuse it.',
        'Study 3 great decks; copy one slide structure each.',
        'Run a contrast/spacing pass before submitting anything.',
      ],
      'Mechanical Visualization' => [
        'Predict a system’s failure mode and test it safely.',
        'Sketch a software or machine sequence as state → transition.',
        'Use exploded diagrams when learning a new device or lib.',
      ],
      'Artistic Representation' => [
        'Swap one text section for a well-labeled figure or storyboard.',
        'Practice iconography: replace words with simple symbols where clear.',
        'Do a 5-minute sketch warm-up before design work.',
      ],
    ],
    'bodily-kinesthetic' => [
      'Physical Coordination' => [
        'Grease-the-groove: short skill reps between study blocks.',
        'Film and compare to a model; fix a single cue at a time.',
        'Weekly mobility session to keep studying posture pain-free.',
      ],
      'Hands-On Building' => [
        'Write a build brief (goal, constraints, test) before starting.',
        'Learn one lab/shop technique each month and teach it once.',
        'Keep a build log with photos and “what I’d change.”',
      ],
      'Expressive Movement' => [
        'Practice presenting standing, with intentional gestures.',
        'Use tempo changes (slow → regular) to drill difficult moves.',
        'Perform for friends; ask for one clarity note.',
      ],
      'Athletic Performance' => [
        'Program weeks: skill/strength/conditioning; don’t mix everything.',
        'Choose a measurable 6-week target and track sessions.',
        'Protect sleep during exam weeks like training.',
      ],
      'Somatic Awareness' => [
        'Set a posture timer; reset shoulders/neck each hour.',
        'Use box-breathing before exams or interviews.',
        'Walk 5 minutes every 50 minutes of desk work.',
      ],
    ],
    'musical' => [
      'Musical Perception' => [
        'Transcribe 8 bars of rhythm each week.',
        'Label song sections and mark where energy changes.',
        'A/B two recordings to hear mix and arrangement differences.',
      ],
      'Instrumental Skill' => [
        'Practice slow with a click; speed only after clean takes.',
        'Alternate technique and repertoire blocks.',
        'Keep a weekly practice log with tiny goals.',
      ],
      'Vocal Ability' => [
        'Gentle warm-ups; avoid shouting or strain on busy days.',
        'Work intervals and breath support with a drone note.',
        'Mark phrasing and consonant releases on scores.',
      ],
      'Composition & Arrangement' => [
        'Write a 16-bar sketch each week—finish over polish.',
        'Borrow a groove and reharmonize a new melody on top.',
        'Arrange with contrast in texture, register, and density.',
      ],
      'Sound Engineering' => [
        'Organize sessions and name tracks clearly.',
        'Balance with faders first; EQ later.',
        'Reference mixes at equal loudness while you work.',
      ],
    ],
    'interpersonal' => [
      'Emotional Sensitivity' => [
        'Use “name it to tame it”: reflect feelings you hear in seminars.',
        'Match pace/tone first, then guide toward calm problem-solving.',
        'Respond to messages within 24 hours—even if only to acknowledge.',
      ],
      'Team Facilitation' => [
        'Start meetings with agenda + timeboxes; end with owners + dates.',
        'Run quick rounds so everyone speaks once per meeting.',
        'Publish decisions in the doc right away.',
      ],
      'Conflict Resolution' => [
        'Ask for interests (“What matters most here?”) before debating solutions.',
        'Offer two viable options aligned to shared goals.',
        'Summarize agreements and next steps in writing.',
      ],
      'Persuasion & Influence' => [
        'Open with the audience’s metric; tie your idea to it.',
        'Show the smallest pilot that could prove value.',
        'Use one relevant story rather than many claims.',
      ],
      'Mentoring & Support' => [
        'Adopt one mentee; set a bi-weekly 20-minute slot.',
        'Ask “What does a good week look like?” and trim the plan to that.',
        'Share resources in the moment (template, article, intro).',
      ],
    ],
    'intrapersonal' => [
      'Self-Reflection' => [
        'Weekly review: wins, lessons, experiments for next week.',
        'Name your inner critic and reply with facts.',
        'Schedule one solo “thinking block” on your calendar.',
      ],
      'Goal Orientation' => [
        'Pick one outcome per 6-week sprint; track 1–2 lead measures.',
        'Use visual progress bars on key tasks.',
        'Do a Friday check-in: keep/adjust/kill.',
      ],
      'Values Clarity' => [
        'Write 5 values and match them to classes, work, and friends.',
        'Say “no” to one misaligned request this week.',
        'Choose roles that move you toward your values.',
      ],
      'Emotional Regulation' => [
        'Name the emotion + intensity, then choose a tool (walk, water, reframe).',
        'Practice 2 minutes of breathing before presentations.',
        'Plan “pressure valves” during deadlines (movement, sunlight, music).',
      ],
      'Decision-Making Autonomy' => [
        'Define a reversible first step and time-box it.',
        'Document reasoning briefly; future-you will thank you.',
        'Reflect after each decision: what signal mattered most?',
      ],
    ],
    'naturalistic' => [
      'Pattern Recognition in Nature' => [
        'Keep a small field log of campus weather/wildlife for two weeks.',
        'Compare two green spaces and note biodiversity differences.',
        'Map a micro-ecosystem around your residence.',
      ],
      'Animal Interaction' => [
        'Learn species cues relevant to your area; observe respectfully.',
        'Volunteer for a local animal or conservation group monthly.',
        'Practice reward-based training with a pet or study animal.',
      ],
      'Environmental Stewardship' => [
        'Choose one footprint lever (transport, diet, energy) and improve it 10%.',
        'Audit your apartment’s energy; switch one device/setting.',
        'Join or organize a community clean-up each term.',
      ],
      'Plant & Ecosystem Knowledge' => [
        'Identify 10 campus plants/trees; add notes on habitat.',
        'Grow herbs or a small plant; document care routines.',
        'Visit two distinct habitats and compare soil, light, and species.',
      ],
      'Outdoor Navigation' => [
        'Plan new walking routes without maps; check your accuracy.',
        'Practice compass basics on a campus field.',
        'Estimate travel times and compare to reality for calibration.',
      ],
    ],
  ],
];

// NEW: Potential Skills based on intelligence combinations
$mi_potential_skills = [
  'adult' => [
    'Intrapersonal+Interpersonal+Naturalistic' => [
      "You'd be good at leading a group hike up a mountain, pausing along the way to reflect and share insights.",
      "You'd thrive at helping people connect their inner growth with the beauty of nature.",
      "You'd shine at designing an outdoor challenge that builds teamwork and personal reflection."
    ],
    'Intrapersonal+Interpersonal+Bodily-Kinesthetic' => [
      "You'd be good at coaching a team through a physical challenge while encouraging honesty and trust.",
      "You'd thrive at helping a sports group reflect on their teamwork after practice.",
      "You'd shine at using movement to help groups bond and share experiences."
    ],
    'Intrapersonal+Interpersonal+Logical-Mathematical' => [
      "You'd be good at guiding a group in solving problems while making sure everyone's voice is heard.",
      "You'd thrive at combining emotional insight with logical thinking in group projects.",
      "You'd shine at balancing people's needs with fair and structured decision-making."
    ],
    'Intrapersonal+Interpersonal+Linguistic' => [
      "You'd be good at leading group discussions where people share stories about themselves.",
      "You'd thrive at helping a group write something meaningful together, like a poem or pledge.",
      "You'd shine at weaving together different voices into one shared message."
    ],
    'Intrapersonal+Interpersonal+Musical' => [
      "You'd be good at helping a group write and perform a song that reflects their shared experiences.",
      "You'd thrive at encouraging people to express their feelings through rhythm or music.",
      "You'd shine at using music to build group identity and reflection."
    ],
    'Intrapersonal+Interpersonal+Visual-Spatial' => [
      "You'd be good at guiding a group to create a mural or vision board that reflects shared values.",
      "You'd thrive at helping people use symbols and images to express what they feel inside.",
      "You'd shine at leading collaborative art projects that spark conversation and growth."
    ],
    'Intrapersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at planning and leading outdoor challenges that require teamwork and self-reflection.",
      "You'd thrive at guiding others in using their bodies to connect with nature and personal growth.",
      "You'd shine at combining physical activity with quiet reflection outdoors."
    ],
    'Intrapersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at tracking patterns in nature and reflecting on what they teach about life.",
      "You'd thrive at analyzing natural systems and drawing personal lessons from them.",
      "You'd shine at helping others see the logic in how nature and self-discovery connect."
    ],
    'Intrapersonal+Naturalistic+Linguistic' => [
      "You'd be good at journaling about outdoor experiences and sharing them with others.",
      "You'd thrive at writing stories or essays that connect nature and personal reflection.",
      "You'd shine at leading discussions that combine environmental awareness with self-growth."
    ],
    'Intrapersonal+Naturalistic+Musical' => [
      "You'd be good at composing music inspired by your personal experiences in nature.",
      "You'd thrive at creating rhythms or melodies that reflect the natural world and inner emotions.",
      "You'd shine at leading group activities that connect music, nature, and reflection."
    ],
    'Intrapersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at sketching landscapes that reflect both nature and your inner life.",
      "You'd thrive at creating art projects outdoors that spark reflection.",
      "You'd shine at helping others see themselves in the beauty of nature through visuals."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at mastering physical routines through step-by-step logic.",
      "You'd thrive at designing group challenges that combine movement with problem-solving.",
      "You'd shine at analyzing performance to improve physical skills."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at writing about personal experiences with sports or movement.",
      "You'd thrive at acting or performing in ways that communicate your inner world.",
      "You'd shine at explaining physical techniques with clarity and self-awareness."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at expressing your inner life through dance or rhythm.",
      "You'd thrive at creating movement routines set to music.",
      "You'd shine at leading group exercises that combine fitness and reflection."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at choreographing movements that tell a personal story.",
      "You'd thrive at designing physical performances that are visually striking.",
      "You'd shine at helping others connect body, image, and self-expression."
    ],
    'Intrapersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing essays that use logic to explore personal ideas.",
      "You'd thrive at analyzing your own reasoning and expressing it clearly.",
      "You'd shine at teaching others through structured arguments rooted in self-awareness."
    ],
    'Intrapersonal+Logical-Mathematical+Musical' => [
      "You'd be good at finding mathematical patterns in your personal creative process.",
      "You'd thrive at composing music that reflects both structure and emotion.",
      "You'd shine at explaining how rhythm mirrors your thought process."
    ],
    'Intrapersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at designing diagrams that map your personal growth.",
      "You'd thrive at building systems that visually represent logical steps.",
      "You'd shine at using charts to show progress toward your goals."
    ],
    'Intrapersonal+Linguistic+Musical' => [
      "You'd be good at writing lyrics that express deep self-awareness.",
      "You'd thrive at combining storytelling and music to process personal experiences.",
      "You'd shine at helping groups reflect through songs you create."
    ],
    'Intrapersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at writing and illustrating personal stories.",
      "You'd thrive at designing presentations that combine words and images to inspire reflection.",
      "You'd shine at leading projects that blend writing with visual expression."
    ],
    'Intrapersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating art projects that blend sound and imagery.",
      "You'd thrive at composing pieces inspired by visual symbols.",
      "You'd shine at helping others feel both music and visuals in new ways."
    ],
    'Interpersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at organizing outdoor team games that require both strategy and movement.",
      "You'd thrive at leading physical activities in natural environments.",
      "You'd shine at building group trust through outdoor adventures."
    ],
    'Interpersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at leading a team in solving nature-based problems, like designing a sustainable project.",
      "You'd thrive at guiding groups to notice patterns in the environment.",
      "You'd shine at helping people connect data with real-world teamwork."
    ],
    'Interpersonal+Naturalistic+Linguistic' => [
      "You'd be good at telling stories about nature that bring people together.",
      "You'd thrive at leading group reflections outdoors with words and conversation.",
      "You'd shine at teaching others about the environment in ways that inspire action."
    ],
    'Interpersonal+Naturalistic+Musical' => [
      "You'd be good at organizing outdoor events with music to bring people together.",
      "You'd thrive at composing songs that celebrate the natural world.",
      "You'd shine at leading rhythm-based activities in nature."
    ],
    'Interpersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at leading a group mural that celebrates nature.",
      "You'd thrive at designing outdoor spaces that help people connect with each other.",
      "You'd shine at creating visual projects that combine environment and community."
    ],
    'Interpersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing physical team challenges that require logical problem-solving.",
      "You'd thrive at leading escape-room style games for groups.",
      "You'd shine at guiding others through puzzles that mix movement and strategy."
    ],
    'Interpersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at leading group drama performances that explore relationships.",
      "You'd thrive at acting in plays that help people reflect on community.",
      "You'd shine at coaching public speaking with gestures and body awareness."
    ],
    'Interpersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at choreographing dances that groups perform together.",
      "You'd thrive at teaching rhythm-based team games.",
      "You'd shine at blending movement and music to bring groups closer."
    ],
    'Interpersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at designing group art projects that involve performance.",
      "You'd thrive at creating dramatic performances with sets and visuals.",
      "You'd shine at leading workshops that combine movement and design."
    ],
    'Interpersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at leading debates where arguments are backed by logic and respect.",
      "You'd thrive at explaining complex ideas in ways groups understand.",
      "You'd shine at mediating conflicts using both reason and words."
    ],
    'Interpersonal+Logical-Mathematical+Musical' => [
      "You'd be good at organizing music ensembles with structured roles.",
      "You'd thrive at helping groups practice rhythm with precision.",
      "You'd shine at showing how math and music unite teams."
    ],
    'Interpersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at leading design teams that plan projects step by step.",
      "You'd thrive at showing groups diagrams that simplify big ideas.",
      "You'd shine at balancing creativity and logic in teamwork."
    ],
    'Interpersonal+Linguistic+Musical' => [
      "You'd be good at helping groups write songs that tell their shared story.",
      "You'd thrive at leading chants or performances that unite people.",
      "You'd shine at storytelling through rhythm and sound."
    ],
    'Interpersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at helping groups create posters or campaigns that share a message.",
      "You'd thrive at designing events that blend words and visuals.",
      "You'd shine at leading collaborative art with a powerful story."
    ],
    'Interpersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating performances that mix rhythm and imagery.",
      "You'd thrive at designing group projects like music videos or shows.",
      "You'd shine at helping others express stories through sound and visuals."
    ],
    'Naturalistic+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing experiments outdoors that require teamwork.",
      "You'd thrive at leading survival or nature-based challenges.",
      "You'd shine at mixing data and movement in the environment."
    ],
    'Naturalistic+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at acting in plays or skits about nature and environment.",
      "You'd thrive at guiding outdoor storytelling through movement.",
      "You'd shine at sharing environmental lessons with drama."
    ],
    'Naturalistic+Bodily-Kinesthetic+Musical' => [
      "You'd be good at composing music inspired by outdoor adventures.",
      "You'd thrive at leading rhythm-based group activities outdoors.",
      "You'd shine at celebrating nature through sound and movement."
    ],
    'Naturalistic+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at creating sculptures or installations from natural materials.",
      "You'd thrive at guiding outdoor art projects with teams.",
      "You'd shine at blending movement, space, and the environment."
    ],
    'Naturalistic+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing reports or stories based on environmental data.",
      "You'd thrive at sharing scientific ideas in simple words.",
      "You'd shine at persuading people with both evidence and passion for nature."
    ],
    'Naturalistic+Logical-Mathematical+Musical' => [
      "You'd be good at finding rhythm in environmental patterns.",
      "You'd thrive at creating songs that explain natural cycles.",
      "You'd shine at leading groups in celebrating the math of nature with music."
    ],
    'Naturalistic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at creating infographics that explain ecosystems.",
      "You'd thrive at mapping natural systems for others.",
      "You'd shine at blending data and visuals to inspire care for the planet."
    ],
    'Naturalistic+Linguistic+Musical' => [
      "You'd be good at writing songs about the beauty of nature.",
      "You'd thrive at performing pieces that inspire people to care for the environment.",
      "You'd shine at leading creative projects that combine words, sound, and the outdoors."
    ],
    'Naturalistic+Linguistic+Visual-Spatial' => [
      "You'd be good at creating children's books about the environment with words and illustrations.",
      "You'd thrive at teaching through posters or campaigns about nature.",
      "You'd shine at storytelling that mixes writing and visuals outdoors."
    ],
    'Naturalistic+Musical+Visual-Spatial' => [
      "You'd be good at composing soundtracks inspired by landscapes.",
      "You'd thrive at designing shows or videos that pair nature and music.",
      "You'd shine at leading projects where sound and visuals bring the outdoors to life."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Linguistic' => [
      "You'd be good at explaining sports strategies with clear logic and words.",
      "You'd thrive at writing guides for physical training routines.",
      "You'd shine at teaching complex movement through step-by-step explanation."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Musical' => [
      "You'd be good at creating dance routines based on mathematical patterns.",
      "You'd thrive at analyzing rhythm in sports or performance.",
      "You'd shine at helping groups coordinate timing and movement."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at building obstacle courses that require teamwork.",
      "You'd thrive at guiding others through puzzle-like challenges.",
      "You'd shine at blending design, logic, and physical skills."
    ],
    'Bodily-Kinesthetic+Linguistic+Musical' => [
      "You'd be good at choreographing performances that tell a story.",
      "You'd thrive at using rhythm to enhance acting or movement.",
      "You'd shine at guiding groups to express emotions with body and sound."
    ],
    'Bodily-Kinesthetic+Linguistic+Visual-Spatial' => [
      "You'd be good at performing plays with strong movement and staging.",
      "You'd thrive at directing shows where words, movement, and visuals combine.",
      "You'd shine at teaching expression through drama and design."
    ],
    'Bodily-Kinesthetic+Musical+Visual-Spatial' => [
      "You'd be good at designing performances that combine dance and visuals.",
      "You'd thrive at choreographing group routines with powerful staging.",
      "You'd shine at creating shows where movement and imagery tell a story."
    ],
    'Logical-Mathematical+Linguistic+Musical' => [
      "You'd be good at writing songs that explain big ideas clearly.",
      "You'd thrive at teaching math or science concepts through rhythm.",
      "You'd shine at showing how structure and creativity work together."
    ],
    'Logical-Mathematical+Linguistic+Visual-Spatial' => [
      "You'd be good at creating charts and stories that explain complex ideas.",
      "You'd thrive at designing visual reports that persuade with logic.",
      "You'd shine at teaching others with both evidence and images."
    ],
    'Logical-Mathematical+Musical+Visual-Spatial' => [
      "You'd be good at composing music that mirrors geometric patterns.",
      "You'd thrive at designing light or sound shows with mathematical precision.",
      "You'd shine at blending math, rhythm, and visuals into one project."
    ],
    'Linguistic+Musical+Visual-Spatial' => [
      "You'd be good at creating illustrated lyrics or music videos.",
      "You'd thrive at designing group projects that combine song and image.",
      "You'd shine at storytelling through both visuals and sound."
    ]
  ],
  'teen' => [
    // Using same skills for teens - they would be applicable across age groups
    'Intrapersonal+Interpersonal+Naturalistic' => [
      "You'd be good at leading a group hike up a mountain, pausing along the way to reflect and share insights.",
      "You'd thrive at helping people connect their inner growth with the beauty of nature.",
      "You'd shine at designing an outdoor challenge that builds teamwork and personal reflection."
    ],
    'Intrapersonal+Interpersonal+Bodily-Kinesthetic' => [
      "You'd be good at coaching a team through a physical challenge while encouraging honesty and trust.",
      "You'd thrive at helping a sports group reflect on their teamwork after practice.",
      "You'd shine at using movement to help groups bond and share experiences."
    ],
    'Intrapersonal+Interpersonal+Logical-Mathematical' => [
      "You'd be good at guiding a group in solving problems while making sure everyone's voice is heard.",
      "You'd thrive at combining emotional insight with logical thinking in group projects.",
      "You'd shine at balancing people's needs with fair and structured decision-making."
    ],
    'Intrapersonal+Interpersonal+Linguistic' => [
      "You'd be good at leading group discussions where people share stories about themselves.",
      "You'd thrive at helping a group write something meaningful together, like a poem or pledge.",
      "You'd shine at weaving together different voices into one shared message."
    ],
    'Intrapersonal+Interpersonal+Musical' => [
      "You'd be good at helping a group write and perform a song that reflects their shared experiences.",
      "You'd thrive at encouraging people to express their feelings through rhythm or music.",
      "You'd shine at using music to build group identity and reflection."
    ],
    'Intrapersonal+Interpersonal+Visual-Spatial' => [
      "You'd be good at guiding a group to create a mural or vision board that reflects shared values.",
      "You'd thrive at helping people use symbols and images to express what they feel inside.",
      "You'd shine at leading collaborative art projects that spark conversation and growth."
    ],
    'Intrapersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at planning and leading outdoor challenges that require teamwork and self-reflection.",
      "You'd thrive at guiding others in using their bodies to connect with nature and personal growth.",
      "You'd shine at combining physical activity with quiet reflection outdoors."
    ],
    'Intrapersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at tracking patterns in nature and reflecting on what they teach about life.",
      "You'd thrive at analyzing natural systems and drawing personal lessons from them.",
      "You'd shine at helping others see the logic in how nature and self-discovery connect."
    ],
    'Intrapersonal+Naturalistic+Linguistic' => [
      "You'd be good at journaling about outdoor experiences and sharing them with others.",
      "You'd thrive at writing stories or essays that connect nature and personal reflection.",
      "You'd shine at leading discussions that combine environmental awareness with self-growth."
    ],
    'Intrapersonal+Naturalistic+Musical' => [
      "You'd be good at composing music inspired by your personal experiences in nature.",
      "You'd thrive at creating rhythms or melodies that reflect the natural world and inner emotions.",
      "You'd shine at leading group activities that connect music, nature, and reflection."
    ],
    'Intrapersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at sketching landscapes that reflect both nature and your inner life.",
      "You'd thrive at creating art projects outdoors that spark reflection.",
      "You'd shine at helping others see themselves in the beauty of nature through visuals."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at mastering physical routines through step-by-step logic.",
      "You'd thrive at designing group challenges that combine movement with problem-solving.",
      "You'd shine at analyzing performance to improve physical skills."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at writing about personal experiences with sports or movement.",
      "You'd thrive at acting or performing in ways that communicate your inner world.",
      "You'd shine at explaining physical techniques with clarity and self-awareness."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at expressing your inner life through dance or rhythm.",
      "You'd thrive at creating movement routines set to music.",
      "You'd shine at leading group exercises that combine fitness and reflection."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at choreographing movements that tell a personal story.",
      "You'd thrive at designing physical performances that are visually striking.",
      "You'd shine at helping others connect body, image, and self-expression."
    ],
    'Intrapersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing essays that use logic to explore personal ideas.",
      "You'd thrive at analyzing your own reasoning and expressing it clearly.",
      "You'd shine at teaching others through structured arguments rooted in self-awareness."
    ],
    'Intrapersonal+Logical-Mathematical+Musical' => [
      "You'd be good at finding mathematical patterns in your personal creative process.",
      "You'd thrive at composing music that reflects both structure and emotion.",
      "You'd shine at explaining how rhythm mirrors your thought process."
    ],
    'Intrapersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at designing diagrams that map your personal growth.",
      "You'd thrive at building systems that visually represent logical steps.",
      "You'd shine at using charts to show progress toward your goals."
    ],
    'Intrapersonal+Linguistic+Musical' => [
      "You'd be good at writing lyrics that express deep self-awareness.",
      "You'd thrive at combining storytelling and music to process personal experiences.",
      "You'd shine at helping groups reflect through songs you create."
    ],
    'Intrapersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at writing and illustrating personal stories.",
      "You'd thrive at designing presentations that combine words and images to inspire reflection.",
      "You'd shine at leading projects that blend writing with visual expression."
    ],
    'Intrapersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating art projects that blend sound and imagery.",
      "You'd thrive at composing pieces inspired by visual symbols.",
      "You'd shine at helping others feel both music and visuals in new ways."
    ],
    'Interpersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at organizing outdoor team games that require both strategy and movement.",
      "You'd thrive at leading physical activities in natural environments.",
      "You'd shine at building group trust through outdoor adventures."
    ],
    'Interpersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at leading a team in solving nature-based problems, like designing a sustainable project.",
      "You'd thrive at guiding groups to notice patterns in the environment.",
      "You'd shine at helping people connect data with real-world teamwork."
    ],
    'Interpersonal+Naturalistic+Linguistic' => [
      "You'd be good at telling stories about nature that bring people together.",
      "You'd thrive at leading group reflections outdoors with words and conversation.",
      "You'd shine at teaching others about the environment in ways that inspire action."
    ],
    'Interpersonal+Naturalistic+Musical' => [
      "You'd be good at organizing outdoor events with music to bring people together.",
      "You'd thrive at composing songs that celebrate the natural world.",
      "You'd shine at leading rhythm-based activities in nature."
    ],
    'Interpersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at leading a group mural that celebrates nature.",
      "You'd thrive at designing outdoor spaces that help people connect with each other.",
      "You'd shine at creating visual projects that combine environment and community."
    ],
    'Interpersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing physical team challenges that require logical problem-solving.",
      "You'd thrive at leading escape-room style games for groups.",
      "You'd shine at guiding others through puzzles that mix movement and strategy."
    ],
    'Interpersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at leading group drama performances that explore relationships.",
      "You'd thrive at acting in plays that help people reflect on community.",
      "You'd shine at coaching public speaking with gestures and body awareness."
    ],
    'Interpersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at choreographing dances that groups perform together.",
      "You'd thrive at teaching rhythm-based team games.",
      "You'd shine at blending movement and music to bring groups closer."
    ],
    'Interpersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at designing group art projects that involve performance.",
      "You'd thrive at creating dramatic performances with sets and visuals.",
      "You'd shine at leading workshops that combine movement and design."
    ],
    'Interpersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at leading debates where arguments are backed by logic and respect.",
      "You'd thrive at explaining complex ideas in ways groups understand.",
      "You'd shine at mediating conflicts using both reason and words."
    ],
    'Interpersonal+Logical-Mathematical+Musical' => [
      "You'd be good at organizing music ensembles with structured roles.",
      "You'd thrive at helping groups practice rhythm with precision.",
      "You'd shine at showing how math and music unite teams."
    ],
    'Interpersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at leading design teams that plan projects step by step.",
      "You'd thrive at showing groups diagrams that simplify big ideas.",
      "You'd shine at balancing creativity and logic in teamwork."
    ],
    'Interpersonal+Linguistic+Musical' => [
      "You'd be good at helping groups write songs that tell their shared story.",
      "You'd thrive at leading chants or performances that unite people.",
      "You'd shine at storytelling through rhythm and sound."
    ],
    'Interpersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at helping groups create posters or campaigns that share a message.",
      "You'd thrive at designing events that blend words and visuals.",
      "You'd shine at leading collaborative art with a powerful story."
    ],
    'Interpersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating performances that mix rhythm and imagery.",
      "You'd thrive at designing group projects like music videos or shows.",
      "You'd shine at helping others express stories through sound and visuals."
    ],
    'Naturalistic+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing experiments outdoors that require teamwork.",
      "You'd thrive at leading survival or nature-based challenges.",
      "You'd shine at mixing data and movement in the environment."
    ],
    'Naturalistic+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at acting in plays or skits about nature and environment.",
      "You'd thrive at guiding outdoor storytelling through movement.",
      "You'd shine at sharing environmental lessons with drama."
    ],
    'Naturalistic+Bodily-Kinesthetic+Musical' => [
      "You'd be good at composing music inspired by outdoor adventures.",
      "You'd thrive at leading rhythm-based group activities outdoors.",
      "You'd shine at celebrating nature through sound and movement."
    ],
    'Naturalistic+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at creating sculptures or installations from natural materials.",
      "You'd thrive at guiding outdoor art projects with teams.",
      "You'd shine at blending movement, space, and the environment."
    ],
    'Naturalistic+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing reports or stories based on environmental data.",
      "You'd thrive at sharing scientific ideas in simple words.",
      "You'd shine at persuading people with both evidence and passion for nature."
    ],
    'Naturalistic+Logical-Mathematical+Musical' => [
      "You'd be good at finding rhythm in environmental patterns.",
      "You'd thrive at creating songs that explain natural cycles.",
      "You'd shine at leading groups in celebrating the math of nature with music."
    ],
    'Naturalistic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at creating infographics that explain ecosystems.",
      "You'd thrive at mapping natural systems for others.",
      "You'd shine at blending data and visuals to inspire care for the planet."
    ],
    'Naturalistic+Linguistic+Musical' => [
      "You'd be good at writing songs about the beauty of nature.",
      "You'd thrive at performing pieces that inspire people to care for the environment.",
      "You'd shine at leading creative projects that combine words, sound, and the outdoors."
    ],
    'Naturalistic+Linguistic+Visual-Spatial' => [
      "You'd be good at creating children's books about the environment with words and illustrations.",
      "You'd thrive at teaching through posters or campaigns about nature.",
      "You'd shine at storytelling that mixes writing and visuals outdoors."
    ],
    'Naturalistic+Musical+Visual-Spatial' => [
      "You'd be good at composing soundtracks inspired by landscapes.",
      "You'd thrive at designing shows or videos that pair nature and music.",
      "You'd shine at leading projects where sound and visuals bring the outdoors to life."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Linguistic' => [
      "You'd be good at explaining sports strategies with clear logic and words.",
      "You'd thrive at writing guides for physical training routines.",
      "You'd shine at teaching complex movement through step-by-step explanation."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Musical' => [
      "You'd be good at creating dance routines based on mathematical patterns.",
      "You'd thrive at analyzing rhythm in sports or performance.",
      "You'd shine at helping groups coordinate timing and movement."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at building obstacle courses that require teamwork.",
      "You'd thrive at guiding others through puzzle-like challenges.",
      "You'd shine at blending design, logic, and physical skills."
    ],
    'Bodily-Kinesthetic+Linguistic+Musical' => [
      "You'd be good at choreographing performances that tell a story.",
      "You'd thrive at using rhythm to enhance acting or movement.",
      "You'd shine at guiding groups to express emotions with body and sound."
    ],
    'Bodily-Kinesthetic+Linguistic+Visual-Spatial' => [
      "You'd be good at performing plays with strong movement and staging.",
      "You'd thrive at directing shows where words, movement, and visuals combine.",
      "You'd shine at teaching expression through drama and design."
    ],
    'Bodily-Kinesthetic+Musical+Visual-Spatial' => [
      "You'd be good at designing performances that combine dance and visuals.",
      "You'd thrive at choreographing group routines with powerful staging.",
      "You'd shine at creating shows where movement and imagery tell a story."
    ],
    'Logical-Mathematical+Linguistic+Musical' => [
      "You'd be good at writing songs that explain big ideas clearly.",
      "You'd thrive at teaching math or science concepts through rhythm.",
      "You'd shine at showing how structure and creativity work together."
    ],
    'Logical-Mathematical+Linguistic+Visual-Spatial' => [
      "You'd be good at creating charts and stories that explain complex ideas.",
      "You'd thrive at designing visual reports that persuade with logic.",
      "You'd shine at teaching others with both evidence and images."
    ],
    'Logical-Mathematical+Musical+Visual-Spatial' => [
      "You'd be good at composing music that mirrors geometric patterns.",
      "You'd thrive at designing light or sound shows with mathematical precision.",
      "You'd shine at blending math, rhythm, and visuals into one project."
    ],
    'Linguistic+Musical+Visual-Spatial' => [
      "You'd be good at creating illustrated lyrics or music videos.",
      "You'd thrive at designing group projects that combine song and image.",
      "You'd shine at storytelling through both visuals and sound."
    ]
  ],
  'graduate' => [
    // Using same skills for graduates as they are universally applicable
    'Intrapersonal+Interpersonal+Naturalistic' => [
      "You'd be good at leading a group hike up a mountain, pausing along the way to reflect and share insights.",
      "You'd thrive at helping people connect their inner growth with the beauty of nature.",
      "You'd shine at designing an outdoor challenge that builds teamwork and personal reflection."
    ],
    'Intrapersonal+Interpersonal+Bodily-Kinesthetic' => [
      "You'd be good at coaching a team through a physical challenge while encouraging honesty and trust.",
      "You'd thrive at helping a sports group reflect on their teamwork after practice.",
      "You'd shine at using movement to help groups bond and share experiences."
    ],
    'Intrapersonal+Interpersonal+Logical-Mathematical' => [
      "You'd be good at guiding a group in solving problems while making sure everyone's voice is heard.",
      "You'd thrive at combining emotional insight with logical thinking in group projects.",
      "You'd shine at balancing people's needs with fair and structured decision-making."
    ],
    'Intrapersonal+Interpersonal+Linguistic' => [
      "You'd be good at leading group discussions where people share stories about themselves.",
      "You'd thrive at helping a group write something meaningful together, like a poem or pledge.",
      "You'd shine at weaving together different voices into one shared message."
    ],
    'Intrapersonal+Interpersonal+Musical' => [
      "You'd be good at helping a group write and perform a song that reflects their shared experiences.",
      "You'd thrive at encouraging people to express their feelings through rhythm or music.",
      "You'd shine at using music to build group identity and reflection."
    ],
    'Intrapersonal+Interpersonal+Visual-Spatial' => [
      "You'd be good at guiding a group to create a mural or vision board that reflects shared values.",
      "You'd thrive at helping people use symbols and images to express what they feel inside.",
      "You'd shine at leading collaborative art projects that spark conversation and growth."
    ],
    'Intrapersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at planning and leading outdoor challenges that require teamwork and self-reflection.",
      "You'd thrive at guiding others in using their bodies to connect with nature and personal growth.",
      "You'd shine at combining physical activity with quiet reflection outdoors."
    ],
    'Intrapersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at tracking patterns in nature and reflecting on what they teach about life.",
      "You'd thrive at analyzing natural systems and drawing personal lessons from them.",
      "You'd shine at helping others see the logic in how nature and self-discovery connect."
    ],
    'Intrapersonal+Naturalistic+Linguistic' => [
      "You'd be good at journaling about outdoor experiences and sharing them with others.",
      "You'd thrive at writing stories or essays that connect nature and personal reflection.",
      "You'd shine at leading discussions that combine environmental awareness with self-growth."
    ],
    'Intrapersonal+Naturalistic+Musical' => [
      "You'd be good at composing music inspired by your personal experiences in nature.",
      "You'd thrive at creating rhythms or melodies that reflect the natural world and inner emotions.",
      "You'd shine at leading group activities that connect music, nature, and reflection."
    ],
    'Intrapersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at sketching landscapes that reflect both nature and your inner life.",
      "You'd thrive at creating art projects outdoors that spark reflection.",
      "You'd shine at helping others see themselves in the beauty of nature through visuals."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at mastering physical routines through step-by-step logic.",
      "You'd thrive at designing group challenges that combine movement with problem-solving.",
      "You'd shine at analyzing performance to improve physical skills."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at writing about personal experiences with sports or movement.",
      "You'd thrive at acting or performing in ways that communicate your inner world.",
      "You'd shine at explaining physical techniques with clarity and self-awareness."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at expressing your inner life through dance or rhythm.",
      "You'd thrive at creating movement routines set to music.",
      "You'd shine at leading group exercises that combine fitness and reflection."
    ],
    'Intrapersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at choreographing movements that tell a personal story.",
      "You'd thrive at designing physical performances that are visually striking.",
      "You'd shine at helping others connect body, image, and self-expression."
    ],
    'Intrapersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing essays that use logic to explore personal ideas.",
      "You'd thrive at analyzing your own reasoning and expressing it clearly.",
      "You'd shine at teaching others through structured arguments rooted in self-awareness."
    ],
    'Intrapersonal+Logical-Mathematical+Musical' => [
      "You'd be good at finding mathematical patterns in your personal creative process.",
      "You'd thrive at composing music that reflects both structure and emotion.",
      "You'd shine at explaining how rhythm mirrors your thought process."
    ],
    'Intrapersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at designing diagrams that map your personal growth.",
      "You'd thrive at building systems that visually represent logical steps.",
      "You'd shine at using charts to show progress toward your goals."
    ],
    'Intrapersonal+Linguistic+Musical' => [
      "You'd be good at writing lyrics that express deep self-awareness.",
      "You'd thrive at combining storytelling and music to process personal experiences.",
      "You'd shine at helping groups reflect through songs you create."
    ],
    'Intrapersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at writing and illustrating personal stories.",
      "You'd thrive at designing presentations that combine words and images to inspire reflection.",
      "You'd shine at leading projects that blend writing with visual expression."
    ],
    'Intrapersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating art projects that blend sound and imagery.",
      "You'd thrive at composing pieces inspired by visual symbols.",
      "You'd shine at helping others feel both music and visuals in new ways."
    ],
    'Interpersonal+Naturalistic+Bodily-Kinesthetic' => [
      "You'd be good at organizing outdoor team games that require both strategy and movement.",
      "You'd thrive at leading physical activities in natural environments.",
      "You'd shine at building group trust through outdoor adventures."
    ],
    'Interpersonal+Naturalistic+Logical-Mathematical' => [
      "You'd be good at leading a team in solving nature-based problems, like designing a sustainable project.",
      "You'd thrive at guiding groups to notice patterns in the environment.",
      "You'd shine at helping people connect data with real-world teamwork."
    ],
    'Interpersonal+Naturalistic+Linguistic' => [
      "You'd be good at telling stories about nature that bring people together.",
      "You'd thrive at leading group reflections outdoors with words and conversation.",
      "You'd shine at teaching others about the environment in ways that inspire action."
    ],
    'Interpersonal+Naturalistic+Musical' => [
      "You'd be good at organizing outdoor events with music to bring people together.",
      "You'd thrive at composing songs that celebrate the natural world.",
      "You'd shine at leading rhythm-based activities in nature."
    ],
    'Interpersonal+Naturalistic+Visual-Spatial' => [
      "You'd be good at leading a group mural that celebrates nature.",
      "You'd thrive at designing outdoor spaces that help people connect with each other.",
      "You'd shine at creating visual projects that combine environment and community."
    ],
    'Interpersonal+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing physical team challenges that require logical problem-solving.",
      "You'd thrive at leading escape-room style games for groups.",
      "You'd shine at guiding others through puzzles that mix movement and strategy."
    ],
    'Interpersonal+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at leading group drama performances that explore relationships.",
      "You'd thrive at acting in plays that help people reflect on community.",
      "You'd shine at coaching public speaking with gestures and body awareness."
    ],
    'Interpersonal+Bodily-Kinesthetic+Musical' => [
      "You'd be good at choreographing dances that groups perform together.",
      "You'd thrive at teaching rhythm-based team games.",
      "You'd shine at blending movement and music to bring groups closer."
    ],
    'Interpersonal+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at designing group art projects that involve performance.",
      "You'd thrive at creating dramatic performances with sets and visuals.",
      "You'd shine at leading workshops that combine movement and design."
    ],
    'Interpersonal+Logical-Mathematical+Linguistic' => [
      "You'd be good at leading debates where arguments are backed by logic and respect.",
      "You'd thrive at explaining complex ideas in ways groups understand.",
      "You'd shine at mediating conflicts using both reason and words."
    ],
    'Interpersonal+Logical-Mathematical+Musical' => [
      "You'd be good at organizing music ensembles with structured roles.",
      "You'd thrive at helping groups practice rhythm with precision.",
      "You'd shine at showing how math and music unite teams."
    ],
    'Interpersonal+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at leading design teams that plan projects step by step.",
      "You'd thrive at showing groups diagrams that simplify big ideas.",
      "You'd shine at balancing creativity and logic in teamwork."
    ],
    'Interpersonal+Linguistic+Musical' => [
      "You'd be good at helping groups write songs that tell their shared story.",
      "You'd thrive at leading chants or performances that unite people.",
      "You'd shine at storytelling through rhythm and sound."
    ],
    'Interpersonal+Linguistic+Visual-Spatial' => [
      "You'd be good at helping groups create posters or campaigns that share a message.",
      "You'd thrive at designing events that blend words and visuals.",
      "You'd shine at leading collaborative art with a powerful story."
    ],
    'Interpersonal+Musical+Visual-Spatial' => [
      "You'd be good at creating performances that mix rhythm and imagery.",
      "You'd thrive at designing group projects like music videos or shows.",
      "You'd shine at helping others express stories through sound and visuals."
    ],
    'Naturalistic+Bodily-Kinesthetic+Logical-Mathematical' => [
      "You'd be good at designing experiments outdoors that require teamwork.",
      "You'd thrive at leading survival or nature-based challenges.",
      "You'd shine at mixing data and movement in the environment."
    ],
    'Naturalistic+Bodily-Kinesthetic+Linguistic' => [
      "You'd be good at acting in plays or skits about nature and environment.",
      "You'd thrive at guiding outdoor storytelling through movement.",
      "You'd shine at sharing environmental lessons with drama."
    ],
    'Naturalistic+Bodily-Kinesthetic+Musical' => [
      "You'd be good at composing music inspired by outdoor adventures.",
      "You'd thrive at leading rhythm-based group activities outdoors.",
      "You'd shine at celebrating nature through sound and movement."
    ],
    'Naturalistic+Bodily-Kinesthetic+Visual-Spatial' => [
      "You'd be good at creating sculptures or installations from natural materials.",
      "You'd thrive at guiding outdoor art projects with teams.",
      "You'd shine at blending movement, space, and the environment."
    ],
    'Naturalistic+Logical-Mathematical+Linguistic' => [
      "You'd be good at writing reports or stories based on environmental data.",
      "You'd thrive at sharing scientific ideas in simple words.",
      "You'd shine at persuading people with both evidence and passion for nature."
    ],
    'Naturalistic+Logical-Mathematical+Musical' => [
      "You'd be good at finding rhythm in environmental patterns.",
      "You'd thrive at creating songs that explain natural cycles.",
      "You'd shine at leading groups in celebrating the math of nature with music."
    ],
    'Naturalistic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at creating infographics that explain ecosystems.",
      "You'd thrive at mapping natural systems for others.",
      "You'd shine at blending data and visuals to inspire care for the planet."
    ],
    'Naturalistic+Linguistic+Musical' => [
      "You'd be good at writing songs about the beauty of nature.",
      "You'd thrive at performing pieces that inspire people to care for the environment.",
      "You'd shine at leading creative projects that combine words, sound, and the outdoors."
    ],
    'Naturalistic+Linguistic+Visual-Spatial' => [
      "You'd be good at creating children's books about the environment with words and illustrations.",
      "You'd thrive at teaching through posters or campaigns about nature.",
      "You'd shine at storytelling that mixes writing and visuals outdoors."
    ],
    'Naturalistic+Musical+Visual-Spatial' => [
      "You'd be good at composing soundtracks inspired by landscapes.",
      "You'd thrive at designing shows or videos that pair nature and music.",
      "You'd shine at leading projects where sound and visuals bring the outdoors to life."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Linguistic' => [
      "You'd be good at explaining sports strategies with clear logic and words.",
      "You'd thrive at writing guides for physical training routines.",
      "You'd shine at teaching complex movement through step-by-step explanation."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Musical' => [
      "You'd be good at creating dance routines based on mathematical patterns.",
      "You'd thrive at analyzing rhythm in sports or performance.",
      "You'd shine at helping groups coordinate timing and movement."
    ],
    'Bodily-Kinesthetic+Logical-Mathematical+Visual-Spatial' => [
      "You'd be good at building obstacle courses that require teamwork.",
      "You'd thrive at guiding others through puzzle-like challenges.",
      "You'd shine at blending design, logic, and physical skills."
    ],
    'Bodily-Kinesthetic+Linguistic+Musical' => [
      "You'd be good at choreographing performances that tell a story.",
      "You'd thrive at using rhythm to enhance acting or movement.",
      "You'd shine at guiding groups to express emotions with body and sound."
    ],
    'Bodily-Kinesthetic+Linguistic+Visual-Spatial' => [
      "You'd be good at performing plays with strong movement and staging.",
      "You'd thrive at directing shows where words, movement, and visuals combine.",
      "You'd shine at teaching expression through drama and design."
    ],
    'Bodily-Kinesthetic+Musical+Visual-Spatial' => [
      "You'd be good at designing performances that combine dance and visuals.",
      "You'd thrive at choreographing group routines with powerful staging.",
      "You'd shine at creating shows where movement and imagery tell a story."
    ],
    'Logical-Mathematical+Linguistic+Musical' => [
      "You'd be good at writing songs that explain big ideas clearly.",
      "You'd thrive at teaching math or science concepts through rhythm.",
      "You'd shine at showing how structure and creativity work together."
    ],
    'Logical-Mathematical+Linguistic+Visual-Spatial' => [
      "You'd be good at creating charts and stories that explain complex ideas.",
      "You'd thrive at designing visual reports that persuade with logic.",
      "You'd shine at teaching others with both evidence and images."
    ],
    'Logical-Mathematical+Musical+Visual-Spatial' => [
      "You'd be good at composing music that mirrors geometric patterns.",
      "You'd thrive at designing light or sound shows with mathematical precision.",
      "You'd shine at blending math, rhythm, and visuals into one project."
    ],
    'Linguistic+Musical+Visual-Spatial' => [
      "You'd be good at creating illustrated lyrics or music videos.",
      "You'd thrive at designing group projects that combine song and image.",
      "You'd shine at storytelling through both visuals and sound."
    ]
  ]
];

// MI Pair Library - for scenarios when third score is weak
$mi_pair_library = [
  "Intrapersonal+Interpersonal" => [
    "You'd be good at guiding friends through honest reflection after a shared project.",
    "You'd thrive at building trust by noticing emotions and turning them into learning moments.",
    "You'd shine at hosting conversations where everyone feels safe to share."
  ],
  "Intrapersonal+Naturalistic" => [
    "You'd be good at leading quiet nature walks that spark personal insight.",
    "You'd thrive at journaling outdoor experiences and helping others do the same.",
    "You'd shine at connecting lessons from the natural world to everyday life."
  ],
  "Intrapersonal+Bodily-Kinesthetic" => [
    "You'd be good at creating movement routines that express inner stories.",
    "You'd thrive at teaching physical skills with patient, self-aware coaching.",
    "You'd shine at using body-based practices to support wellbeing."
  ],
  "Intrapersonal+Logical-Mathematical" => [
    "You'd be good at breaking personal goals into measurable steps.",
    "You'd thrive at designing simple systems that keep values and habits aligned.",
    "You'd shine at spotting thinking traps and correcting course."
  ],
  "Intrapersonal+Linguistic" => [
    "You'd be good at writing reflective pieces that help others know themselves.",
    "You'd thrive at crafting talks that turn experience into wisdom.",
    "You'd shine at choosing words that make inner life understandable."
  ],
  "Intrapersonal+Musical" => [
    "You'd be good at using music to explore and express emotion.",
    "You'd thrive at writing lyrics that turn feelings into meaning.",
    "You'd shine at curating playlists that guide a mood or moment."
  ],
  "Intrapersonal+Visual-Spatial" => [
    "You'd be good at mapping growth with sketches, diagrams, or vision boards.",
    "You'd thrive at turning abstract ideas into clear visuals.",
    "You'd shine at designing symbols that represent personal values."
  ],
  "Interpersonal+Naturalistic" => [
    "You'd be good at organizing outdoor projects that bring people together.",
    "You'd thrive at leading team challenges in nature that build trust.",
    "You'd shine at helping groups connect with each other and the environment."
  ],
  "Interpersonal+Bodily-Kinesthetic" => [
    "You'd be good at coordinating team activities that use movement to bond.",
    "You'd thrive at coaching groups through physical challenges with care.",
    "You'd shine at using body language to encourage participation."
  ],
  "Interpersonal+Logical-Mathematical" => [
    "You'd be good at facilitating group problem-solving with fair structure.",
    "You'd thrive at mediating disagreements by clarifying the logic in each view.",
    "You'd shine at helping a team choose criteria and decide together."
  ],
  "Interpersonal+Linguistic" => [
    "You'd be good at leading storytelling circles where every voice is heard.",
    "You'd thrive at writing or speaking in ways that connect people.",
    "You'd shine at translating complex feelings into shared language."
  ],
  "Interpersonal+Musical" => [
    "You'd be good at using rhythm or song to unify a group.",
    "You'd thrive at leading music-based warmups that lift the room.",
    "You'd shine at shaping the vibe so people feel welcomed and in sync."
  ],
  "Interpersonal+Visual-Spatial" => [
    "You'd be good at guiding teams to sketch ideas and agree visually.",
    "You'd thrive at creating spaces where people feel included and focused.",
    "You'd shine at leading collaborative murals or poster builds."
  ],
  "Naturalistic+Bodily-Kinesthetic" => [
    "You'd be good at planning and leading safe, engaging outdoor adventures.",
    "You'd thrive at hands-on projects using natural materials.",
    "You'd shine at teaching skills outdoors through movement."
  ],
  "Naturalistic+Logical-Mathematical" => [
    "You'd be good at spotting patterns in ecosystems and explaining them.",
    "You'd thrive at collecting and interpreting environmental data.",
    "You'd shine at designing simple, sustainable systems."
  ],
  "Naturalistic+Linguistic" => [
    "You'd be good at telling stories that make people care about nature.",
    "You'd thrive at writing clear guides for outdoor learning.",
    "You'd shine at turning field notes into engaging articles."
  ],
  "Naturalistic+Musical" => [
    "You'd be good at composing pieces inspired by sounds of the outdoors.",
    "You'd thrive at rhythm games that mirror natural patterns.",
    "You'd shine at leading music moments in outdoor settings."
  ],
  "Naturalistic+Visual-Spatial" => [
    "You'd be good at designing gardens, maps, or trail layouts.",
    "You'd thrive at photographing or sketching landscapes to teach others.",
    "You'd shine at visualizing environmental change over time."
  ],
  "Bodily-Kinesthetic+Logical-Mathematical" => [
    "You'd be good at building puzzle-like obstacle courses.",
    "You'd thrive at breaking techniques into precise, learnable steps.",
    "You'd shine at optimizing practice plans for steady improvement."
  ],
  "Bodily-Kinesthetic+Linguistic" => [
    "You'd be good at teaching complex movements with simple, vivid cues.",
    "You'd thrive at storytelling through acting or physical theatre.",
    "You'd shine at writing how-to guides for hands-on skills."
  ],
  "Bodily-Kinesthetic+Musical" => [
    "You'd be good at choreographing routines that express emotion.",
    "You'd thrive at leading rhythm-based group activities.",
    "You'd shine at coordinating timing so teams move as one."
  ],
  "Bodily-Kinesthetic+Visual-Spatial" => [
    "You'd be good at staging performances with striking movement and design.",
    "You'd thrive at building models or props that bring ideas to life.",
    "You'd shine at teaching through demos people can see and do."
  ],
  "Logical-Mathematical+Linguistic" => [
    "You'd be good at explaining complex ideas in clear, persuasive language.",
    "You'd thrive at writing arguments that use evidence well.",
    "You'd shine at teaching step-by-step reasoning through stories."
  ],
  "Logical-Mathematical+Musical" => [
    "You'd be good at composing or analyzing rhythm with mathematical precision.",
    "You'd thrive at using beat and meter to teach concepts.",
    "You'd shine at showing others the structure beneath sound."
  ],
  "Logical-Mathematical+Visual-Spatial" => [
    "You'd be good at diagramming systems so others instantly 'get it.'",
    "You'd thrive at building models that solve real problems.",
    "You'd shine at turning data into clear, compelling visuals."
  ],
  "Linguistic+Musical" => [
    "You'd be good at writing lyrics that bring people together.",
    "You'd thrive at spoken-word or songs that carry a message.",
    "You'd shine at hosting creative sessions where words find a beat."
  ],
  "Linguistic+Visual-Spatial" => [
    "You'd be good at designing presentations that make ideas pop.",
    "You'd thrive at illustrating your own stories for clarity and impact.",
    "You'd shine at turning complex topics into simple visuals with captions."
  ],
  "Musical+Visual-Spatial" => [
    "You'd be good at creating performances that blend sound and imagery.",
    "You'd thrive at storyboarding music videos or live shows.",
    "You'd shine at pairing tone and color to shape a feeling."
  ]
];
