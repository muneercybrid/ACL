#!/usr/bin/env python3
"""Scratch probe 5 (NOT a deliverable): extract programme names from each TOC and
compare the count with the number of body 'Course Contents and Learning
Outcomes' sections."""
import re, glob, os

SEC = re.compile(r'^Course Contents and Learning Outcomes\s*$')
LEADER = re.compile(r'^(.{2,120}?)\s*\.{3,}\s*(\d{1,4})\s*$')


def norm(s):
    return re.sub(r'\s{2,}', ' ', s.replace('\f', ' ').strip())


tot_toc = tot_sec = 0
for path in sorted(glob.glob('storage/app/nuc-ccmas/*.txt')):
    lines = [norm(l) for l in open(path, encoding='utf-8', errors='replace').read().split('\n')]
    secs = [i for i, l in enumerate(lines) if SEC.match(l)]
    entries = []
    for i, l in enumerate(lines):
        m = LEADER.match(l)
        if m:
            entries.append((i, m.group(1).strip()))
    progs = []
    for k, (i, name) in enumerate(entries):
        nxt = entries[k + 1][1] if k + 1 < len(entries) else ''
        if re.match(r'^Overview\b', nxt, re.I):
            progs.append((i + 1, name))
    tot_toc += len(progs); tot_sec += len(secs)
    flag = 'OK ' if len(progs) == len(secs) else 'MISMATCH'
    print(f"{flag} {os.path.basename(path):24s} toc_progs={len(progs):3d} body_sections={len(secs):3d}")
    for p in progs[:60]:
        print(f"      {p[0]:6d} {p[1]}")
print("TOTAL toc progs", tot_toc, "body sections", tot_sec)
