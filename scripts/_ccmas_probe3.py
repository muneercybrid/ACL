#!/usr/bin/env python3
"""Scratch probe 3 (NOT a deliverable): show the non-table lines preceding each
'Course Contents and Learning Outcomes' section."""
import re, glob, os, sys

SEC = re.compile(r'^Course Contents and Learning Outcomes\s*$')
NOISE = re.compile(r'^(\d{1,4}|[A-Z]|-|–|&|C|LH|PH|PR|CP|TC|TT|EC|LT|SEM|TOT|Total|in|and|of|or|the|to|for|[A-Z]{1,3}\d?)$')


def norm(s):
    return re.sub(r'\s{2,}', ' ', s.replace('\f', ' ').strip())


want = sys.argv[1:]
for path in sorted(glob.glob('storage/app/nuc-ccmas/*.txt')):
    base = os.path.basename(path)
    if want and base[:-4] not in want:
        continue
    lines = [norm(l) for l in open(path, encoding='utf-8', errors='replace').read().split('\n')]
    secs = [i for i, l in enumerate(lines) if SEC.match(l)]
    print(f"##### {base}  sections={len(secs)}")
    for i in secs:
        prev = [l for l in lines[max(0, i - 200):i] if l and not NOISE.match(l)]
        print(f"  -- section line {i+1}; preceding prose (last 4):")
        for l in prev[-4:]:
            print(f"       {l[:160]}")
