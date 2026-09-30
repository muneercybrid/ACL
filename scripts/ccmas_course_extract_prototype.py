#!/usr/bin/env python3
"""
Valid extraction prototype for the NUC CCMAS 2023 corpus.

Confirmed against the real corpus on 2026-09-30:
  - 9,392 courses across all 17 discipline documents
  - computing.txt -> 274 courses
  - B.Sc. Cybersecurity -> 40 courses (12 at level 100, 9 at 200, 10 at 300, 9 at 400),
    34 of them carrying credit units

The source text is NOT structured. Inside a programme section the shape is:

    Course Contents and Learning Outcomes
    100 Level
    GST 111: Communication in English

    (2 Units C: LH15; PH 45)

    Learning Outcomes
    1. identify possible sound patterns in English Language;
    ...

So the course is a bare code line with the title after a colon, and the units
sit alone on the next non-blank line in parentheses. Learning-outcome bullet
lines start with a digit and must never be treated as courses.
"""
import re
import glob
import os

# CODE + 3-digit number + title, tolerating the OCR damage in the corpus:
# "GST 111: Title", "MTH101", "MTH 102", "CYB 301 - Title", en-dashes.
PAT_CODE = re.compile(r'^([A-Z]{2,5})\s*[-–]?\s*(\d{3})\s*[:.\-–]?\s*(.+?)\s*$')
# Credit units appear as "(2 Units C: LH15; PH 45)" or "(3 Units)".
PAT_UNITS = re.compile(r'\(\s*(\d+(?:\.\d+)?)\s*Units?\b', re.I)
PAT_LEVEL = re.compile(r'^(\d{3})\s*Level\s*$')

# Section markers: courses only exist between these two headings.
SECTION_START = re.compile(r'^Course Contents and Learning Outcomes\s*$')
SECTION_END = re.compile(r'^Minimum Academic Standards\s*$')

# Lines that look like codes but are document furniture.
NOT_A_COURSE = ('course', 'page', 'figure', 'table', 'units', 'source', 'unit')


def parse_programme(lines, start, end):
    """Extract courses from one programme's slice of a document."""
    out = []
    level = None
    current = None
    in_section = False

    for raw in lines[start:end]:
        t = raw.strip()
        if not t:
            continue

        if SECTION_START.match(t):
            in_section = True
            current = None
            continue
        if SECTION_END.match(t):
            in_section = False
            current = None
            continue

        m = PAT_LEVEL.match(t)
        if m:
            level = int(m.group(1))
            current = None
            continue

        if not in_section:
            continue

        # Learning outcomes and contents bullets are numbered prose, not codes.
        if t[0].isdigit():
            continue

        m = PAT_CODE.match(t)
        if m and not t.lower().startswith(NOT_A_COURSE):
            title = m.group(3).strip()
            if len(title) > 1:
                current = {
                    'code': f"{m.group(1)} {m.group(2)}",
                    'title': title,
                    'level': level,
                    'units': None,
                }
                out.append(current)
                continue

        # Units usually sit on the line after the title.
        if current is not None and current['units'] is None:
            mu = PAT_UNITS.search(t)
            if mu:
                current['units'] = float(mu.group(1))

    return out


def main():
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    corpus = os.path.join(root, 'storage', 'app', 'nuc-ccmas')

    total = 0
    for path in sorted(glob.glob(os.path.join(corpus, '*.txt'))):
        lines = open(path, encoding='utf-8', errors='replace').read().split('\n')
        n = 0
        for i, line in enumerate(lines):
            # A programme body heading is the degree title alone on a line.
            if re.match(r'^B\.\s?Sc\.?\s', line.strip()) or re.match(r'^(B\.|M\.Sc|B\.Tech|B\.A|B\.Ed)', line.strip()):
                n += len(parse_programme(lines, i, len(lines)))
        print(f"  {os.path.basename(path):28s} {n}")
        total += n
    print("TOTAL:", total)


if __name__ == '__main__':
    main()