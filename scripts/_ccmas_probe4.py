#!/usr/bin/env python3
"""Scratch probe 4 (NOT a deliverable): nearest preceding line matching a broad
degree-heading pattern, searched back up to 3000 lines."""
import re, glob, os, sys

SEC = re.compile(r'^Course Contents and Learning Outcomes\s*$')
DEG = re.compile(
    r'^(B\.?\s?[A-Za-z]{0,12}\.?|M\.?\s?[A-Za-z]{0,12}\.?|LL\.?\s?[BM]\.?|LL\.?\s?B|'
    r'D\.?\s?[A-Za-z]{0,12}\.?|Pharm\.?\s?D\.?|B\.\s?Pharm\.?|P\.\s?hd\.?|V\.?\s?MD\.?|'
    r'Bachelor|Master|Doctor|PhD)\b')


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
        found = None
        for j in range(i - 1, max(-1, i - 3000), -1):
            l = lines[j]
            if not l or len(l) > 180 or '....' in l:
                continue
            if DEG.match(l):
                found = (j + 1, l)
                break
        print(f"  sec@{i+1:6d} -> {found}")
