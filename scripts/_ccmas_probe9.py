#!/usr/bin/env python3
"""Scratch probe 9 (NOT a deliverable): final candidate algorithm with the
code-only-line recovery and a full reject audit."""
import re, glob, os, collections

SEC = re.compile(r'^Course Contents and Learning Outcomes$')
END = re.compile(r'^Minimum Academic Standards$')
OVR = re.compile(r'^Overview$')
LEVEL = re.compile(r'^(\d{3})\s*[-–—]?\s*[Ll][Ee][Vv][Ee][Ll]\b\s*[:\-–—]?\s*(.*)$')
CODE = re.compile(r'^([A-Za-z]{2,5})\s*[-–—]?\s*(\d{3})\s*[:.\-–—/]?\s*(.*)$')
CODE_ONLY = re.compile(r'^([A-Za-z]{2,5})\s*[-–—]?\s*(\d{3})\s*[:.\-–—]?\s*$')
UNITS = re.compile(r'\(\s*(\d+(?:\.\d+)?)\s*[Uu]{1,2}nit[e]?s?\b', re.I)
UNITS_FRAG = re.compile(r'\s*\(\s*\d+(?:\.\d+)?\s*[Uu]{1,2}nit[e]?s?\b[^()]*\)?\s*$', re.I)
LEAD_CODE = re.compile(r'^(?:[A-Za-z]{2,5}\s*\d{3}|\d{3})\s*[:.\-–—/]?\s*')
NOT_A_COURSE = ('course', 'page', 'figure', 'table', 'units', 'source', 'unit')
SECTION_LABELS = ('learning outcome', 'learning outcomes', 'course contents', 'course content')
NOISE = re.compile(r'^(new|\d{1,4}|&|-|–|c|lh|ph|pr|cp|tc|tt|ec|lt|sem|tot|total)$', re.I)
BAD_FIRST = re.compile(r'^[,.;:)\]}>]')
TITLE_MAX = 90


def norm(s):
    return re.sub(r'\s{2,}', ' ', s.replace('\f', ' ').strip())


def level_of(t):
    m = LEVEL.match(t)
    if not m:
        return None
    r = m.group(2).strip()
    if r == '':
        return int(m.group(1))
    if len(r) > 60 or re.search(r'[.,;]', r) or len(r.split()) > 6:
        return None
    return int(m.group(1))


def clean_title(raw):
    t = raw.strip()
    units = None
    m = UNITS_FRAG.search(t)
    if m:
        um = UNITS.search(m.group(0))
        if um:
            units = float(um.group(1))
        t = t[:m.start()].strip()
    t = LEAD_CODE.sub('', t, count=1)
    t = re.sub(r'[\s,;:.\-–—]+$', '', t).strip()
    if not t or BAD_FIRST.match(t) or (t[:1] == '(' and not t[1:2].isalnum()):
        return None, units
    if not re.match(r'^[\(\[]?[A-Z0-9]', t):
        return None, units
    if len(re.findall(r'[A-Za-z]', t)) < 3 or len(t) > TITLE_MAX:
        return None, units
    return t, units


def anchor(lines, sec, prev):
    ovs = [i for i in range(prev + 1, sec) if OVR.match(lines[i])]
    if not ovs:
        return None
    o = ovs[-1]
    for j in range(o - 1, max(-1, o - 14), -1):
        l = lines[j]
        if not l or NOISE.match(l) or l.endswith('.'):
            continue
        if len(l) < 70 and j - 1 >= 0 and lines[j - 1] and not NOISE.match(lines[j - 1]) \
           and not lines[j - 1].endswith('.') and len(lines[j - 1]) < 70:
            return l + ' ' + lines[j - 1]
        return l
    return None


def next_nonblank(lines, i):
    while i < len(lines) and not lines[i]:
        i += 1
    return i


def title_like(t):
    if not t or len(t) > TITLE_MAX:
        return False
    if NOISE.match(t) or t.lower() in SECTION_LABELS:
        return False
    if t[0].isdigit() or UNITS.search(t):
        return False
    return bool(re.match(r'^[\(\[]?[A-Z0-9]', t)) and len(re.findall(r'[A-Za-z]', t)) >= 3


def confirmed(lines, i):
    """Within the next 4 non-blank lines, expect a units line or a section label."""
    seen = 0
    j = i + 1
    while j < len(lines) and seen < 4:
        if lines[j]:
            seen += 1
            if UNITS.search(lines[j]) or lines[j].lower() in SECTION_LABELS:
                return True
        j += 1
    return False


def extract(path, audit=None):
    lines = [norm(l) for l in open(path, encoding='utf-8', errors='replace').read().split('\n')]
    secs = [i for i, l in enumerate(lines) if SEC.match(l)]
    out = []
    prev = -1
    for s in secs:
        prog = anchor(lines, s, prev)
        e = next((i for i in range(s + 1, len(lines)) if END.match(lines[i])), len(lines))
        prev = s
        level = None
        cur = None
        i = s + 1
        while i < e:
            t = lines[i]
            if not t:
                i += 1
                continue
            lv = level_of(t)
            if lv is not None:
                level = lv; cur = None; i += 1; continue
            if t[0].isdigit():
                i += 1; continue
            m = CODE.match(t)
            mo = CODE_ONLY.match(t)
            if (m and not t.lower().startswith(NOT_A_COURSE)) or (mo and not t.lower().startswith(NOT_A_COURSE)):
                raw = m.group(3) if m else ''
                if not raw.strip():
                    j = next_nonblank(lines, i + 1)
                    if mo and j < e and title_like(lines[j]) and confirmed(lines, j):
                        title, units = clean_title(lines[j])
                        if title:
                            cur = {'line': i + 1, 'code': f"{mo.group(1).upper()} {mo.group(2)}",
                                   'title': title, 'level': level, 'units': units, 'prog': prog}
                            out.append(cur)
                            i = j + 1
                            continue
                    cur = None
                    i += 1
                    continue
                title, units = clean_title(raw)
                if title:
                    cur = {'line': i + 1, 'code': f"{m.group(1).upper()} {m.group(2)}",
                           'title': title, 'level': level, 'units': units, 'prog': prog}
                    out.append(cur)
                    i += 1
                    continue
                if audit is not None:
                    audit.append((os.path.basename(path), i + 1, t))
                cur = None
                i += 1
                continue
            if cur is not None and cur['units'] is None:
                mu = UNITS.search(t)
                if mu:
                    cur['units'] = float(mu.group(1))
            i += 1
    return out


if __name__ == '__main__':
    audit = []
    allrows = []
    for path in sorted(glob.glob('storage/app/nuc-ccmas/*.txt')):
        rows = extract(path, audit)
        allrows += rows
        miss = sum(1 for r in rows if r['units'] is None)
        nolv = sum(1 for r in rows if r['level'] is None)
        print(f"{os.path.basename(path):24s} n={len(rows):5d} no_units={miss:5d} no_level={nolv:4d} "
              f"progs={len({r['prog'] for r in rows})}")
    print("TOTAL", len(allrows))
    n = len(allrows)
    print("no units:", sum(1 for r in allrows if r['units'] is None),
          f"({100*sum(1 for r in allrows if r['units'] is None)/n:.1f}%)")
    print("no level:", sum(1 for r in allrows if r['level'] is None))
    print("no prog :", sum(1 for r in allrows if not r['prog']))
    cyb = [r for r in allrows if r['prog'] and 'Cybersecurity' in r['prog']]
    print("\nCyber:", len(cyb), "with units:", sum(1 for r in cyb if r['units'] is not None),
          "levels:", sorted(collections.Counter(r['level'] for r in cyb).items()))
    print("\n=== REJECTED (%d) ===" % len(audit))
    for f, ln, t in audit:
        print(f"{f:22s} {ln:6d} {t[:140]}")
