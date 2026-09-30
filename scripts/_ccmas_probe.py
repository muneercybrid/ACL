#!/usr/bin/env python3
"""Scratch probe (NOT a deliverable). Mirrors the intended PHP parser so the
regexes can be checked against the real corpus before any DB work happens."""
import re, glob, os, sys, collections

PAT_CODE = re.compile(r'^([A-Za-z]{2,5})\s*[-–—]?\s*(\d{3})\s*[:.\-–—]?\s*(.+?)\s*$')
PAT_UNITS = re.compile(r'\(\s*(\d+(?:\.\d+)?)\s*Units?\b', re.I)
PAT_LEVEL = re.compile(r'^(\d{3})\s*Level\s*$')
SEC_START = re.compile(r'^Course Contents and Learning Outcomes\s*$')
SEC_END = re.compile(r'^Minimum Academic Standards\s*$')
PAT_DEGREE = re.compile(
    r'^(B\.?\s?Sc\.?|M\.?\s?Sc\.?|B\.?\s?Tech\.?|B\.?\s?A\.?|B\.?\s?Ed\.?|'
    r'P\.?\s?hd\.?|M\.?\s?A\.?|M\.?\s?Tech\.?|M\.?\s?Ed\.?|M\.?\s?Phil\.?)\s+(.{2,})$')
NOT_A_COURSE = ('course', 'page', 'figure', 'table', 'units', 'source', 'unit')


def headings(lines):
    out = []
    for i, raw in enumerate(lines):
        t = raw.replace('\f', ' ').strip()
        t = re.sub(r'\s{2,}', ' ', t)
        if len(t) > 180 or '....' in t:
            continue
        m = PAT_DEGREE.match(t)
        if m:
            out.append((i, t))
    return out


def parse(lines, start, end):
    out = []
    level = None
    current = None
    in_section = False
    skipped = []
    for i in range(start, end):
        t = lines[i].replace('\f', ' ').strip()
        t = re.sub(r'\s{2,}', ' ', t)
        if not t:
            continue
        if SEC_START.match(t):
            in_section = True; current = None; continue
        if SEC_END.match(t):
            in_section = False; current = None; continue
        m = PAT_LEVEL.match(t)
        if m:
            level = int(m.group(1)); current = None; continue
        if not in_section:
            continue
        if t[0].isdigit():
            continue
        m = PAT_CODE.match(t)
        if m and not t.lower().startswith(NOT_A_COURSE):
            title = m.group(3).strip()
            if len(title) > 1:
                current = {'line': i + 1, 'code': f"{m.group(1).upper()} {m.group(2)}",
                           'title': title, 'level': level, 'units': None}
                out.append(current)
                continue
        if current is not None and current['units'] is None:
            mu = PAT_UNITS.search(t)
            if mu:
                current['units'] = float(mu.group(1))
        else:
            skipped.append((i + 1, t))
    return out, skipped


def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    corpus = os.path.join(root, 'storage', 'app', 'nuc-ccmas')
    total = 0
    for path in sorted(glob.glob(os.path.join(corpus, '*.txt'))):
        lines = open(path, encoding='utf-8', errors='replace').read().split('\n')
        hs = headings(lines)
        n = 0
        progs = []
        for k, (i, t) in enumerate(hs):
            end = hs[k + 1][0] if k + 1 < len(hs) else len(lines)
            c, _ = parse(lines, i, end)
            if c:
                progs.append((t, len(c)))
            n += len(c)
        print(f"{os.path.basename(path):24s} headings={len(hs):4d} courses={n:5d}  {progs[:3]}")
        total += n
    print("TOTAL", total)


if __name__ == '__main__':
    main()
