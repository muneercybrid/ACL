#!/usr/bin/env python3
"""Scratch probe 6 (NOT a deliverable): for every body 'Course Contents and
Learning Outcomes' section, find the nearest preceding 'Overview' line and show
the non-blank lines immediately before it (the candidate programme heading)."""
import re, glob, os

SEC = re.compile(r'^Course Contents and Learning Outcomes$')
OV = re.compile(r'^Overview$')


def norm(s):
    return re.sub(r'\s{2,}', ' ', s.replace('\f', ' ').strip())


for path in sorted(glob.glob('storage/app/nuc-ccmas/*.txt')):
    lines = [norm(l) for l in open(path, encoding='utf-8', errors='replace').read().split('\n')]
    secs = [i for i, l in enumerate(lines) if SEC.match(l)]
    ovs = [i for i, l in enumerate(lines) if OV.match(l)]
    print(f"##### {os.path.basename(path)} sections={len(secs)}")
    prev_sec = -1
    for s in secs:
        cand = [o for o in ovs if prev_sec < o < s]
        prev_sec = s
        if not cand:
            print(f"  sec@{s+1:6d} -> NO OVERVIEW")
            continue
        o = cand[-1]
        before = [l for l in lines[max(0, o - 12):o] if l]
        print(f"  sec@{s+1:6d} ov@{o+1:6d} head={before[-2:] if len(before)>=2 else before}")
