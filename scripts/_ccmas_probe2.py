#!/usr/bin/env python3
"""Scratch probe 2 (NOT a deliverable): for every 'Course Contents and Learning
Outcomes' section, print the preceding non-blank lines so the programme-heading
shape can be read off the real corpus instead of guessed."""
import re, glob, os

SEC = re.compile(r'^Course Contents and Learning Outcomes\s*$')


def norm(s):
    return re.sub(r'\s{2,}', ' ', s.replace('\f', ' ').strip())


for path in sorted(glob.glob('storage/app/nuc-ccmas/*.txt')):
    lines = [norm(l) for l in open(path, encoding='utf-8', errors='replace').read().split('\n')]
    secs = [i for i, l in enumerate(lines) if SEC.match(l)]
    print(f"##### {os.path.basename(path)}  sections={len(secs)}")
    for i in secs:
        prev = [l for l in lines[max(0, i - 90):i] if l]
        print(f"  -- section at line {i+1}; last 6 non-blank before:")
        for l in prev[-6:]:
            print(f"       {l[:150]}")
