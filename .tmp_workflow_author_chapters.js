export const meta = {
  name: 'author-chapters',
  description: 'Author 20 CCMAS-aligned chapter titles per course via parallel subagents, writing JSON to disk (no DB access)',
  phases: [
    { title: 'author', detail: 'Each subagent authors 20 chapter titles per course for its slice of the queue, evaluated against the CCMAS statement' },
    { title: 'collect', detail: 'Tally authored files and courses completed' },
  ],
}

// The queue was exported from the database by courses:export-queue.
// Each entry: { id, code, title, ccmas } where ccmas is the NUC
// CCMAS learning outcomes + course contents. Subagents read this
// file, author chapter plans, and write one JSON file per course
// to the output directory. They never touch the database: that
// split is deliberate, so a database error cannot destroy the
// authoring work.

const QUEUE_FILE = args.queue_file || '/tmp/acl-gen-queue.json'
const OUTPUT_DIR = args.output_dir || '/tmp/acl-gen-output'
const AGENTS = args.agents || 50

phase('author')

// Build the work slices. Each agent gets a contiguous index range
// so every course is claimed by exactly one agent and none is
// duplicated or skipped.
const slices = []
for (let i = 0; i < AGENTS; i++) {
  slices.push({ agent: i, total: AGENTS })
}

const PROMPT = (slice) => `You are authoring academic chapter titles for the ACL ("Anyone Can Learn") course catalogue. Your work must be good enough for a university student to study from.

TASK
Read the course queue at ${QUEUE_FILE}. It is a JSON array of objects: {"id": <course_id>, "code": "...", "title": "...", "ccmas": "<NUC CCMAS learning outcomes and course contents>"}.

You own index range: every course whose 0-based array index i satisfies (i % ${AGENTS}) === ${slice.agent}. Process ALL of them.

FOR EACH COURSE YOU OWN
1. Read the "ccmas" field. This is the authoritative course specification (learning outcomes + course contents).
2. Author EXACTLY 20 chapter titles that:
   - cover the CCMAS course contents in the order they are given,
   - run easiest first, hardest last,
   - each name a SPECIFIC idea, topic, technique or concept actually taught in the course (NOT generic filler like "Introduction", "Summary", "Assessment", "Course Overview"),
   - are concrete enough that a student knows exactly what the chapter teaches,
   - are at most 140 characters, plain text, no markdown, no numbering, no quotes.
3. Write the result to ${OUTPUT_DIR}/<id>.json (where <id> is the course's numeric id) as:
   {"course_id": <id>, "titles": ["Title one", ... 20 strings ...]}

RULES
- Use python3 through bash to read the queue and write files. Example:
  python3 -c "import json; d=json.load(open('${QUEUE_FILE}')); mine=[(i,x) for i,x in enumerate(d) if i % ${AGENTS} === ${slice.agent}]; print(len(mine), 'courses')"
- Do NOT connect to any database. Do NOT run php artisan. You only read the queue file and write JSON files.
- If a course's "ccmas" field is empty, still author 20 sensible titles from the course code and title.
- Quality over speed: these titles become the permanent chapter structure students see.
- Work through every course in your range. Do not stop early.

WHEN DONE
Reply with exactly: "AGENT ${slice.agent}: <N> courses authored" where <N> is the number of JSON files you wrote.`

const results = await parallel(
  slices.map((slice) => () =>
    agent(PROMPT(slice), { label: `author-${slice.agent}`, phase: 'author' })
  )
)

phase('collect')

const completed = results.filter(Boolean).length

return {
  agents_launched: AGENTS,
  agents_completed: completed,
  queue_file: QUEUE_FILE,
  output_dir: OUTPUT_DIR,
}
